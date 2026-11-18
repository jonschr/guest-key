<?php
/** Local access for isolated test users; original site state restored at shutdown. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Local WP-CLI tests only.' ); }
function guest_key_test_enable( $user_id ) {
	static $saved = array();
	$blog = get_current_blog_id(); $name = Guest_Key_Site_Access::OPTION;
	if ( ! isset( $saved[ $blog ] ) ) {
		$saved[ $blog ] = true; $original = get_option( $name, null );
		register_shutdown_function( static function () use ( $blog, $name, $original ) {
			$switch = get_current_blog_id() !== $blog; if ( $switch ) { switch_to_blog( $blog ); }
			if ( null === $original ) { delete_option( $name ); } else { update_option( $name, $original, false ); }
			if ( $switch ) { restore_current_blog(); }
		} );
	}
	Guest_Key_Site_Access::initialize();
	$state = get_option( $name, array() );
	$state['owner'] = $state['owner'] ?? 0; $state['site'] = Guest_Key_Site_Access::site();
	$state['users'] = array_values( array_unique( array_merge( $state['users'] ?? array(), array( (int) $user_id ) ) ) );
	update_option( $name, $state, false );
}
function guest_key_test_enabled_create( $user_id ) {
	guest_key_test_enable( $user_id );
	return Guest_Key_Access::create( $user_id );
}
