<?php
/** wp --path=/path/to/local/site eval-file /path/to/guest-key/tests/browser.php */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Use WP-CLI on a local/development single site with its web server running.' );
}
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$original_uid = get_current_user_id();
$original_cookies = $_COOKIE;
$grants = Guest_Key_Access::grants();
$adapter = is_plugin_active( Guest_Key_Dependency::PLUGIN );
$managed = get_option( 'guest_key_adapter_managed' );
$checks = 0;
$requests = 0;
$uid = null;
$mail = static function () { return true; };
add_filter( 'pre_wp_mail', $mail );
$assert = static function ( $ok, $message ) use ( &$checks ) {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	$checks++;
	echo 'PASS: ' . $message . "\n";
};
$http = static function ( $url, $method = 'GET', $body = array(), $cookies = array(), $headers = array() ) use ( &$requests ) {
	$requests++;
	$response = wp_remote_request( $url, array( 'method' => $method, 'body' => $body, 'cookies' => $cookies, 'headers' => $headers, 'redirection' => 0, 'sslverify' => false, 'timeout' => 10,
		'user-agent' => 'T3Code/0.0.46-nightly.20261005.2689 (user-directed AI agent; agent=Codex; model=gpt-6.1-sol) GuestKey/' . GUEST_KEY_VERSION . ' WordPress/' . get_bloginfo( 'version' ) ) );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
	if ( 429 === wp_remote_retrieve_response_code( $response ) ) { throw new RuntimeException( 'HTTP throttled; stop testing.' ); }
	return $response;
};
$nonce = static function () use ( $http ) {
	$response = $http( Guest_Key_Browser::url() );
	if ( ! preg_match( '/name="guest_key_nonce"\s+value="([^"]+)"/', wp_remote_retrieve_body( $response ), $match ) ) { throw new RuntimeException( 'Guest Key login form is missing.' ); }
	return $match[1];
};
$login = static function ( $key ) use ( $http, $nonce, $assert, &$uid ) {
	$response = $http( Guest_Key_Browser::url(), 'POST', array( 'log' => $key['username'], 'pwd' => $key['password'], 'guest_key_nonce' => $nonce() ) );
	$assert( 302 === wp_remote_retrieve_response_code( $response ) && admin_url() === wp_remote_retrieve_header( $response, 'location' ), 'Guest Key form signs in and redirects to the dashboard' );
	$cookies = wp_remote_retrieve_cookies( $response );
	$assert( count( $cookies ) >= 2, 'WordPress issues native browser cookies' );
	wp_cache_delete( $uid, 'user_meta' );
	return $cookies;
};
$auth_cookies = static function ( $response ) {
	return array_filter( wp_remote_retrieve_cookies( $response ), static function ( $cookie ) {
		return in_array( $cookie->name, array( AUTH_COOKIE, SECURE_AUTH_COOKIE, LOGGED_IN_COOKIE ), true );
	} );
};
$anonymous = static function ( $cookies ) use ( $http ) {
	$response = $http( admin_url(), 'GET', array(), $cookies );
	return 302 === wp_remote_retrieve_response_code( $response ) && false !== strpos( wp_remote_retrieve_header( $response, 'location' ), 'wp-login.php' );
};
try {
	$uid = wp_insert_user( array( 'user_login' => 'guest-key-browser-test-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
	$assert( ! is_wp_error( $uid ), 'Create a temporary browser-test administrator' );
	wp_set_current_user( $uid );
	$key = Guest_Key_Access::create( $uid );
	$assert( ! is_wp_error( $key ) && false !== strpos( $key['bundle'], Guest_Key_Browser::url() ), 'Copied instructions include browser sign-in using the same credential' );
	$assert( false !== strpos( $key['bundle'], 'User-Agent: GuestKey/' . GUEST_KEY_VERSION . ' (+https://elod.in/guest-key-wordpress-mcp)' ) && false === strpos( $key['bundle'], '<client>' ), 'Copied instructions identify Guest Key with its version and documentation URL' );
	$cookies = $login( $key );
	$response = $http( admin_url(), 'GET', array(), $cookies );
	$assert( 200 === wp_remote_retrieve_response_code( $response ) && false !== strpos( wp_remote_retrieve_body( $response ), 'wp-admin-bar-my-account' ), 'Temporary browser session accesses wp-admin' );
	$response = $http( home_url( '/' ), 'GET', array(), $cookies );
	$assert( 200 === wp_remote_retrieve_response_code( $response ) && false !== strpos( wp_remote_retrieve_body( $response ), 'wpadminbar' ), 'The same browser session accesses the frontend while signed in' );
	$obscured = array_values( array_filter( $cookies, static function ( $cookie ) { return SECURE_AUTH_COOKIE !== $cookie->name; } ) );
	$obscured[] = new WP_Http_Cookie( array( 'name' => SECURE_AUTH_COOKIE, 'value' => 'invalid|9999999999|invalid|invalid', 'path' => '/' ) );
	$response = $http( home_url( '/' ), 'GET', array(), $obscured );
	$assert( false !== strpos( wp_remote_retrieve_body( $response ), 'wpadminbar' ) && false === strpos( wp_remote_retrieve_body( $response ), 'wp-admin-bar-guest-key-access' ), 'An invalid second cookie cannot hide the guest session restriction' );
	foreach ( array( 'plugin-editor.php', 'theme-editor.php' ) as $editor ) {
		$response = $http( admin_url( $editor ), 'GET', array(), $cookies );
		$assert( 403 === wp_remote_retrieve_response_code( $response ), 'Guest browser cannot open ' . $editor );
	}
	$response = $http( Guest_Key_Admin::page_url(), 'GET', array(), $cookies );
	$html = wp_remote_retrieve_body( $response );
	$assert( false !== strpos( $html, 'Registered abilities' ) && false === strpos( $html, '<button type="button" class="button button-primary" data-guest-key-create' ), 'Temporary sessions can inspect abilities without issuing new keys' );
	if ( ! preg_match( '/"nonce":"([^"]+)"/', $html, $match ) ) { throw new RuntimeException( 'Admin nonce missing.' ); }
	$response = $http( admin_url( 'admin-ajax.php' ), 'POST', array( 'action' => 'guest_key_create', 'nonce' => $match[1] ), $cookies );
	$assert( 403 === wp_remote_retrieve_response_code( $response ), 'Temporary browser session cannot extend its access through AJAX' );

	// PHP decodes Set-Cookie values before populating $_COOKIE on a real request.
	foreach ( $cookies as $cookie ) { $_COOKIE[ $cookie->name ] = rawurldecode( $cookie->value ); }
	wp_cache_delete( $uid, 'user_meta' ); // HTTP requests updated native session metadata.
	$assert( Guest_Key_Browser::is_guest_session() && ! current_user_can( 'edit_plugins' ) && ! current_user_can( 'edit_themes' ) && ! current_user_can( 'create_app_password', $uid ), 'Guest browser permissions exclude direct file editing and durable application passwords' );
	$assert( is_wp_error( Guest_Key_Access::create( $uid ) ) && current_user_can( 'install_plugins' ) && current_user_can( 'upload_files' ), 'Guest sessions retain native installation/upload permissions without issuing replacement keys' );
	$manager = WP_Session_Tokens::get_instance( $uid );
	$inherited = $manager->create( time() + DAY_IN_SECONDS );
	$assert( ! empty( $manager->get( $inherited )[ Guest_Key_Browser::SESSION ] ) && apply_filters( 'auth_cookie_expiration', DAY_IN_SECONDS, $uid, false ) <= Guest_Key_Access::TTL, 'Replacement WordPress sessions inherit the Guest Key binding and remaining expiry' );
	$_COOKIE = $original_cookies;

	$regular = WP_Application_Passwords::create_new_application_password( $uid, array( 'name' => 'Unrelated browser test password' ) );
	$response = $http( Guest_Key_Browser::url(), 'POST', array( 'log' => $key['username'], 'pwd' => $regular[0], 'guest_key_nonce' => $nonce() ) );
	$assert( 200 === wp_remote_retrieve_response_code( $response ) && ! $auth_cookies( $response ), 'Unrelated application passwords cannot create Guest Key browser sessions' );
	$response = $http( Guest_Key_Browser::url(), 'POST', array( 'log' => $key['username'], 'pwd' => $key['password'], 'guest_key_nonce' => $nonce() ), array(), array( 'Origin' => 'https://example.invalid' ) );
	$assert( 200 === wp_remote_retrieve_response_code( $response ) && ! $auth_cookies( $response ), 'Cross-origin login submissions are rejected' );
	$response = $http( Guest_Key_Browser::url(), 'POST', array( 'log' => $key['username'], 'pwd' => $key['password'], 'guest_key_nonce' => 'invalid' ) );
	$assert( 200 === wp_remote_retrieve_response_code( $response ) && ! $auth_cookies( $response ), 'Missing or invalid sign-in nonce cannot create a browser session' );

	$normal_token = $manager->create( time() + DAY_IN_SECONDS );
	$replacement = Guest_Key_Access::create( $uid );
	$assert( ! is_wp_error( $replacement ) && $anonymous( $cookies ), 'Key rotation immediately ends an existing guest browser session' );
	$assert( ! $manager->verify( $inherited ) && $manager->verify( $normal_token ), 'Rotation physically removes guest tokens and preserves normal sessions' );
	$cookies = $login( $replacement );
	$expired = Guest_Key_Access::grant( $uid );
	$expired['expires'] = time() - 1;
	update_user_meta( $uid, Guest_Key_Access::META, $expired );
	$obscured = array_values( array_filter( $cookies, static function ( $cookie ) { return SECURE_AUTH_COOKIE !== $cookie->name; } ) );
	$obscured[] = new WP_Http_Cookie( array( 'name' => SECURE_AUTH_COOKIE, 'value' => 'invalid|9999999999|invalid|invalid', 'path' => '/' ) );
	$response = $http( home_url( '/' ), 'GET', array(), $obscured );
	$assert( false === strpos( wp_remote_retrieve_body( $response ), 'wpadminbar' ), 'An invalid second cookie cannot bypass frontend key expiry' );
	$assert( $anonymous( $cookies ), 'Browser access ends at key expiry without waiting for cron or cookie grace' );
	$key = Guest_Key_Access::create( $uid );
	$cookies = $login( $key );
	get_userdata( $uid )->set_role( 'subscriber' );
	$assert( $anonymous( $cookies ), 'Demotion ends Guest Key browser access without authentication recursion' );
	get_userdata( $uid )->set_role( 'administrator' );
	$cookies = $login( $key );
	$assert( true === Guest_Key_Access::revoke( $uid ) && $anonymous( $cookies ), 'Explicit revocation ends browser access' );
	$assert( $manager->verify( $normal_token ), 'Revocation preserves ordinary administrator sessions' );
} finally {
	$_COOKIE = $original_cookies;
	wp_set_current_user( $original_uid );
	if ( is_int( $uid ) ) {
		Guest_Key_Access::revoke( $uid );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $uid );
	}
	if ( $adapter ) { Guest_Key_Dependency::ensure(); }
	if ( $managed ) { update_option( 'guest_key_adapter_managed', $managed, false ); }
	else { delete_option( 'guest_key_adapter_managed' ); }
	remove_filter( 'pre_wp_mail', $mail );
	$assert( $grants === Guest_Key_Access::grants(), 'Existing administrator grants are preserved' );
}
echo 'Completed ' . $checks . ' browser checks across ' . $requests . " HTTP requests.\n";
