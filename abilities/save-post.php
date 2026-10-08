<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/save-post', array(
	'label' => 'Save a post with its metadata, terms and image',
	'description' => 'Create a draft (no id), or update an existing post (id). fields uses post create/update fields. meta maps keys to JSON values; terms maps taxonomy names to existing integer term IDs and replaces those assignments. featured_media sets an existing image attachment, or 0 clears it. Works without REST exposure. Discover fields with post meta describe. Writes are sequential, not atomic: an error may include post_id and completed steps; repair that post rather than recreating it. Plugin-specific derived data may require the plugin API.',
	'category' => 'site',
	'input_schema' => array( 'type' => 'object', 'properties' => array(
		'id' => array( 'type' => 'integer', 'minimum' => 1 ),
		'post_type' => array( 'type' => 'string' ),
		'fields' => array( 'type' => 'object', 'additionalProperties' => true, 'description' => 'Fields from post create/update: title, content, excerpt, slug, status, author, parent, menu_order, password, date, comment_status, ping_status.' ),
		'meta' => array( 'type' => 'object', 'maxProperties' => 100, 'additionalProperties' => true ),
		'terms' => array( 'type' => 'object', 'maxProperties' => 100, 'additionalProperties' => array( 'type' => 'array', 'maxItems' => 100, 'items' => array( 'type' => 'integer', 'minimum' => 1 ) ) ),
		'featured_media' => array( 'type' => 'integer', 'minimum' => 0 ),
	), 'additionalProperties' => false ),
	'execute_callback' => array( 'Guest_Key_Content_Abilities', 'save_post' ),
	'permission_callback' => static function () { return Guest_Key_Access::administrator(); },
	'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ) ),
) );
