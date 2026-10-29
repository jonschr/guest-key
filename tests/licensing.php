<?php
/** Run using wp --user=1 eval-file tests/licensing.php on a local single site. HTTP provider calls are simulated. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Use an isolated local single site.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
$saved = get_option( Guest_Key_License::option_name(), null ); $pending = get_option( Guest_Key_License::pending_name(), null );
$schedule = wp_get_scheduled_event( 'guest_key_license_refresh' );
$checks = 0; $calls = array(); $mode = 'valid'; $variant = 2274395; $product = 1457777; $expiry = gmdate( 'c', time() + YEAR_IN_SECONDS );
$assert = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } echo 'PASS: ' . $message . "\n"; $checks++; };
$mock = static function ( $pre, $args, $url ) use ( &$calls, &$mode, &$variant, &$product, &$expiry ) {
	if ( 0 !== strpos( $url, 'https://api.lemonsqueezy.com/v1/licenses/' ) ) { return $pre; }
	$action = basename( $url ); $calls[] = $action;
	if ( 'offline' === $mode ) { return new WP_Error( 'fixture_offline', 'Simulated provider outage.' ); }
	$data = array( 'valid' => ! in_array( $mode, array( 'invalid', 'remove-expired' ), true ), 'activated' => 'limit' !== $mode, 'deactivated' => ! in_array( $mode, array( 'remove-failed', 'remove-expired' ), true ),
		'license_key' => array( 'expires_at' => $expiry, 'activation_limit' => 2274403 === $variant ? 0 : 1, 'status' => 'invalid' === $mode ? 'expired' : 'active' ),
		'instance' => array( 'id' => 'fixture-instance' ), 'meta' => array( 'product_id' => $product, 'variant_id' => $variant ) );
	if ( 'truthy-invalid' === $mode ) { $data['valid'] = 'false'; }
	return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( $data ) );
};
add_filter( 'pre_http_request', $mock, 10, 3 );
try {
	delete_option( Guest_Key_License::option_name() ); delete_option( Guest_Key_License::pending_name() );
	$assert( is_wp_error( Guest_Key_License::require_access() ), 'Unlicensed sites cannot issue access' );
	$before = Guest_Key_Access::grants(); $created = Guest_Key_Access::create( get_current_user_id() );
	$assert( is_wp_error( $created ) && 'guest_key_license_required' === $created->get_error_code() && $before === Guest_Key_Access::grants(), 'Unlicensed credential creation preserves existing grants' );
	$assert( true === Guest_Key_License::activate( 'fixture-paid-license' ), 'Single-site paid key activates' );
	$assert( array( 'validate', 'activate' ) === $calls, 'Product validation precedes consuming an activation' );
	$assert( ! empty( Guest_Key_License::view()['active'] ) && ! isset( Guest_Key_License::view()['key'], Guest_Key_License::view()['instance'] ), 'Public UI state excludes the license and instance secrets' );
	$assert( true === Guest_Key_License::activate( 'fixture-paid-license' ) && 1 === count( array_filter( $calls, static function ( $a ) { return 'activate' === $a; } ) ), 'Re-entering a saved key validates its existing activation without using another slot' );
	$assert( is_wp_error( Guest_Key_License::activate( 'different-key' ) ), 'Replacing a key requires releasing the existing activation' );
	$record = get_option( Guest_Key_License::option_name() ); $record['checked'] = time() - 5 * HOUR_IN_SECONDS; update_option( Guest_Key_License::option_name(), $record, false ); $mode = 'offline';
	$assert( true === Guest_Key_License::require_access(), 'A brief outage retains recently verified paid access' );
	$record['checked'] = time() - DAY_IN_SECONDS; update_option( Guest_Key_License::option_name(), $record, false );
	$assert( is_wp_error( Guest_Key_License::require_access() ) && ! Guest_Key_License::active_until(), 'Offline cache cannot extend paid access beyond one day' );
	$record['checked'] = time(); $record['expires'] = time(); update_option( Guest_Key_License::option_name(), $record, false );
	$assert( ! Guest_Key_License::active_until(), 'Known expiration is enforced exactly, even during an outage' );
	$mode = 'valid'; $assert( true === Guest_Key_License::require_access() && Guest_Key_License::active_until() > time(), 'Known expiry triggers a renewal check without waiting for the cache interval' );
	$mode = 'invalid'; $assert( false === Guest_Key_License::check( true ) && ! Guest_Key_License::active_until(), 'A provider-revoked key stops access' );
	$mode = 'remove-failed'; $assert( is_wp_error( Guest_Key_License::remove() ) && get_option( Guest_Key_License::option_name() ), 'Failed deactivation preserves the key so its slot can be released on retry' );
	$mode = 'valid'; $assert( true === Guest_Key_License::remove() && ! get_option( Guest_Key_License::option_name() ), 'Successful deactivation releases the slot and removes local access' );
	Guest_Key_License::activate( 'expired-instance' ); $mode = 'remove-expired';
	$assert( true === Guest_Key_License::remove() && ! get_option( Guest_Key_License::option_name() ), 'An already-invalid provider instance can be removed so a replacement key is not blocked' );
	$mode = 'valid';
	$product = 999; $n = count( $calls );
	$assert( is_wp_error( Guest_Key_License::activate( 'wrong-product' ) ) && count( $calls ) === $n + 1, 'An unrelated product is rejected before activation' );
	$product = 1457777; $variant = 2274403;
	$assert( is_wp_error( Guest_Key_License::activate( 'old-free-key' ) ), 'The old unverified one-day key cannot bypass email verification' );
	$expiry = null;
	$assert( true === Guest_Key_License::activate( 'owner-perpetual-key' ) && Guest_Key_License::active_until() > time(), 'Perpetual unlimited owner override is recognized' );
	Guest_Key_License::remove(); $variant = 2274401; $expiry = 'malformed';
	$assert( is_wp_error( Guest_Key_License::activate( 'bad-expiry' ) ) && ! Guest_Key_License::active_until(), 'Malformed provider expiry fails closed' );
	$expiry = gmdate( 'c', time() + YEAR_IN_SECONDS ); $mode = 'limit';
	$assert( is_wp_error( Guest_Key_License::activate( 'limit-key' ) ) && ! get_option( Guest_Key_License::option_name() ), 'Activation-limit errors grant no local access' );
	$mode = 'truthy-invalid';
	$assert( is_wp_error( Guest_Key_License::activate( 'malformed-boolean' ) ) && ! get_option( Guest_Key_License::option_name() ), 'Truthy strings cannot impersonate a successful provider validation' );
	foreach ( array( array(), null, str_repeat( 'x', 201 ) ) as $bad ) { $assert( is_wp_error( Guest_Key_License::activate( $bad ) ), 'Invalid license input is rejected safely' ); }
	$assert( is_wp_error( Guest_Key_License::email( array() ) ) && is_wp_error( Guest_Key_License::verify( array() ) ), 'Invalid email/code shapes are rejected safely' );
	$record = array( 'kind' => 'trial', 'site' => trailingslashit( home_url( '/' ) ), 'status' => 'active', 'checked' => time(), 'expires' => time() + 60, 'token' => str_repeat( 't', 43 ) );
	update_option( Guest_Key_License::option_name(), $record, false );
	$assert( Guest_Key_License::active_until() === $record['expires'], 'A trial never extends beyond its confirmed expiration' );
	$record['site'] = 'https://different-site.com/'; update_option( Guest_Key_License::option_name(), $record, false );
	$assert( ! Guest_Key_License::active_until(), 'Copying the WordPress database onto another site does not carry access' );
	$unknown = new WP_REST_Request( 'GET', '/guest-key/v1/trial-proof' ); $unknown->set_param( 'challenge', str_repeat( 'a', 64 ) );
	$assert( 404 === rest_do_request( $unknown )->get_status(), 'Unknown site-proof IDs are not echoed back' );
	$assert( is_wp_error( Guest_Key_Data_Abilities::options( array( 'action' => 'get', 'key' => 'guest_key_license' ) ) ), 'Agent option commands cannot read license credentials' );
} finally {
	remove_filter( 'pre_http_request', $mock, 10 );
	if ( null === $saved ) { delete_option( Guest_Key_License::option_name() ); } else { update_option( Guest_Key_License::option_name(), $saved, false ); }
	if ( null === $pending ) { delete_option( Guest_Key_License::pending_name() ); } else { update_option( Guest_Key_License::pending_name(), $pending, false ); }
	wp_clear_scheduled_hook( 'guest_key_license_refresh' );
	if ( $schedule ) { wp_schedule_event( $schedule->timestamp, $schedule->schedule, 'guest_key_license_refresh', $schedule->args ); }
}
echo "Completed {$checks} licensing checks; original local licensing restored.\n";
