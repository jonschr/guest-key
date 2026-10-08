<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/deactivate-plugin', array(
	'label' => 'Deactivate a plugin', 'description' => 'Deactivate an installed plugin using its identifier from list-plugins. Network-active plugins require native network permissions. Deactivating Guest Key revokes its temporary credentials.', 'category' => 'site',
	'input_schema' => array( 'type' => 'object', 'properties' => array( 'plugin' => array( 'type' => 'string', 'minLength' => 1 ) ), 'required' => array( 'plugin' ), 'additionalProperties' => false ),
	'execute_callback' => array( 'Guest_Key_Plugin_Abilities', 'deactivate_plugin' ),
	'permission_callback' => static function () { return Guest_Key_Access::administrator() && current_user_can( 'activate_plugins' ); },
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ) ),
) );
