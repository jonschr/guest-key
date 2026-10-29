<?php
/** Run with the isolated WP_PLUGIN_DIR bootstrap documented in README. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! defined( 'GUEST_KEY_INSTALL_TEST' ) || GUEST_KEY_INSTALL_TEST !== WP_PLUGIN_DIR || file_exists( WP_PLUGIN_DIR . '/mcp-adapter' ) ) { throw new RuntimeException( 'Use the isolated dependency-install bootstrap from README.' ); }
require dirname( __DIR__ ) . '/guest-key.php';
error_reporting( E_ALL & ~E_DEPRECATED );
$checks = 0; $requests = 0;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo 'PASS: ' . $message . "\n"; };
$api = static function ( $result, $action, $args ) {
	return 'plugin_information' === $action && 'mcp-adapter' === $args->slug
		? (object) array( 'slug' => 'mcp-adapter', 'download_link' => 'https://downloads.wordpress.org/plugin/mcp-adapter.0.7.1.zip' ) : $result;
};
$block_http = static function () use ( &$requests ) { $requests++; return new WP_Error( 'unexpected_http', 'No network during the installer failure test.' ); };
$filesystem = static function () { return 'ftpext'; };
add_filter( 'plugins_api', $api, 10, 3 );
add_filter( 'filesystem_method', $filesystem );
add_filter( 'request_filesystem_credentials', '__return_false' );
add_filter( 'pre_http_request', $block_http );
try {
	$error = Guest_Key_Dependency::ensure( false );
	$assert( is_wp_error( $error ) && 'guest_key_install' === $error->get_error_code(), 'Missing filesystem credentials return a setup error instead of an undefined-method exception (received ' . ( is_wp_error( $error ) ? $error->get_error_code() : gettype( $error ) ) . ')' );
	$assert( false !== strpos( $error->get_error_message(), 'FTP/SSH' ), 'The setup error explains configured filesystem credentials' );
	$assert( ! file_exists( WP_PLUGIN_DIR . '/mcp-adapter' ) && 0 === $requests, 'The failure test installs nothing and makes no HTTP requests' );
	$skin = new WP_Ajax_Upgrader_Skin();
	$skin->feedback( new WP_Error( 'filesystem_fixture', 'Fixture filesystem failure.' ) );
	$method = new ReflectionMethod( Guest_Key_Package_Abilities::class, 'upgrade_result' );
	$method->setAccessible( true );
	$result = $method->invoke( null, false, $skin );
	$assert( is_wp_error( $result ) && 'filesystem_fixture' === $result->get_error_code(), 'Package operations preserve the underlying upgrader error' );
	$assert( true === $method->invoke( null, true, new WP_Ajax_Upgrader_Skin() ), 'Successful package operations return success without an undefined-method exception' );
} finally {
	remove_filter( 'plugins_api', $api, 10 );
	remove_filter( 'filesystem_method', $filesystem );
	remove_filter( 'request_filesystem_credentials', '__return_false' );
	remove_filter( 'pre_http_request', $block_http );
}
echo "Completed {$checks} isolated installer checks; no plugin or option changes.\n";
