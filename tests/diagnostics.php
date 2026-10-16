<?php
/** wp --user=admin eval-file tests/diagnostics.php on a local/development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Use WP-CLI as an administrator on a local/development site.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
$owner = get_current_user_id(); $checks = 0; $requests = 0;
$options = array( 'guest_key_dependency_error', 'guest_key_dependency_last_error' );
$saved = array_map( static function ( $key ) { return get_option( $key, null ); }, $options );
$grants = Guest_Key_Access::grants();
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo 'PASS: ' . $message . "\n"; };
$block_http = static function () use ( &$requests ) { $requests++; return new WP_Error( 'diagnostic_no_network', 'Reports must not make HTTP requests.' ); };
$active = static function ( $plugins ) { $plugins[] = Guest_Key_Dependency::PLUGIN; return array_unique( $plugins ); };
add_filter( 'pre_http_request', $block_http );
try {
	$error = new WP_Error( 'download_failed', 'Download failed: https://user:private@example.test/package?token=secret /var/www/private/site.php Basic abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWX abcd efgh ijkl mnop qrst uvwx' );
	Guest_Key_Dependency::record_error( $error );
	$report = Guest_Key_Admin::diagnostics();
	$assert( GUEST_KEY_VERSION === $report['guest_key'] && ! empty( $report['wordpress'] ) && ! empty( $report['php'] ), 'Report identifies installed runtime versions' );
	$assert( isset( $report['adapter']['installed'], $report['file_mods_allowed'], $report['filesystem_method'] ), 'Report includes adapter state and filesystem policy' );
	$assert( 'download_failed' === $report['last_mcp_failure']['code'] && false !== strtotime( $report['last_mcp_failure']['time_utc'] ), 'Last failure retains its code and UTC time' );
	$json = wp_json_encode( $report );
	foreach ( array( 'private', 'token=secret', 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWX', 'abcd efgh ijkl mnop qrst uvwx' ) as $secret ) {
		$assert( false === strpos( $json, $secret ), 'Reports redact the fixture secret: ' . substr( $secret, 0, 5 ) );
	}
	$assert( ! isset( $report['password'], $report['username'], $report['nonce'], $report['grants'] ), 'Report excludes connection credentials and access records' );
	$last = $report['last_mcp_failure'];
	add_filter( 'option_active_plugins', $active );
	$assert( true === Guest_Key_Dependency::ensure( false ), 'An installed compatible adapter recovers successfully' );
	remove_filter( 'option_active_plugins', $active );
	$report = Guest_Key_Admin::diagnostics();
	$assert( '' === $report['current_mcp_error'] && $last === $report['last_mcp_failure'], 'Recovery clears the active warning and retains the last failure' );
	$assert( 0 === $requests && $grants === Guest_Key_Access::grants(), 'Reports make no HTTP requests and preserve credentials' );
	wp_set_current_user( 0 );
	$assert( is_wp_error( Guest_Key_Admin::diagnostics() ), 'Unauthenticated users cannot obtain diagnostic reports' );
} finally {
	wp_set_current_user( $owner );
	remove_filter( 'option_active_plugins', $active );
	remove_filter( 'pre_http_request', $block_http );
	foreach ( $options as $i => $key ) {
		if ( null === $saved[ $i ] ) { delete_option( $key ); } else { update_option( $key, $saved[ $i ], false ); }
	}
}
echo "Completed {$checks} diagnostic checks; existing state restored.\n";
