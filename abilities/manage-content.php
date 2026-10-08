<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-content', Guest_Key_Admin_Abilities::rest_args(
	'manage-content',
	'Manage posts, pages, and custom post types through REST',
	'Create, read, update, publish, trash, or delete posts, pages, and REST-enabled custom post types. Includes registered REST meta fields, taxonomy assignments, revisions, and autosaves. Use get-admin-api to discover routes and fields. For types or custom fields without REST exposure, use manage-posts, manage-terms, and manage-post-meta.',
	array( 'Guest_Key_Admin_Abilities', 'content_bases' )
) );
