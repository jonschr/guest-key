<?php
/** wp --user=admin eval-file tests/commands.php; temporary content and credentials are removed. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) {
	throw new RuntimeException( 'Use WP-CLI as an administrator on a local/development site.' );
}
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( post_type_exists( 'gk_command_portfolio' ) || taxonomy_exists( 'gk_command_kind' ) ) { throw new RuntimeException( 'Fixture definitions already exist.' ); }
$owner = get_current_user_id();
$grants = Guest_Key_Access::grants();
$adapter_active = is_plugin_active( Guest_Key_Dependency::PLUGIN );
$managed = get_option( 'guest_key_adapter_managed', null );
$uid = wp_insert_user( array( 'user_login' => 'gk-command-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
if ( is_wp_error( $uid ) ) { throw new RuntimeException( $uid->get_error_message() ); }
$posts = $attachments = $terms = $options = array();
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$checks++; echo 'PASS: ' . $message . "\n";
};
$run = static function ( $commands ) { return wp_get_ability( 'guest-key/run' )->execute( array( 'commands' => $commands ) ); };
$http = static function ( $method, $path, $key = null, $body = null, $url = null ) {
	$headers = array( 'Content-Type' => 'application/json' );
	if ( $key ) { $headers['Authorization'] = 'Basic ' . base64_encode( $key['username'] . ':' . $key['password'] ); }
	$args = array( 'method' => $method, 'headers' => $headers, 'sslverify' => false, 'timeout' => 30, 'user-agent' => 'GuestKey/' . GUEST_KEY_VERSION . ' (local integration test)' );
	if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
	$response = wp_remote_request( $url ?: rest_url( 'guest-key/v1/' . $path ), $args );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
	return array( 'status' => wp_remote_retrieve_response_code( $response ), 'data' => json_decode( wp_remote_retrieve_body( $response ), true ) );
};
$deny = static function ( $caps ) { $caps['edit_posts'] = false; $caps['edit_published_posts'] = false; $caps['install_plugins'] = false; return $caps; };
try {
	wp_set_current_user( $uid );
	register_post_type( 'gk_command_portfolio', array( 'label' => 'Command test portfolio', 'show_in_rest' => false, 'supports' => array( 'title', 'editor', 'thumbnail' ), 'map_meta_cap' => true ) );
	register_taxonomy( 'gk_command_kind', 'gk_command_portfolio', array( 'label' => 'Command test kind' ) );
	register_post_meta( 'gk_command_portfolio', 'gk_score', array( 'type' => 'integer', 'single' => true, 'auth_callback' => '__return_true' ) );
	register_post_meta( 'gk_command_portfolio', '_gk_details', array( 'type' => 'object', 'single' => true, 'auth_callback' => '__return_true' ) );
	register_post_meta( 'gk_command_portfolio', 'gk_denied', array( 'type' => 'string', 'auth_callback' => '__return_false' ) );
	$help = wp_get_ability( 'guest-key/help' )->execute( array() );
	$assert( in_array( 'post save', $help['commands'], true ) && ! isset( $help['input_schema'] ), 'Default help lists only compact command names' );
	$help = wp_get_ability( 'guest-key/help' )->execute( array( 'command' => 'post meta update' ) );
	$assert( ! isset( $help['input_schema']['properties']['action'] ) && isset( $help['input_schema']['properties']['value'] ), 'Focused help returns the existing input schema without a redundant action' );
	$help = wp_get_ability( 'guest-key/help' )->execute( array( 'command' => array( 'plugin install', 'post save', 'post meta describe' ) ) );
	$assert( 3 === count( $help['schemas'] ) && isset( $help['schemas'][0]['input_schema']['properties']['slug'] ), 'Discover several relevant command schemas in one request' );
	$help = wp_get_ability( 'guest-key/help' )->execute( array( 'command' => 'design guide' ) );
	$assert( in_array( 'motion', $help['input_schema']['properties']['section']['enum'], true ), 'Design advice is discoverable through focused command help' );
	$guide = $run( array( array( 'command' => 'design guide' ) ) );
	$overview = $guide['results'][0]['data'];
	$assert( $guide['ok'] && 'overview' === $overview['section'] && strlen( $overview['content'] ) < 4000 && isset( $overview['sections']['images'], $overview['sections']['wordpress'], $overview['sections']['review'] ), 'Default design guide returns a compact workflow and topic index' );
	$topics = array_keys( $overview['sections'] );
	$guide = $run( array_map( static function ( $section ) { return array( 'command' => 'design guide', 'input' => array( 'section' => $section ) ); }, $topics ) );
	$assert( $guide['ok'] && count( $topics ) === count( $guide['results'] ), 'All bundled design references load through one command batch' );
	foreach ( $guide['results'] as $index => $result ) {
		$assert( $topics[ $index ] === $result['data']['section'] && strlen( $result['data']['content'] ) > 600 && ( 'overview' === $topics[ $index ] || ! isset( $result['data']['sections'] ) ), 'Requested design section returns only its own guidance: ' . $topics[ $index ] );
	}
	foreach ( array( 'unknown', '../../guest-key.php' ) as $section ) {
		$guide = $run( array( array( 'command' => 'design guide', 'input' => array( 'section' => $section ) ) ) );
		$assert( ! $guide['ok'] && empty( $guide['results'] ), 'Unknown design topics and filesystem paths are rejected: ' . $section );
	}
	wp_set_current_user( 0 );
	$assert( is_wp_error( wp_get_ability( 'guest-key/design-guide' )->execute( array() ) ), 'Design reference access preserves the native administrator permission gate' );
	wp_set_current_user( $uid );
	$schema = $run( array( array( 'command' => 'post meta describe', 'input' => array( 'post_type' => 'gk_command_portfolio' ) ) ) );
	$assert( $schema['ok'] && isset( $schema['results'][0]['data']['gk_score'] ) && ! isset( $schema['results'][0]['data']['gk_score']['can_edit'] ), 'Discover registered fields before creating a non-REST item without claiming object authorization' );
	$content = '<!-- wp:paragraph --><p>Quotes "one", apostrophe\'s, path C:\\portfolio.</p><!-- /wp:paragraph -->';
	$details = array( 'label' => 'C:\\portfolio "one"', 'images' => array( 1, 2 ) );
	$batch = $run( array(
		array( 'command' => 'media upload', 'input' => array( 'filename' => 'gk-command-test.gif', 'base64' => 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'fields' => array( 'alt_text' => 'Temporary portfolio image' ) ) ),
		array( 'command' => 'term create', 'input' => array( 'taxonomy' => 'gk_command_kind', 'fields' => array( 'name' => 'Temporary kind' ) ) ),
		array( 'command' => 'post save', 'input' => array( 'post_type' => 'gk_command_portfolio', 'fields' => array( 'title' => 'Command portfolio', 'content' => $content ), 'meta' => array( 'gk_score' => 42, '_gk_details' => $details ), 'terms' => array( 'gk_command_kind' => array( array( '$ref' => '1.data.id' ) ) ), 'featured_media' => array( '$ref' => '0.data.id' ) ) ),
		array( 'command' => 'post get', 'input' => array( 'id' => array( '$ref' => '2.data.id' ) ) ),
	) );
	if ( isset( $batch['results'][0]['data']['id'] ) ) { $attachments[] = $batch['results'][0]['data']['id']; }
	if ( isset( $batch['results'][1]['data']['id'] ) ) { $terms[] = $batch['results'][1]['data']['id']; }
	if ( isset( $batch['results'][2]['data']['id'] ) ) { $posts[] = $batch['results'][2]['data']['id']; }
	if ( isset( $batch['error']['data']['post_id'] ) ) { $posts[] = $batch['error']['data']['post_id']; }
	$assert( ! is_wp_error( $batch ) && $batch['ok'] && 4 === count( $batch['results'] ), 'Upload image, create term, save non-REST portfolio and read it in one batch' );
	$id = $posts[0];
	$assert( $content === get_post( $id )->post_content && $details === get_post_meta( $id, '_gk_details', true ), 'Combined content writes preserve HTML, quotes, backslashes and structured metadata' );
	$assert( 42 === (int) get_post_meta( $id, 'gk_score', true ) && $attachments[0] === (int) get_post_thumbnail_id( $id ) && $terms === wp_get_object_terms( $id, 'gk_command_kind', array( 'fields' => 'ids' ) ), 'Combined write persists meta, taxonomy and featured image' );
	$assert( ! isset( $batch['results'][2]['data']['post_content'] ), 'Write results return IDs without echoing large content' );
	$list = $run( array( array( 'command' => 'post list', 'input' => array( 'post_type' => 'gk_command_portfolio' ) ) ) );
	$assert( $list['ok'] && $id === $list['results'][0]['data']['items'][0]['id'] && ! isset( $list['results'][0]['data']['items'][0]['post_content'] ), 'Post lists retain pagination and IDs without loading each content body' );
	$updated = $run( array( array( 'command' => 'post save', 'input' => array( 'id' => $id, 'meta' => array( 'gk_score' => 43 ), 'terms' => array( 'gk_command_kind' => array() ), 'featured_media' => 0 ) ) ) );
	$assert( $updated['ok'] && 43 === (int) get_post_meta( $id, 'gk_score', true ) && ! get_post_thumbnail_id( $id ) && ! wp_get_object_terms( $id, 'gk_command_kind' ), 'Update metadata without fields, clear terms and featured image' );
	$failed = $run( array(
		array( 'command' => 'post meta update', 'input' => array( 'post_id' => $id, 'key' => 'gk_score', 'value' => 44 ) ),
		array( 'command' => 'post meta update', 'input' => array( 'post_id' => $id, 'key' => 'gk_denied', 'value' => 'denied' ) ),
		array( 'command' => 'post meta update', 'input' => array( 'post_id' => $id, 'key' => 'gk_score', 'value' => 99 ) ),
	) );
	$assert( ! $failed['ok'] && 1 === $failed['failed_index'] && 1 === $failed['skipped'] && 1 === count( $failed['results'] ) && 44 === (int) get_post_meta( $id, 'gk_score', true ), 'Batch failure reports completed writes and stops before later mutations' );
	foreach ( array( array( 'gk_score' => 'wrong type' ), array( '_unregistered' => 'denied' ) ) as $meta ) {
		$failed = $run( array( array( 'command' => 'post save', 'input' => array( 'id' => $id, 'fields' => array( 'title' => 'Must not change' ), 'meta' => $meta ) ) ) );
		$assert( ! $failed['ok'] && 'Command portfolio' === get_post( $id )->post_title, 'Invalid or unauthorized metadata is rejected before updating fields' );
	}
	$failed = $run( array( array( 'command' => 'post save', 'input' => array( 'post_type' => 'gk_command_portfolio', 'fields' => array( 'title' => 'Partial fixture' ), 'meta' => array( 'gk_denied' => 'denied' ) ) ) ) );
	$partial = $failed['error']['data']['post_id'] ?? 0;
	if ( $partial ) { $posts[] = $partial; }
	$assert( ! $failed['ok'] && $partial && array( 'fields' ) === $failed['error']['data']['completed'], 'A new-post permission failure returns the created ID and completed steps for repair' );
	$failed = $run( array( array( 'command' => 'post save', 'input' => array( 'id' => $id, 'fields' => array( 'unsupported' => true ) ) ) ) );
	$assert( ! $failed['ok'] && 'Command portfolio' === get_post( $id )->post_title, 'Combined writes retain the existing field schema validation' );
	foreach ( array( 'wp plugin list', 'eval', 'post create; shell' ) as $command ) {
		$failed = $run( array( array( 'command' => $command ) ) );
		$assert( ! $failed['ok'] && 'guest_key_unknown_command' === $failed['error']['code'], 'Only exact supported command names execute: ' . $command );
	}
	$failed = $run( array( array( 'command' => 'post get', 'input' => array( 'id' => array( '$ref' => '1.data.id' ) ) ) ) );
	$assert( ! $failed['ok'] && 'guest_key_reference' === $failed['error']['code'], 'Forward or missing references fail without running a command' );
	$assert( is_wp_error( $run( array_fill( 0, 21, array( 'command' => 'plugin list' ) ) ) ), 'Batch size is bounded to 20 commands' );
	$failed = $run( array( array( 'command' => 'ability run', 'input' => array( 'name' => 'guest-key/run', 'input' => array( 'commands' => array( array( 'command' => 'plugin list' ) ) ) ) ) ) );
	$assert( ! $failed['ok'], 'Ability execution cannot nest the command runner' );
	add_filter( 'user_has_cap', $deny );
	$failed = $run( array( array( 'command' => 'post save', 'input' => array( 'id' => $id, 'meta' => array( 'gk_score' => 99 ) ) ) ) );
	$assert( ! $failed['ok'] && 44 === (int) get_post_meta( $id, 'gk_score', true ), 'Command writes preserve object-specific capabilities' );
	$failed = $run( array( array( 'command' => 'plugin install', 'input' => array( 'slug' => 'hello-dolly' ) ) ) );
	$assert( ! $failed['ok'], 'Command plugin installation preserves native permissions' );
	remove_filter( 'user_has_cap', $deny );
	$key = Guest_Key_Access::create( $uid );
	if ( is_wp_error( $key ) ) { throw new RuntimeException( $key->get_error_message() ); }
	$assert( false !== strpos( $key['bundle'], 'Commands: GET ' ) && false !== strpos( $key['bundle'], 'run design guide' ), 'Copied connection bundle teaches commands and design guide discovery' );
	$response = $http( 'GET', 'help', $key );
	$assert( 200 === $response['status'] && in_array( 'post save', $response['data']['commands'], true ), 'Native Basic authentication reaches command help over HTTP' );
	$response = $http( 'GET', 'help', $key, null, add_query_arg( array( 'rest_route' => '/guest-key/v1/help', 'command' => 'post save' ), home_url( '/' ) ) );
	$assert( 200 === $response['status'] && 'post save' === $response['data']['command'], 'Plain-permalink command URLs ignore the REST transport parameter while preserving help input' );
	$response = $http( 'OPTIONS', 'run', $key );
	$assert( 200 === $response['status'] && isset( $response['data']['endpoints'][0]['args']['commands']['items']['properties']['input'] ), 'HTTP OPTIONS exposes the command item schema' );
	$options[] = 'gk_command_option_' . $uid;
	$response = $http( 'POST', 'run', $key, array( 'commands' => array(
		array( 'command' => 'option update', 'input' => array( 'key' => $options[0], 'value' => array( 'label' => 'HTTP "quotes"', 'path' => 'C:\\portfolio' ) ) ),
		array( 'command' => 'option get', 'input' => array( 'key' => $options[0] ) ),
	) ) );
	$assert( 200 === $response['status'] && $response['data']['ok'] && array( 'label' => 'HTTP "quotes"', 'path' => 'C:\\portfolio' ) === $response['data']['results'][1]['data']['value'], 'Generic option commands preserve structured JSON through authenticated HTTP' );
	$response = $http( 'POST', 'run', $key, array( 'commands' => array( array( 'command' => 'design guide', 'input' => array( 'section' => 'motion' ) ) ) ) );
	$assert( 200 === $response['status'] && $response['data']['ok'] && 'motion' === $response['data']['results'][0]['data']['section'], 'Temporary credentials retrieve a focused design reference over HTTP' );
	$response = $http( 'POST', 'run', $key, array( 'commands' => array(
		array( 'command' => 'post save', 'input' => array( 'fields' => array( 'title' => 'HTTP command draft' ), 'meta' => array( 'gk_http_value' => array( 'label' => 'HTTP "quotes"' ) ) ) ),
		array( 'command' => 'post get', 'input' => array( 'id' => array( '$ref' => '0.data.id' ) ) ),
	) ) );
	if ( isset( $response['data']['results'][0]['data']['id'] ) ) { $posts[] = $response['data']['results'][0]['data']['id']; }
	$assert( 200 === $response['status'] && $response['data']['ok'] && 'HTTP command draft' === $response['data']['results'][1]['data']['post_title'], 'Create and verify content through one authenticated HTTP command batch' );
	$assert( array( 'label' => 'HTTP "quotes"' ) === get_post_meta( end( $posts ), 'gk_http_value', true ), 'HTTP command batch persists structured metadata' );
	$response = $http( 'POST', 'run', $key, array( 'commands' => array( array( 'command' => 'rest request', 'input' => array( 'method' => 'POST', 'path' => '/wp/v2/users/me/application-passwords', 'parameters' => array( 'name' => 'Denied' ) ) ) ) ) );
	$assert( ! $response['data']['ok'], 'Generic REST commands cannot issue durable credentials' );
	$response = $http( 'GET', 'help' );
	$assert( in_array( $response['status'], array( 401, 403 ), true ), 'Anonymous command access is denied' );
	$grant = Guest_Key_Access::grant( $uid ); $grant['expires'] = time() - 1; update_user_meta( $uid, Guest_Key_Access::META, $grant );
	$response = $http( 'GET', 'help', $key );
	$assert( 401 === $response['status'], 'Expired credentials cannot reach direct commands' );
} finally {
	remove_filter( 'user_has_cap', $deny );
	wp_set_current_user( $uid );
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	foreach ( $attachments as $id ) { wp_delete_attachment( $id, true ); }
	foreach ( $terms as $id ) { wp_delete_term( $id, 'gk_command_kind' ); }
	foreach ( $options as $name ) { delete_option( $name ); }
	Guest_Key_Access::revoke( $uid ); wp_delete_user( $uid ); wp_set_current_user( $owner );
	if ( $adapter_active ) { Guest_Key_Dependency::ensure(); } elseif ( is_plugin_active( Guest_Key_Dependency::PLUGIN ) ) { deactivate_plugins( Guest_Key_Dependency::PLUGIN ); }
	if ( null === $managed ) { delete_option( 'guest_key_adapter_managed' ); } else { update_option( 'guest_key_adapter_managed', $managed, false ); }
	unregister_taxonomy( 'gk_command_kind' ); unregister_post_type( 'gk_command_portfolio' );
	$assert( $grants === Guest_Key_Access::grants(), 'Existing access grants are preserved' );
}
echo "Completed {$checks} command checks; temporary fixtures removed.\n";
