<?php
/** Run with WP-CLI on an isolated multisite clone whose database name starts guest_key_integration_. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! is_multisite() || 0 !== strpos( DB_NAME, 'guest_key_integration_' ) ) {
	throw new RuntimeException( 'These tests require an isolated multisite integration-test database.' );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$GLOBALS['guest_key_multisite_checks'] = 0;
function guest_key_multisite_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$GLOBALS['guest_key_multisite_checks']++;
	echo 'PASS: ' . $message . "\n";
}

$root = get_main_site_id();
$uid = get_current_user_id();
grant_super_admin( $uid );
foreach ( Guest_Key_Access::grants() as $owner => $grant ) {
	Guest_Key_Access::revoke( $owner );
}
deactivate_plugins( plugin_basename( GUEST_KEY_FILE ), false, false );
deactivate_plugins( Guest_Key_Dependency::PLUGIN, false, false );
$activated = activate_plugin( plugin_basename( GUEST_KEY_FILE ), '', true );
guest_key_multisite_assert( ! is_wp_error( $activated ) && Guest_Key_Dependency::network(), 'Activate Guest Key network-wide' );
guest_key_multisite_assert( is_plugin_active_for_network( Guest_Key_Dependency::PLUGIN ), 'Activate the dependency network-wide' );
$network = get_network();
$blog = wpmu_create_blog( $network->domain, trailingslashit( $network->path ) . 'guest-key-subsite/', 'Guest Key subsite', $uid, array(), $network->id );
guest_key_multisite_assert( ! is_wp_error( $blog ), 'Create an isolated subsite' );
$first = Guest_Key_Access::create( $uid );
guest_key_multisite_assert( ! is_wp_error( $first ), 'Super administrator can issue access on the main site' );
$original = Guest_Key_Access::grant( $uid );
$first_item = WP_Application_Passwords::get_user_application_password( $uid, $original['uuid'] );
switch_to_blog( $blog );
$_GET['rest_route'] = Guest_Key_Access::ROUTE;
$error = new WP_Error();
Guest_Key_Access::authenticate( $error, get_userdata( $uid ), $first_item );
guest_key_multisite_assert( $error->has_errors(), 'A main-site credential cannot authenticate on a subsite' );
$second = Guest_Key_Access::create( $uid );
guest_key_multisite_assert( ! is_wp_error( $second ), 'Super administrator can issue subsite access' );
$replacement = Guest_Key_Access::grant( $uid );
guest_key_multisite_assert( (int) $replacement['blog_id'] === (int) $blog && $original['uuid'] !== $replacement['uuid'], 'New access is bound to the issuing subsite' );
guest_key_multisite_assert( null === WP_Application_Passwords::get_user_application_password( $uid, $original['uuid'] ), 'Issuing on another subsite removes the old password' );
$item = WP_Application_Passwords::get_user_application_password( $uid, $replacement['uuid'] );
$error = new WP_Error();
Guest_Key_Access::authenticate( $error, get_userdata( $uid ), $item );
guest_key_multisite_assert( ! $error->has_errors(), 'Subsite credential authenticates on its issuing site' );
$site_admin = wp_insert_user( array( 'user_login' => 'guest-key-site-admin', 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
guest_key_multisite_assert( ! is_wp_error( $site_admin ) && user_can( $site_admin, 'manage_options' ) && ! Guest_Key_Access::administrator( $site_admin ), 'A multisite site administrator is not eligible for Guest Key' );
guest_key_multisite_assert( is_wp_error( Guest_Key_Access::create( $site_admin ) ), 'A site administrator cannot create Guest Key access' );
restore_current_blog();
$other = wp_insert_user( array( 'user_login' => 'guest-key-other-super-admin', 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
grant_super_admin( $other );
wp_set_current_user( $other );
$other_key = Guest_Key_Access::create( $other );
guest_key_multisite_assert( ! is_wp_error( $other_key ), 'Another super administrator can have independent access' );
Guest_Key_Access::revoke( $other );
guest_key_multisite_assert( is_plugin_active_for_network( Guest_Key_Dependency::PLUGIN ), 'Revoking one user preserves the other user’s subsite access' );
wp_set_current_user( $uid );
switch_to_blog( $blog );
$expired = $replacement;
$expired['expires'] = time() - 1;
update_user_meta( $uid, Guest_Key_Access::META, $expired );
$error = new WP_Error();
Guest_Key_Access::authenticate( $error, get_userdata( $uid ), $item );
guest_key_multisite_assert( $error->has_errors(), 'Multisite expiration is enforced before scheduled cleanup' );
Guest_Key_Access::expire( $uid, $replacement['uuid'] );
guest_key_multisite_assert( ! is_plugin_active_for_network( Guest_Key_Dependency::PLUGIN ), 'The final expiry disables the owned network adapter' );
restore_current_blog();
$last = Guest_Key_Access::create( $uid );
guest_key_multisite_assert( ! is_wp_error( $last ) && is_plugin_active_for_network( Guest_Key_Dependency::PLUGIN ), 'The next click re-enables the network adapter' );
$nested = Guest_Key_Access::locked( $uid, static function () use ( $uid ) { return Guest_Key_Access::create( $uid ); } );
guest_key_multisite_assert( is_wp_error( $nested ) && 'guest_key_busy' === $nested->get_error_code(), 'Overlapping access changes are serialized' );
add_option( 'guest_key_access_lock', 'crashed-holder|' . ( time() - 1 ), '', false );
$recovered = Guest_Key_Access::locked( $uid, '__return_true' );
guest_key_multisite_assert( true === $recovered && ! get_option( 'guest_key_access_lock' ), 'Expired locks recover after interrupted requests' );
$last_grant = Guest_Key_Access::grant( $uid );
deactivate_plugins( plugin_basename( GUEST_KEY_FILE ), false, true );
guest_key_multisite_assert( ! Guest_Key_Access::grant( $uid ) && null === WP_Application_Passwords::get_user_application_password( $uid, $last_grant['uuid'] ), 'Network deactivation deletes the actual temporary password' );
echo 'Completed ' . $GLOBALS['guest_key_multisite_checks'] . " multisite checks.\n";
