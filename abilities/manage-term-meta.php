<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/manage-term-meta', Guest_Key_Data_Abilities::args(
	'Manage term metadata',
	'Describe registered fields, list every stored key name, or get/add/update/delete term metadata, including non-REST taxonomies. Reads require permission to edit the term and include protected, unregistered and authorization-denied fields. Writes retain native per-key capabilities. Registered taxonomy-specific schemas, sanitizers, readonly and single-value declarations apply. Writes exclude Guest Key records; reads include every stored key and its values. Update replaces all values; delete removes all unless given a nonempty exact-value filter.',
	'term_meta', array(
		'action' => array( 'type' => 'string', 'enum' => array( 'describe', 'list', 'get', 'add', 'update', 'delete' ) ),
		'term_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'key' => array( 'type' => 'string', 'minLength' => 1, 'description' => 'Exact stored key. Writes require ASCII without whitespace.' ),
		'value' => Guest_Key_Data_Abilities::value_schema(), 'unique' => array( 'type' => 'boolean' ),
	), array( 'action', 'term_id' )
) );
