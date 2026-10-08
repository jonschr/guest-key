<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-post-meta', Guest_Key_Content_Abilities::args(
	'Manage post meta and custom fields',
	'Describe registered fields or list/read/add/update/delete authorized post meta for any registered post type, including fields without REST exposure. Revision IDs resolve to their parent. Registered types, schemas, sanitizers, and per-key authorization apply. Protected keys require explicit native authorization. Update replaces all values for a key; add appends a value; delete removes all values unless a nonempty value filter is provided.',
	'post_meta',
	array(
		'action' => array( 'type' => 'string', 'enum' => array( 'describe', 'list', 'get', 'add', 'update', 'delete' ) ),
		'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
		'post_type' => array( 'type' => 'string', 'description' => 'Describe registered fields before creating an item. Other actions require post_id.' ),
		'key' => array( 'type' => 'string', 'minLength' => 1, 'description' => 'Required for get/add/update/delete; optional filter for describe.' ),
		'value' => array( 'type' => array( 'string', 'number', 'boolean', 'object', 'array', 'null' ), 'description' => 'JSON-compatible value. Required for add/update; optional exact-value filter for delete. Registered single=false fields validate each stored value against their item type.' ),
		'unique' => array( 'type' => 'boolean', 'default' => false, 'description' => 'For add: reject an existing key. Registered single-value fields always enforce this.' ),
	),
	array( 'action' )
) );
