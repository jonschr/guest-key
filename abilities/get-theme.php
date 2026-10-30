<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/get-theme', Guest_Key_Package_Abilities::args(
	'get_theme', 'Inspect a theme', 'Inspect the active theme by default, or an installed stylesheet. Includes parent, version, active status and theme metadata. Use theme activate, theme mod, and native appearance APIs for changes.',
	array( 'stylesheet' => array( 'type' => 'string', 'minLength' => 1 ) ), array(), 'switch_themes', true
) );
