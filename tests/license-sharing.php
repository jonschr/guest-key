<?php
/** Local integration regression; provider responses are mocked and temporary accounts are removed. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Local administrator only.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';
$original_user = get_current_user_id();
$legacy = get_option( Guest_Key_License::OPTION, null );
$legacy_pending = get_option( Guest_Key_License::PENDING, null );
$dependency_error = get_option( 'guest_key_dependency_error', null );
$users = array(); $checks = 0; $calls = array(); $migration_email = ''; $purchases = array();
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo "PASS: {$message}\n"; };
$mock = static function ( $pre, $args, $url ) use ( &$calls, &$migration_email, &$purchases ) {
	if ( false === strpos( $url, 'api.lemonsqueezy.com/v1/licenses/' ) && false === strpos( $url, '/v1/trials/' ) && false === strpos( $url, '/v1/licenses/claim' ) ) { return $pre; }
	$calls[] = basename( $url );
	if ( false !== strpos( $url, '/claim' ) ) { $data = array( 'licenses' => $purchases ); }
	elseif ( false !== strpos( $url, '/trials/status' ) ) { $data = array( 'status' => 'expired', 'expires_at' => gmdate( 'c', time() - 1 ) ); }
	else {
		$key = $args['body']['license_key'] ?? 'fixture';
		$data = array( 'valid' => true, 'activated' => true, 'deactivated' => true, 'license_key' => array( 'expires_at' => null, 'activation_limit' => 0 ),
			'instance' => array( 'id' => 'fixture-' . substr( hash( 'sha256', $key ), 0, 8 ) ),
			'meta' => array( 'product_id' => 1457777, 'variant_id' => 2274401, 'user_email' => $migration_email ) );
	}
	return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( $data ) );
};
$mail = '__return_true'; add_filter( 'pre_wp_mail', $mail ); add_filter( 'pre_http_request', $mock, 10, 3 );
try {
	foreach ( array( 'a', 'b', 'c', 'd' ) as $label ) {
		$id = wp_insert_user( array( 'user_login' => 'gk-owner-' . $label . '-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator', 'user_email' => 'gk-' . wp_generate_password( 12, false ) . '@example.com', 'display_name' => 'Owner fixture ' . strtoupper( $label ) ) );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Account setup failed.' ); } $users[ $label ] = $id;
	}
	list( $a, $b, $c, $d ) = array_values( $users );
	wp_set_current_user( $a );
	$assert( true === Guest_Key_License::activate( 'fixture-owner-a' ), 'First administrator activates their own license' );
	$assert( Guest_Key_License::active_until( $a ) && ! Guest_Key_License::active_until( $b ), 'Activation defaults to its owner, not every administrator' );
	$stale = get_option( Guest_Key_License::option_name( $a ) ); $stale['checked'] = time() - 2 * DAY_IN_SECONDS;
	update_option( Guest_Key_License::option_name( $a ), $stale, false ); $n = count( $calls );
	$refreshed = Guest_Key_Access::create( $a );
	$assert( ! is_wp_error( $refreshed ) && count( $calls ) === $n + 1 && Guest_Key_License::active_until( $a ), 'Copying access refreshes a stale license without reacquiring the credential lock' );
	Guest_Key_Access::revoke( $a );
	wp_set_current_user( $b );
	$assert( true === Guest_Key_License::activate( 'fixture-owner-b' ) && Guest_Key_License::active_until( $a ) && Guest_Key_License::active_until( $b ), 'Two separate licenses coexist on one site' );
	$n = count( $calls ); wp_set_current_user( $c );
	$assert( is_wp_error( Guest_Key_License::activate( 'fixture-owner-a' ) ) && count( $calls ) === $n, 'Entering another owner’s key cannot consume a duplicate provider activation' );
	$bar = new WP_Admin_Bar(); Guest_Key_Admin::toolbar( $bar );
	$assert( (bool) $bar->get_node( 'guest-key-access' ) && is_wp_error( Guest_Key_Access::create( $c ) ), 'Unlicensed administrators see Copy Guest Key but cannot issue access' );
	$unlicensed = Guest_Key_Access::create( $c );
	$assert( 'guest_key_license_required' === $unlicensed->get_error_code() && false !== strpos( $unlicensed->get_error_message(), 'free trial' ), 'Unlicensed access returns onboarding guidance instead of a nested-lock busy message' );
	$assert( is_wp_error( Guest_Key_License::share( array( $d ) ) ) && true === Guest_Key_License::remove() && Guest_Key_License::active_until( $a ), 'Unlicensed accounts cannot share or remove another owner’s license' );
	wp_set_current_user( $a );
	$assert( true === Guest_Key_License::share( array( $c ) ) && Guest_Key_License::active_until( $c ) && ! Guest_Key_License::active_until( $d ), 'Owner shares only with the selected administrator' );
	wp_set_current_user( $b ); Guest_Key_License::share( array( $c ) );
	wp_set_current_user( $c ); $view = Guest_Key_License::view();
	$assert( 'shared' === $view['kind'] && 2 === count( $view['sharedBy'] ) && ! isset( $view['key'], $view['token'], $view['instance'] ), 'Overlapping shares appear without exposing license secrets' );
	$plugins = get_option( 'active_plugins', array() );
	$adapter = static function () use ( $plugins ) { return array_unique( array_merge( $plugins, array( Guest_Key_Dependency::PLUGIN ) ) ); };
	add_filter( 'pre_option_active_plugins', $adapter );
	try { $key = Guest_Key_Access::create( $c ); } finally { remove_filter( 'pre_option_active_plugins', $adapter ); }
	$assert( ! is_wp_error( $key ), 'A shared administrator can issue their own temporary credential' );
	$rest = static function () use ( $key ) {
		$response = wp_remote_get( rest_url( 'wp/v2/users/me' ), array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 15, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $key['username'] . ':' . $key['password'] ) ), 'user-agent' => 'GuestKey-License-QA/1.0' ) );
		if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
		return wp_remote_retrieve_response_code( $response );
	};
	$assert( 200 === $rest(), 'Native REST authenticates the credential using the issuing user’s shared entitlement' );

	wp_set_current_user( $a ); Guest_Key_License::share( array() );
	$assert( (bool) Guest_Key_License::active_until( $c ), 'Removing one share preserves access through the other owner' );
	$assert( 200 === $rest(), 'The existing REST credential remains valid while another share covers it' );
	wp_set_current_user( $b ); Guest_Key_License::share( array() );
	$assert( ! Guest_Key_License::active_until( $c ) && Guest_Key_License::active_until( $a ), 'Removing the last share ends only that recipient’s entitlement' );
	$assert( 401 === $rest(), 'Removing the last share immediately blocks an already-issued REST credential' );
	$assert( is_wp_error( Guest_Key_License::share( array( 999999999 ) ) ), 'Sharing rejects nonexistent administrators' );
	get_userdata( $d )->set_role( 'subscriber' );
	$assert( is_wp_error( Guest_Key_License::share( array( $d ) ) ), 'Sharing rejects non-administrators' );
	get_userdata( $d )->set_role( 'administrator' );
	wp_set_current_user( $a ); Guest_Key_License::share( array( $c ) );
	wp_set_current_user( $b ); Guest_Key_License::remove();
	$assert( Guest_Key_License::active_until( $a ) && Guest_Key_License::active_until( $c ) && ! Guest_Key_License::active_until( $b ), 'Removing one license preserves another owner and their recipients' );
	$trial = array( 'kind' => 'trial', 'email' => get_userdata( $b )->user_email, 'token' => str_repeat( 't', 43 ), 'site' => trailingslashit( home_url( '/' ) ), 'status' => 'active', 'checked' => time(), 'expires' => time() + 60 );
	update_option( Guest_Key_License::option_name( $b ), $trial, false );
	$assert( Guest_Key_License::active_until( $b ) && ! Guest_Key_License::active_until( $d ), 'A trial belongs only to its verified administrator' );
	Guest_Key_License::check( true, $b );
	$assert( ! Guest_Key_License::active_until( $b ) && Guest_Key_License::active_until( $a ), 'A different administrator’s trial can expire independently' );
	$purchases = array( 'fixture-owner-b-upgrade' ); wp_set_current_user( 0 );
	$assert( true === Guest_Key_License::check( true, $b ) && 'fixture-owner-b-upgrade' === get_option( Guest_Key_License::option_name( $b ) )['key'], 'A background purchase claim upgrades the correct owner without a signed-in user' );
	$assert( 'fixture-owner-a' === get_option( Guest_Key_License::option_name( $a ) )['key'], 'Another owner’s existing key is not overwritten during automatic purchase matching' );
	$purchases = array(); wp_set_current_user( $b );

	$assert( false === get_option( Guest_Key_License::pending_name( $a ) ) && Guest_Key_License::pending_name( $a ) !== Guest_Key_License::pending_name( $b ), 'Verification requests have separate account-scoped storage' );
	$n = count( $calls ); Guest_Key_License::active_until( $c );
	$assert( count( $calls ) === $n, 'Reading a recipient’s entitlement performs no provider requests' );
	get_userdata( $a )->set_role( 'subscriber' );
	$assert( ! Guest_Key_License::active_until( $c ), 'A demoted owner cannot continue sharing administrator access' );
	get_userdata( $a )->set_role( 'administrator' );
	// Existing site-wide trial matches its verified email; no token or expiry is changed.
	delete_option( Guest_Key_License::option_name( $b ) ); update_option( Guest_Key_License::OPTION, $trial, false );
	wp_set_current_user( $a ); Guest_Key_License::migrate_legacy();
	$migrated = get_option( Guest_Key_License::option_name( $b ) );
	$assert( ! get_option( Guest_Key_License::OPTION ) && $migrated['token'] === $trial['token'] && $migrated['expires'] === $trial['expires'] && empty( $migrated['shared_users'] ), 'Legacy trial migrates to its email-matched administrator without sharing or resetting expiration' );
	delete_option( Guest_Key_License::option_name( $b ) );
	$paid = get_option( Guest_Key_License::option_name( $a ) ); $paid['key'] = 'fixture-legacy-paid'; $migration_email = get_userdata( $b )->user_email;
	unset( $paid['email'] ); update_option( Guest_Key_License::OPTION, $paid, false ); Guest_Key_License::migrate_legacy();
	$assert( (bool) get_option( Guest_Key_License::option_name( $b ) ) && ! get_option( Guest_Key_License::OPTION ), 'Legacy paid license matches its provider purchase email' );
	$trial['email'] = 'unmatched@example.com'; update_option( Guest_Key_License::OPTION, $trial, false );
	wp_set_current_user( $d ); Guest_Key_License::migrate_legacy();
	$assert( Guest_Key_License::view()['legacyClaim'] && ! Guest_Key_License::active_until( $d ), 'Unmatched legacy trial requires explicit claiming and unlocks nobody by default' );
	Guest_Key_License::claim_existing();
	$assert( ! get_option( Guest_Key_License::OPTION ) && get_option( Guest_Key_License::option_name( $d ) )['token'] === $trial['token'], 'Explicit claim keeps the existing verified identity and trial token' );
	wp_set_current_user( $original_user );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $users as $id ) { Guest_Key_Access::revoke( $id ); delete_option( Guest_Key_License::option_name( $id ) ); delete_option( Guest_Key_License::pending_name( $id ) ); wp_delete_user( $id ); }
	foreach ( array( Guest_Key_License::OPTION => $legacy, Guest_Key_License::PENDING => $legacy_pending ) as $name => $value ) { if ( null === $value ) { delete_option( $name ); } else { update_option( $name, $value, false ); } }
	if ( null === $dependency_error ) { delete_option( 'guest_key_dependency_error' ); } else { update_option( 'guest_key_dependency_error', $dependency_error, false ); }
	remove_filter( 'pre_http_request', $mock, 10 ); remove_filter( 'pre_wp_mail', $mail );
}
echo "Completed {$checks} administrator-license checks; temporary accounts removed.\n";
