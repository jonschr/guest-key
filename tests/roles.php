<?php
/** wp --user=admin eval-file tests/roles.php; local single-site regression with a temporary account/role. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Use WP-CLI as an administrator on a local/development single site.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';
$owner = get_current_user_id(); $cookies = $_COOKIE; $grants = Guest_Key_Access::grants(); $checks = $requests = 0; $uid = null;
$role = 'gk_role_' . strtolower( wp_generate_password( 8, false ) );
$options = array( 'active_plugins', 'guest_key_adapter_managed', 'guest_key_dependency_error' );
$before = array_map( 'get_option', $options );
$menus = array();
foreach ( array( 'menu', 'submenu', '_registered_pages', '_parent_pages', 'admin_page_hooks' ) as $key ) { $menus[ $key ] = array( array_key_exists( $key, $GLOBALS ), $GLOBALS[ $key ] ?? null ); }
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo 'PASS: ' . $message . "\n"; };
$mail = '__return_true'; $deny = static function ( $caps ) { $caps['manage_options'] = false; return $caps; };
add_filter( 'pre_wp_mail', $mail );
try {
	$uid = wp_insert_user( array( 'user_login' => $role, 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
	if ( is_wp_error( $uid ) ) { throw new RuntimeException( $uid->get_error_message() ); }
	add_role( $role, 'Guest Key non-admin regression', array( 'read' => true, 'manage_options' => true, 'manage_network_options' => true, 'activate_plugins' => true, 'install_plugins' => true ) );
	foreach ( array( 'subscriber', 'contributor', 'author', 'editor', $role ) as $name ) {
		get_userdata( $uid )->set_role( $name ); wp_set_current_user( 0 ); wp_set_current_user( $uid );
		if ( $role === $name ) { $assert( current_user_can( 'manage_options' ), 'The custom role has admin-like capabilities without the administrator role' ); }
		$assert( ! Guest_Key_Access::administrator(), 'The ' . $name . ' role is ineligible for Guest Key' );
		$result = Guest_Key_Access::create( $uid );
		$assert( is_wp_error( $result ) && 'guest_key_forbidden' === $result->get_error_code() && ! Guest_Key_Access::grant( $uid ) && ! WP_Application_Passwords::get_user_application_passwords( $uid ), 'The ' . $name . ' role cannot create credentials or access records' );
		$bar = new WP_Admin_Bar(); Guest_Key_Admin::toolbar( $bar ); Guest_Key_Admin::assets();
		$assert( ! $bar->get_node( 'guest-key-access' ) && ! wp_script_is( 'guest-key', 'enqueued' ) && ! wp_style_is( 'guest-key', 'enqueued' ), 'The ' . $name . ' role gets no Guest Key toolbar, scripts or styles' );
		Guest_Key_Admin::menu(); Guest_Key_Admin::network_menu();
		$unchanged = true;
		foreach ( $menus as $key => $value ) { $unchanged = $unchanged && $value === array( array_key_exists( $key, $GLOBALS ), $GLOBALS[ $key ] ?? null ); }
		ob_start(); Guest_Key_Admin::dependency_notice(); $notice = ob_get_clean();
		$assert( $unchanged && '' === $notice, 'The ' . $name . ' role gets no Guest Key menus or notices' );
		$assert( is_wp_error( wp_get_ability( 'guest-key/help' )->execute( array() ) ) && ! Guest_Key_Access::can_connect(), 'The ' . $name . ' role cannot use Guest Key abilities or command transport' );
	}
	// A normal signed-in custom-role session with a valid nonce must also fail AJAX.
	$token = WP_Session_Tokens::get_instance( $uid )->create( time() + HOUR_IN_SECONDS );
	$login_cookie = wp_generate_auth_cookie( $uid, time() + HOUR_IN_SECONDS, 'logged_in', $token );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $login_cookie;
	$http_cookies = array(
		new WP_Http_Cookie( array( 'name' => LOGGED_IN_COOKIE, 'value' => $login_cookie, 'path' => '/' ) ),
		new WP_Http_Cookie( array( 'name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, time() + HOUR_IN_SECONDS, 'secure_auth', $token ), 'path' => '/' ) ),
	);
	foreach ( array( 'guest_key_create', 'guest_key_revoke' ) as $action ) {
		$requests++;
		$response = wp_remote_post( admin_url( 'admin-ajax.php' ), array( 'body' => array( 'action' => $action, 'nonce' => wp_create_nonce( 'guest_key_access' ) ), 'cookies' => $http_cookies, 'sslverify' => false, 'redirection' => 0, 'timeout' => 15, 'user-agent' => 'T3Code/0.0.46-nightly.20261005.2689 (user-directed AI agent; agent=Codex; model=gpt-6.1-sol)' ) );
		if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
		if ( 429 === wp_remote_retrieve_response_code( $response ) ) { throw new RuntimeException( 'Rate limited; stop testing.' ); }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$assert( 403 === wp_remote_retrieve_response_code( $response ) && isset( $data['data']['message'] ) && 'Use an administrator’s logged-in browser session to manage access.' === $data['data']['message'], 'Custom-role AJAX ' . $action . ' is denied even with a valid session and nonce' );
	}
	foreach ( array( home_url( '/' ), Guest_Key_Admin::page_url() ) as $url ) {
		$requests++;
		$response = wp_remote_get( $url, array( 'cookies' => $http_cookies, 'sslverify' => false, 'redirection' => 0, 'timeout' => 15, 'user-agent' => 'T3Code/0.0.46-nightly.20261005.2689 (user-directed AI agent; agent=Codex; model=gpt-6.1-sol)' ) );
		if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
		if ( 429 === wp_remote_retrieve_response_code( $response ) ) { throw new RuntimeException( 'Rate limited; stop testing.' ); }
		$html = wp_remote_retrieve_body( $response );
		$assert( ( home_url( '/' ) === $url ? 200 : 403 ) === wp_remote_retrieve_response_code( $response ) && false === strpos( $html, 'wp-admin-bar-guest-key-access' ) && false === strpos( $html, 'assets/guest-key.js' ) && false === strpos( $html, 'assets/guest-key.css' ) && false === strpos( $html, 'data-guest-key-create' ), 'A signed-in custom-role account gets no Guest Key frontend UI or direct settings-page access' );
	}
	$assert( WP_Session_Tokens::get_instance( $uid )->verify( $token ), 'Guest Key leaves the non-admin ordinary browser session intact' );
	get_userdata( $uid )->set_role( 'administrator' ); wp_set_current_user( 0 ); wp_set_current_user( $uid );
	$assert( Guest_Key_Access::administrator(), 'The administrator role remains eligible' );
	add_filter( 'user_has_cap', $deny );
	try { $assert( ! Guest_Key_Access::administrator() && is_wp_error( Guest_Key_Access::create( $uid ) ), 'An administrator still needs its native manage_options capability' ); }
	finally { remove_filter( 'user_has_cap', $deny ); }
	wp_set_current_user( 0 );
	$assert( ! Guest_Key_Access::administrator() && ! Guest_Key_Access::administrator( PHP_INT_MAX ), 'Anonymous and nonexistent accounts are ineligible' );
} finally {
	$_COOKIE = $cookies; wp_set_current_user( $owner );
	if ( is_int( $uid ) && $uid > 0 ) { Guest_Key_Access::revoke( $uid ); wp_delete_user( $uid ); }
	remove_role( $role ); remove_filter( 'pre_wp_mail', $mail );
	$assert( $grants === Guest_Key_Access::grants() && $before === array_map( 'get_option', $options ), 'Role checks preserve existing grants and adapter configuration' );
}
echo "Completed {$checks} role checks across {$requests} HTTP requests; temporary fixtures removed.\n";
