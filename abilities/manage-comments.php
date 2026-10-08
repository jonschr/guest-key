<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-comments', Guest_Key_Admin_Abilities::rest_args(
	'manage-comments',
	'Manage comments',
	'Read, create, edit, approve, hold, spam, trash, and delete comments using native comment fields and permissions.',
	array( '/wp/v2/comments' )
) );
