<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-post-meta', Guest_Key_Content_Abilities::args(
	'Manage post meta and custom fields',
	'Describe registered field schemas, or read/edit metadata on posts you can edit, including unregistered underscore keys without REST exposure. Reads return all stored fields and distinguish missing keys from empty values. Writes ignore underscore-only denial while honoring native field auth callbacks, capability filters, readonly schemas, value validation and sanitizers/hooks. Plugin-specific form validation is not automatically run. Revisions resolve to their parent. Update replaces all values; add appends; delete removes all values unless a nonempty value filter is given.',
	'post_meta',
	array(
		'action' => array( 'type' => 'string', 'enum' => array( 'describe', 'list', 'get', 'add', 'update', 'delete' ) ),
		'post_id' => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Numeric ID of the post containing the metadata. key identifies a field on that post, not the post itself.' ),
		'post_type' => array( 'type' => 'string', 'description' => 'Describe registered fields before creating an item. Other actions require post_id.' ),
		'key' => array( 'type' => 'string', 'minLength' => 1, 'description' => 'Required for get/add/update/delete; optional filter for describe. Get can read any stored key on a post you can edit, including protected or unregistered keys.' ),
		'value' => array( 'type' => array( 'string', 'number', 'boolean', 'object', 'array', 'null' ), 'description' => 'JSON-compatible value. Required for add/update; optional exact-value filter for delete. Registered single=false fields validate each stored value against their item type.' ),
		'unique' => array( 'type' => 'boolean', 'default' => false, 'description' => 'For add: reject an existing key. Registered single-value fields always enforce this.' ),
	),
	array( 'action' )
) );
