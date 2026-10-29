<?php
/** Local regression: complete metadata reads remain independent of declared field write restrictions. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Use a local administrator.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
$uid = get_current_user_id(); $id = null; $checks = 0;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo "PASS: {$message}\n"; };
$call = static function ( $action, $id, $key = null, $extra = array() ) {
	$input = array_merge( array( 'action' => $action, 'post_id' => $id ), $extra );
	if ( null !== $key ) { $input['key'] = $key; }
	return wp_get_ability( 'guest-key/manage-post-meta' )->execute( $input );
};
$deny_post = static function ( $caps, $cap, $user, $args ) use ( &$id ) {
	return 'edit_post' === $cap && (int) ( $args[0] ?? 0 ) === (int) $id ? array( 'do_not_allow' ) : $caps;
};
register_post_meta( 'post', '_gk_read_denied', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'auth_callback' => '__return_false' ) );
register_post_meta( 'post', 'gk_read_sanitized', array( 'type' => 'string', 'single' => true, 'auth_callback' => '__return_true', 'sanitize_callback' => 'sanitize_text_field' ) );
try {
	$id = wp_insert_post( array( 'post_title' => 'Temporary Guest Key metadata-read fixture', 'post_status' => 'draft', 'post_author' => $uid ), true );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Could not create fixture.' ); }
	$expected = array(
		'_land_co_property_sms_number' => array( '555-0100' ),
		'gk_read_ordinary' => array( 'ordinary value' ),
		'_gk_read_multi' => array( 'first', array( 'label' => 'second', 'empty' => '' ) ),
		'_gk_read_empty' => array( '' ),
		'_gk_read_empty_array' => array( array() ),
		'_gk_read_zero' => array( '0' ),
		'_gk_read_denied' => array( 'registered but not editable' ),
	);
	foreach ( $expected as $key => $values ) { foreach ( $values as $value ) { add_post_meta( $id, $key, $value ); } }
	$assert( ! current_user_can( 'edit_post_meta', $id, '_land_co_property_sms_number' ) && ! current_user_can( 'edit_post_meta', $id, '_gk_read_denied' ), 'Fixture denies per-key write permission for unregistered protected and registered private fields' );
	foreach ( $expected as $key => $values ) {
		$result = $call( 'get', $id, $key );
		$assert( ! is_wp_error( $result ) && array( 'key' => $key, 'exists' => true, 'values' => $values ) === $result, 'Post editors read all existing values for ' . $key );
	}
	$missing = $call( 'get', $id, '_gk_read_missing' );
	$assert( array( 'key' => '_gk_read_missing', 'exists' => false, 'values' => array() ) === $missing, 'Missing keys remain distinct from stored empty strings, arrays, and zeroes' );
	$all = $call( 'list', $id );
	foreach ( $expected as $key => $values ) { $assert( isset( $all[$key] ) && $values === $all[$key], 'Listing includes all values for ' . $key ); }
	$actual_keys = array_keys( get_post_meta( $id ) ); $listed_keys = array_keys( $all ); sort( $actual_keys ); sort( $listed_keys );
	$assert( $actual_keys === $listed_keys && ! array_key_exists( '_gk_read_missing', $all ), 'Listing returns every stored key without inventing missing ones' );
	$before = get_post_meta( $id );
	foreach ( array( '_gk_read_denied' ) as $key ) {
		foreach ( array( 'add', 'update', 'delete' ) as $action ) {
			$assert( is_wp_error( $call( $action, $id, $key, 'delete' === $action ? array() : array( 'value' => 'must not save' ) ) ), 'Reading does not grant ' . $action . ' access to ' . $key );
		}
	}
	$assert( $before === get_post_meta( $id ), 'Denied mutations leave all metadata unchanged' );
	$assert( ! is_wp_error( $call( 'update', $id, '_land_co_property_sms_number', array( 'value' => '555-0101' ) ) ) && '555-0101' === get_post_meta( $id, '_land_co_property_sms_number', true ), 'Underscore-only protection does not deny legitimate post-editor writes' );
	$assert( ! is_wp_error( $call( 'update', $id, 'gk_read_ordinary', array( 'value' => 'updated' ) ) ), 'Existing public-key write authorization is retained' );
	$assert( ! is_wp_error( $call( 'update', $id, 'gk_read_sanitized', array( 'value' => '<b>Clean</b>' ) ) ) && 'Clean' === get_post_meta( $id, 'gk_read_sanitized', true ), 'Registered sanitizers still apply to authorized writes' );
	$assert( is_wp_error( $call( 'update', $id, 'gk_read_sanitized', array( 'value' => array( 'wrong type' ) ) ) ) && 'Clean' === get_post_meta( $id, 'gk_read_sanitized', true ), 'Registered value validation still rejects invalid writes' );
	add_filter( 'map_meta_cap', $deny_post, 99, 4 );
	try {
		$assert( ! current_user_can( 'edit_post', $id ), 'Administrator fixture lacks edit permission on the target post' );
		$assert( is_wp_error( $call( 'get', $id, '_land_co_property_sms_number' ) ) && is_wp_error( $call( 'list', $id ) ), 'Users without post-level edit permission cannot read metadata' );
		$assert( is_wp_error( $call( 'get', $id, '_gk_read_missing' ) ), 'Unauthorized reads do not disclose whether a key exists' );
	} finally { remove_filter( 'map_meta_cap', $deny_post, 99 ); }
} finally {
	remove_filter( 'map_meta_cap', $deny_post, 99 );
	if ( is_int( $id ) ) { wp_delete_post( $id, true ); }
	unregister_post_meta( 'post', '_gk_read_denied' ); unregister_post_meta( 'post', 'gk_read_sanitized' );
}
echo "Completed {$checks} metadata-read checks; temporary content removed.\n";
