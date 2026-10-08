<?php
/** wp --user=admin eval-file tests/content.php; all content fixtures are removed afterward. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) {
	throw new RuntimeException( 'Use WP-CLI on a local/development site as an administrator.' );
}
error_reporting( E_ALL & ~E_DEPRECATED );
foreach ( array( 'gk_native_test', 'gk_rest_test' ) as $name ) {
	if ( post_type_exists( $name ) ) { throw new RuntimeException( 'A fixture type already exists; refusing to replace it.' ); }
	register_post_type( $name, array( 'label' => 'Guest Key content test', 'public' => false, 'show_in_rest' => 'gk_rest_test' === $name,
		'rest_namespace' => 'gk-content-test/v1', 'rest_base' => 'articles', 'supports' => array( 'title', 'editor', 'custom-fields', 'revisions' ), 'map_meta_cap' => true ) );
}
foreach ( array( 'gk_native_group', 'gk_rest_group' ) as $name ) {
	if ( taxonomy_exists( $name ) ) { throw new RuntimeException( 'A fixture taxonomy already exists; refusing to replace it.' ); }
	register_taxonomy( $name, array( 'gk_native_test', 'gk_rest_test' ), array( 'label' => 'Guest Key term test', 'hierarchical' => true, 'show_in_rest' => 'gk_rest_group' === $name,
		'rest_namespace' => 'gk-content-test/v1', 'rest_base' => 'topics' ) );
}
$auth = static function () { return true; };
register_post_meta( 'gk_native_test', 'gk_score', array( 'type' => 'integer', 'single' => true, 'auth_callback' => $auth, 'show_in_rest' => false ) );
register_post_meta( 'gk_native_test', '_gk_data', array( 'type' => 'object', 'single' => true, 'auth_callback' => $auth, 'show_in_rest' => array( 'schema' => array( 'type' => 'object', 'properties' => array( 'label' => array( 'type' => 'string' ) ), 'additionalProperties' => false ) ) ) );
register_post_meta( 'gk_native_test', 'gk_normalized', array( 'type' => 'string', 'single' => true, 'auth_callback' => $auth, 'sanitize_callback' => 'sanitize_text_field' ) );
register_post_meta( 'gk_native_test', 'gk_denied', array( 'type' => 'string', 'auth_callback' => '__return_false' ) );
register_post_meta( 'gk_native_test', 'gk_readonly', array( 'type' => 'string', 'show_in_rest' => array( 'schema' => array( 'type' => 'string', 'readonly' => true ) ) ) );
register_post_meta( 'gk_rest_test', 'gk_score', array( 'type' => 'integer', 'single' => true, 'auth_callback' => $auth, 'show_in_rest' => true ) );
$checks = 0;
$posts = $terms = array();
$grants = Guest_Key_Access::grants();
$uid = get_current_user_id();
$hook_calls = 0;
$save_hook = static function () use ( &$hook_calls ) { $hook_calls++; };
add_action( 'save_post_gk_native_test', $save_hook );
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$checks++; echo 'PASS: ' . $message . "\n";
};
$call = static function ( $name, $input ) {
	$ability = wp_get_ability( 'guest-key/' . $name );
	$result = $ability->execute( $input );
	return is_wp_error( $result ) ? $result : json_decode( wp_json_encode( $result ), true );
};
$deny = static function ( $caps ) { $caps['edit_posts'] = false; $caps['edit_published_posts'] = false; $caps['manage_categories'] = false; return $caps; };
try {
	$types = $call( 'manage-posts', array( 'action' => 'describe', 'post_type' => 'gk_native_test' ) );
	$assert( ! is_wp_error( $types ) && false === $types[0]['show_in_rest'] && in_array( 'gk_native_group', $types[0]['taxonomies'], true ), 'Discover non-REST custom post types and their taxonomies' );
	$taxonomies = $call( 'manage-terms', array( 'action' => 'describe', 'taxonomy' => 'gk_native_group' ) );
	$assert( ! is_wp_error( $taxonomies ) && $taxonomies[0]['hierarchical'] && false === $taxonomies[0]['show_in_rest'], 'Discover non-REST custom taxonomy configuration' );
	$post = $call( 'manage-posts', array( 'action' => 'create', 'post_type' => 'gk_native_test', 'fields' => array( 'title' => 'Guest Key fixture', 'content' => '<!-- wp:paragraph --><p>Path C:\\example and "quotes".</p><!-- /wp:paragraph -->' ) ) );
	$assert( ! is_wp_error( $post ) && 'draft' === $post['post_status'], 'Create a non-REST custom draft' );
	$posts[] = $post['ID'];
	$id = $post['ID'];
	$assert( $hook_calls > 0 && false !== strpos( $post['post_content'], 'C:\\example' ), 'Native save hooks fire and content escaping is preserved' );
	$updated = $call( 'manage-posts', array( 'action' => 'update', 'id' => $id, 'fields' => array( 'title' => 'Guest Key updated fixture', 'status' => 'publish' ) ) );
	$assert( ! is_wp_error( $updated ) && 'publish' === get_post_status( $id ) && 'Guest Key updated fixture' === $updated['post_title'], 'Edit and publish a non-REST custom post' );
	$list = $call( 'manage-posts', array( 'action' => 'list', 'post_type' => 'gk_native_test', 'per_page' => 1 ) );
	$assert( ! is_wp_error( $list ) && 1 === $list['total'] && $id === $list['items'][0]['ID'], 'List custom posts with pagination totals' );
	$assert( is_wp_error( $call( 'manage-posts', array( 'action' => 'get' ) ) ), 'Item operations require an explicit post ID' );
	$assert( is_wp_error( $call( 'manage-posts', array( 'action' => 'update', 'id' => $id, 'fields' => array( 'status' => 'not-a-status' ) ) ) ), 'Reject unregistered post statuses' );
	$term = $call( 'manage-terms', array( 'action' => 'create', 'taxonomy' => 'gk_native_group', 'fields' => array( 'name' => 'Guest Key C:\\group "one"' ) ) );
	$assert( ! is_wp_error( $term ) && false !== strpos( $term['name'], 'C:\\group' ), 'Create a custom term with escaping preserved' );
	$terms[] = array( $term['term_id'], 'gk_native_group' );
	$term_id = $term['term_id'];
	$child = $call( 'manage-terms', array( 'action' => 'create', 'taxonomy' => 'gk_native_group', 'fields' => array( 'name' => 'Guest Key child', 'parent' => $term_id ) ) );
	$assert( ! is_wp_error( $child ) && $term_id === $child['parent'], 'Create hierarchical custom terms' );
	$terms[] = array( $child['term_id'], 'gk_native_group' );
	$changed = $call( 'manage-terms', array( 'action' => 'update', 'taxonomy' => 'gk_native_group', 'id' => $child['term_id'], 'fields' => array( 'description' => 'Custom term updated.' ) ) );
	$assert( ! is_wp_error( $changed ) && 'Custom term updated.' === $changed['description'], 'Update taxonomy terms' );
	$assigned = $call( 'manage-terms', array( 'action' => 'assign', 'taxonomy' => 'gk_native_group', 'post_id' => $id, 'term_ids' => array( $term_id ) ) );
	$assert( ! is_wp_error( $assigned ) && array( $term_id ) === wp_get_object_terms( $id, 'gk_native_group', array( 'fields' => 'ids' ) ), 'Assign existing term IDs to custom posts' );
	$appended = $call( 'manage-terms', array( 'action' => 'assign', 'taxonomy' => 'gk_native_group', 'post_id' => $id, 'term_ids' => array( $child['term_id'] ), 'append' => true ) );
	$assert( ! is_wp_error( $appended ) && 2 === count( $appended ), 'Append taxonomy assignments without removing existing terms' );
	$cleared = $call( 'manage-terms', array( 'action' => 'assign', 'taxonomy' => 'gk_native_group', 'post_id' => $id, 'term_ids' => array() ) );
	$assert( ! is_wp_error( $cleared ) && array() === $cleared, 'An explicit empty term array clears assignments' );
	$assert( is_wp_error( $call( 'manage-terms', array( 'action' => 'assign', 'taxonomy' => 'gk_rest_group', 'post_id' => $id, 'term_ids' => array( $term_id ) ) ) ), 'Reject term IDs from a different taxonomy' );
	$assert( is_wp_error( $call( 'manage-terms', array( 'action' => 'assign', 'taxonomy' => 'gk_native_group', 'post_id' => $id, 'term_ids' => array( 'new term' ) ) ) ), 'Term assignment cannot silently create terms from strings' );
	$meta = $call( 'manage-post-meta', array( 'action' => 'update', 'post_id' => $id, 'key' => 'gk_score', 'value' => 7 ) );
	$assert( ! is_wp_error( $meta ) && 7 === (int) get_post_meta( $id, 'gk_score', true ), 'Write registered meta without REST exposure' );
	$assert( is_wp_error( $call( 'manage-post-meta', array( 'action' => 'update', 'post_id' => $id, 'key' => 'gk_score', 'value' => 'invalid' ) ) ) && 7 === (int) get_post_meta( $id, 'gk_score', true ), 'Registered meta types reject invalid values without mutation' );
	$data = array( 'label' => 'C:\\custom "value"' );
	$meta = $call( 'manage-post-meta', array( 'action' => 'update', 'post_id' => $id, 'key' => '_gk_data', 'value' => $data ) );
	$assert( ! is_wp_error( $meta ) && $data === get_post_meta( $id, '_gk_data', true ), 'Authorized protected object meta preserves structured values and escaping' );
	$assert( is_wp_error( $call( 'manage-post-meta', array( 'action' => 'update', 'post_id' => $id, 'key' => '_gk_data', 'value' => array( 'unknown' => 1 ) ) ) ), 'Registered object schemas reject unknown properties' );
	$meta = $call( 'manage-post-meta', array( 'action' => 'update', 'post_id' => $id, 'key' => 'gk_normalized', 'value' => '<b>Normalized</b>' ) );
	$assert( ! is_wp_error( $meta ) && 'Normalized' === get_post_meta( $id, 'gk_normalized', true ), 'Registered sanitizers run during native meta writes' );
	$assert( is_wp_error( $call( 'manage-post-meta', array( 'action' => 'add', 'post_id' => $id, 'key' => 'gk_score', 'value' => 8 ) ) ), 'Single-value registered fields reject duplicate adds' );
	$revision_id = _wp_put_post_revision( $id );
	$revision_meta = $call( 'manage-post-meta', array( 'action' => 'get', 'post_id' => $revision_id, 'key' => '_gk_data' ) );
	$assert( ! is_wp_error( $revision_meta ) && $data === $revision_meta['values'][0], 'Revision IDs resolve to the parent for reads and authorization' );
	$assert( is_wp_error( $call( 'manage-post-meta', array( 'action' => 'update', 'post_id' => $revision_id, 'key' => 'gk_denied', 'value' => 'Must not save' ) ) ), 'Revision meta cannot bypass the parent field authorization' );
	$assert( is_wp_error( $call( 'manage-post-meta', array( 'action' => 'update', 'post_id' => $revision_id, 'key' => 'gk_score', 'value' => 'invalid' ) ) ), 'Revision meta uses the parent subtype schema' );
	$escaped = 'C:\\meta "quoted"';
	$call( 'manage-post-meta', array( 'action' => 'add', 'post_id' => $id, 'key' => 'gk_escaped', 'value' => $escaped ) );
	$call( 'manage-post-meta', array( 'action' => 'add', 'post_id' => $id, 'key' => 'gk_escaped', 'value' => 'Keep this value' ) );
	$call( 'manage-post-meta', array( 'action' => 'delete', 'post_id' => $id, 'key' => 'gk_escaped', 'value' => $escaped ) );
	$assert( array( 'Keep this value' ) === get_post_meta( $id, 'gk_escaped', false ), 'Filtered meta deletion preserves backslashes and quotes' );
	foreach ( array( 'alpha', 'beta' ) as $value ) { $call( 'manage-post-meta', array( 'action' => 'add', 'post_id' => $id, 'key' => 'gk_multi', 'value' => $value ) ); }
	$assert( array( 'alpha', 'beta' ) === get_post_meta( $id, 'gk_multi', false ), 'Unregistered public multi-value fields are supported' );
	$meta = $call( 'manage-post-meta', array( 'action' => 'delete', 'post_id' => $id, 'key' => 'gk_multi', 'value' => 'alpha' ) );
	$assert( ! is_wp_error( $meta ) && array( 'beta' ) === get_post_meta( $id, 'gk_multi', false ), 'Delete one matching meta value without removing other values' );
	$assert( is_wp_error( $call( 'manage-post-meta', array( 'action' => 'delete', 'post_id' => $id, 'key' => 'gk_multi', 'value' => '' ) ) ) && array( 'beta' ) === get_post_meta( $id, 'gk_multi', false ), 'Ambiguous empty deletion filters cannot remove all values' );
	$call( 'manage-post-meta', array( 'action' => 'delete', 'post_id' => $id, 'key' => 'gk_multi' ) );
	$assert( ! metadata_exists( 'post', $id, 'gk_multi' ), 'Omitting the deletion filter removes the requested post/key only' );
	$fields = $call( 'manage-post-meta', array( 'action' => 'describe', 'post_id' => $id ) );
	$assert( isset( $fields['gk_score'], $fields['_gk_data'] ) && false === $fields['gk_score']['registration']['show_in_rest'], 'Discover registered meta schemas and native authorization' );
	update_post_meta( $id, '_gk_secret', 'Must stay hidden' );
	update_post_meta( $id, 'gk_denied', 'Must stay hidden' );
	foreach ( array( '_gk_secret', 'gk_denied', 'gk_readonly' ) as $key ) {
		$assert( is_wp_error( $call( 'manage-post-meta', array( 'action' => 'update', 'post_id' => $id, 'key' => $key, 'value' => 'Must not save' ) ) ), 'Native authorization/readonly policy protects ' . $key );
	}
	$values = $call( 'manage-post-meta', array( 'action' => 'list', 'post_id' => $id ) );
	$assert( ! isset( $values['_gk_secret'], $values['gk_denied'] ) && $data === $values['_gk_data'][0], 'Meta listing returns authorized values without disclosing denied keys' );
	$rest_term = $call( 'manage-taxonomies', array( 'method' => 'POST', 'path' => '/gk-content-test/v1/topics', 'parameters' => array( 'name' => 'Guest Key REST topic' ) ) );
	$assert( ! is_wp_error( $rest_term ) && 201 === $rest_term['status'], 'Existing REST taxonomy tooling supports custom namespaces and bases' );
	$terms[] = array( $rest_term['data']['id'], 'gk_rest_group' );
	$rest_post = $call( 'manage-content', array( 'method' => 'POST', 'path' => '/gk-content-test/v1/articles', 'parameters' => array( 'title' => 'Guest Key REST fixture', 'meta' => array( 'gk_score' => 9 ), 'topics' => array( $rest_term['data']['id'] ) ) ) );
	$assert( ! is_wp_error( $rest_post ) && 201 === $rest_post['status'], 'Existing REST content tooling creates custom posts with meta and terms' );
	$posts[] = $rest_post['data']['id'];
	$assert( 9 === (int) get_post_meta( end( $posts ), 'gk_score', true ) && array( $rest_term['data']['id'] ) === wp_get_object_terms( end( $posts ), 'gk_rest_group', array( 'fields' => 'ids' ) ), 'REST custom fields and taxonomy assignments are persisted' );
	add_filter( 'user_has_cap', $deny );
	$assert( is_wp_error( $call( 'manage-posts', array( 'action' => 'update', 'id' => $id, 'fields' => array( 'title' => 'Denied update' ) ) ) ), 'Restricted administrators cannot bypass post permissions' );
	$assert( is_wp_error( $call( 'manage-terms', array( 'action' => 'create', 'taxonomy' => 'gk_native_group', 'fields' => array( 'name' => 'Denied term' ) ) ) ), 'Restricted administrators cannot bypass taxonomy permissions' );
	$assert( is_wp_error( $call( 'manage-post-meta', array( 'action' => 'get', 'post_id' => $id, 'key' => 'gk_score' ) ) ), 'Restricted administrators cannot bypass post-meta permissions' );
	$assert( is_wp_error( $call( 'manage-terms', array( 'action' => 'assign', 'taxonomy' => 'gk_native_group', 'post_id' => $id, 'term_ids' => array( $term_id ) ) ) ), 'Restricted administrators cannot bypass assignment permissions' );
	remove_filter( 'user_has_cap', $deny );
	wp_set_current_user( 0 );
	$assert( is_wp_error( $call( 'manage-posts', array( 'action' => 'describe' ) ) ) && is_wp_error( $call( 'manage-terms', array( 'action' => 'describe' ) ) ) && is_wp_error( $call( 'manage-post-meta', array( 'action' => 'list', 'post_id' => $id ) ) ), 'All three abilities require administrative access' );
	wp_set_current_user( $uid );
	$trash = $call( 'manage-posts', array( 'action' => 'trash', 'id' => $id ) );
	$assert( ! is_wp_error( $trash ) && 'trash' === get_post_status( $id ), 'Trash custom content reversibly' );
	$restored = $call( 'manage-posts', array( 'action' => 'restore', 'id' => $id ) );
	$assert( ! is_wp_error( $restored ) && 'trash' !== get_post_status( $id ), 'Restore trashed custom content' );
	$assert( is_wp_error( $call( 'manage-posts', array( 'action' => 'delete', 'id' => $id ) ) ) && get_post( $id ), 'Permanent deletion requires explicit force' );
	$deleted = $call( 'manage-posts', array( 'action' => 'delete', 'id' => $id, 'force' => true ) );
	$assert( ! is_wp_error( $deleted ) && ! get_post( $id ), 'Explicit permanent deletion removes custom content' );
	$deleted = $call( 'manage-terms', array( 'action' => 'delete', 'taxonomy' => 'gk_native_group', 'id' => $child['term_id'] ) );
	$assert( ! is_wp_error( $deleted ) && ! get_term( $child['term_id'], 'gk_native_group' ), 'Delete a custom taxonomy term' );
	foreach ( Guest_Key_Abilities::inventory() as $row ) {
		if ( in_array( $row['name'], array( 'guest-key/manage-posts', 'guest-key/manage-terms', 'guest-key/manage-post-meta' ), true ) ) {
			$assert( false !== strpos( $row['source']['file'], '/abilities/' . substr( $row['name'], 10 ) . '.php' ), 'Backend attributes ' . $row['name'] . ' to its separate ability file' );
		}
	}
	$servers = Guest_Key_Discovery::inventory();
	$guest_server = $servers[ array_search( 'guest-key', array_column( $servers, 'name' ), true ) ];
	$assert( 2 === count( $guest_server['details']['tools'] ) && isset( $guest_server['details']['tools']['guest-key-help'], $guest_server['details']['tools']['guest-key-run'] ), 'MCP exposes only command help and execution' );
} finally {
	remove_filter( 'user_has_cap', $deny );
	wp_set_current_user( $uid );
	foreach ( $posts as $id ) { if ( get_post( $id ) ) { wp_delete_post( $id, true ); } }
	foreach ( array_reverse( $terms ) as $term ) { wp_delete_term( $term[0], $term[1] ); }
	remove_action( 'save_post_gk_native_test', $save_hook );
	foreach ( array( 'gk_native_group', 'gk_rest_group' ) as $name ) { unregister_taxonomy( $name ); }
	foreach ( array( 'gk_native_test', 'gk_rest_test' ) as $name ) { unregister_post_type( $name ); }
	$assert( $grants === Guest_Key_Access::grants(), 'Existing access grants are preserved' );
}
echo "Completed {$checks} content checks; temporary posts and terms removed.\n";
