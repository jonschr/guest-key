<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/manage-settings', Guest_Key_Admin_Abilities::rest_args(
	'manage-settings',
	'Manage site settings',
	'Read and update settings registered with the WordPress REST API, including title, description, timezone, language, homepage, and default discussion settings. Plugin settings must be registered for REST access.',
	array( '/wp/v2/settings' )
) );
