<?php
/** Local HTTP integration: wp --user=admin eval-file tests/rest.php. Uses only temporary content/accounts. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) {
	throw new RuntimeException( 'Use WP-CLI as an administrator on a local/development single site with its web server running.' );
}
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$original_uid = get_current_user_id();
$grants = Guest_Key_Access::grants();
$adapter = is_plugin_active( Guest_Key_Dependency::PLUGIN );
$managed = get_option( 'guest_key_adapter_managed' );
$uid = null;
$posts = array();
$checks = $requests = 0;
$mail = static function () { return true; };
add_filter( 'pre_wp_mail', $mail );
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$checks++; echo 'PASS: ' . $message . "\n";
};
$http = static function ( $method, $route, $key, $parameters = array() ) use ( &$requests ) {
	$url = rest_url( $route );
	$headers = array( 'Authorization' => 'Basic ' . base64_encode( $key['username'] . ':' . $key['password'] ), 'Content-Type' => 'application/json' );
	$args = array( 'method' => $method, 'headers' => $headers, 'sslverify' => false, 'timeout' => 15, 'redirection' => 0,
		'user-agent' => 'T3Code/0.0.46-nightly.20261005.2689 (user-directed AI agent; agent=Codex; model=gpt-6.1-sol) GuestKey/' . GUEST_KEY_VERSION . ' WordPress/' . get_bloginfo( 'version' ) );
	if ( 'GET' === $method || 'OPTIONS' === $method ) { $url = add_query_arg( $parameters, $url ); }
	else { $args['body'] = wp_json_encode( $parameters ?: new stdClass() ); }
	$requests++;
	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
	$status = wp_remote_retrieve_response_code( $response );
	if ( 429 === $status ) { throw new RuntimeException( 'Rate limited; stop testing.' ); }
	return array( 'status' => $status, 'data' => json_decode( wp_remote_retrieve_body( $response ), true ) );
};
try {
	$uid = wp_insert_user( array( 'user_login' => 'guest-key-rest-test-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
	$assert( ! is_wp_error( $uid ), 'Create a temporary native REST administrator' );
	wp_set_current_user( $uid );
	$key = Guest_Key_Access::create( $uid );
	$assert( ! is_wp_error( $key ) && rest_url() === $key['rest_api'] && 0 === strpos( $key['bundle'], 'WordPress REST API (HTTP Basic): ' ), 'Copied connection instructions lead with native REST credentials' );
	$assert( false !== strpos( $key['bundle'], 'Optional MCP endpoint' ) && strpos( $key['bundle'], 'WordPress REST API (HTTP Basic): ' ) < strpos( $key['bundle'], 'Optional MCP endpoint' ), 'MCP is an additional connection option' );
	$response = $http( 'GET', 'wp/v2/users/me', $key, array( 'context' => 'edit' ) );
	$assert( 200 === $response['status'] && $uid === $response['data']['id'], 'HTTP Basic application password authenticates to native users/me' );
	$response = $http( 'GET', '', $key );
	$assert( 200 === $response['status'] && isset( $response['data']['routes']['/wp/v2/posts'] ), 'Native REST index discovers existing routes' );
	$response = $http( 'OPTIONS', 'wp/v2/posts', $key );
	$assert( 200 === $response['status'] && isset( $response['data']['schema']['properties']['meta']['properties']['footnotes'] ), 'Native REST OPTIONS discovers registered meta schemas' );
	$meta = '[{"id":"guest-key-rest-test","content":"Original REST meta"}]';
	$response = $http( 'POST', 'wp/v2/posts', $key, array( 'title' => 'Guest Key native REST fixture', 'status' => 'draft', 'content' => '<!-- wp:paragraph --><p>Temporary API fixture.</p><!-- /wp:paragraph -->', 'meta' => array( 'footnotes' => $meta ) ) );
	$assert( 201 === $response['status'] && isset( $response['data']['id'] ), 'Create a draft through the ordinary posts endpoint' );
	$id = $response['data']['id'];
	$posts[] = $id;
	$assert( $meta === $response['data']['meta']['footnotes'], 'Native REST writes and returns registered post meta' );
	$response = $http( 'POST', 'wp/v2/posts/' . $id, $key, array( 'title' => 'Guest Key native REST edited fixture', 'meta' => array( 'footnotes' => '[]' ) ) );
	$assert( 200 === $response['status'] && 'Guest Key native REST edited fixture' === $response['data']['title']['raw'] && '[]' === $response['data']['meta']['footnotes'], 'Update content and meta directly without MCP' );
	$response = $http( 'GET', 'wp/v2/posts', $key, array( 'status' => 'draft', 'author' => $uid, 'context' => 'edit' ) );
	$assert( 200 === $response['status'] && in_array( $id, array_column( $response['data'], 'id' ), true ), 'Native REST lists private draft content for the temporary administrator' );
	$response = $http( 'POST', 'wp/v2/posts/' . $id, $key, array( 'status' => 'not-a-status' ) );
	$assert( 400 === $response['status'], 'Native controller validation remains in force' );
	$response = $http( 'POST', 'wp/v2/users/' . $uid . '/application-passwords', $key, array( 'name' => 'Must not create a durable credential' ) );
	$assert( 403 === $response['status'] && 1 === count( WP_Application_Passwords::get_user_application_passwords( $uid ) ), 'Temporary REST key cannot create another application password' );
	$response = $http( 'GET', 'wp/v2/users/me', $key, array( 'context' => 'edit' ) );
	$assert( 200 === $response['status'], 'A denied operation does not break subsequent ordinary access' );
	$grant = Guest_Key_Access::grant( $uid );
	$expired = $grant;
	$expired['expires'] = time() - 1;
	update_user_meta( $uid, Guest_Key_Access::META, $expired );
	$response = $http( 'GET', 'wp/v2/users/me', $key );
	$assert( 401 === $response['status'] && 'guest_key_expired' === $response['data']['code'], 'Native REST access expires immediately without waiting for cron' );
	$response = $http( 'GET', '', $key );
	$assert( 401 === $response['status'] && 'guest_key_expired' === $response['data']['code'], 'Expired credentials return an authentication error even on the public REST index' );
	update_user_meta( $uid, Guest_Key_Access::META, $grant );
	get_userdata( $uid )->set_role( 'subscriber' );
	$response = $http( 'GET', 'wp/v2/users/me', $key );
	$assert( 403 === $response['status'] && 'guest_key_forbidden' === $response['data']['code'], 'Demotion removes native REST access without authentication recursion' );
	get_userdata( $uid )->set_role( 'administrator' );
	$key2 = Guest_Key_Access::create( $uid );
	$assert( ! is_wp_error( $key2 ), 'Issue a replacement temporary REST key' );
	$response = $http( 'GET', 'wp/v2/users/me', $key );
	$assert( 401 === $response['status'], 'Rotation ends the old key’s ordinary REST access' );
	$response = $http( 'GET', 'wp/v2/users/me', $key2 );
	$assert( 200 === $response['status'], 'The replacement key authenticates normally' );
	$response = $http( 'DELETE', 'wp/v2/posts/' . $id, $key2 );
	$assert( 200 === $response['status'] && 'trash' === $response['data']['status'], 'Native REST can trash temporary content' );
	$assert( true === Guest_Key_Access::revoke( $uid ), 'Revoke the temporary credential' );
	$response = $http( 'GET', 'wp/v2/users/me', $key2 );
	$assert( 401 === $response['status'], 'Revocation ends ordinary REST access' );
} finally {
	wp_set_current_user( $original_uid );
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	if ( is_int( $uid ) && $uid > 0 ) {
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
echo "Completed {$checks} native REST checks across {$requests} HTTP requests; temporary content and account removed.\n";
