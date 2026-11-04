<?php
/** wp --user=admin eval-file tests/activation-redirect.php; no persistent fixtures. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Use WP-CLI as an administrator on a local/development site.' ); }
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
$checks = 0;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo "PASS: {$message}\n"; };
$screen = $GLOBALS['current_screen'] ?? null;
$page = $GLOBALS['pagenow'] ?? null;
$request = $_REQUEST;
$plugin = plugin_basename( GUEST_KEY_FILE );
$callback = array( 'Guest_Key_Admin', 'activation_destination' );
$armed = static function () use ( $callback ) { return false !== has_filter( 'wp_redirect', $callback ); };
try {
	$assert( 10 === has_action( 'activated_plugin', array( 'Guest_Key_Admin', 'activated' ) ), 'Activation handling is registered' );
	Guest_Key_Admin::activated( $plugin, false );
	$assert( ! $armed(), 'Command-line activation does not arm a redirect' );
	set_current_screen( 'plugins' ); $GLOBALS['pagenow'] = 'plugins.php'; $_REQUEST['action'] = 'activate';
	Guest_Key_Admin::activated( 'other/other.php', false );
	$assert( ! $armed(), 'Other plugin activations are unaffected' );
	Guest_Key_Admin::activated( $plugin, true );
	$assert( ! $armed(), 'Network activation does not arm a redirect' );
	$_REQUEST['action'] = 'activate-selected'; Guest_Key_Admin::activated( $plugin, false );
	$assert( ! $armed(), 'Bulk activation does not arm a redirect' );
	$_REQUEST['action'] = 'activate'; Guest_Key_Admin::activated( $plugin, false );
	$assert( $armed(), 'An individual dashboard activation arms the success redirect' );
	$url = admin_url( 'plugins.php?activate=true&plugin_status=all&paged=1&s=' );
	$assert( Guest_Key_Admin::page_url() === Guest_Key_Admin::activation_destination( $url ), 'Successful activation opens Guest Key settings' );
	foreach ( array( 'plugins.php?error=true&plugin=guest-key%2Fguest-key.php', 'plugins.php?activate=true&error=true', 'plugins.php?activate=true&activate-multi=true', 'plugins.php?activate=false', 'plugins.php', 'update.php?action=update-plugin', 'import.php?import=example' ) as $path ) {
		$url = admin_url( $path );
		$assert( $url === Guest_Key_Admin::activation_destination( $url ), 'Preserve destination: ' . $path );
	}
} finally {
	remove_filter( 'wp_redirect', $callback );
	$GLOBALS['current_screen'] = $screen; $GLOBALS['pagenow'] = $page; $_REQUEST = $request;
}
echo "Completed {$checks} activation redirect checks; no persistent fixtures created.\n";
