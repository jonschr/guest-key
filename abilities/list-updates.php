<?php
defined( 'ABSPATH' ) || exit;

wp_register_ability( 'guest-key/list-updates', Guest_Key_Package_Abilities::args(
	'list_updates', 'List available updates',
	'List cached WordPress, plugin, and theme update offers. Use refresh=true to check the native update services first.',
	array( 'refresh' => array( 'type' => 'boolean', 'default' => false ) ), array(), 'manage_options', true
) );
