<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-users', Guest_Key_Admin_Abilities::rest_args(
	'manage-users',
	'Manage users',
	'List users and edit basic profile fields: name, first_name, last_name, nickname, description, url and locale. Cannot create/delete accounts or change roles, passwords, email, authentication or arbitrary user metadata. Application-password endpoints are excluded.',
	array( '/wp/v2/users' )
) );
