<?php
defined( 'ABSPATH' ) || exit;

final class Guest_Key_Admin_Abilities {

	private static $groups = array();

	public static function content_bases() {
		$bases = array();
		$special = array( 'attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'wp_global_styles' );
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $type ) {
			if ( ! in_array( $type->name, $special, true ) ) {
				$bases[] = '/' . ( $type->rest_namespace ?: 'wp/v2' ) . '/' . ( $type->rest_base ?: $type->name );
			}
		}
		return $bases;
	}

	public static function taxonomy_bases() {
		$bases = array();
		foreach ( get_taxonomies( array( 'show_in_rest' => true ), 'objects' ) as $taxonomy ) {
			if ( 'nav_menu' !== $taxonomy->name ) {
				$bases[] = '/' . ( $taxonomy->rest_namespace ?: 'wp/v2' ) . '/' . ( $taxonomy->rest_base ?: $taxonomy->name );
			}
		}
		return $bases;
	}

	private static function bases( $name ) {
		$bases = self::$groups[ $name ] ?? array();
		return is_callable( $bases ) ? call_user_func( $bases ) : $bases;
	}

	/** Shared request schema; each ability declares its own name and routes in abilities/. */
	public static function rest_args( $name, $label, $description, $bases, $readonly = false ) {
		self::$groups[ $name ] = $bases;
		return array(
			'label' => $label, 'description' => $description, 'category' => 'site',
			'input_schema' => array(
				'type' => 'object',
				'properties' => array(
					'method' => array( 'type' => 'string', 'enum' => $readonly ? array( 'GET' ) : array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), 'default' => 'GET' ),
					'path' => array( 'type' => 'string', 'description' => 'Native REST path without a URL or query string. Allowed collections: ' . implode( ', ', self::bases( $name ) ) . '. Append item IDs or native subroutes as needed.' ),
					'parameters' => array( 'type' => 'object', 'additionalProperties' => true, 'description' => 'Native REST parameters, e.g. title, content, status, meta, context, search, page, per_page, force, or reassign. See get-admin-api for supported fields.' ),
				), 'required' => array( 'path' ), 'additionalProperties' => false,
			),
			'execute_callback' => static function ( $input ) use ( $name ) { return self::execute_rest( $name, $input ); },
			'permission_callback' => static function () { return Guest_Key_Access::administrator(); },
			'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => $readonly, 'destructive' => ! $readonly, 'idempotent' => $readonly ) ),
		);
	}

	private static function allowed( $path, $bases, $route_pattern = false ) {
		if ( ( ! $route_pattern && preg_match( '~[\\\\%?#\x00-\x20]|(?:^|/)\.{1,2}(?:/|$)~', $path ) ) || preg_match( '#/application-passwords(?:/|$)#', $path ) ) {
			return false;
		}
		foreach ( $bases as $base ) {
			if ( $path === $base || 0 === strpos( $path, $base . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function execute_rest( $group, $input ) {
		if ( ! isset( self::$groups[ $group ] ) || ! self::allowed( $input['path'], self::bases( $group ) ) ) {
			return new WP_Error( 'guest_key_route_denied', 'This route is outside the ability’s supported admin operations.' );
		}
		$request = new WP_REST_Request( $input['method'] ?? 'GET', $input['path'] );
		$request->set_body_params( (array) ( $input['parameters'] ?? array() ) );
		return self::response( rest_do_request( $request ) );
	}

	private static function response( $response ) {
		if ( $response->is_error() ) {
			return $response->as_error();
		}
		$result = array( 'status' => $response->get_status(), 'data' => $response->get_data() );
		foreach ( $response->get_headers() as $name => $value ) {
			if ( 'x-wp-total' === strtolower( $name ) ) { $result['total'] = (int) $value; }
			if ( 'x-wp-totalpages' === strtolower( $name ) ) { $result['total_pages'] = (int) $value; }
		}
		return $result;
	}

	public static function discover( $input ) {
		$groups = array();
		foreach ( self::$groups as $name => $bases ) { $groups[ $name ] = array( 'bases' => self::bases( $name ) ); }
		$groups['plugins'] = array( 'bases' => array( '/wp/v2/plugins' ) );
		$ability = preg_replace( '#^guest-key/#', '', $input['ability'] ?? '' );
		if ( $ability && ! isset( $groups[ $ability ] ) ) {
			return new WP_Error( 'guest_key_unknown_group', 'Unknown admin API group.' );
		}
		$routes = array();
		foreach ( rest_get_server()->get_routes() as $path => $handlers ) {
			foreach ( $groups as $name => $group ) {
				if ( ( $ability && $ability !== $name ) || ! self::allowed( $path, $group['bases'], true ) || ( ! empty( $input['search'] ) && false === stripos( $path, $input['search'] ) ) ) {
					continue;
				}
				$endpoints = array();
				foreach ( $handlers as $handler ) {
					if ( isset( $handler['methods'] ) ) {
						$endpoints[] = array( 'methods' => array_keys( array_filter( $handler['methods'] ) ), 'args' => self::schema_args( $handler['args'] ?? array() ) );
					}
				}
				$abilities = 'plugins' === $name ? array( 'guest-key/list-plugins', 'guest-key/install-plugin', 'guest-key/activate-plugin', 'guest-key/deactivate-plugin', 'guest-key/delete-plugin' ) : array( 'guest-key/' . $name );
				$routes[] = array( 'group' => $name, 'abilities' => $abilities, 'path' => $path, 'endpoints' => $endpoints );
				break;
			}
		}
		return $routes;
	}

	private static function schema_args( $args ) {
		// Callback objects are implementation details and cannot be serialized as schemas.
		foreach ( $args as &$arg ) { unset( $arg['validate_callback'], $arg['sanitize_callback'] ); }
		unset( $arg );
		return $args;
	}

	public static function upload_media( $input ) {
		$limit = wp_max_upload_size();
		if ( strlen( $input['base64'] ) > 4 * ceil( $limit / 3 ) ) {
			return new WP_Error( 'guest_key_upload_size', 'The file exceeds this site’s upload limit.' );
		}
		$bytes = base64_decode( $input['base64'], true );
		$filename = sanitize_file_name( $input['filename'] );
		$type = wp_check_filetype( $filename );
		if ( false === $bytes || '' === $bytes || ! $filename || ! $type['type'] ) {
			return new WP_Error( 'guest_key_upload_invalid', 'Provide valid base64 bytes and a filename with an allowed media extension.' );
		}
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'content-type', $type['type'] );
		$request->set_header( 'content-disposition', 'attachment; filename="' . $filename . '"' );
		$request->set_body( $bytes );
		$request->set_body_params( (array) ( $input['fields'] ?? array() ) );
		return self::response( rest_do_request( $request ) );
	}
}
