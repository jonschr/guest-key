<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-navigation', Guest_Key_Admin_Abilities::rest_args(
	'manage-navigation',
	'Manage menus and navigation',
	'Manage classic menus, menu items, and block navigation. Read menu locations. WordPress determines which operations are supported by each route.',
	array( '/wp/v2/menus', '/wp/v2/menu-items', '/wp/v2/menu-locations', '/wp/v2/navigation' )
) );
