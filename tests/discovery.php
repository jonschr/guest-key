<?php
/** Run with WP-CLI eval-file on a local/development site. Fixtures exist only in this process. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Use WP-CLI on a local/development site.' );
}
error_reporting( E_ALL & ~E_DEPRECATED );
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$checks++;
	echo 'PASS: ' . $message . "\n";
};
$invocations = 0;
$network = 0;
$options = array( 'active_plugins', 'elementor_mcp_enabled', 'guest_key_adapter_managed' );
$before = array_map( 'get_option', $options );
$grants = Guest_Key_Access::grants();
$callback = static function () use ( &$invocations ) { $invocations++; throw new RuntimeException( 'Inventory invoked a tool or permission callback.' ); };
add_filter( 'pre_http_request', static function () use ( &$network ) { $network++; return new WP_Error( 'inventory_network', 'Inventory must not make HTTP requests.' ); } );
// WP-CLI initializes the registries during init. Run each fixture in isolation
// under its required registration action, without replaying other providers.
$register = static function ( $action, $fixture, ...$args ) {
	$saved = $GLOBALS['wp_filter'][ $action ] ?? null;
	$GLOBALS['wp_filter'][ $action ] = new WP_Hook();
	add_action( $action, $fixture );
	try { do_action( $action, ...$args ); }
	finally {
		if ( $saved ) { $GLOBALS['wp_filter'][ $action ] = $saved; }
		else { unset( $GLOBALS['wp_filter'][ $action ] ); }
	}
};
wp_get_abilities();
$register( 'wp_abilities_api_init', static function () use ( $callback ) {
	wp_register_ability( 'inventory-test/probe', array(
		'label' => 'Inventory probe', 'description' => 'Read-only inventory fixture.', 'category' => 'site',
		'input_schema' => array( 'type' => 'object', 'properties' => array( 'searchable_fixture' => array( 'type' => 'string' ) ) ),
		'output_schema' => array( 'type' => 'object' ), 'execute_callback' => $callback, 'permission_callback' => $callback,
		'meta' => array( 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
	) );
} );
$register( 'mcp_adapter_init', static function ( $adapter ) use ( $callback ) {
	$adapter->create_server( 'inventory-test', 'inventory-test/v1', 'mcp', 'Inventory test server', 'Runtime provider fixture.', '1.0.0', array( \WP\MCP\Transport\HttpTransport::class ), null, null, array( 'inventory-test/probe' ), array(), array(), $callback );
}, \WP\MCP\Core\McpAdapter::instance() );
add_action( 'rest_api_init', static function () use ( $callback ) {
	register_rest_route( 'inventory-unrelated/v1', '/mcp-settings', array( 'methods' => 'GET', 'callback' => $callback, 'permission_callback' => $callback ) );
} );
$abilities = Guest_Key_Abilities::inventory();
$ability_names = array_column( $abilities, 'name' );
$servers = Guest_Key_Discovery::inventory();
$registered = \WP\MCP\Core\McpAdapter::instance()->get_servers();
$assert( in_array( 'inventory-test/probe', $ability_names, true ), 'Generic registered abilities appear automatically' );
$assert( array_column( $servers, 'name' ) === array_keys( $registered ), 'Only servers from the shared adapter registry appear' );
$server = $servers[ array_search( 'inventory-test', array_column( $servers, 'name' ), true ) ];
$assert( '/inventory-test/v1/mcp' === $server['details']['route'], 'Generic provider endpoint is discovered automatically' );
$assert( 1 === $server['details']['counts']['tools'] && 1 === count( $server['details']['tools'] ), 'Server tool counts and definitions are readable' );
$assert( false !== strpos( wp_json_encode( $server['details']['tools'] ), 'searchable_fixture' ), 'Input schemas are available for inspection and search' );
$assert( isset( $server['details']['source_attribution'] ), 'Implementation source is distinguished from registering plugin attribution' );
$assert( ! in_array( '/inventory-unrelated/v1/mcp-settings', array_column( $servers, 'name' ), true ), 'An MCP-like route name does not create an inventory entry' );
$assert( 0 === $invocations && 0 === $network, 'Discovery does not execute tools, permission callbacks, or HTTP requests' );
$assert( $before === array_map( 'get_option', $options ) && $grants === Guest_Key_Access::grants(), 'Discovery preserves plugin settings and access grants' );
$assert( $ability_names === array_column( Guest_Key_Abilities::inventory(), 'name' ), 'Server discovery does not add abilities to WordPress' );
wp_unregister_ability( 'inventory-test/probe' );
$assert( ! in_array( 'inventory-test/probe', array_column( Guest_Key_Abilities::inventory(), 'name' ), true ), 'Unregistered abilities disappear automatically' );
echo "Completed {$checks} discovery checks. No persistent fixtures created.\n";
