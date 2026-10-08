<?php
/**
 * Plugin Name: Guest Key integration test fixture
 * Description: Temporary private abilities for integration tests. Remove after testing.
 */
defined( 'ABSPATH' ) || exit;
add_action( 'wp_abilities_api_init', static function () {
	wp_register_ability( 'guest-key-test/private-echo', array(
		'label' => 'Private test echo', 'description' => 'Integration test for a private ability.', 'category' => 'site',
		'input_schema' => array( 'type' => 'object', 'properties' => array( 'message' => array( 'type' => 'string' ) ), 'required' => array( 'message' ) ),
		'execute_callback' => static function ( $input ) { return $input['message']; },
		'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
		'meta' => array( 'public' => false ),
	) );
	wp_register_ability( 'guest-key-test/denied', array(
		'label' => 'Denied test ability', 'description' => 'Integration test for preserved permission checks.', 'category' => 'site',
		'execute_callback' => static function () { return 'This must never execute.'; },
		'permission_callback' => '__return_false', 'meta' => array( 'public' => false ),
	) );
} );
