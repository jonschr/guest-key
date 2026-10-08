<?php
defined( 'ABSPATH' ) || exit;

/** A small command surface over the registered abilities, not a shell or a second implementation. */
final class Guest_Key_Commands {

	private static $running = false;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		foreach ( array( 'help' => 'GET', 'run' => 'POST' ) as $name => $method ) {
			self::register_ability_route( '/' . $name, 'guest-key/' . $name, $method );
		}
	}

	public static function register_ability_route( $path, $ability, $method ) {
		$schema = wp_get_ability( $ability )->get_input_schema();
		$args = $schema['properties'];
		foreach ( $args as $key => &$arg ) {
			$arg['required'] = in_array( $key, $schema['required'] ?? array(), true );
			// Preserve JSON types; the ability remains the authoritative validator.
			$arg['sanitize_callback'] = static function ( $value ) { return $value; };
		}
		unset( $arg );
		register_rest_route( 'guest-key/v1', $path, array( array(
			'methods' => $method, 'args' => $args,
			'permission_callback' => array( 'Guest_Key_Access', 'can_connect' ),
			'callback' => static function ( $request ) use ( $ability ) {
				$input = $request->get_params();
				if ( isset( $request->get_query_params()['rest_route'] ) ) { unset( $input['rest_route'] ); }
				$result = wp_get_ability( $ability )->execute( $input );
				if ( is_wp_error( $result ) ) { return $result; }
				$response = rest_ensure_response( $result );
				$response->header( 'Cache-Control', 'private, no-store' );
				return $response;
			},
		), 'schema' => static function () use ( $schema ) { return $schema; } ) );
	}

	public static function help_schema() {
		return array( 'type' => 'object', 'properties' => array(
			'command' => array( 'type' => array( 'string', 'array' ), 'items' => array( 'type' => 'string' ), 'maxItems' => 20, 'description' => 'Exact command for its input schema, a prefix such as post for names, or an array of commands to get several schemas in one call. Omit for command names only.' ),
		), 'additionalProperties' => false );
	}

	public static function run_schema() {
		return array( 'type' => 'object', 'properties' => array(
			'commands' => array( 'type' => 'array', 'minItems' => 1, 'maxItems' => 20, 'items' => array(
				'type' => 'object', 'properties' => array(
					'command' => array( 'type' => 'string', 'minLength' => 1 ),
					'input' => array( 'type' => 'object', 'additionalProperties' => true, 'description' => 'Command arguments. A value {"$ref":"0.data.id"} uses an earlier result, e.g. an uploaded image or created term. Zero-based indices; whole values only.' ),
				), 'required' => array( 'command' ), 'additionalProperties' => false,
			) ),
		), 'required' => array( 'commands' ), 'additionalProperties' => false );
	}

	private static function catalog() {
		$commands = array();
		foreach ( array(
			'plugin list' => 'list-plugins', 'plugin install' => 'install-plugin',
			'plugin activate' => 'activate-plugin', 'plugin deactivate' => 'deactivate-plugin', 'plugin delete' => 'delete-plugin',
			'theme install' => 'install-theme', 'theme activate' => 'activate-theme', 'theme delete' => 'delete-theme',
			'update list' => 'list-updates', 'update apply' => 'apply-update',
			'media upload' => 'upload-media', 'post save' => 'save-post', 'rest request' => 'request', 'design guide' => 'design-guide',
			'file list' => 'list-files', 'file read' => 'read-file', 'file search' => 'search-files',
		) as $command => $ability ) { $commands[ $command ] = array( 'ability' => 'guest-key/' . $ability ); }
		foreach ( array( 'post' => 'manage-posts', 'post meta' => 'manage-post-meta', 'term' => 'manage-terms', 'option' => 'manage-options', 'theme mod' => 'manage-theme-mods', 'user meta' => 'manage-user-meta', 'term meta' => 'manage-term-meta' ) as $prefix => $name ) {
			$ability = wp_get_ability( 'guest-key/' . $name );
			foreach ( $ability->get_input_schema()['properties']['action']['enum'] as $action ) {
				$commands[ $prefix . ' ' . $action ] = array( 'ability' => $ability->get_name(), 'action' => $action );
			}
		}
		$commands['ability list'] = array( 'schema' => array( 'type' => 'object', 'properties' => array(
			'search' => array( 'type' => 'string' ), 'page' => array( 'type' => 'integer', 'minimum' => 1 ),
		), 'additionalProperties' => false ) );
		$commands['ability describe'] = array( 'schema' => array( 'type' => 'object', 'properties' => array(
			'name' => array( 'type' => 'string', 'minLength' => 1 ),
		), 'required' => array( 'name' ), 'additionalProperties' => false ) );
		$commands['ability run'] = array( 'schema' => array( 'type' => 'object', 'properties' => array(
			'name' => array( 'type' => 'string', 'minLength' => 1 ), 'input' => array( 'type' => array( 'object', 'array', 'string', 'number', 'boolean', 'null' ), 'description' => 'Input matching the ability schema; omit for abilities with no input.' ),
		), 'required' => array( 'name' ), 'additionalProperties' => false ) );
		return $commands;
	}

	private static function schema( $entry ) {
		$schema = $entry['schema'] ?? wp_get_ability( $entry['ability'] )->get_input_schema();
		if ( isset( $entry['action'] ) ) {
			unset( $schema['properties']['action'] );
			$schema['required'] = array_values( array_diff( $schema['required'] ?? array(), array( 'action' ) ) );
		}
		return $schema;
	}

	public static function help( $input ) {
		$catalog = self::catalog();
		$command = $input['command'] ?? '';
		if ( is_array( $command ) ) { return array( 'schemas' => array_map( static function ( $name ) { return self::help( array( 'command' => $name ) ); }, $command ) ); }
		if ( isset( $catalog[ $command ] ) ) {
			$entry = $catalog[ $command ];
			$result = array( 'command' => $command, 'input_schema' => self::schema( $entry ) );
			if ( isset( $entry['ability'] ) ) { $result['description'] = wp_get_ability( $entry['ability'] )->get_description(); }
			return $result;
		}
		return array( 'commands' => array_values( array_filter( array_keys( $catalog ), static function ( $name ) use ( $command ) {
			return '' === $command || 0 === strpos( $name, $command . ' ' );
		} ) ) );
	}

	public static function run( $input ) {
		if ( self::$running ) { return new WP_Error( 'guest_key_recursive_commands', 'Nested command execution is not supported.' ); }
		self::$running = true;
		$results = array();
		try {
			$catalog = self::catalog();
			foreach ( $input['commands'] as $index => $item ) {
				$command = $item['command'];
				$args = self::resolve( $item['input'] ?? array(), $results );
				$entry = $catalog[ $command ] ?? null;
				$result = is_wp_error( $args ) ? $args : ( $entry ? rest_validate_value_from_schema( $args, self::schema( $entry ), 'input' ) : new WP_Error( 'guest_key_unknown_command', 'Unknown command. Use guest-key-help for command names and input schemas.' ) );
				if ( ! is_wp_error( $result ) ) {
					if ( isset( $entry['ability'] ) ) {
						if ( isset( $entry['action'] ) ) { $args['action'] = $entry['action']; }
						$result = wp_get_ability( $entry['ability'] )->execute( $args );
					} else { $result = self::ability_command( $command, $args ); }
				}
				if ( is_wp_error( $result ) ) {
					return array( 'ok' => false, 'results' => $results, 'failed_index' => $index, 'command' => $command,
						'error' => array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $result->get_error_data() ),
						'skipped' => count( $input['commands'] ) - $index - 1 );
				}
				// Writes need an ID and status, not a second copy of every content field.
				if ( in_array( $command, array( 'post create', 'post update' ), true ) ) {
					$result = array( 'id' => $result['ID'], 'status' => $result['post_status'] );
				}
				if ( 'post list' === $command ) {
					$result['items'] = array_map( static function ( $post ) {
						return array( 'id' => $post['ID'], 'title' => $post['post_title'], 'slug' => $post['post_name'], 'status' => $post['post_status'], 'post_type' => $post['post_type'] );
					}, $result['items'] );
				}
				if ( 'media upload' === $command ) { $result = array( 'id' => $result['data']['id'], 'url' => $result['data']['source_url'] ); }
				if ( 'term create' === $command || 'term update' === $command ) { $result = array( 'id' => $result->term_id, 'name' => $result->name, 'taxonomy' => $result->taxonomy ); }
				if ( 0 === strpos( $command, 'plugin ' ) && 'plugin delete' !== $command ) {
					$pick = static function ( $plugin ) { return array_intersect_key( $plugin, array_flip( array( 'plugin', 'name', 'status', 'version' ) ) ); };
					$result = 'plugin list' === $command ? array_map( $pick, $result ) : $pick( $result );
				}
				$results[] = array( 'command' => $command, 'data' => $result );
			}
			return array( 'ok' => true, 'results' => $results );
		} finally { self::$running = false; }
	}

	private static function resolve( $value, $results, $depth = 0 ) {
		if ( $depth > 32 ) { return new WP_Error( 'guest_key_input_depth', 'Command input is nested too deeply.' ); }
		if ( is_object( $value ) ) { $value = (array) $value; }
		if ( ! is_array( $value ) ) { return $value; }
		if ( 1 === count( $value ) && array_key_exists( '$ref', $value ) ) {
			$path = $value['$ref'];
			if ( ! is_string( $path ) || ! preg_match( '/^[0-9]+(?:\.[a-zA-Z0-9_-]+)+$/D', $path ) ) { return new WP_Error( 'guest_key_reference', 'Use a reference such as 0.data.id to an earlier result.' ); }
			$resolved = $results;
			foreach ( explode( '.', $path ) as $key ) {
				if ( is_object( $resolved ) ) { $resolved = (array) $resolved; }
				if ( ! is_array( $resolved ) || ! array_key_exists( $key, $resolved ) ) { return new WP_Error( 'guest_key_reference', 'Reference not found in completed results: ' . $path ); }
				$resolved = $resolved[ $key ];
			}
			return $resolved;
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::resolve( $item, $results, $depth + 1 );
			if ( is_wp_error( $value[ $key ] ) ) { return $value[ $key ]; }
		}
		return $value;
	}

	private static function ability_command( $command, $input ) {
		if ( 'ability list' === $command ) {
			$rows = array();
			foreach ( wp_get_abilities() as $ability ) {
				if ( ! empty( $input['search'] ) && false === stripos( $ability->get_name() . ' ' . $ability->get_label() . ' ' . $ability->get_description(), $input['search'] ) ) { continue; }
				$rows[] = array( 'name' => $ability->get_name(), 'label' => $ability->get_label() );
			}
			return array( 'items' => array_slice( $rows, ( ( $input['page'] ?? 1 ) - 1 ) * 50, 50 ), 'total' => count( $rows ), 'total_pages' => (int) ceil( count( $rows ) / 50 ) );
		}
		$ability = wp_get_ability( $input['name'] );
		if ( ! $ability ) { return new WP_Error( 'guest_key_unknown_ability', 'Ability not found. Use ability list to discover registered names.' ); }
		if ( 'ability describe' === $command ) {
			return array( 'name' => $ability->get_name(), 'description' => $ability->get_description(), 'input_schema' => $ability->get_input_schema(), 'output_schema' => $ability->get_output_schema(), 'meta' => $ability->get_meta() );
		}
		if ( in_array( $ability->get_name(), array( 'guest-key/run', 'guest-key/help' ), true ) ) { return new WP_Error( 'guest_key_recursive_commands', 'Use the command tools directly.' ); }
		return $ability->execute( $input['input'] ?? null );
	}
}
