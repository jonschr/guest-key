<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-users', Guest_Key_Admin_Abilities::rest_args(
	'manage-users',
	'Manage users',
	'List, create, edit, and delete users, including roles and profile fields. User deletion uses native force and reassign parameters. Application-password endpoints are excluded.',
	array( '/wp/v2/users' )
) );
