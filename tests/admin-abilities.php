<?php
/**
 * Local integration test. Install admin-fixture.php as a temporary MU plugin and
 * provide wp-content/uploads/guest-key-admin-test.zip containing a harmless test plugin.
 * Run with wp --user=admin eval-file tests/admin-abilities.php. Remove the fixture/ZIP afterward.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() || ! post_type_exists( 'guest_key_test' ) ) {
	throw new RuntimeException( 'Use a local site, an administrator, and the admin fixture.' );
}
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( file_exists( WP_PLUGIN_DIR . '/guest-key-admin-test' ) ) { throw new RuntimeException( 'Test package is already installed; refusing to replace it.' ); }
$original_uid = get_current_user_id();
$original_grants = Guest_Key_Access::grants();
$original_active = is_plugin_active( Guest_Key_Dependency::PLUGIN );
$original_managed = get_option( 'guest_key_adapter_managed', null );
$comment_status = get_option( 'default_comment_status' );
$uid = wp_insert_user( array( 'user_login' => 'guest-key-admin-test-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Could not create test administrator.' ); }
$posts = $terms = $users = $media = array();
$count = $requests = 0;
$started = microtime( true );
$assert = static function ( $condition, $message ) use ( &$count ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$count++; echo 'PASS: ' . $message . "\n";
};
$http = static function ( $body, $credential, $session = '' ) use ( &$requests ) {
	$requests++;
	$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'Authorization' => 'Basic ' . base64_encode( $credential['username'] . ':' . $credential['password'] ) );
	if ( $session ) { $headers['Mcp-Session-Id'] = $session; }
	$response = wp_remote_post( Guest_Key_Access::endpoint(), array( 'headers' => $headers, 'body' => wp_json_encode( $body ), 'sslverify' => false, 'timeout' => 40, 'user-agent' => 'T3Code/0.0.46-nightly.20261005.2689 (user-directed AI agent; agent=Codex; model=gpt-6.1-sol) GuestKey/' . GUEST_KEY_VERSION . ' WordPress/' . get_bloginfo( 'version' ) ) );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
	if ( 429 === wp_remote_retrieve_response_code( $response ) ) { throw new RuntimeException( 'Rate limited; stop test traffic and honor Retry-After.' ); }
	$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $decoded ) ) { throw new RuntimeException( 'MCP response was not JSON.' ); }
	return array( 'body' => $decoded, 'session' => wp_remote_retrieve_header( $response, 'mcp-session-id' ) );
};
try {
	wp_set_current_user( $uid );
	$credential = Guest_Key_Access::create( $uid );
	if ( is_wp_error( $credential ) ) { throw new RuntimeException( $credential->get_error_message() ); }
	$init = $http( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array( 'protocolVersion' => '2025-11-25', 'capabilities' => new stdClass(), 'clientInfo' => array( 'name' => 'Guest Key admin tests', 'version' => '1.0' ) ) ), $credential );
	$session = $init['session'];
	$assert( $session && isset( $init['body']['result'] ), 'Temporary administrator connects through MCP' );
	$call = static function ( $name, $arguments = array() ) use ( $http, $credential, $session ) {
		$response = $http( array( 'jsonrpc' => '2.0', 'id' => wp_rand(), 'method' => 'tools/call', 'params' => array( 'name' => 'guest-key-' . $name, 'arguments' => $arguments ?: new stdClass() ) ), $credential, $session );
		$body = $response['body'];
		if ( ! empty( $body['error'] ) || ! empty( $body['result']['isError'] ) ) { return new WP_Error( 'mcp_failure', wp_json_encode( $body ) ); }
		return $body['result']['structuredContent'] ?? json_decode( $body['result']['content'][0]['text'] ?? 'null', true );
	};
	$tool_response = $http( array( 'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new stdClass() ), $credential, $session );
	$names = array_column( $tool_response['body']['result']['tools'] ?? array(), 'name' );
	foreach ( glob( GUEST_KEY_DIR . '/abilities/*.php' ) as $file ) {
		$name = basename( $file, '.php' );
		$assert( in_array( 'guest-key-' . $name, $names, true ), 'MCP discovers ' . $name );
	}
	foreach ( Guest_Key_Abilities::inventory() as $row ) {
		if ( 0 === strpos( $row['name'], 'guest-key/' ) ) { $assert( 'Guest Key' === $row['source']['name'] && false !== strpos( $row['source']['file'], '/abilities/' ), 'Backend attributes ' . $row['name'] . ' to its individual file' ); }
	}
	$api = $call( 'get-admin-api', array( 'ability' => 'manage-content' ) );
	$assert( ! is_wp_error( $api ) && false !== strpos( wp_json_encode( $api ), '(?P<id>' ), 'API discovery includes item routes and native schemas' );
	$post = $call( 'manage-content', array( 'method' => 'POST', 'path' => '/wp/v2/posts', 'parameters' => array( 'title' => 'Guest Key admin integration', 'content' => '<!-- wp:paragraph --><p>Original test text.</p><!-- /wp:paragraph -->', 'status' => 'draft' ) ) );
	$assert( ! is_wp_error( $post ) && 201 === $post['status'], 'Create a draft through MCP with native REST validation' );
	$posts[] = $post['data']['id'];
	$changed = $call( 'manage-content', array( 'method' => 'POST', 'path' => '/wp/v2/posts/' . $posts[0], 'parameters' => array( 'title' => 'Guest Key edited test draft' ) ) );
	$assert( ! is_wp_error( $changed ) && 'Guest Key edited test draft' === get_post( $posts[0] )->post_title, 'Update draft content through MCP' );
	$invalid = $call( 'manage-content', array( 'method' => 'POST', 'path' => '/wp/v2/posts/' . $posts[0], 'parameters' => array( 'status' => 'not-a-status' ) ) );
	$assert( is_wp_error( $invalid ), 'Native input validation rejects an invalid post status' );
	$custom = $call( 'manage-content', array( 'method' => 'POST', 'path' => '/wp/v2/guest-key-test-content', 'parameters' => array( 'title' => 'Guest Key custom post test', 'status' => 'draft' ) ) );
	$assert( ! is_wp_error( $custom ) && 201 === $custom['status'], 'REST-enabled custom post types are supported' );
	$posts[] = $custom['data']['id'];
	$list = $call( 'manage-content', array( 'path' => '/wp/v2/posts', 'parameters' => array( 'status' => 'draft', 'per_page' => 1 ) ) );
	$assert( ! is_wp_error( $list ) && isset( $list['total'], $list['total_pages'] ), 'Pagination totals survive the MCP response' );
	$term = $call( 'manage-taxonomies', array( 'method' => 'POST', 'path' => '/wp/v2/categories', 'parameters' => array( 'name' => 'Guest Key test ' . wp_generate_password( 8, false ) ) ) );
	$assert( ! is_wp_error( $term ) && 201 === $term['status'], 'Create a taxonomy term through MCP' );
	$terms[] = $term['data']['id'];
	$settings = $call( 'manage-settings', array( 'method' => 'POST', 'path' => '/wp/v2/settings', 'parameters' => array( 'default_comment_status' => 'open' === $comment_status ? 'closed' : 'open' ) ) );
	wp_cache_delete( 'alloptions', 'options' );
	$assert( ! is_wp_error( $settings ) && get_option( 'default_comment_status' ) !== $comment_status, 'Update a native site setting through MCP' );
	update_option( 'default_comment_status', $comment_status );
	$user = $call( 'manage-users', array( 'method' => 'POST', 'path' => '/wp/v2/users', 'parameters' => array( 'username' => 'guest-key-created-' . wp_generate_password( 10, false ), 'email' => 'guest-key-test-' . wp_generate_password( 10, false ) . '@example.invalid', 'password' => wp_generate_password( 32 ), 'roles' => array( 'subscriber' ) ) ) );
	$assert( ! is_wp_error( $user ) && 201 === $user['status'], 'Create a user through native account permissions' );
	$users[] = $user['data']['id'];
	$upload = $call( 'upload-media', array( 'filename' => 'guest-key-admin-test.gif', 'base64' => 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'fields' => array( 'alt_text' => 'Guest Key temporary upload' ) ) );
	$assert( ! is_wp_error( $upload ) && 201 === $upload['status'], 'Upload media through the native attachment controller' );
	$media[] = $upload['data']['id'];
	$assert( 'Guest Key temporary upload' === get_post_meta( $media[0], '_wp_attachment_image_alt', true ), 'Upload fields are saved through native validation' );
	$assert( is_wp_error( $call( 'upload-media', array( 'filename' => 'test.php', 'base64' => base64_encode( '<?php echo 1;' ) ) ) ), 'PHP file uploads are rejected' );
	foreach ( array( '/wp/v2/plugins', '/wp/v2/posts/../users', '/wp/v2/posts%2F..%2Fusers', '/wp/v2/posts?status=draft' ) as $path ) {
		$assert( is_wp_error( $call( 'manage-content', array( 'path' => $path ) ) ), 'Grouped ability rejects out-of-scope path ' . $path );
	}
	$assert( is_wp_error( $call( 'manage-users', array( 'path' => '/wp/v2/users/me/application-passwords', 'method' => 'POST', 'parameters' => array( 'name' => 'Must not create a permanent password' ) ) ) ), 'Admin abilities cannot issue durable application passwords' );
	$assert( is_wp_error( $call( 'activate-theme', array( 'stylesheet' => 'guest-key-does-not-exist' ) ) ), 'Theme activation rejects an uninstalled theme' );
	$assert( is_wp_error( $call( 'delete-theme', array( 'stylesheet' => get_stylesheet() ) ) ), 'Theme deletion protects the active theme' );
	$updates = $call( 'list-updates' );
	$assert( ! is_wp_error( $updates ) && isset( $updates['plugins'], $updates['themes'], $updates['core'] ), 'Native update offers are discoverable' );
	$assert( is_wp_error( $call( 'apply-update', array( 'type' => 'core', 'target' => 'wordpress' ) ) ), 'Core updates require an explicit version' );
	$installed = $call( 'install-plugin', array( 'slug' => 'guest-key-admin-test' ) );
	$assert( ! is_wp_error( $installed ) && file_exists( WP_PLUGIN_DIR . '/guest-key-admin-test/guest-key-admin-test.php' ) && ! is_plugin_active( 'guest-key-admin-test/guest-key-admin-test.php' ), 'Install a test ZIP through the native plugin installer' );
	$activated = $call( 'activate-plugin', array( 'plugin' => 'guest-key-admin-test/guest-key-admin-test.php' ) );
	wp_cache_delete( 'alloptions', 'options' );
	$assert( ! is_wp_error( $activated ) && is_plugin_active( 'guest-key-admin-test/guest-key-admin-test.php' ), 'Activate an installed plugin through MCP' );
	$next = $http( array( 'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list', 'params' => new stdClass() ), $credential, $session );
	$assert( in_array( 'guest-key-test-installed', array_column( $next['body']['result']['tools'], 'name' ), true ), 'Newly activated plugin abilities appear on the next MCP request' );
	$assert( is_wp_error( $call( 'delete-plugin', array( 'plugin' => 'guest-key-admin-test/guest-key-admin-test' ) ) ), 'Active plugin deletion is rejected' );
	$deactivated = $call( 'deactivate-plugin', array( 'plugin' => 'guest-key-admin-test/guest-key-admin-test' ) );
	wp_cache_delete( 'alloptions', 'options' );
	$assert( ! is_wp_error( $deactivated ) && ! is_plugin_active( 'guest-key-admin-test/guest-key-admin-test.php' ), 'Deactivate a plugin through MCP' );
	$deleted = $call( 'delete-plugin', array( 'plugin' => 'guest-key-admin-test/guest-key-admin-test' ) );
	$assert( ! is_wp_error( $deleted ) && ! file_exists( WP_PLUGIN_DIR . '/guest-key-admin-test' ), 'Delete an inactive test plugin through MCP' );
	$deny = static function ( $caps ) { $caps['edit_posts'] = false; $caps['install_plugins'] = false; return $caps; };
	add_filter( 'user_has_cap', $deny );
	$assert( is_wp_error( wp_get_ability( 'guest-key/manage-content' )->execute( array( 'method' => 'POST', 'path' => '/wp/v2/posts', 'parameters' => array( 'title' => 'Must not be created' ) ) ) ), 'Native content permissions still apply to administrators with restricted caps' );
	$assert( is_wp_error( wp_get_ability( 'guest-key/install-plugin' )->execute( array( 'slug' => 'hello-dolly' ) ) ), 'Plugin installation capability is enforced' );
	remove_filter( 'user_has_cap', $deny );
	$disable_files = static function () { return false; };
	add_filter( 'file_mod_allowed', $disable_files );
	$assert( is_wp_error( wp_get_ability( 'guest-key/install-plugin' )->execute( array( 'slug' => 'hello-dolly' ) ) ), 'Host filesystem policy is enforced' );
	remove_filter( 'file_mod_allowed', $disable_files );
	$subscriber = wp_insert_user( array( 'user_login' => 'guest-key-admin-test-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
	$users[] = $subscriber;
	wp_set_current_user( $subscriber );
	$assert( is_wp_error( wp_get_ability( 'guest-key/manage-content' )->execute( array( 'path' => '/wp/v2/posts' ) ) ), 'Non-administrators cannot use the grouped abilities' );
	wp_set_current_user( $uid );
} finally {
	if ( isset( $deny ) ) { remove_filter( 'user_has_cap', $deny ); }
	if ( isset( $disable_files ) ) { remove_filter( 'file_mod_allowed', $disable_files ); }
	wp_set_current_user( $uid );
	foreach ( $media as $id ) { wp_delete_attachment( $id, true ); }
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	foreach ( $terms as $id ) { wp_delete_term( $id, 'category' ); }
	foreach ( $users as $id ) { wp_delete_user( $id ); }
	update_option( 'default_comment_status', $comment_status );
	if ( file_exists( WP_PLUGIN_DIR . '/guest-key-admin-test' ) ) { deactivate_plugins( 'guest-key-admin-test/guest-key-admin-test.php' ); delete_plugins( array( 'guest-key-admin-test/guest-key-admin-test.php' ) ); }
	Guest_Key_Access::revoke( $uid );
	wp_delete_user( $uid );
	wp_set_current_user( $original_uid );
	if ( $original_active && ! is_plugin_active( Guest_Key_Dependency::PLUGIN ) ) { activate_plugin( Guest_Key_Dependency::PLUGIN ); }
	if ( ! $original_active && is_plugin_active( Guest_Key_Dependency::PLUGIN ) ) { deactivate_plugins( Guest_Key_Dependency::PLUGIN ); }
	if ( null === $original_managed ) { delete_option( 'guest_key_adapter_managed' ); } else { update_option( 'guest_key_adapter_managed', $original_managed ); }
	$assert( $original_grants === Guest_Key_Access::grants(), 'Existing administrator grants are preserved' );
}
echo 'Completed ' . $count . ' admin checks over ' . $requests . ' sequential MCP requests in ' . round( microtime( true ) - $started, 1 ) . " seconds.\n";
