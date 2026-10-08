<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-widgets', Guest_Key_Admin_Abilities::rest_args(
	'manage-widgets',
	'Manage widgets and sidebars',
	'Read widget types and manage widgets and sidebar assignments through WordPress. Use get-admin-api for native widget field schemas.',
	array( '/wp/v2/widgets', '/wp/v2/widget-types', '/wp/v2/sidebars' )
) );
