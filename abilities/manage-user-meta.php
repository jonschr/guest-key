<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/manage-user-meta', Guest_Key_Data_Abilities::args(
	'Manage user metadata',
	'Describe registered fields, list authorized stored key names, or get/add/update/delete user metadata. Requires user_id and native object/per-key capabilities. Registered schemas, sanitizers, readonly and single-value declarations apply. Authentication, sessions, role/capability and Guest Key records are excluded. Update replaces all values; delete removes all unless given a nonempty exact-value filter.',
	'user_meta', array(
		'action' => array( 'type' => 'string', 'enum' => array( 'describe', 'list', 'get', 'add', 'update', 'delete' ) ),
		'user_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'key' => array( 'type' => 'string', 'minLength' => 1, 'pattern' => '^[!-~]+$', 'description' => 'Exact ASCII key without whitespace.' ),
		'value' => Guest_Key_Data_Abilities::value_schema(), 'unique' => array( 'type' => 'boolean' ),
	), array( 'action', 'user_id' )
) );
