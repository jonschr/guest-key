<?php
/** Run with an empty WP_PLUGIN_DIR, DISALLOW_FILE_MODS=true and the adapter filtered out of active_plugins. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! defined( 'DISALLOW_FILE_MODS' ) || ! DISALLOW_FILE_MODS || file_exists( WP_PLUGIN_DIR . '/mcp-adapter/mcp-adapter.php' ) ) { throw new RuntimeException( 'Use the isolated missing-adapter bootstrap described in README on a local single site.' ); }
if ( ! class_exists( 'Guest_Key_Access' ) ) { require dirname( __DIR__ ) . '/guest-key.php'; }
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/user.php';
$owner = get_current_user_id(); $grants = Guest_Key_Access::grants();
$saved_error = get_option( 'guest_key_dependency_error', null );
$server = $_SERVER; $get = $_GET; $saved_pagenow = $GLOBALS['pagenow'];
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } $checks++; echo 'PASS: ' . $message . "\n"; };
$uid = wp_insert_user( array( 'user_login' => 'gk-native-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
if ( is_wp_error( $uid ) ) { throw new RuntimeException( $uid->get_error_message() ); }
$managed = static function () { return false; };
$active_plugins = static function ( $plugins ) { return array_values( array_diff( $plugins, array( Guest_Key_Dependency::PLUGIN ) ) ); };
add_filter( 'option_guest_key_adapter_managed', $managed );
add_filter( 'option_active_plugins', $active_plugins );
try {
	wp_set_current_user( $uid );
	$key = Guest_Key_Access::create( $uid );
	$assert( ! is_wp_error( $key ) && ! empty( $key['password'] ), 'Native credential creation succeeds without an installable MCP adapter' );
	$assert( false === $key['mcp']['ready'] && null === $key['endpoint'] && 'guest_key_install_denied' === $key['mcp']['error']['code'], 'MCP readiness and setup error are reported separately' );
	$assert( false !== strpos( $key['bundle'], 'Optional MCP unavailable:' ) && false !== strpos( $key['bundle'], 'Native REST commands and browser sign-in are ready.' ), 'Connection bundle gives working native paths instead of an unavailable MCP endpoint' );
	$grant = Guest_Key_Access::grant( $uid );
	$assert( $grant['expires'] > time() && $grant['expires'] <= time() + Guest_Key_Access::TTL, 'Native-only grants retain the six-hour expiry' );
	$headers = array( 'Authorization' => 'Basic ' . base64_encode( $key['username'] . ':' . $key['password'] ) );
	$r = wp_remote_get( rest_url( 'wp/v2/users/me' ), array( 'headers' => $headers, 'sslverify' => false, 'timeout' => 20, 'user-agent' => 'GuestKey/' . GUEST_KEY_VERSION . ' (native access regression test)' ) );
	$assert( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) && $uid === json_decode( wp_remote_retrieve_body( $r ), true )['id'], 'Credential issued after MCP failure authenticates to ordinary REST over HTTP' );
	$r = wp_remote_get( rest_url( 'guest-key/v1/help' ), array( 'headers' => $headers, 'sslverify' => false, 'timeout' => 20, 'user-agent' => 'GuestKey/' . GUEST_KEY_VERSION . ' (native access regression test)' ) );
	$assert( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) && isset( json_decode( wp_remote_retrieve_body( $r ), true )['commands'] ), 'Credential issued after MCP failure reaches direct command help over HTTP' );
	$_SERVER['HTTPS'] = 'on'; $_SERVER['REQUEST_METHOD'] = 'POST'; $_GET['action'] = 'guest-key'; $GLOBALS['pagenow'] = 'wp-login.php';
	$user = Guest_Key_Browser::authenticate( $key['username'], $key['password'] );
	$assert( $user instanceof WP_User && $user->ID === $uid, 'Dedicated browser authentication works in the process with no adapter' );
	$token = Guest_Key_Browser::create_session( $uid );
	$assert( ! is_wp_error( $token ) && WP_Session_Tokens::get_instance( $uid )->verify( $token ), 'Native browser session creation works without an adapter' );
	Guest_Key_Access::revoke( $uid );
	$assert( ! WP_Session_Tokens::get_instance( $uid )->verify( $token ) && ! Guest_Key_Access::grant( $uid ), 'Revocation ends native-only credentials and browser sessions' );
} finally {
	Guest_Key_Access::revoke( $uid ); wp_delete_user( $uid ); wp_set_current_user( $owner );
	remove_filter( 'option_guest_key_adapter_managed', $managed );
	remove_filter( 'option_active_plugins', $active_plugins );
	if ( null === $saved_error ) { delete_option( 'guest_key_dependency_error' ); } else { update_option( 'guest_key_dependency_error', $saved_error, false ); }
	$_SERVER = $server; $_GET = $get; $GLOBALS['pagenow'] = $saved_pagenow;
	$assert( $grants === Guest_Key_Access::grants(), 'Existing grants are preserved after missing-adapter verification' );
}
echo "Completed {$checks} native-only access checks; temporary fixtures removed.\n";
