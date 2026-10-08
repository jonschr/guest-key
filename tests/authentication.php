<?php
/**
 * wp --path=/path/to/local/site eval-file /path/to/guest-key/tests/authentication.php
 * Runs without a web server. Uses a temporary user and no existing credentials.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Use WP-CLI on a local/development single site.' );
}
error_reporting( E_ALL & ~E_DEPRECATED );

$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$checks++;
	echo 'PASS: ' . $message . "\n";
};
$saved = array();
foreach ( array( 'current_user', 'wp_rest_application_password_uuid', 'wp_rest_application_password_status' ) as $key ) {
	$saved[ $key ] = array( array_key_exists( $key, $GLOBALS ), $GLOBALS[ $key ] ?? null );
}
$server = $_SERVER;
$get = $_GET;
$determine = $GLOBALS['wp_filter']['determine_current_user'] ?? null;
$grants = Guest_Key_Access::grants();
$uid = null;
$mail = static function () { return true; };
$attempts = 0;
$cap_checks = 0;
$early_cap_checks = 0;
$cap_filter = static function ( $caps ) use ( &$cap_checks, &$early_cap_checks ) {
	$cap_checks++;
	if ( empty( $GLOBALS['current_user'] ) ) { $early_cap_checks++; }
	// Reproduce Yoast and other capability filters that resolve the current user.
	wp_get_current_user();
	return $caps;
};
$guard = static function ( $value ) use ( &$attempts ) {
	if ( ++$attempts > 1 ) {
		throw new RuntimeException( 'Authentication re-entered before the current user was established.' );
	}
	return $value;
};
$reset = static function ( $password ) use ( &$attempts, &$cap_checks, &$early_cap_checks ) {
	unset( $GLOBALS['current_user'], $GLOBALS['wp_rest_application_password_uuid'], $GLOBALS['wp_rest_application_password_status'] );
	$_SERVER['PHP_AUTH_PW'] = $password;
	$attempts = 0;
	$cap_checks = 0;
	$early_cap_checks = 0;
};
$mcp = static function ( $method, $params, $session = '' ) {
	$request = new WP_REST_Request( 'POST', Guest_Key_Access::ROUTE );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_header( 'Accept', 'application/json, text/event-stream' );
	$request->set_header( 'MCP-Protocol-Version', '2025-11-25' );
	if ( $session ) { $request->set_header( 'Mcp-Session-Id', $session ); }
	$request->set_body( wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params ) ) );
	$response = rest_do_request( $request );
	// Normalize protocol objects as a JSON HTTP client would.
	$response->set_data( json_decode( wp_json_encode( $response->get_data() ), true ) );
	return $response;
};
add_filter( 'pre_wp_mail', $mail );
try {
	$uid = wp_insert_user( array( 'user_login' => 'guest-key-auth-test-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
	$assert( ! is_wp_error( $uid ), 'Create a temporary authentication-test administrator' );
	$created = WP_Application_Passwords::create_new_application_password( $uid, array( 'name' => 'Guest Key authentication regression', 'app_id' => Guest_Key_Access::APP_ID ) );
	$assert( ! is_wp_error( $created ), 'Create a temporary native application password' );
	list( $password, $item ) = $created;
	$grant = array( 'uuid' => $item['uuid'], 'expires' => time() + Guest_Key_Access::TTL, 'blog_id' => get_current_blog_id(), 'network_id' => get_current_network_id() );
	update_user_meta( $uid, Guest_Key_Access::META, $grant );
	$_SERVER['PHP_AUTH_USER'] = get_userdata( $uid )->user_login;
	$_GET['rest_route'] = Guest_Key_Access::ROUTE;
	add_filter( 'application_password_is_api_request', '__return_true' );
	add_filter( 'map_meta_cap', $cap_filter );
	// Simulate a fresh request, without WP-CLI's current-user override or cookies.
	$GLOBALS['wp_filter']['determine_current_user'] = new WP_Hook();
	add_filter( 'determine_current_user', $guard, 1 );
	add_filter( 'determine_current_user', 'wp_validate_application_password', 20 );
	$yoast = false;
	foreach ( $GLOBALS['wp_filter']['map_meta_cap']->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && $fn[0] instanceof WPSEO_Register_Capabilities ) { $yoast = true; }
		}
	}
	echo $yoast ? "INFO: Actual Yoast capability filter is active.\n" : "INFO: Testing the current-user capability filter without Yoast installed.\n";
	$reset( $password );
	$assert( get_current_user_id() === $uid && 1 === $attempts, 'Native application-password authentication completes once' );
	$assert( 0 === $early_cap_checks, 'Capability filters run only after the current user is established' );
	$assert( Guest_Key_Access::can_connect() && $cap_checks > 0 && 1 === $attempts, 'MCP checks administrator permissions after authentication without re-entry' );
	$initialize = array( 'protocolVersion' => '2025-11-25', 'capabilities' => new stdClass(), 'clientInfo' => array( 'name' => 'Guest Key authentication regression', 'version' => '1.0' ) );
	$reset( $password );
	$response = $mcp( 'initialize', $initialize );
	$headers = array_change_key_case( $response->get_headers() );
	$session = $headers['mcp-session-id'] ?? '';
	$assert( 200 === $response->get_status() && isset( $response->get_data()['result']['serverInfo'] ) && $session && 1 === $attempts, 'Native MCP initialize completes through the registered REST route' );
	$response = $mcp( 'tools/list', new stdClass(), $session );
	$names = array_column( $response->get_data()['result']['tools'] ?? array(), 'name' );
	$assert( 200 === $response->get_status() && in_array( 'guest-key-install-plugin', $names, true ), 'Authenticated MCP session discovers Guest Key administrator tools' );
	$response = $mcp( 'tools/call', array( 'name' => 'core-get-site-info', 'arguments' => new stdClass() ), $session );
	$assert( 200 === $response->get_status() && isset( $response->get_data()['result'] ) && empty( $response->get_data()['result']['isError'] ), 'Authenticated MCP session executes a read-only core ability' );

	$deny = static function ( $caps ) { $caps['manage_options'] = false; return $caps; };
	add_filter( 'user_has_cap', $deny );
	try { $assert( ! Guest_Key_Access::can_connect(), 'MCP honors filtered administrator permissions' ); }
	finally { remove_filter( 'user_has_cap', $deny ); }
	get_userdata( $uid )->set_role( 'subscriber' );
	$reset( $password );
	$assert( get_current_user_id() === $uid && ! Guest_Key_Access::can_connect() && 1 === $attempts, 'A demoted administrator cannot connect to MCP' );
	$response = $mcp( 'tools/list', new stdClass(), $session );
	$assert( 403 === $response->get_status(), 'The registered MCP route rejects a demoted administrator’s existing session' );
	get_userdata( $uid )->set_role( 'administrator' );

	$_GET['rest_route'] = '/wp/v2/users/' . $uid . '/application-passwords';
	$reset( $password );
	$assert( 0 === get_current_user_id() && 'guest_key_scope' === $GLOBALS['wp_rest_application_password_status']->get_error_code(), 'Guest Key cannot authenticate on application-password REST routes' );
	$_GET['rest_route'] = Guest_Key_Access::ROUTE;
	$wrong_site = $grant;
	$wrong_site['blog_id']++;
	update_user_meta( $uid, Guest_Key_Access::META, $wrong_site );
	$reset( $password );
	$assert( 0 === get_current_user_id(), 'Credentials cannot authenticate on another site' );
	$expired = $grant;
	$expired['expires'] = time() - 1;
	update_user_meta( $uid, Guest_Key_Access::META, $expired );
	$reset( $password );
	$assert( 0 === get_current_user_id() && 'guest_key_expired' === $GLOBALS['wp_rest_application_password_status']->get_error_code(), 'Expired credentials fail before cron cleanup' );
	delete_user_meta( $uid, Guest_Key_Access::META );
	$reset( $password );
	$assert( 0 === get_current_user_id(), 'An orphaned credential is rejected' );
	update_user_meta( $uid, Guest_Key_Access::META, $grant );
	$old_item = $item;
	$old_item['created'] = time() - Guest_Key_Access::TTL;
	$error = new WP_Error();
	Guest_Key_Access::authenticate( $error, get_userdata( $uid ), $old_item );
	$assert( 'guest_key_expired' === $error->get_error_code(), 'Native password creation time enforces the six-hour maximum' );
	$regular = WP_Application_Passwords::create_new_application_password( $uid, array( 'name' => 'Unrelated regression password' ) );
	$assert( ! is_wp_error( $regular ), 'Create an unrelated application password' );
	$reset( $regular[0] );
	$assert( get_current_user_id() === $uid && ! Guest_Key_Access::can_connect(), 'Unrelated passwords still authenticate normally and cannot enter Guest Key MCP' );
} finally {
	remove_filter( 'map_meta_cap', $cap_filter );
	remove_filter( 'application_password_is_api_request', '__return_true' );
	if ( $determine ) { $GLOBALS['wp_filter']['determine_current_user'] = $determine; }
	else { unset( $GLOBALS['wp_filter']['determine_current_user'] ); }
	$_SERVER = $server;
	$_GET = $get;
	wp_set_current_user( 0 );
	if ( is_int( $uid ) && $uid > 0 ) {
		delete_user_meta( $uid, Guest_Key_Access::META );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $uid );
	}
	foreach ( $saved as $key => $value ) {
		if ( $value[0] ) { $GLOBALS[ $key ] = $value[1]; }
		else { unset( $GLOBALS[ $key ] ); }
	}
	remove_filter( 'pre_wp_mail', $mail );
	$assert( $grants === Guest_Key_Access::grants(), 'Existing administrator grants are preserved' );
}
echo 'Completed ' . $checks . " authentication regression checks.\n";
