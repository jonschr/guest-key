<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/get-admin-api', array(
	'label' => 'Discover admin API routes',
	'description' => 'List supported admin REST routes with HTTP methods and parameter schemas. Filter by ability name (e.g. manage-content) or search text. Includes plugin management and API metadata. No request is executed.',
	'category' => 'site',
	'input_schema' => array( 'type' => 'object', 'properties' => array( 'ability' => array( 'type' => 'string' ), 'search' => array( 'type' => 'string' ) ), 'additionalProperties' => false ),
	'execute_callback' => array( 'Guest_Key_Admin_Abilities', 'discover' ),
	'permission_callback' => static function () { return Guest_Key_Access::administrator(); },
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
) );
