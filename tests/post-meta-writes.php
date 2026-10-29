<?php
/** Local regression: editable posts accept underscore fields while declared field rules still apply. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Use a local administrator.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
$id = null; $created = array(); $checks = 0;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo "PASS: {$message}\n"; };
$call = static function ( $action, $key, $extra = array() ) use ( &$id ) { return wp_get_ability( 'guest-key/manage-post-meta' )->execute( array_merge( array( 'action' => $action, 'post_id' => $id, 'key' => $key ), $extra ) ); };
$deny = static function () { return false; };
$deny_post = static function ( $caps, $cap, $user, $args ) use ( &$id ) { return 'edit_post' === $cap && (int) ( $args[0] ?? 0 ) === $id ? array( 'do_not_allow' ) : $caps; };
$deny_meta = static function ( $caps, $cap, $user, $args ) use ( &$id ) { return 'edit_post_meta' === $cap && (int) ( $args[0] ?? 0 ) === $id && '_gk_map_denied' === ( $args[1] ?? '' ) ? array( 'do_not_allow' ) : $caps; };
$sanitize = static function ( $value ) { return sanitize_text_field( $value ); };
register_post_meta( 'post', '_gk_explicit_denied', array( 'type' => 'string', 'single' => true, 'auth_callback' => '__return_false' ) );
register_post_meta( 'post', '_gk_write_integer', array( 'type' => 'integer', 'single' => true, 'auth_callback' => '__return_true' ) );
register_post_meta( 'post', '_gk_write_readonly', array( 'type' => 'string', 'single' => true, 'auth_callback' => '__return_true', 'show_in_rest' => array( 'schema' => array( 'type' => 'string', 'readonly' => true ) ) ) );
add_filter( 'auth_post_meta__gk_hook_denied', $deny );
add_filter( 'auth_post_meta__gk_subtype_denied_for_post', $deny );
add_filter( 'sanitize_post_meta__gk_write_sanitized_for_post', $sanitize );
add_filter( 'map_meta_cap', $deny_meta, 99, 4 );
try {
	$id = wp_insert_post( array( 'post_title' => 'Temporary Guest Key metadata-write fixture', 'post_status' => 'draft', 'post_author' => get_current_user_id() ), true );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Could not create fixture.' ); }
	$assert( ! current_user_can( 'edit_post_meta', $id, '_elodin_schema_script' ), 'Native underscore-only denial is present outside Guest Key' );
	$script = '{"@context":"https://schema.org","name":"Quoted \\"name\\""}';
	$r = $call( 'update', '_elodin_schema_script', array( 'value' => $script ) );
	$assert( ! is_wp_error( $r ) && array( $script ) === $r['values'], 'Unregistered protected schema scripts can be written without losing escaping' );
	$assert( ! current_user_can( 'edit_post_meta', $id, '_elodin_schema_script' ) && ! has_filter( 'auth_post_meta__elodin_schema_script' ), 'The underscore override is removed after the capability check' );
	$call( 'add', '_gk_write_multi', array( 'value' => '' ) );
	$call( 'add', '_gk_write_multi', array( 'value' => array( 'label' => 'Second', 'path' => 'C:\\data' ) ) );
	$assert( array( '', array( 'label' => 'Second', 'path' => 'C:\\data' ) ) === get_post_meta( $id, '_gk_write_multi', false ), 'Protected fields support empty and structured multiple values' );
	$call( 'delete', '_gk_write_multi', array( 'value' => array( 'label' => 'Second', 'path' => 'C:\\data' ) ) );
	$assert( array( '' ) === get_post_meta( $id, '_gk_write_multi', false ), 'Protected-field filtered deletion preserves unrelated values' );
	$call( 'delete', '_gk_write_multi' );
	$assert( ! metadata_exists( 'post', $id, '_gk_write_multi' ), 'Protected-field deletion removes the requested key' );
	$save = wp_get_ability( 'guest-key/save-post' )->execute( array( 'id' => $id, 'meta' => array( 'gk_field' => 'value', '_gk_field' => 'field_example' ) ) );
	$assert( ! is_wp_error( $save ) && 'value' === get_post_meta( $id, 'gk_field', true ) && 'field_example' === get_post_meta( $id, '_gk_field', true ), 'Combined save supports ACF-style value and protected field-reference pairs' );
	$save = wp_get_ability( 'guest-key/save-post' )->execute( array( 'fields' => array( 'title' => 'Temporary new protected fields fixture' ), 'meta' => array( '_gk_new_field' => 'created' ) ) );
	if ( ! is_wp_error( $save ) ) { $created[] = $save['id']; }
	$assert( ! is_wp_error( $save ) && 'created' === get_post_meta( $save['id'], '_gk_new_field', true ), 'New-post combined save accepts unregistered protected fields' );
	foreach ( array( '_gk_explicit_denied', '_gk_hook_denied', '_gk_subtype_denied', '_gk_write_readonly' ) as $key ) {
		update_post_meta( $id, $key, 'original' );
		foreach ( array( 'add', 'update', 'delete' ) as $action ) {
			$assert( is_wp_error( $call( $action, $key, 'delete' === $action ? array() : array( 'value' => 'denied' ) ) ) && 'original' === get_post_meta( $id, $key, true ), 'Declared permissions/readonly rule denies ' . $action . ' for ' . $key );
		}
		$save = wp_get_ability( 'guest-key/save-post' )->execute( array( 'id' => $id, 'fields' => array( 'title' => 'Must not change' ), 'meta' => array( $key => 'denied' ) ) );
		$assert( is_wp_error( $save ) && 'Temporary Guest Key metadata-write fixture' === get_the_title( $id ), 'Combined save preflights declared denial for ' . $key );
	}
	$assert( is_wp_error( $call( 'update', '_gk_map_denied', array( 'value' => 'denied' ) ) ), 'Custom map_meta_cap restrictions remain enforced' );
	$assert( ! is_wp_error( $call( 'update', '_gk_write_integer', array( 'value' => 7 ) ) ) && is_wp_error( $call( 'update', '_gk_write_integer', array( 'value' => 'invalid' ) ) ) && 7 === (int) get_post_meta( $id, '_gk_write_integer', true ), 'Registered schemas still validate permitted protected writes' );
	$assert( ! is_wp_error( $call( 'update', '_gk_write_sanitized', array( 'value' => '<b>Clean</b>' ) ) ) && 'Clean' === get_post_meta( $id, '_gk_write_sanitized', true ), 'Unregistered native metadata sanitizers still run' );
	$fields = wp_get_ability( 'guest-key/manage-post-meta' )->execute( array( 'action' => 'describe', 'post_id' => $id ) );
	$assert( false === $fields['_gk_explicit_denied']['can_edit'] && true === $fields['_gk_write_integer']['can_edit'] && false === $fields['_gk_write_readonly']['can_edit'], 'Field descriptions report actual authorization and readonly status' );
	add_filter( 'map_meta_cap', $deny_post, 99, 4 );
	foreach ( array( 'add', 'update', 'delete' ) as $action ) { $assert( is_wp_error( $call( $action, '_elodin_schema_script', 'delete' === $action ? array() : array( 'value' => 'denied' ) ) ), 'Post-level edit denial blocks ' . $action . ' on protected fields' ); }
} finally {
	remove_filter( 'map_meta_cap', $deny_post, 99 ); remove_filter( 'map_meta_cap', $deny_meta, 99 );
	remove_filter( 'auth_post_meta__gk_hook_denied', $deny ); remove_filter( 'auth_post_meta__gk_subtype_denied_for_post', $deny );
	remove_filter( 'sanitize_post_meta__gk_write_sanitized_for_post', $sanitize );
	if ( is_int( $id ) ) { wp_delete_post( $id, true ); }
	foreach ( $created as $post_id ) { wp_delete_post( $post_id, true ); }
	foreach ( array( '_gk_explicit_denied', '_gk_write_integer', '_gk_write_readonly' ) as $key ) { unregister_post_meta( 'post', $key ); }
}
echo "Completed {$checks} metadata-write checks; temporary content removed.\n";
