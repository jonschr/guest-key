<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/manage-term-meta', Guest_Key_Data_Abilities::args(
	'Manage term metadata',
	'Describe registered fields, list authorized stored key names, or get/add/update/delete term metadata, including non-REST taxonomies. Requires term_id and native object/per-key capabilities. Registered taxonomy-specific schemas, sanitizers, readonly and single-value declarations apply. Guest Key records are excluded. Update replaces all values; delete removes all unless given a nonempty exact-value filter.',
	'term_meta', array(
		'action' => array( 'type' => 'string', 'enum' => array( 'describe', 'list', 'get', 'add', 'update', 'delete' ) ),
		'term_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'key' => array( 'type' => 'string', 'minLength' => 1, 'pattern' => '^[!-~]+$', 'description' => 'Exact ASCII key without whitespace.' ),
		'value' => Guest_Key_Data_Abilities::value_schema(), 'unique' => array( 'type' => 'boolean' ),
	), array( 'action', 'term_id' )
) );
