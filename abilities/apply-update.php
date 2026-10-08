<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/apply-update', Guest_Key_Package_Abilities::args(
	'apply_update', 'Apply a WordPress update',
	'Update an installed plugin, theme, or WordPress core through the native upgrader. Use list-updates first. Supply a plugin identifier or theme stylesheet as target; core requires target=wordpress and an explicit version. Optional version pins plugin/theme updates to the offer reviewed.',
	array( 'type' => array( 'type' => 'string', 'enum' => array( 'plugin', 'theme', 'core' ) ), 'target' => array( 'type' => 'string', 'minLength' => 1 ), 'version' => array( 'type' => 'string', 'minLength' => 1 ) ), array( 'type', 'target' ), 'manage_options'
) );
