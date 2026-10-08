<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-terms', Guest_Key_Content_Abilities::args(
	'Manage native taxonomy terms and assignments',
	'Discover registered taxonomies, list/read/create/update/delete terms, or assign existing term IDs to a post. Works with custom taxonomies without REST exposure. Assignment replaces terms unless append=true; an empty term_ids array clears assignments. Native taxonomy, term, and post permissions apply.',
	'terms',
	array(
		'action' => array( 'type' => 'string', 'enum' => array( 'describe', 'list', 'get', 'create', 'update', 'delete', 'assign' ) ),
		'taxonomy' => array( 'type' => 'string', 'description' => 'Required except when describing all taxonomies.' ),
		'id' => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Term ID for get/update/delete.' ),
		'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
		'term_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'uniqueItems' => true ),
		'append' => array( 'type' => 'boolean', 'default' => false ),
		'page' => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
		'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
		'search' => array( 'type' => 'string' ), 'hide_empty' => array( 'type' => 'boolean', 'default' => false ),
		'fields' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array(
			'name' => array( 'type' => 'string' ), 'slug' => array( 'type' => 'string' ), 'description' => array( 'type' => 'string' ), 'parent' => array( 'type' => 'integer', 'minimum' => 0 ),
		) ),
	)
) );
