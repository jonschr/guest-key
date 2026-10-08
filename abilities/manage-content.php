<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-content', Guest_Key_Admin_Abilities::rest_args(
	'manage-content',
	'Manage posts and pages',
	'Create, read, update, publish, trash, or delete posts, pages, and REST-enabled custom post types. Includes revisions and autosaves. Use get-admin-api to discover routes and fields.',
	array( 'Guest_Key_Admin_Abilities', 'content_bases' )
) );
