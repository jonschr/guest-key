<?php
defined( 'ABSPATH' ) || exit;

/** Inspect registered MCP Adapter servers without calling endpoints or executing tools. */
final class Guest_Key_Discovery {

	public static function inventory() {
		// Providers register their servers during normal REST initialization.
		rest_get_server();
		$rows = array();
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) { return $rows; }
		foreach ( \WP\MCP\Core\McpAdapter::instance()->get_servers() as $server ) {
			$route = '/' . trim( $server->get_server_route_namespace(), '/' ) . '/' . ltrim( $server->get_server_route(), '/' );
			$details = array( 'id' => $server->get_server_id(), 'version' => $server->get_server_version(), 'endpoint' => rest_url( ltrim( $route, '/' ) ), 'route' => $route );
			foreach ( array( 'tools', 'resources', 'prompts' ) as $kind ) {
				if ( is_callable( array( $server, 'count_' . $kind ) ) ) { $details['counts'][ $kind ] = $server->{ 'count_' . $kind }(); }
			}
			try {
				$schema = $server->get_schemas()->forVersion( '2025-11-25' );
				foreach ( array( 'tools', 'resources', 'prompts' ) as $kind ) {
					$details[ $kind ] = json_decode( wp_json_encode( $server->{ 'get_' . $kind }( $schema ) ), true );
				}
			} catch ( Throwable $error ) {
				$details['inspection_notice'] = __( 'Component schemas could not be read from this adapter version.', 'guest-key' );
			}
			$ours = 'guest-key' === $server->get_server_id() && Guest_Key_Access::ROUTE === $route;
			if ( ! $ours ) { $details['source_attribution'] = __( 'Source identifies the adapter server implementation; the registering plugin is not supplied by the shared registry.', 'guest-key' ); }
			$rows[] = array(
				'name' => $server->get_server_id(), 'label' => $server->get_server_name(), 'description' => $server->get_server_description(),
				'source' => $ours ? Guest_Key_Abilities::identify( GUEST_KEY_FILE ) : Guest_Key_Abilities::identify( ( new ReflectionClass( $server ) )->getFileName() ),
				'status' => $ours ? __( 'Guest Key endpoint', 'guest-key' ) : __( 'Registered server; provider permissions apply', 'guest-key' ),
				'kind' => __( 'MCP server', 'guest-key' ), 'details' => $details,
			);
		}
		return $rows;
	}
}
