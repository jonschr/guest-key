<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/help', array(
	'label' => 'WordPress command help',
	'description' => 'List compact command names, or supply a command such as post save or plugin install for its input schema. Prefixes such as post narrow the list. Before designing pages, load design guide for a short workflow and focused references. Use ability list/describe for plugin-provided operations. Inputs are structured JSON, not shell strings.',
	'category' => 'site', 'input_schema' => Guest_Key_Commands::help_schema(),
	'execute_callback' => array( 'Guest_Key_Commands', 'help' ),
	'permission_callback' => static function () { return Guest_Key_Access::administrator(); },
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
) );
