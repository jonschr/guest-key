<?php
/** wp --user=admin eval-file tests/acf-fields.php; optionally set GUEST_KEY_ACF_TEST_PLUGIN to a local ACF entrypoint. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Use a local administrator.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
if ( ! function_exists( 'acf_filter_save_values' ) && getenv( 'GUEST_KEY_ACF_TEST_PLUGIN' ) ) { require getenv( 'GUEST_KEY_ACF_TEST_PLUGIN' ); acf()->init(); }
if ( ! function_exists( 'acf_filter_save_values' ) ) { throw new RuntimeException( 'ACF 6.8.11+ is required.' ); }
$checks = 0; $id = null;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; echo "PASS: $message\n"; };
$call = static function ( $action, $key = null, $value = null ) use ( &$id ) {
	$args = array( 'action' => $action, 'post_id' => $id );
	if ( null !== $key ) { $args['field_key'] = $key; }
	if ( null !== $value ) { $args['value'] = $value; }
	return wp_get_ability( 'guest-key/manage-acf-fields' )->execute( $args );
};
$deny = static function () { return false; };
$validation = static function ( $valid, $value ) { return 'bad' === $value ? 'Choose a different statistic.' : $valid; };
$deny_post = static function ( $caps, $cap, $user, $args ) use ( &$id ) { return 'edit_post' === $cap && (int) ( $args[0] ?? 0 ) === $id ? array( 'do_not_allow' ) : $caps; };
$allowed = static function () { return array(); };
$block_write = static function ( $check, $object_id, $name ) use ( &$id ) { return (int) $object_id === $id && 'gk_acf_stat' === $name ? false : $check; };
$transform = static function ( $value ) { return strtoupper( $value ); };
$sanitize = static function ( $value ) { return sanitize_text_field( $value ); };
$global_validation = static function () { acf_add_validation_error( '', 'Form-level rejection.' ); };
add_filter( 'acf/is_trusted_save_context', '__return_false' );
acf_add_local_field_group( array( 'key' => 'group_gk_acf_test', 'title' => 'Guest Key ACF fixture', 'show_in_rest' => false,
	'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
	'fields' => array(
		array( 'key' => 'field_gk_stat', 'name' => 'gk_acf_stat', 'label' => 'Statistic', 'type' => 'text', 'required' => 1, 'maxlength' => 100 ),
		array( 'key' => 'field_gk_hidden', 'name' => 'gk_acf_hidden', 'label' => 'Hidden', 'type' => 'text' ),
		array( 'key' => 'field_gk_readonly', 'name' => 'gk_acf_readonly', 'label' => 'Readonly', 'type' => 'text', 'readonly' => 1 ),
		array( 'key' => 'field_gk_complex', 'name' => 'gk_acf_complex', 'label' => 'Complex', 'type' => 'group', 'sub_fields' => array() ),
		array( 'key' => 'field_gk_conditional', 'name' => 'gk_acf_conditional', 'label' => 'Conditional', 'type' => 'text', 'conditional_logic' => array( array( array( 'field' => 'field_gk_stat', 'operator' => '==', 'value' => 'yes' ) ) ) ),
	) ) );
acf_add_local_field_group( array( 'key' => 'group_gk_acf_other', 'title' => 'Other', 'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ) ),
	'fields' => array( array( 'key' => 'field_gk_other', 'name' => 'gk_acf_other', 'label' => 'Other', 'type' => 'text' ) ) ) );
add_filter( 'acf/prepare_field/key=field_gk_hidden', $deny );
add_filter( 'acf/validate_value/key=field_gk_stat', $validation, 10, 2 );
try {
	$id = wp_insert_post( array( 'post_title' => 'Temporary Guest Key ACF fixture', 'post_status' => 'draft' ) );
	$list = $call( 'list' );
	$assert( ! is_wp_error( $list ) && in_array( 'field_gk_stat', array_column( $list, 'field_key' ), true ) && ! in_array( 'field_gk_hidden', array_column( $list, 'field_key' ), true ), 'Discovery includes applicable fields and honors prepare_field hiding' );
	$r = $call( 'update', 'field_gk_stat', '98%' );
	$assert( ! is_wp_error( $r ) && '98%' === $r['value'] && 'field_gk_stat' === get_post_meta( $id, '_gk_acf_stat', true ), 'ACF saves the text value and stable field reference' );
	$read = $call( 'get', 'field_gk_stat' );
	$assert( '98%' === $read['value'] && '98%' === get_field( 'field_gk_stat', $id ), 'Readback agrees with the value loaded by the editor' );
	$native = wp_get_ability( 'guest-key/manage-post-meta' )->execute( array( 'action' => 'update', 'post_id' => $id, 'key' => 'gk_acf_stat', 'value' => 'bypass' ) );
	$assert( is_wp_error( $native ) && 'post acf update' === $native->get_error_data()['required_pathway'] && '98%' === get_field( 'field_gk_stat', $id ), 'Generic metadata still obeys ACF guard and explains required pathway' );
	foreach ( array( '', 'bad', str_repeat( 'x', 101 ) ) as $value ) {
		$r = $call( 'update', 'field_gk_stat', $value );
		$assert( is_wp_error( $r ) && 'guest_key_acf_validation' === $r->get_error_code() && ! empty( $r->get_error_data()['errors'] ) && '98%' === get_field( 'field_gk_stat', $id ), 'Required, custom and type validation reject without saving' );
	}
	$assert( ! is_wp_error( $call( 'validate', 'field_gk_stat', '99%' ) ) && '98%' === get_field( 'field_gk_stat', $id ), 'Validate-only runs without writing' );
	foreach ( array( 'field_gk_unknown', 'field_gk_other', 'field_gk_hidden', 'field_gk_readonly', 'field_gk_complex', 'field_gk_conditional' ) as $key ) { $assert( is_wp_error( $call( 'update', $key, 'denied' ) ), 'Rejects unknown, mismatched, hidden or unsupported field: ' . $key ); }
	add_filter( 'acf/form/post/allowed_field_keys', $allowed );
	$assert( is_wp_error( $call( 'update', 'field_gk_stat', 'denied' ) ), 'Native ACF allowed-field filter can deny a save' );
	remove_filter( 'acf/form/post/allowed_field_keys', $allowed );
	add_filter( 'map_meta_cap', $deny_post, 99, 4 );
	$assert( is_wp_error( $call( 'update', 'field_gk_stat', 'denied' ) ), 'Object authorization remains enforced' );
	remove_filter( 'map_meta_cap', $deny_post, 99 );
	$owner = get_current_user_id();
	wp_set_current_user( 0 );
	$assert( is_wp_error( $call( 'update', 'field_gk_stat', 'denied' ) ), 'Unauthenticated access is rejected by the ability' );
	wp_set_current_user( $owner );
	add_action( 'acf/validate_save_post', $global_validation, 20 );
	$assert( is_wp_error( $call( 'update', 'field_gk_stat', 'denied' ) ), 'Form-level custom validation also rejects saves' );
	remove_action( 'acf/validate_save_post', $global_validation, 20 );
	add_filter( 'acf/update_value/key=field_gk_stat', $transform );
	$r = $call( 'update', 'field_gk_stat', 'transformed' );
	$assert( ! is_wp_error( $r ) && 'TRANSFORMED' === $r['value'], 'ACF field-specific update hooks transform saved values' );
	remove_filter( 'acf/update_value/key=field_gk_stat', $transform );
	$assert( ! is_wp_error( $call( 'update', 'field_gk_stat', 'TRANSFORMED' ) ), 'Saving an unchanged value succeeds' );
	add_filter( 'update_post_metadata', $block_write, 10, 3 );
	$r = $call( 'update', 'field_gk_stat', 'blocked' );
	$assert( is_wp_error( $r ) && 'guest_key_acf_save' === $r->get_error_code() && 'TRANSFORMED' === get_post_meta( $id, 'gk_acf_stat', true ), 'Blocked storage is reported as a failed save' );
	remove_filter( 'update_post_metadata', $block_write );
	$quoted = 'A "quote" and C:\\data';
	$assert( ! is_wp_error( $call( 'update', 'field_gk_stat', $quoted ) ) && $quoted === get_field( 'field_gk_stat', $id ), 'Text values preserve quotes and backslashes' );
	add_filter( 'sanitize_post_meta_gk_acf_stat_for_post', $sanitize );
	$r = $call( 'update', 'field_gk_stat', '<b>Sanitized</b>' );
	$assert( ! is_wp_error( $r ) && 'Sanitized' === $r['value'], 'Native metadata sanitizers remain effective without false save failures' );
	remove_filter( 'sanitize_post_meta_gk_acf_stat_for_post', $sanitize );
	$before = $_POST; $request = $_REQUEST; $form = acf_get_store( 'form' )->get();
	$run = Guest_Key_Commands::run( array( 'commands' => array(
		array( 'command' => 'post acf update', 'input' => array( 'post_id' => $id, 'field_key' => 'field_gk_stat', 'value' => '97%' ) ),
		array( 'command' => 'post acf update', 'input' => array( 'post_id' => $id, 'field_key' => 'field_gk_stat', 'value' => 'bad' ) ),
	) ) );
	$assert( false === $run['ok'] && 1 === $run['failed_index'] && 1 === count( $run['results'] ) && '97%' === get_field( 'field_gk_stat', $id ), 'Batch failure reports completed write and failing operation' );
	$assert( $before === $_POST && $request === $_REQUEST && $form === acf_get_store( 'form' )->get(), 'Commands restore request and form state' );
	$assert( false === (bool) acf_get_field_group( 'group_gk_acf_test' )['show_in_rest'], 'Public REST exposure stays disabled' );
} finally {
	if ( isset( $owner ) ) { wp_set_current_user( $owner ); }
	remove_filter( 'update_post_metadata', $block_write );
	remove_filter( 'sanitize_post_meta_gk_acf_stat_for_post', $sanitize );
	remove_filter( 'acf/update_value/key=field_gk_stat', $transform );
	remove_action( 'acf/validate_save_post', $global_validation, 20 );
	remove_filter( 'acf/prepare_field/key=field_gk_hidden', $deny );
	remove_filter( 'acf/validate_value/key=field_gk_stat', $validation );
	remove_filter( 'map_meta_cap', $deny_post, 99 );
	remove_filter( 'acf/form/post/allowed_field_keys', $allowed );
	remove_filter( 'acf/is_trusted_save_context', '__return_false' );
	if ( $id ) { wp_delete_post( $id, true ); }
	acf_remove_local_field_group( 'group_gk_acf_test' ); acf_remove_local_field_group( 'group_gk_acf_other' );
}
echo "Completed $checks ACF checks; temporary content removed.\n";
