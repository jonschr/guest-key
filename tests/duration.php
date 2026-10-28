<?php
/** Local-only: native settings POST, boundary validation, and real REST/browser lifetime checks. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Run as an administrator on an isolated local single site.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/user.php';
$owner = get_current_user_id(); $cookies = $_COOKIE; $original = get_option( Guest_Key_Access::DURATION_OPTION, null );
$license = get_option( Guest_Key_License::OPTION, null ); $grants = Guest_Key_Access::grants(); $uid = null; $checks = 0;
$mail = '__return_true'; add_filter( 'pre_wp_mail', $mail );
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo "PASS: {$message}\n"; };
$http = static function ( $url, $args = array() ) {
	$r = wp_remote_request( $url, array_merge( array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 15, 'user-agent' => 'T3Code (user-directed AI agent; agent=Codex; model=gpt-6.1-sol)' ), $args ) );
	if ( is_wp_error( $r ) ) { throw new RuntimeException( $r->get_error_message() ); }
	if ( 429 === wp_remote_retrieve_response_code( $r ) ) { throw new RuntimeException( 'Rate limited; stop testing.' ); } return $r;
};
$read = static function () { wp_cache_delete( Guest_Key_Access::DURATION_OPTION, 'options' ); wp_cache_delete( 'notoptions', 'options' ); return Guest_Key_Access::duration(); };
$age = static function ( $seconds, $ttl = null ) use ( &$uid ) {
	$items = WP_Application_Passwords::get_user_application_passwords( $uid ); $items[0]['created'] = time() - $seconds;
	update_user_meta( $uid, WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS, $items );
	$g = Guest_Key_Access::grant( $uid ); $g['expires'] = time() + HOUR_IN_SECONDS;
	if ( null === $ttl ) { unset( $g['ttl'] ); } else { $g['ttl'] = $ttl; }
	update_user_meta( $uid, Guest_Key_Access::META, $g );
};
$rest = static function ( $key ) use ( $http ) { return wp_remote_retrieve_response_code( $http( rest_url( 'wp/v2/users/me' ), array( 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $key['username'] . ':' . $key['password'] ) ) ) ) ); };
$login = static function ( $key ) use ( $http ) {
	$form = $http( Guest_Key_Browser::url() );
	if ( ! preg_match( '/name="guest_key_nonce"\s+value="([^"]+)"/', wp_remote_retrieve_body( $form ), $nonce ) ) { throw new RuntimeException( 'Missing browser sign-in nonce.' ); }
	return $http( Guest_Key_Browser::url(), array( 'method' => 'POST', 'body' => array( 'log' => $key['username'], 'pwd' => $key['password'], 'guest_key_nonce' => $nonce[1] ) ) );
};
try {
	delete_option( Guest_Key_Access::DURATION_OPTION );
	$assert( 7200 === Guest_Key_Access::duration(), 'Missing setting defaults to two hours' );
	foreach ( array( 30, 90, 120, 1440 ) as $minutes ) { $assert( true === Guest_Key_Access::set_duration( (string) $minutes ) && $minutes * 60 === Guest_Key_Access::duration(), "Accept {$minutes} whole minutes, including both boundaries" ); }
	foreach ( array( 29, 1441, 0, -1, '30.5', '3e2', '', ' 30', array( 120 ), true, 120.5 ) as $bad ) { $assert( is_wp_error( Guest_Key_Access::set_duration( $bad ) ) && DAY_IN_SECONDS === Guest_Key_Access::duration(), 'Invalid duration is rejected without changing the saved value' ); }
	foreach ( array( 29, 1441, array( 120 ) ) as $bad ) { update_option( Guest_Key_Access::DURATION_OPTION, $bad, false ); $assert( 7200 === Guest_Key_Access::duration(), 'Malformed database settings fall back to the bounded default' ); }
	Guest_Key_Access::set_duration( 120 );
	$uid = wp_insert_user( array( 'user_login' => 'gk-duration-' . strtolower( wp_generate_password( 8, false ) ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
	if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Could not create duration-test administrator.' ); }
	wp_set_current_user( $uid );
	$token = WP_Session_Tokens::get_instance( $uid )->create( time() + HOUR_IN_SECONDS );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $uid, time() + HOUR_IN_SECONDS, 'logged_in', $token );
	$normal = array( new WP_Http_Cookie( array( 'name' => LOGGED_IN_COOKIE, 'value' => $_COOKIE[ LOGGED_IN_COOKIE ], 'path' => '/' ) ), new WP_Http_Cookie( array( 'name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, time() + HOUR_IN_SECONDS, 'secure_auth', $token ), 'path' => '/' ) ) );
	$post = static function ( $value, $nonce ) use ( $http, $normal ) { return $http( admin_url( 'admin-post.php' ), array( 'method' => 'POST', 'cookies' => $normal, 'body' => array( 'action' => 'guest_key_save_duration', 'guest_key_duration_minutes' => $value, '_wpnonce' => $nonce ) ) ); };
	$r = $post( '30', wp_create_nonce( 'guest_key_duration' ) );
	$assert( 302 === wp_remote_retrieve_response_code( $r ) && 1800 === $read(), 'The native settings form saves 30 minutes through actual HTTP' );
	$r = $post( '1441', wp_create_nonce( 'guest_key_duration' ) );
	$assert( 302 === wp_remote_retrieve_response_code( $r ) && false !== strpos( wp_remote_retrieve_header( $r, 'location' ), 'invalid' ) && 1800 === $read(), 'Bypassing the input maximum is rejected on the server' );
	$r = $post( '29', wp_create_nonce( 'guest_key_duration' ) );
	$assert( false !== strpos( wp_remote_retrieve_header( $r, 'location' ), 'invalid' ) && 1800 === $read(), 'Bypassing the input minimum preserves the previous setting' );
	$assert( 403 === wp_remote_retrieve_response_code( $post( '1440', 'bad-nonce' ) ) && 1800 === $read(), 'A bad CSRF nonce cannot change the duration' );
	update_option( Guest_Key_License::OPTION, array( 'kind' => 'paid', 'site' => trailingslashit( home_url( '/' ) ), 'status' => 'active', 'expires' => 0, 'checked' => time(), 'key' => 'fixture', 'instance' => 'fixture' ), false );
	$key = Guest_Key_Access::create( $uid ); if ( is_wp_error( $key ) ) { throw new RuntimeException( $key->get_error_message() ); }
	$g = Guest_Key_Access::grant( $uid );
	$assert( 1800 === $g['ttl'] && abs( $g['expires'] - time() - 1800 ) <= 2, 'A 30-minute setting issues a 30-minute credential' );
	$short_browser = $login( $key ); $short_cookies = wp_remote_retrieve_cookies( $short_browser );
	$assert( 302 === wp_remote_retrieve_response_code( $short_browser ) && count( $short_cookies ) >= 2, 'The 30-minute credential supports native browser sign-in' );
	Guest_Key_Access::set_duration( 1440 );
	$assert( $g === Guest_Key_Access::grant( $uid ), 'Increasing the setting cannot extend a previously issued key' );
	$age( 31 * MINUTE_IN_SECONDS, 1800 );
	$assert( 401 === $rest( $key ), 'The original 30-minute ceiling survives a settings increase or forged future expiration' );
	$assert( 302 === wp_remote_retrieve_response_code( $http( admin_url(), array( 'cookies' => $short_cookies ) ) ), 'The 30-minute browser session uses the original duration ceiling' );
	$key = Guest_Key_Access::create( $uid ); if ( is_wp_error( $key ) ) { throw new RuntimeException( $key->get_error_message() ); }
	$g = Guest_Key_Access::grant( $uid );
	$assert( 86400 === $g['ttl'] && $g['expires'] > time() + 86300 && $g['expires'] <= time() + 86400, 'The maximum setting issues up to 24 hours of access' );
	$age( 3 * HOUR_IN_SECONDS, 86400 );
	$assert( 200 === $rest( $key ), 'A 24-hour key remains valid after the previous two-hour cutoff' );
	$browser = $login( $key ); $guest = wp_remote_retrieve_cookies( $browser );
	$dashboard = $http( admin_url(), array( 'cookies' => $guest ) );
	$assert( 302 === wp_remote_retrieve_response_code( $browser ) && count( $guest ) >= 2 && 200 === wp_remote_retrieve_response_code( $dashboard ), 'A 24-hour browser sign-in and session work after the old two-hour cutoff' );
	$r = $http( admin_url( 'admin-post.php' ), array( 'method' => 'POST', 'cookies' => $guest, 'body' => array( 'action' => 'guest_key_save_duration', 'guest_key_duration_minutes' => '30', '_wpnonce' => 'unused' ) ) );
	$assert( 403 === wp_remote_retrieve_response_code( $r ) && 86400 === $read(), 'A temporary agent browser cannot change the duration' );
	Guest_Key_Access::set_duration( 30 );
	$assert( 200 === $rest( $key ), 'Decreasing the setting does not shorten existing 24-hour credentials' );
	$age( 25 * HOUR_IN_SECONDS, 86400 );
	$assert( 401 === $rest( $key ) && 302 === wp_remote_retrieve_response_code( $http( admin_url(), array( 'cookies' => $guest ) ) ), 'API and browser access both enforce the absolute 24-hour ceiling' );
	$age( 3 * HOUR_IN_SECONDS );
	$assert( 401 === $rest( $key ), 'Legacy keys without a duration retain their two-hour maximum' );
	$age( 0, 86401 );
	$assert( 401 === $rest( $key ), 'A forged out-of-range grant duration fails closed' );
	$l = get_option( Guest_Key_License::OPTION ); $l['expires'] = time() + 90; update_option( Guest_Key_License::OPTION, $l, false );
	$key = Guest_Key_Access::create( $uid );
	$assert( ! is_wp_error( $key ) && Guest_Key_Access::grant( $uid )['expires'] === $l['expires'], 'Trial or license expiration still caps the selected access duration' );
	wp_set_current_user( 0 );
	$assert( is_wp_error( Guest_Key_Access::set_duration( 120 ) ) && 1800 === Guest_Key_Access::duration(), 'An anonymous caller cannot change the setting' );
} finally {
	$_COOKIE = $cookies; wp_set_current_user( $owner );
	if ( is_int( $uid ) && $uid > 0 ) { Guest_Key_Access::revoke( $uid ); wp_delete_user( $uid ); }
	foreach ( array( Guest_Key_Access::DURATION_OPTION => $original, Guest_Key_License::OPTION => $license ) as $name => $value ) { if ( null === $value ) { delete_option( $name ); } else { update_option( $name, $value, false ); } }
	remove_filter( 'pre_wp_mail', $mail );
	$assert( $grants === Guest_Key_Access::grants(), 'Original site grants are preserved and temporary fixtures removed' );
}
echo "Completed {$checks} duration checks; original duration/license restored.\n";
