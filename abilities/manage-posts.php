<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-posts', Guest_Key_Content_Abilities::args(
	'Manage native posts and custom post types',
	'Discover registered post types, or list, read, create, update, trash, restore, and permanently delete their posts. Works without REST exposure. Uses native WordPress permissions and save hooks. Use manage-terms for assignments and manage-post-meta for custom fields. Permanent deletion requires force=true.',
	'posts',
	array(
		'action' => array( 'type' => 'string', 'enum' => array( 'describe', 'list', 'get', 'create', 'update', 'trash', 'restore', 'delete' ) ),
		'post_type' => array( 'type' => 'string', 'description' => 'Registered post type name. List/create default to post; item operations infer it from id. Describe without this returns all types.' ),
		'id' => array( 'type' => 'integer', 'minimum' => 1 ),
		'page' => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
		'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
		'search' => array( 'type' => 'string' ),
		'status' => array( 'type' => 'string', 'description' => 'List filter: a registered post status, or any. Defaults to any (excluding trash and auto-drafts).' ),
		'force' => array( 'type' => 'boolean', 'default' => false ),
		'fields' => array(
			'type' => 'object', 'additionalProperties' => false,
			'properties' => array(
				'title' => array( 'type' => 'string' ), 'content' => array( 'type' => 'string' ), 'excerpt' => array( 'type' => 'string' ),
				'slug' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string' ),
				'author' => array( 'type' => 'integer', 'minimum' => 1 ), 'parent' => array( 'type' => 'integer', 'minimum' => 0 ),
				'menu_order' => array( 'type' => 'integer' ), 'password' => array( 'type' => 'string' ),
				'date' => array( 'type' => 'string', 'format' => 'date-time' ),
				'comment_status' => array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ),
				'ping_status' => array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ),
			),
		),
	)
) );
