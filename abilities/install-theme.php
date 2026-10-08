<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/install-theme', Guest_Key_Package_Abilities::args(
	'install_theme', 'Install a WordPress.org theme',
	'Install a new theme from WordPress.org by slug without activating it. Uses the native installer; existing themes are not replaced.',
	array( 'slug' => array( 'type' => 'string', 'pattern' => '^[a-z0-9]+(?:-[a-z0-9]+)*$' ) ), array( 'slug' ), 'install_themes'
) );
