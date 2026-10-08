<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/activate-theme', Guest_Key_Package_Abilities::args(
	'activate_theme', 'Activate an installed theme',
	'Switch this site to an installed theme after checking native requirements and multisite theme availability.',
	array( 'stylesheet' => array( 'type' => 'string', 'minLength' => 1 ) ), array( 'stylesheet' ), 'switch_themes'
) );
