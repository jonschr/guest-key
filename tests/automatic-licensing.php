<?php
/** Local-only tests: simulate connector/provider responses; preserve the site's real license. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { throw new RuntimeException( 'Use an isolated local single site.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
$original = get_option( Guest_Key_License::option_name(), null ); $pending = get_option( Guest_Key_License::pending_name(), null );
$checks = 0; $calls = array(); $keys = array(); $offline = false; $wrong = false; $mode = 'valid'; $trial_days = 3;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo "PASS: {$message}\n"; };
$mock = static function ( $pre, $args, $url ) use ( &$calls, &$keys, &$offline, &$wrong, &$mode, &$trial_days ) {
	if ( false === strpos( $url, '/v1/licenses/' ) && false === strpos( $url, '/v1/trials/' ) ) { return $pre; }
	$calls[] = $url;
	if ( false !== strpos( $url, '/v1/licenses/claim' ) ) { $data = array( 'licenses' => $keys ); if ( $offline ) { return new WP_Error( 'fixture_offline', 'Connector offline.' ); } }
	elseif ( false !== strpos( $url, '/v1/trials/start' ) ) { $data = array( 'trial_token' => str_repeat( 't', 43 ), 'status' => 'active', 'expires_at' => gmdate( 'c', time() + $trial_days * DAY_IN_SECONDS ) ); }
	elseif ( false !== strpos( $url, '/v1/trials/status' ) ) { $data = array( 'status' => 'active', 'expires_at' => gmdate( 'c', time() + HOUR_IN_SECONDS ) ); }
	else { $data = array( 'valid' => 'invalid' !== $mode, 'activated' => 'limit' !== $mode, 'deactivated' => true, 'license_key' => array( 'expires_at' => null ), 'instance' => array( 'id' => 'fixture-auto-instance' ), 'meta' => array( 'product_id' => $wrong ? 999 : 1457777, 'variant_id' => 2274395 ) ); }
	return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( $data ) );
};
$trial = array( 'kind' => 'trial', 'token' => str_repeat( 't', 43 ), 'site' => trailingslashit( home_url( '/' ) ), 'email' => 'verified@example.com', 'status' => 'active', 'expires' => time() + HOUR_IN_SECONDS, 'checked' => time() );
add_filter( 'pre_http_request', $mock, 10, 3 );
try {
	update_option( Guest_Key_License::option_name(), $trial, false );
	$assert( true === Guest_Key_License::check(), 'No matching purchase preserves the current trial' );
	$n = count( $calls ); Guest_Key_License::check(); $assert( $n === count( $calls ), 'Repeated checks use the short purchase cache' );
	$keys = array( 'fixture-purchase' );
	$assert( true === Guest_Key_License::check( true ) && 'paid' === get_option( Guest_Key_License::option_name() )['kind'], 'A matching purchase upgrades an active trial automatically' );
	$assert( 'fixture-purchase' === get_option( Guest_Key_License::option_name() )['key'], 'The activated key is saved in the WordPress database' );
	$assert( ! isset( Guest_Key_License::view()['key'], Guest_Key_License::view()['token'], Guest_Key_License::view()['instance'] ), 'Browser state never contains keys, trial tokens or provider instance IDs' );
	$n = count( array_filter( $calls, static function ( $url ) { return false !== strpos( $url, '/claim' ); } ) ); Guest_Key_License::check( true );
	$assert( $n === count( array_filter( $calls, static function ( $url ) { return false !== strpos( $url, '/claim' ); } ) ), 'A saved paid or manually entered license is never replaced by automatic matching' );
	$assert( is_wp_error( Guest_Key_License::email( 'new@example.com' ) ), 'A stale signup tab cannot request a trial over a saved paid license' );
	$assert( is_wp_error( Guest_Key_License::verify( '123456' ) ) && 'paid' === get_option( Guest_Key_License::option_name() )['kind'], 'A stale verification tab cannot overwrite a paid key or orphan its provider activation' );
	$assert( true === Guest_Key_License::remove(), 'An automatically applied key can be removed normally' );
	$n = count( $calls ); Guest_Key_License::check( true ); $assert( $n === count( $calls ) && ! get_option( Guest_Key_License::option_name() ), 'Explicit removal does not silently reactivate the removed purchase' );
	$assert( true === Guest_Key_License::activate( 'fixture-replacement' ), 'A replacement key can be entered after removing the previous key' );
	$expired = $trial; $expired['status'] = 'expired'; $expired['expires'] = time() - 1;
	update_option( Guest_Key_License::option_name(), $expired, false );
	$assert( true === Guest_Key_License::check() && 'paid' === get_option( Guest_Key_License::option_name() )['kind'], 'The same verified identity can upgrade after its trial expires' );
	update_option( Guest_Key_License::option_name(), $trial, false ); $offline = true;
	$assert( true === Guest_Key_License::check( true ) && 'trial' === get_option( Guest_Key_License::option_name() )['kind'], 'A connector outage preserves valid trial access' );
	$offline = false; $wrong = true;
	$assert( true === Guest_Key_License::check( true ) && 'trial' === get_option( Guest_Key_License::option_name() )['kind'], 'An unrelated product returned by the connector is rejected without replacing the trial' );
	$wrong = false; $mode = 'limit';
	$assert( true === Guest_Key_License::check( true ) && 'trial' === get_option( Guest_Key_License::option_name() )['kind'], 'An exhausted single-site activation grants no paid access and preserves the trial' );
	$mode = 'valid'; $calls = array();
	Guest_Key_License::active_until(); $assert( ! $calls, 'Native authentication still performs no network requests' );
	update_option( Guest_Key_License::pending_name(), array( 'email' => 'verified@example.com', 'site' => $trial['site'], 'until' => time() + 60, 'challenge' => 'fixture' ), false );
	$assert( true === Guest_Key_License::verify( '123456' ) && 'verified@example.com' === Guest_Key_License::view()['email'], 'Completed email verification retains the address for purchase guidance' );
	$assert( get_option( Guest_Key_License::option_name() )['expires'] >= time() + 3 * DAY_IN_SECONDS - 5, 'Verified trials retain the full three-day expiration' );
	$trial_days = 4;
	update_option( Guest_Key_License::pending_name(), array( 'email' => 'verified@example.com', 'site' => $trial['site'], 'until' => time() + 60, 'challenge' => 'fixture' ), false );
	$assert( is_wp_error( Guest_Key_License::verify( '123456' ) ), 'Trials exceeding three days are rejected' );
	$assert( ! array_key_exists( Guest_Key_License::option_name(), wp_load_alloptions() ), 'Saved licensing credentials are not autoloaded' );
} finally {
	remove_filter( 'pre_http_request', $mock, 10 );
	foreach ( array( Guest_Key_License::option_name() => $original, Guest_Key_License::pending_name() => $pending ) as $name => $value ) { if ( null === $value ) { delete_option( $name ); } else { update_option( $name, $value, false ); } }
}
echo "Completed {$checks} automatic licensing checks; original local licensing restored.\n";
