<?php
/** Local test entitlement for temporary users; never activates a provider license. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Local WP-CLI tests only.' ); }
function guest_key_test_entitle( $user_id ) {
	static $saved = array();
	$blog = get_current_blog_id(); $name = Guest_Key_License::option_name( $user_id ); $id = $blog . ':' . $name;
	if ( isset( $saved[ $id ] ) ) { return; }
	$saved[ $id ] = true;
	$original = get_option( $name, null );
	register_shutdown_function( static function () use ( $blog, $name, $original ) {
		$switch = get_current_blog_id() !== $blog; if ( $switch ) { switch_to_blog( $blog ); }
		if ( null === $original ) { delete_option( $name ); } else { update_option( $name, $original, false ); }
		if ( $switch ) { restore_current_blog(); }
	} );
	update_option( $name, array( 'kind' => 'paid', 'status' => 'active', 'site' => trailingslashit( home_url( '/' ) ), 'checked' => time(), 'expires' => time() + 2 * DAY_IN_SECONDS, 'key' => 'fixture-local-user', 'instance' => 'fixture-local-instance', 'shared_users' => array() ), false );
}
function guest_key_test_entitled_create( $user_id ) {
	guest_key_test_entitle( $user_id );
	return Guest_Key_Access::create( $user_id );
}
