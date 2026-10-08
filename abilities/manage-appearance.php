<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-appearance', Guest_Key_Admin_Abilities::rest_args(
	'manage-appearance',
	'Manage styles and fonts',
	'Manage global styles, font families, and font faces. Read installed themes, block types, patterns, font collections, and icons. Native route permissions and supported HTTP methods apply.',
	array( '/wp/v2/global-styles', '/wp/v2/font-families', '/wp/v2/themes', '/wp/v2/block-types', '/wp/v2/block-patterns', '/wp/v2/pattern-directory', '/wp/v2/block-directory', '/wp/v2/font-collections', '/wp/v2/icons', '/wp/v2/icon-collections' )
) );
