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
	$assert( 200 === $response->get_status() && array( 'guest-key-help', 'guest-key-run' ) === $names, 'Authenticated MCP session discovers only the two command tools' );
	$response = $mcp( 'tools/call', array( 'name' => 'guest-key-run', 'arguments' => array( 'commands' => array( array( 'command' => 'ability run', 'input' => array( 'name' => 'core/get-site-info' ) ) ) ) ), $session );
	$assert( 200 === $response->get_status() && ! empty( $response->get_data()['result']['structuredContent']['ok'] ), 'Authenticated MCP session executes a read-only core ability through commands' );
	$response = $mcp( 'tools/call', array( 'name' => 'guest-key-run', 'arguments' => array( 'commands' => array( array( 'command' => 'design guide', 'input' => array( 'section' => 'system' ) ) ) ) ), $session );
	$guide = $response->get_data()['result']['structuredContent'] ?? array();
	$assert( 200 === $response->get_status() && ! empty( $guide['ok'] ) && 'system' === ( $guide['results'][0]['data']['section'] ?? '' ), 'Existing MCP command tool delivers focused typography and color guidance' );
	$response = $mcp( 'tools/call', array( 'name' => 'guest-key-run', 'arguments' => array( 'commands' => array( array( 'command' => 'user meta get', 'input' => array( 'user_id' => $uid, 'key' => 'nickname' ) ) ) ) ), $session );
	$data = $response->get_data()['result']['structuredContent'] ?? array();
	$assert( 200 === $response->get_status() && ! empty( $data['ok'] ) && ! empty( $data['results'][0]['data']['values'] ), 'Generic metadata commands use the existing MCP tool without expanding its list' );

	$deny = static function ( $caps ) { $caps['manage_options'] = false; return $caps; };
	add_filter( 'user_has_cap', $deny );
	try { $assert( ! Guest_Key_Access::can_connect(), 'MCP honors filtered administrator permissions' ); }
	finally { remove_filter( 'user_has_cap', $deny ); }
	get_userdata( $uid )->set_role( 'subscriber' );
	get_userdata( $uid )->add_cap( 'manage_options' );
	$reset( $password );
	$assert( 0 === get_current_user_id() && ! Guest_Key_Access::can_connect() && 1 === $attempts, 'Removing the administrator role ends API access even when manage_options is retained' );
	$response = $mcp( 'tools/list', new stdClass(), $session );
	$assert( in_array( $response->get_status(), array( 401, 403 ), true ), 'The registered MCP route rejects a demoted administrator’s existing session' );
	get_userdata( $uid )->set_role( 'administrator' );

	$_GET['rest_route'] = '/wp/v2/users/' . $uid . '/application-passwords';
	$reset( $password );
	$assert( $uid === get_current_user_id() && Guest_Key_Access::is_guest_request( $uid ), 'Guest Key authenticates through ordinary WordPress REST routes' );
	$assert( ! current_user_can( 'create_app_password', $uid ) && ! current_user_can( 'edit_plugins' ) && ! current_user_can( 'edit_themes' ), 'Temporary API requests cannot issue durable application passwords or edit files' );
	$assert( current_user_can( 'edit_posts' ) && current_user_can( 'install_plugins' ), 'Temporary API requests retain ordinary content and package permissions' );
	$_GET['rest_route'] = '/wp/v2/users/me';
	$reset( $password );
	$request = new WP_REST_Request( 'GET', '/wp/v2/users/me' );
	$request->set_param( 'context', 'edit' );
	$response = rest_do_request( $request );
	$assert( 200 === $response->get_status() && $uid === $response->get_data()['id'], 'Native REST controller accepts the temporary application password' );
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
	$assert( get_current_user_id() === $uid && ! Guest_Key_Access::can_connect() && ! Guest_Key_Access::is_guest_request( $uid ) && current_user_can( 'create_app_password', $uid ) && current_user_can( 'edit_plugins' ), 'Unrelated passwords retain their ordinary permissions and cannot enter Guest Key MCP' );
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
