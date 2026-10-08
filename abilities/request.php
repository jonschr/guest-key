<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/request', array(
	'label' => 'Call a native REST endpoint',
	'description' => 'Call any native core or plugin REST route with its own validation and permissions. Use GET / for the route index, or OPTIONS on a route for its schema. Supply path without /wp-json or query text; query/body fields go in parameters. Prefer _fields and pagination for small responses.',
	'category' => 'site',
	'input_schema' => array( 'type' => 'object', 'properties' => array(
		'path' => array( 'type' => 'string', 'pattern' => '^/' ),
		'method' => array( 'type' => 'string', 'enum' => array( 'GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE' ), 'default' => 'GET' ),
		'parameters' => array( 'type' => 'object', 'additionalProperties' => true ),
	), 'required' => array( 'path' ), 'additionalProperties' => false ),
	'execute_callback' => array( 'Guest_Key_Admin_Abilities', 'request' ),
	'permission_callback' => static function () { return Guest_Key_Access::administrator(); },
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
) );
