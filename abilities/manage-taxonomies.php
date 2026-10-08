<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-taxonomies', Guest_Key_Admin_Abilities::rest_args(
	'manage-taxonomies',
	'Manage categories and tags',
	'Create, read, update, and delete terms in REST-enabled taxonomies, including categories, tags, and custom taxonomies.',
	array( 'Guest_Key_Admin_Abilities', 'taxonomy_bases' )
) );
