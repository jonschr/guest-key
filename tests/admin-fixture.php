<?php
/**
 * Plugin Name: Guest Key admin integration fixture
 * Description: Temporary local admin-workflow test fixture. Remove after testing.
 */
defined( 'ABSPATH' ) || exit;
if ( ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { return; }
add_action( 'init', static function () {
	register_post_type( 'guest_key_test', array( 'label' => 'Guest Key test content', 'public' => true, 'show_in_rest' => true, 'rest_base' => 'guest-key-test-content', 'supports' => array( 'title', 'editor', 'revisions' ) ) );
} );
add_filter( 'plugins_api', static function ( $result, $action, $args ) {
	if ( 'plugin_information' === $action && 'guest-key-admin-test' === ( $args->slug ?? '' ) ) {
		return (object) array( 'slug' => 'guest-key-admin-test', 'name' => 'Guest Key test package', 'version' => '1.0', 'download_link' => home_url( '/wp-content/uploads/guest-key-admin-test.zip' ), 'language_packs' => array() );
	}
	return $result;
}, 10, 3 );
add_filter( 'http_headers_useragent', static function ( $agent ) {
	return 0 === strpos( wp_get_current_user()->user_login, 'guest-key-admin-test-' )
		? 'T3Code/0.0.46-nightly.20261005.2689 (user-directed AI agent; agent=Codex; model=gpt-6.1-sol) GuestKey/' . GUEST_KEY_VERSION . ' WordPress/' . get_bloginfo( 'version' ) : $agent;
} );
add_filter( 'http_request_args', static function ( $args, $url ) {
	if ( home_url( '/wp-content/uploads/guest-key-admin-test.zip' ) === $url ) { $args['sslverify'] = false; }
	return $args;
}, 10, 2 );
add_filter( 'pre_wp_mail', static function ( $result ) {
	return 0 === strpos( wp_get_current_user()->user_login, 'guest-key-admin-test-' ) ? true : $result;
} );
