<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/activate-plugin', array(
	'label' => __( 'Activate an installed plugin', 'guest-key' ),
	'description' => __( 'Activate an installed plugin using its identifier from guest-key/list-plugins (with or without .php). Supports network activation for multisite super administrators and preserves WordPress dependency and compatibility checks. Newly registered abilities become available on the next MCP request.', 'guest-key' ),
	'category' => 'site',
	'input_schema' => array(
		'type' => 'object',
		'properties' => array(
			'plugin' => array( 'type' => 'string', 'minLength' => 1, 'description' => 'Installed plugin identifier, for example query-monitor/query-monitor.' ),
			'network_wide' => array( 'type' => 'boolean', 'default' => false, 'description' => 'Activate across a multisite network.' ),
		),
		'required' => array( 'plugin' ), 'additionalProperties' => false,
	),
	'execute_callback' => array( 'Guest_Key_Plugin_Abilities', 'activate_plugin' ),
	'permission_callback' => static function ( $input ) {
		return Guest_Key_Access::administrator() && current_user_can( 'activate_plugins' )
			&& ( empty( $input['network_wide'] ) || current_user_can( 'manage_network_plugins' ) );
	},
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) ),
) );
