<?php
/** Plugin Name: Guest Key test package
 * Version: 1.0
 * Temporary local installer fixture; never ship as part of Guest Key.
 */
defined( 'ABSPATH' ) || exit;
add_action( 'wp_abilities_api_init', static function () {
	wp_register_ability( 'guest-key-test/installed', array(
		'label' => 'Installed test ability', 'description' => 'Verify discovery after plugin activation.', 'category' => 'site',
		'execute_callback' => '__return_true', 'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
		'meta' => array( 'public' => false ),
	) );
} );
