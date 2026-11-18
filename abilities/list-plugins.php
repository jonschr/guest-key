<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/list-plugins', array(
	'label' => __( 'List installed plugins', 'guest-key' ),
	'description' => __( 'List installed plugins and their activation status. Read-only inventory remains available to temporary access. Plugin activation and other code changes require a normal administrator session.', 'guest-key' ),
	'category' => 'site',
	'input_schema' => array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string', 'description' => 'Optional plugin search text.' ) ), 'additionalProperties' => false ),
	'execute_callback' => array( 'Guest_Key_Plugin_Abilities', 'list_plugins' ),
	'permission_callback' => static function () { return Guest_Key_Access::administrator() && current_user_can( 'manage_options' ); },
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
) );
