<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-taxonomies', Guest_Key_Admin_Abilities::rest_args(
	'manage-taxonomies',
	'Manage categories, tags, and custom taxonomies through REST',
	'Create, read, update, and delete terms in REST-enabled taxonomies, including categories, tags, and custom taxonomies. Use manage-terms for taxonomies without REST exposure and for assigning existing terms to posts.',
	array( 'Guest_Key_Admin_Abilities', 'taxonomy_bases' )
) );
