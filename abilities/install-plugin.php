<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/install-plugin', array(
	'label' => __( 'Install a WordPress.org plugin', 'guest-key' ),
	'description' => __( 'Install the latest version of a plugin from WordPress.org by directory slug. Optionally activate it. Existing installations are not overwritten. Installation and activation follow WordPress permissions, filesystem policy, and dependency checks. Newly activated abilities become available on the next MCP request.', 'guest-key' ),
	'category' => 'site',
	'input_schema' => array(
		'type' => 'object',
		'properties' => array(
			'slug' => array( 'type' => 'string', 'pattern' => '^[a-z0-9]+(?:-[a-z0-9]+)*$', 'description' => 'WordPress.org plugin directory slug, for example query-monitor.' ),
			'activate' => array( 'type' => 'boolean', 'default' => false, 'description' => 'Activate after installation. Defaults to false.' ),
			'network_wide' => array( 'type' => 'boolean', 'default' => false, 'description' => 'Network activation on multisite; requires activate=true and super administrator permissions.' ),
		),
		'required' => array( 'slug' ), 'additionalProperties' => false,
	),
	'execute_callback' => array( 'Guest_Key_Plugin_Abilities', 'install_plugin' ),
	'permission_callback' => static function ( $input ) {
		return Guest_Key_Access::administrator() && current_user_can( 'install_plugins' )
			&& ( empty( $input['activate'] ) || current_user_can( 'activate_plugins' ) )
			&& ( empty( $input['network_wide'] ) || current_user_can( 'manage_network_plugins' ) );
	},
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
) );
