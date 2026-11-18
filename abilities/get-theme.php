<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/get-theme', Guest_Key_Package_Abilities::args(
	'get_theme', 'Inspect a theme', 'Inspect the active theme by default, or an installed stylesheet. Includes parent, version, active status and theme metadata. Use theme mod and native appearance APIs for supported settings changes. Switching themes requires a normal administrator session.',
	array( 'stylesheet' => array( 'type' => 'string', 'minLength' => 1 ) ), array(), 'edit_theme_options', true
) );
