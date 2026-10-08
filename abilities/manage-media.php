<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-media', Guest_Key_Admin_Abilities::rest_args(
	'manage-media',
	'Manage media library',
	'List, edit, and delete attachments, including alt text, captions, descriptions, and image edits. Use upload-media to add a file.',
	array( '/wp/v2/media' )
) );
