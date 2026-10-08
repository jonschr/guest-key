<?php
defined( 'ABSPATH' ) || exit;
wp_register_ability( 'guest-key/manage-theme-mods', Guest_Key_Data_Abilities::args(
	'Manage theme modifications',
	'List stored key names, or get/update/delete one modification on the active theme using native theme-mod APIs. Requires edit_theme_options. Native pre_set_theme_mod filters apply; Customizer-only validation and builder CSS regeneration are not implied. Inspect the theme’s supported settings before changing values. Guest Key records are excluded.',
	'theme_mods', array(
		'action' => array( 'type' => 'string', 'enum' => array( 'list', 'get', 'update', 'delete' ) ),
		'key' => array( 'type' => 'string', 'minLength' => 1, 'pattern' => '^[!-~]+$', 'description' => 'Exact ASCII key without whitespace.' ), 'value' => Guest_Key_Data_Abilities::value_schema(),
	)
) );
