<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/delete-plugin', array(
	'label' => 'Delete an inactive plugin', 'description' => 'Remove an inactive plugin using its identifier from list-plugins. WordPress refuses deletion of active plugins and applies its filesystem policy.', 'category' => 'site',
	'input_schema' => array( 'type' => 'object', 'properties' => array( 'plugin' => array( 'type' => 'string', 'minLength' => 1 ) ), 'required' => array( 'plugin' ), 'additionalProperties' => false ),
	'execute_callback' => array( 'Guest_Key_Plugin_Abilities', 'delete_plugin' ),
	'permission_callback' => static function () { return Guest_Key_Access::administrator() && current_user_can( 'delete_plugins' ) && current_user_can( 'activate_plugins' ); },
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
) );
