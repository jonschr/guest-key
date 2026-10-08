<?php
/**
 * Run on a disposable local/development WordPress site with Guest Key active:
 * 1. Copy tests/fixture.php to wp-content/mu-plugins/guest-key-test-fixture.php.
 * 2. wp --path=/path/to/site --user=admin eval-file /path/to/guest-key/tests/integration.php
 * 3. Remove the temporary mu-plugin fixture.
 * Tests use a temporary administrator and preserve other users' credentials.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Use WP-CLI on a disposable local/development site.' );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! Guest_Key_Access::administrator() || ! wp_has_ability( 'guest-key-test/private-echo' ) ) {
	WP_CLI::error( 'Use an administrator and install the temporary test fixture.' );
}

$GLOBALS['guest_key_test_checks'] = 0;
function guest_key_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$GLOBALS['guest_key_test_checks']++;
	echo 'PASS: ' . $message . "\n";
}

function guest_key_http( $credential, $body, $session = '', $url = null ) {
	$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-11-25' );
	if ( $credential ) {
		$headers['Authorization'] = 'Basic ' . base64_encode( $credential['username'] . ':' . $credential['password'] );
	}
	if ( $session ) {
		$headers['Mcp-Session-Id'] = $session;
	}
	$result = wp_remote_post( $url ?: Guest_Key_Access::endpoint(), array(
		'headers' => $headers, 'body' => wp_json_encode( $body ), 'timeout' => 15,
		// The Cove development certificate is not in WordPress's bundled public CA store.
		'sslverify' => false,
		'user-agent' => 'T3Code/0.0.46-nightly.20261005.2689 (user-directed AI agent; agent=Codex; model=gpt-6.1-sol)',
	) );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( 'HTTP test failed: ' . $result->get_error_message() );
	}
	return array( 'status' => wp_remote_retrieve_response_code( $result ), 'body' => json_decode( wp_remote_retrieve_body( $result ), true ), 'session' => wp_remote_retrieve_header( $result, 'mcp-session-id' ) );
}

$original_uid = get_current_user_id();
$other_grants = Guest_Key_Access::grants();
$uid = wp_insert_user( array( 'user_login' => 'guest-key-test-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
if ( is_wp_error( $uid ) ) {
	throw new RuntimeException( 'Could not create the temporary integration-test account.' );
}
wp_set_current_user( $uid );
$regular = null;
$adapter_was_active = is_plugin_active( Guest_Key_Dependency::PLUGIN );
$managed = get_option( 'guest_key_adapter_managed' );
$init = array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array( 'protocolVersion' => '2025-11-25', 'capabilities' => new stdClass(), 'clientInfo' => array( 'name' => 'Guest Key integration tests', 'version' => '1.0' ) ) );
try {
	$data = get_plugin_data( GUEST_KEY_FILE, false, false );
	guest_key_assert( 'Jon Schroeder' === $data['Author'] && 'https://elod.in' === $data['PluginURI'], 'Plugin author and URI' );
	$inventory = Guest_Key_Abilities::inventory();
	$core = array_values( array_filter( $inventory, static function ( $row ) { return 'core/get-site-info' === $row['name']; } ) );
	guest_key_assert( $core && 'WordPress core' === $core[0]['source']['name'], 'Core ability source attribution' );
	guest_key_assert( count( $inventory ) >= 5 && false === wp_get_ability( 'guest-key-test/private-echo' )->get_meta_item( 'public' ), 'Inventory includes private abilities without changing their metadata' );
	$deny = static function ( $caps ) { $caps['manage_options'] = false; return $caps; };
	add_filter( 'user_has_cap', $deny );
	guest_key_assert( is_wp_error( Guest_Key_Access::create( $uid ) ), 'Non-administrators cannot issue access' );
	remove_filter( 'user_has_cap', $deny );
	add_filter( 'wp_is_application_passwords_available_for_user', '__return_false' );
	guest_key_assert( is_wp_error( Guest_Key_Access::create( $uid ) ), 'Disabled application-password policy is respected' );
	remove_filter( 'wp_is_application_passwords_available_for_user', '__return_false' );
	$regular = WP_Application_Passwords::create_new_application_password( $uid, array( 'name' => 'Guest Key unrelated-password test' ) );
	guest_key_assert( ! is_wp_error( $regular ), 'Create an unrelated credential for isolation testing' );
	$first = Guest_Key_Access::create( $uid );
	guest_key_assert( ! is_wp_error( $first ), 'Issue temporary access' );
	$first_grant = Guest_Key_Access::grant( $uid );
	guest_key_assert( abs( $first_grant['expires'] - time() - 21600 ) <= 2, 'Credential expires after six hours' );
	guest_key_assert( false !== wp_next_scheduled( Guest_Key_Access::CRON, array( $uid, $first_grant['uuid'] ) ), 'Schedule cleanup for the issued UUID' );
	$second = Guest_Key_Access::create( $uid );
	guest_key_assert( ! is_wp_error( $second ) && $first['password'] !== $second['password'], 'A second click creates a fresh credential' );
	$second_grant = Guest_Key_Access::grant( $uid );
	$items = WP_Application_Passwords::get_user_application_passwords( $uid );
	guest_key_assert( 1 === count( array_filter( $items, static function ( $item ) { return Guest_Key_Access::APP_ID === $item['app_id']; } ) ), 'Only one Guest Key password remains' );
	guest_key_assert( null === WP_Application_Passwords::get_user_application_password( $uid, $first_grant['uuid'] ), 'Previous credential is deleted' );
	Guest_Key_Access::expire( $uid, $first_grant['uuid'] );
	guest_key_assert( $second_grant === Guest_Key_Access::grant( $uid ), 'An old expiry event cannot revoke its replacement' );
	$result = guest_key_http( $first, $init );
	guest_key_assert( 401 === $result['status'], 'Old password fails over HTTP' );
	$result = guest_key_http( $second, $init );
	guest_key_assert( 200 === $result['status'] && isset( $result['body']['result']['serverInfo'] ) && $result['session'], 'MCP initialize succeeds with the new credential' );
	$session = $result['session'];
	$list = guest_key_http( $second, array( 'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new stdClass() ), $session );
	$names = array_column( $list['body']['result']['tools'] ?? array(), 'name' );
	guest_key_assert( in_array( 'core-get-site-info', $names, true ) && in_array( 'guest-key-test-private-echo', $names, true ), 'MCP lists core and private registered abilities' );
	$call = guest_key_http( $second, array( 'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => array( 'name' => 'guest-key-test-private-echo', 'arguments' => array( 'message' => 'Guest Key test succeeded' ) ) ), $session );
	guest_key_assert( 200 === $call['status'] && empty( $call['body']['result']['isError'] ) && false !== strpos( wp_json_encode( $call['body'] ), 'Guest Key test succeeded' ), 'Execute a private ability through MCP' );
	$call = guest_key_http( $second, array( 'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => array( 'name' => 'guest-key-test-denied', 'arguments' => new stdClass() ) ), $session );
	guest_key_assert( ! empty( $call['body']['error'] ) || ! empty( $call['body']['result']['isError'] ), 'An administrator still respects an ability’s permission callback' );
	$result = guest_key_http( $second, array( 'name' => 'Must not create a permanent password' ), '', rest_url( 'wp/v2/users/' . $uid . '/application-passwords' ) );
	guest_key_assert( 401 === $result['status'], 'Guest Key password cannot mint credentials through core REST (HTTP ' . $result['status'] . ')' );
	$other = array( 'username' => get_userdata( $uid )->user_login, 'password' => $regular[0] );
	$result = guest_key_http( $other, $init );
	guest_key_assert( 403 === $result['status'], 'Unrelated application passwords cannot enter Guest Key MCP' );
	$result = guest_key_http( null, $init );
	guest_key_assert( in_array( $result['status'], array( 401, 403 ), true ), 'Anonymous MCP access is denied' );
	$expired = $second_grant;
	$expired['expires'] = time() - 1;
	update_user_meta( $uid, Guest_Key_Access::META, $expired );
	$result = guest_key_http( $second, array( 'jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/list' ), $session );
	guest_key_assert( 401 === $result['status'], 'Expired credentials fail before cron runs, including an existing MCP session' );
	Guest_Key_Access::expire( $uid, $second_grant['uuid'] );
	guest_key_assert( ! Guest_Key_Access::grant( $uid ), 'Expiry deletes the grant' );
	guest_key_assert( (bool) $other_grants === is_plugin_active( Guest_Key_Dependency::PLUGIN ), 'Adapter stays active only while other administrators have active grants' );
	guest_key_assert( null !== WP_Application_Passwords::get_user_application_password( $uid, $regular[1]['uuid'] ), 'Unrelated application passwords survive rotation and expiry' );
	$third = Guest_Key_Access::create( $uid );
	guest_key_assert( ! is_wp_error( $third ) && is_plugin_active( Guest_Key_Dependency::PLUGIN ), 'Creating access re-enables the adapter' );
	delete_option( 'guest_key_adapter_managed' );
	Guest_Key_Access::revoke( $uid );
	guest_key_assert( is_plugin_active( Guest_Key_Dependency::PLUGIN ), 'Revocation preserves an independently managed adapter' );
	update_option( 'guest_key_adapter_managed', true, false );
	if ( ! $other_grants ) {
		$fourth = Guest_Key_Access::create( $uid );
		guest_key_assert( ! is_wp_error( $fourth ), 'Create a credential for deactivation testing' );
		deactivate_plugins( plugin_basename( GUEST_KEY_FILE ) );
		guest_key_assert( ! Guest_Key_Access::grant( $uid ), 'Plugin deactivation revokes temporary credentials' );
		activate_plugin( plugin_basename( GUEST_KEY_FILE ) );
	} else {
		echo "SKIP: Plugin deactivation while another administrator has an existing grant.\n";
	}
} finally {
	Guest_Key_Access::revoke( $uid );
	if ( is_array( $regular ) ) {
		WP_Application_Passwords::delete_application_password( $uid, $regular[1]['uuid'] );
	}
	if ( $adapter_was_active ) {
		Guest_Key_Dependency::ensure();
	}
	if ( $managed ) {
		update_option( 'guest_key_adapter_managed', $managed, false );
	} else {
		delete_option( 'guest_key_adapter_managed' );
	}
	wp_set_current_user( $original_uid );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $uid );
}
echo 'Completed ' . $GLOBALS['guest_key_test_checks'] . " integration checks.\n";
