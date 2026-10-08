<?php
/** wp --user=admin eval-file tests/multisite-http.php on a local subdirectory network. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! is_multisite() || is_subdomain_install() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! is_super_admin() || ! Guest_Key_Dependency::network() ) { throw new RuntimeException( 'Use a local/development subdirectory network with network-active Guest Key as a super administrator.' ); }
require_once ABSPATH . 'wp-admin/includes/ms.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$owner = get_current_user_id(); $main = get_current_blog_id(); $grants = Guest_Key_Access::grants();
$adapter = is_plugin_active_for_network( Guest_Key_Dependency::PLUGIN );
$managed = get_option( 'guest_key_adapter_managed', null ); $network_managed = get_site_option( 'guest_key_adapter_network_managed', null );
$token = strtolower( wp_generate_password( 10, false ) ); $users = array(); $blog = null; $checks = 0;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo 'PASS: ' . $message . "\n"; };
$http = static function ( $url, $key = null, $method = 'GET', $body = array(), $cookies = array() ) {
	$headers = $key ? array( 'Authorization' => 'Basic ' . base64_encode( $key['username'] . ':' . $key['password'] ) ) : array();
	$r = wp_remote_request( $url, array( 'method' => $method, 'headers' => $headers, 'body' => $body, 'cookies' => $cookies, 'sslverify' => false, 'redirection' => 0, 'timeout' => 20, 'user-agent' => 'GuestKey/' . GUEST_KEY_VERSION . ' (multisite regression test)' ) );
	if ( is_wp_error( $r ) ) { throw new RuntimeException( $r->get_error_message() ); }
	return array( 'status' => wp_remote_retrieve_response_code( $r ), 'data' => json_decode( wp_remote_retrieve_body( $r ), true ), 'response' => $r );
};
try {
	foreach ( array( 'ordinary', 'super' ) as $kind ) {
		$id = wp_insert_user( array( 'user_login' => 'gk-network-' . $kind . '-' . $token, 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$users[ $kind ] = $id;
	}
	grant_super_admin( $users['super'] );
	$network = get_network();
	$blog = wpmu_create_blog( $network->domain, trailingslashit( $network->path ) . 'gk-network-' . $token . '/', 'Guest Key network fixture', $users['super'], array( 'public' => 0 ), $network->id );
	if ( is_wp_error( $blog ) ) { throw new RuntimeException( $blog->get_error_message() ); }
	$main_help = get_rest_url( $main, 'guest-key/v1/help' ); $sub_help = get_rest_url( $blog, 'guest-key/v1/help' );
	wp_set_current_user( $users['ordinary'] );
	$assert( ! Guest_Key_Access::administrator() && is_wp_error( Guest_Key_Access::create( $users['ordinary'] ) ), 'An ordinary multisite administrator cannot issue access' );
	wp_set_current_user( $users['super'] ); $first = Guest_Key_Access::create( $users['super'] );
	$assert( ! is_wp_error( $first ), 'A super administrator can issue a main-site credential' );
	$r = $http( $main_help, $first ); $assert( 200 === $r['status'], 'Main-site credentials reach main-site command help' );
	$r = $http( $sub_help, $first ); $assert( 401 === $r['status'] && 'guest_key_scope' === $r['data']['code'], 'A main-site key cannot authenticate on a subsite' );
	switch_to_blog( $blog );
	try { $second = Guest_Key_Access::create( $users['super'] ); $login = Guest_Key_Browser::url(); $sub_admin = admin_url(); }
	finally { restore_current_blog(); }
	$assert( ! is_wp_error( $second ) && (int) $blog === (int) Guest_Key_Access::grant( $users['super'] )['blog_id'], 'Issuing on a subsite binds the replacement key to that site' );
	$r = $http( $sub_help, $second ); $assert( 200 === $r['status'], 'The replacement key reaches subsite command help' );
	$r = $http( $main_help, $second ); $assert( 401 === $r['status'] && 'guest_key_scope' === $r['data']['code'], 'A subsite key cannot authenticate on the main site' );
	$r = $http( $main_help, $first ); $assert( 401 === $r['status'], 'Issuing on another site revokes the earlier key' );
	$option = 'gk_network_fixture_' . $token;
	$commands = array( 'commands' => array( array( 'command' => 'option update', 'input' => array( 'key' => $option, 'value' => 'subsite-only' ) ) ) );
	$r = $http( get_rest_url( $blog, 'guest-key/v1/run' ), $second, 'POST', $commands );
	$assert( 200 === $r['status'] && $r['data']['ok'] && 'subsite-only' === get_blog_option( $blog, $option ) && false === get_blog_option( $main, $option ), 'Generic data writes stay on the issuing subsite' );
	$r = $http( add_query_arg( 'path', 'plugins/guest-key/guest-key.php', get_rest_url( $blog, 'guest-key/v1/files/read' ) ), $second );
	$assert( 200 === $r['status'] && false !== strpos( $r['data']['content'], 'Plugin Name: Guest Key' ), 'The issuing subsite provides read-only source inspection' );
	$r = $http( $login );
	if ( ! preg_match( '/name="guest_key_nonce"\s+value="([^"]+)"/', wp_remote_retrieve_body( $r['response'] ), $nonce ) ) { throw new RuntimeException( 'Subsite login form is missing.' ); }
	$r = $http( $login, null, 'POST', array( 'log' => $second['username'], 'pwd' => $second['password'], 'guest_key_nonce' => $nonce[1] ) );
	$cookies = wp_remote_retrieve_cookies( $r['response'] );
	$assert( 302 === $r['status'] && count( $cookies ) >= 2, 'A subsite credential signs into its browser form' );
	$r = $http( $sub_admin, null, 'GET', array(), $cookies ); $assert( 200 === $r['status'], 'The temporary browser session accesses its issuing subsite' );
	$r = $http( admin_url(), null, 'GET', array(), $cookies ); $assert( in_array( $r['status'], array( 302, 403 ), true ), 'The temporary browser session cannot access the main-site dashboard' );
	wp_set_current_user( $owner ); revoke_super_admin( $users['super'] );
	$r = $http( $sub_help, $second ); $assert( 403 === $r['status'], 'Removing super-administrator status ends network API access' );
	grant_super_admin( $users['super'] );
	$grant = Guest_Key_Access::grant( $users['super'] ); $grant['expires'] = time() - 1; update_user_meta( $users['super'], Guest_Key_Access::META, $grant );
	$r = $http( $sub_help, $second ); $assert( 401 === $r['status'], 'Expired network credentials fail immediately' );
	$assert( true === Guest_Key_Access::revoke( $users['super'] ), 'Network credentials can be revoked from the main site' );
	$r = $http( $sub_help, $second ); $assert( 401 === $r['status'], 'Revoked subsite credentials no longer authenticate' );
} finally {
	while ( ms_is_switched() ) { restore_current_blog(); }
	wp_set_current_user( $owner );
	foreach ( $users as $id ) { Guest_Key_Access::revoke( $id ); revoke_super_admin( $id ); wpmu_delete_user( $id ); }
	if ( is_int( $blog ) ) { wpmu_delete_blog( $blog, true ); }
	if ( $adapter ) { Guest_Key_Dependency::ensure( true ); }
	elseif ( is_plugin_active_for_network( Guest_Key_Dependency::PLUGIN ) ) { deactivate_plugins( Guest_Key_Dependency::PLUGIN, false, true ); }
	if ( null === $managed ) { delete_option( 'guest_key_adapter_managed' ); } else { update_option( 'guest_key_adapter_managed', $managed, false ); }
	if ( null === $network_managed ) { delete_site_option( 'guest_key_adapter_network_managed' ); } else { update_site_option( 'guest_key_adapter_network_managed', $network_managed ); }
	$assert( $adapter === is_plugin_active_for_network( Guest_Key_Dependency::PLUGIN ), 'Network checks preserve adapter activation' );
	$assert( $grants === Guest_Key_Access::grants(), 'Network checks preserve existing credentials' );
}
echo "Completed {$checks} multisite checks; temporary users and subsite removed.\n";
