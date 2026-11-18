<?php
/**
 * Run with wp --skip-plugins --skip-themes --require=<bootstrap.php> eval-file <this-file>.
 * The bootstrap must define WP_PLUGIN_DIR as a new empty temporary directory and
 * GUEST_KEY_INSTALL_TEST as that same path before WordPress loads. Use a local test site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! defined( 'GUEST_KEY_INSTALL_TEST' ) || GUEST_KEY_INSTALL_TEST !== WP_PLUGIN_DIR || file_exists( WP_PLUGIN_DIR . '/mcp-adapter' ) ) { throw new RuntimeException( 'Define an isolated temporary WP_PLUGIN_DIR and matching GUEST_KEY_INSTALL_TEST before WordPress loads.' ); }
require dirname( __DIR__ ) . '/guest-key.php';
error_reporting( E_ALL & ~E_DEPRECATED );
$checks = 0; $requests = 0;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo 'PASS: ' . $message . "\n"; };
$api = static function ( $result, $action, $args ) {
	if ( 'plugin_information' === $action && 'mcp-adapter' === $args->slug ) {
		throw new RuntimeException( 'Adapter installation must not depend on plugin-info metadata.' );
	}
	return $result;
};
$block_http = static function () use ( &$requests ) { $requests++; return new WP_Error( 'unexpected_http', 'No network during the installer failure test.' ); };
$filesystem = static function () { return 'ftpext'; };
// The real site's active list can name an adapter outside the isolated plugin directory.
add_filter( 'option_active_plugins', '__return_empty_array' );
add_filter( 'plugins_api', $api, 10, 3 );
add_filter( 'filesystem_method', $filesystem );
add_filter( 'request_filesystem_credentials', '__return_false' );
add_filter( 'pre_http_request', $block_http );
try {
	$error = Guest_Key_Dependency::ensure( false );
	$assert( is_wp_error( $error ) && 'guest_key_install' === $error->get_error_code(), 'Missing filesystem credentials return a setup error instead of an undefined-method exception (received ' . ( is_wp_error( $error ) ? $error->get_error_code() : gettype( $error ) ) . ')' );
	$assert( false !== strpos( $error->get_error_message(), 'FTP/SSH' ), 'The setup error explains configured filesystem credentials' );
	$assert( ! file_exists( WP_PLUGIN_DIR . '/mcp-adapter' ) && 0 === $requests, 'The failure test installs nothing and makes no HTTP requests' );
	remove_filter( 'filesystem_method', $filesystem );
	remove_filter( 'request_filesystem_credentials', '__return_false' );
	$download = null;
	$block_download = static function ( $reply, $url ) use ( &$download ) {
		$download = $url;
		return new WP_Error( 'fixture_download_failed', 'Fixture download failure.' );
	};
	add_filter( 'upgrader_pre_download', $block_download, 10, 2 );
	$error = Guest_Key_Dependency::ensure( false );
	remove_filter( 'upgrader_pre_download', $block_download );
	$assert( 'https://downloads.wordpress.org/plugin/mcp-adapter.zip' === $download, 'The native installer downloads only the canonical official WordPress.org ZIP' );
	$assert( is_wp_error( $error ) && false !== strpos( $error->get_error_message(), 'Fixture download failure.' ), 'Native download error details are preserved without a plugin-info lookup' );
	$assert( ! file_exists( WP_PLUGIN_DIR . '/mcp-adapter' ) && 0 === $requests, 'A failed download installs nothing and does not fall back to another source' );
	$skin = new WP_Ajax_Upgrader_Skin();
	$skin->feedback( new WP_Error( 'filesystem_fixture', 'Fixture filesystem failure.' ) );
	$method = new ReflectionMethod( Guest_Key_Package_Abilities::class, 'upgrade_result' );
	$method->setAccessible( true );
	$result = $method->invoke( null, false, $skin );
	$assert( is_wp_error( $result ) && 'filesystem_fixture' === $result->get_error_code(), 'Package operations preserve the underlying upgrader error' );
	$assert( true === $method->invoke( null, true, new WP_Ajax_Upgrader_Skin() ), 'Successful package operations return success without an undefined-method exception' );
} finally {
	remove_filter( 'upgrader_pre_download', $block_download ?? '__return_false' );
	remove_filter( 'option_active_plugins', '__return_empty_array' );
	remove_filter( 'plugins_api', $api, 10 );
	remove_filter( 'filesystem_method', $filesystem );
	remove_filter( 'request_filesystem_credentials', '__return_false' );
	remove_filter( 'pre_http_request', $block_http );
}
echo "Completed {$checks} isolated installer checks; no plugin or option changes.\n";
