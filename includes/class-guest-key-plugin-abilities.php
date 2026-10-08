<?php
defined( 'ABSPATH' ) || exit;

final class Guest_Key_Plugin_Abilities {

	/** Dispatch internally through core so its validation and permission checks still run. */
	private static function request( $method, $route, $params ) {
		$request = new WP_REST_Request( $method, $route );
		$request->set_body_params( $params );
		$response = rest_do_request( $request );
		return $response->is_error() ? $response->as_error() : $response->get_data();
	}

	public static function list_plugins( $input ) {
		return self::request( 'GET', '/wp/v2/plugins', array( 'search' => $input['search'] ?? '' ) );
	}

	public static function install_plugin( $input ) {
		if ( ! empty( $input['network_wide'] ) && ( ! is_multisite() || empty( $input['activate'] ) ) ) {
			return new WP_Error( 'guest_key_network_activation', __( 'Network activation requires multisite and activate=true.', 'guest-key' ) );
		}
		if ( ! wp_is_file_mod_allowed( 'guest_key_install_plugin' ) ) {
			return new WP_Error( 'guest_key_file_mods_disabled', __( 'Plugin installation is disabled by this site’s filesystem policy.', 'guest-key' ) );
		}
		$status = empty( $input['activate'] ) ? 'inactive' : ( empty( $input['network_wide'] ) ? 'active' : 'network-active' );
		return self::request( 'POST', '/wp/v2/plugins', array( 'slug' => $input['slug'], 'status' => $status ) );
	}

	public static function activate_plugin( $input ) {
		if ( ! empty( $input['network_wide'] ) && ! is_multisite() ) {
			return new WP_Error( 'guest_key_not_multisite', __( 'Network activation is available only on multisite.', 'guest-key' ) );
		}
		$plugin = preg_replace( '/\.php$/', '', $input['plugin'] );
		$route = '/wp/v2/plugins/' . str_replace( '%2F', '/', rawurlencode( $plugin ) );
		return self::request( 'PUT', $route, array( 'status' => empty( $input['network_wide'] ) ? 'active' : 'network-active' ) );
	}

	public static function deactivate_plugin( $input ) {
		return self::request( 'PUT', self::plugin_route( $input['plugin'] ), array( 'status' => 'inactive' ) );
	}

	public static function delete_plugin( $input ) {
		if ( ! wp_is_file_mod_allowed( 'guest_key_delete_plugin' ) ) {
			return new WP_Error( 'guest_key_file_mods_disabled', 'Plugin deletion is disabled by this site’s filesystem policy.' );
		}
		return self::request( 'DELETE', self::plugin_route( $input['plugin'] ), array() );
	}

	private static function plugin_route( $plugin ) {
		return '/wp/v2/plugins/' . str_replace( '%2F', '/', rawurlencode( preg_replace( '/\.php$/', '', $plugin ) ) );
	}
}
