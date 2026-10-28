<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Use a local single site.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/user.php';
$original = get_option( Guest_Key_License::OPTION, null ); $grants = Guest_Key_Access::grants(); $uid = 0; $count = 0;
$assert = static function ( $value, $message ) use ( &$count ) { if ( ! $value ) { throw new RuntimeException( $message ); } echo 'PASS: ' . $message . "\n"; $count++; };
$http = static function ( $url, $args = array() ) {
	$response = wp_remote_request( $url, array_merge( array( 'sslverify' => false, 'timeout' => 15, 'redirection' => 0, 'user-agent' => 'T3Code (user-directed AI agent; agent=Codex; model=gpt-6.1-sol)' ), $args ) );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); } return $response;
};
try {
	$record = array( 'kind' => 'trial', 'site' => trailingslashit( home_url( '/' ) ), 'status' => 'active', 'expires' => time() + 90, 'checked' => time(), 'token' => str_repeat( 't', 43 ) );
	update_option( Guest_Key_License::OPTION, $record, false );
	$uid = wp_insert_user( array( 'user_login' => 'gk-license-expiry-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
	if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Could not create test administrator.' ); }
	wp_set_current_user( $uid ); $key = Guest_Key_Access::create( $uid );
	$assert( ! is_wp_error( $key ), 'Confirmed trial can create access' );
	$grant = Guest_Key_Access::grant( $uid );
	$assert( $grant['expires'] === $record['expires'], 'Issued credentials stop at trial expiration even when less than two hours remain' );
	$headers = array( 'Authorization' => 'Basic ' . base64_encode( $key['username'] . ':' . $key['password'] ) );
	$assert( 200 === wp_remote_retrieve_response_code( $http( rest_url( 'wp/v2/users/me' ), array( 'headers' => $headers ) ) ), 'Trial credential authenticates before expiration' );
	$page = $http( Guest_Key_Browser::url() );
	if ( ! preg_match( '/name="guest_key_nonce"\s+value="([^"]+)"/', wp_remote_retrieve_body( $page ), $nonce ) ) { throw new RuntimeException( 'Missing sign-in nonce.' ); }
	$login = $http( Guest_Key_Browser::url(), array( 'method' => 'POST', 'body' => array( 'log' => $key['username'], 'pwd' => $key['password'], 'guest_key_nonce' => $nonce[1] ) ) );
	$cookies = wp_remote_retrieve_cookies( $login );
	$assert( 302 === wp_remote_retrieve_response_code( $login ) && count( $cookies ) >= 2, 'Trial credential can establish a temporary browser session' );
	$record['expires'] = time() - 1; update_option( Guest_Key_License::OPTION, $record, false );
	$rest = $http( rest_url( 'wp/v2/users/me' ), array( 'headers' => $headers ) );
	$assert( 401 === wp_remote_retrieve_response_code( $rest ), 'License expiration immediately blocks an already-issued REST credential' );
	$browser = $http( admin_url(), array( 'cookies' => $cookies ) );
	$assert( 302 === wp_remote_retrieve_response_code( $browser ) && false !== strpos( wp_remote_retrieve_header( $browser, 'location' ), 'wp-login.php' ), 'License expiration immediately ends an existing temporary browser session' );
	$created = Guest_Key_Access::create( $uid );
	$assert( is_wp_error( $created ) && 'guest_key_license_required' === $created->get_error_code(), 'An expired trial cannot issue replacement credentials' );
	$updates = false;
	foreach ( $GLOBALS['wp_filter']['site_transient_update_plugins']->callbacks ?? array() as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$object = is_array( $callback['function'] ) ? $callback['function'][0] : null;
			if ( is_object( $object ) && method_exists( $object, 'getAbsolutePath' ) && realpath( $object->getAbsolutePath() ) === realpath( GUEST_KEY_FILE ) ) { $updates = true; }
		}
	}
	$assert( $updates, 'Plugin Update Checker remains registered without an active trial or license' );
} finally {
	if ( $uid && ! is_wp_error( $uid ) ) { Guest_Key_Access::revoke( $uid ); wp_delete_user( $uid ); }
	if ( null === $original ) { delete_option( Guest_Key_License::OPTION ); } else { update_option( Guest_Key_License::OPTION, $original, false ); }
	$assert( $grants === Guest_Key_Access::grants(), 'License-expiration checks preserve existing grants' );
}
echo "Completed {$count} license-expiration checks; local state restored.\n";
