<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/list-themes', Guest_Key_Package_Abilities::args(
	'list_themes', 'List installed themes', 'List installed themes with stylesheet, parent template, version and active status. Use theme get for details of the current theme or a specific stylesheet.',
	array(), array(), 'switch_themes', true
) );
