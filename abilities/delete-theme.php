<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/delete-theme', Guest_Key_Package_Abilities::args(
	'delete_theme', 'Delete an unused theme',
	'Delete an installed theme. Active themes and parents required by installed child themes cannot be deleted.',
	array( 'stylesheet' => array( 'type' => 'string', 'minLength' => 1 ) ), array( 'stylesheet' ), 'delete_themes'
) );
