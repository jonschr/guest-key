<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/manage-options', Guest_Key_Data_Abilities::args(
	'Manage site options',
	'Describe registered settings without values, or get/update/delete an exact option key using native APIs. Unregistered options require manage_options; registered schemas, sanitizers and option-group capabilities apply. Credential/access policy, lifecycle and Guest Key records are excluded. No network options or all-options dump. Updates retain existing autoload policy; new options do not autoload.',
	'options', array(
		'action' => array( 'type' => 'string', 'enum' => array( 'describe', 'get', 'update', 'delete' ) ),
		'key' => array( 'type' => 'string', 'minLength' => 1, 'pattern' => '^[!-~]+$', 'description' => 'Exact ASCII key without whitespace.' ), 'value' => Guest_Key_Data_Abilities::value_schema(),
	)
) );
