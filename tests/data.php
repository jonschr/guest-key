<?php
/** wp --user=admin eval-file tests/data.php; native data fixtures are removed afterward. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Use WP-CLI as an administrator on a local/development site.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/user.php';
$owner = get_current_user_id();
$grants = Guest_Key_Access::grants();
$prefix = 'gk_data_' . strtolower( wp_generate_password( 10, false ) );
$uid = wp_insert_user( array( 'user_login' => $prefix, 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
if ( is_wp_error( $uid ) ) { throw new RuntimeException( $uid->get_error_message() ); }
$taxonomy = 'gk_data_tax';
if ( taxonomy_exists( $taxonomy ) ) { wp_delete_user( $uid ); throw new RuntimeException( 'Fixture taxonomy already exists.' ); }
$term_id = 0;
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( $message ); } $checks++; echo 'PASS: ' . $message . "\n"; };
$run = static function ( $command, $input ) { return wp_get_ability( 'guest-key/run' )->execute( array( 'commands' => array( array( 'command' => $command, 'input' => $input ) ) ) ); };
$option = $prefix . '_option'; $structured = $prefix . '_structured'; $autoload = $prefix . '_autoload'; $readonly = $prefix . '_readonly';
$mod = $prefix . '_mod';
$cap_filter = static function ( $caps ) { $caps['edit_users'] = false; $caps['manage_categories'] = false; $caps['edit_theme_options'] = false; return $caps; };
$option_cap = static function () { return 'gk_data_denied'; };
$theme_filter = static function ( $value ) { return strtoupper( $value ); };
try {
	wp_set_current_user( $uid );
	register_taxonomy( $taxonomy, 'post', array( 'public' => false, 'show_in_rest' => false ) );
	$term = wp_insert_term( $prefix, $taxonomy );
	if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); }
	$term_id = $term['term_id'];
	register_setting( $prefix, $option, array( 'type' => 'integer', 'sanitize_callback' => static function ( $value ) { return min( 10, absint( $value ) ); } ) );
	register_setting( $prefix, $readonly, array( 'type' => 'string', 'show_in_rest' => array( 'schema' => array( 'readonly' => true ) ) ) );
	$r = $run( 'option describe', array( 'key' => $option ) );
	$assert( $r['ok'] && 'integer' === $r['results'][0]['data'][ $option ]['schema']['type'] && ! isset( $r['results'][0]['data'][ $option ]['value'] ), 'Option discovery returns registered schemas without stored values' );
	$r = $run( 'option update', array( 'key' => $option, 'value' => 17 ) );
	$assert( $r['ok'] && 10 === (int) get_option( $option ), 'Registered option writes invoke native sanitizers' );
	$r = $run( 'option update', array( 'key' => strtoupper( $option ), 'value' => 'invalid' ) );
	$assert( ! $r['ok'] && 10 === (int) get_option( $option ), 'Case variants cannot bypass registered option schemas' );
	$r = $run( 'option update', array( 'key' => $readonly, 'value' => 'forbidden' ) );
	$assert( ! $r['ok'], 'Registered readonly options reject mutations' );
	add_filter( 'option_page_capability_' . $prefix, $option_cap );
	$r = $run( 'option get', array( 'key' => $option ) );
	$assert( ! $r['ok'], 'Registered option groups retain native capability requirements' );
	remove_filter( 'option_page_capability_' . $prefix, $option_cap );
	$value = array( 'label' => 'C:\\portfolio "quoted"', 'enabled' => true, 'numbers' => array( 1, 2 ) );
	$r = $run( 'option update', array( 'key' => $structured, 'value' => $value ) );
	$assert( $r['ok'] && $value === get_option( $structured ), 'Unregistered options preserve structured values and literal backslashes' );
	$assert( ! array_key_exists( $structured, wp_load_alloptions() ), 'New options do not autoload' );
	add_option( $autoload, 'before', '', true );
	$r = $run( 'option update', array( 'key' => $autoload, 'value' => 'after' ) );
	$assert( $r['ok'] && array_key_exists( $autoload, wp_load_alloptions() ), 'Updating an existing option preserves its autoload policy' );
	$r = $run( 'option delete', array( 'key' => $structured ) );
	$assert( $r['ok'] && ! $r['results'][0]['data']['exists'], 'Delete an exact option through native APIs' );
	foreach ( array( 'active_plugins', 'stylesheet', 'home', 'cron', 'default_role', 'guest_key_adapter_managed', 'GUEST_KEY_ACCESS_LOCK', 'wp_user_roles', '_transient_fixture', 'theme_mods_fixture' ) as $key ) {
		$r = $run( 'option update', array( 'key' => $key, 'value' => 'forbidden' ) );
		$assert( ! $r['ok'], 'Option commands protect lifecycle and access records: ' . $key );
	}
	add_filter( 'pre_set_theme_mod_' . $mod, $theme_filter );
	$r = $run( 'theme mod update', array( 'key' => $mod, 'value' => 'native' ) );
	$assert( $r['ok'] && 'NATIVE' === get_theme_mod( $mod ), 'Theme modifications run native pre-set filters' );
	$r = $run( 'theme mod list', array() );
	$assert( $r['ok'] && in_array( $mod, $r['results'][0]['data']['keys'], true ) && ! isset( $r['results'][0]['data']['values'] ), 'Theme modification discovery lists active-theme key names only' );
	$r = $run( 'theme mod delete', array( 'key' => $mod ) );
	$assert( $r['ok'] && ! $r['results'][0]['data']['exists'], 'Delete one theme modification without clearing other values' );
	foreach ( array( 'user' => $uid, 'term' => $term_id ) as $type => $id ) {
		$subtype = 'term' === $type ? $taxonomy : '';
		$key = $prefix . '_score'; $details = $prefix . '_details'; $denied = $prefix . '_denied'; $ro = $prefix . '_ro';
		register_meta( $type, $key, array( 'object_subtype' => $subtype, 'type' => 'integer', 'single' => true, 'auth_callback' => '__return_true', 'sanitize_callback' => 'absint' ) );
		register_meta( $type, $details, array( 'object_subtype' => $subtype, 'type' => 'object', 'auth_callback' => '__return_true' ) );
		register_meta( $type, $denied, array( 'object_subtype' => $subtype, 'type' => 'string', 'auth_callback' => '__return_false' ) );
		register_meta( $type, $ro, array( 'object_subtype' => $subtype, 'type' => 'string', 'auth_callback' => '__return_true', 'show_in_rest' => array( 'schema' => array( 'readonly' => true ) ) ) );
		$input = array( $type . '_id' => $id ); $command = $type . ' meta ';
		$r = $run( $command . 'describe', $input );
		$assert( $r['ok'] && isset( $r['results'][0]['data'][ $key ] ) && ! $r['results'][0]['data'][ $denied ]['can_edit'], 'Discover registrations and per-key permissions for ' . $type . ' metadata' );
		$r = $run( $command . 'update', $input + array( 'key' => $key, 'value' => -5 ) );
		$assert( $r['ok'] && 5 === (int) get_metadata( $type, $id, $key, true ), 'Native ' . $type . ' metadata sanitizers run' );
		$r = $run( $command . 'update', $input + array( 'key' => strtoupper( $key ), 'value' => 'invalid' ) );
		$assert( ! $r['ok'] && 5 === (int) get_metadata( $type, $id, $key, true ), 'Case variants cannot bypass ' . $type . ' metadata schemas' );
		$r = $run( $command . 'add', $input + array( 'key' => $key, 'value' => 6 ) );
		$assert( ! $r['ok'] && 1 === count( get_metadata( $type, $id, $key, false ) ), 'Registered single-value ' . $type . ' fields reject duplicate additions' );
		$r = $run( $command . 'update', $input + array( 'key' => strtoupper( $denied ), 'value' => 'forbidden' ) );
		$assert( ! $r['ok'] && ! metadata_exists( $type, $id, $denied ), 'Case variants cannot bypass ' . $type . ' per-key authorization' );
		$r = $run( $command . 'update', $input + array( 'key' => $ro, 'value' => 'forbidden' ) );
		$assert( ! $r['ok'], 'Readonly ' . $type . ' metadata rejects updates' );
		$r = $run( $command . 'update', $input + array( 'key' => $details, 'value' => $value ) );
		$assert( $r['ok'] && $value === get_metadata( $type, $id, $details, true ), 'Structured ' . $type . ' metadata preserves quotes and backslashes' );
		$r = $run( $command . 'add', $input + array( 'key' => $details, 'value' => array( 'label' => 'second' ) ) );
		$assert( $r['ok'] && 2 === count( get_metadata( $type, $id, $details, false ) ), 'Multi-value ' . $type . ' metadata permits append' );
		$r = $run( $command . 'delete', $input + array( 'key' => $details, 'value' => $value ) );
		$assert( $r['ok'] && array( array( 'label' => 'second' ) ) === get_metadata( $type, $id, $details, false ), 'Exact-value deletion retains other ' . $type . ' metadata values' );
		$r = $run( $command . 'delete', $input + array( 'key' => $details, 'value' => false ) );
		$assert( ! $r['ok'] && metadata_exists( $type, $id, $details ), 'Ambiguous empty deletion filters cannot clear ' . $type . ' metadata' );
		$r = $run( $command . 'list', $input );
		$assert( $r['ok'] && in_array( $details, $r['results'][0]['data']['keys'], true ), 'List authorized ' . $type . ' metadata names' );
		$r = $run( $command . 'delete', $input + array( 'key' => $details ) );
		$assert( $r['ok'] && ! metadata_exists( $type, $id, $details ), 'Omitting the deletion filter clears the selected ' . $type . ' metadata key' );
		foreach ( array( $key, $details, $denied, $ro ) as $name ) { unregister_meta_key( $type, $name, $subtype ); }
	}
	foreach ( array( '_guest_key_access', '_GUEST_KEY_ACCESS', 'session_tokens', '_application_passwords', 'wp_capabilities', 'wp_user_level' ) as $key ) {
		$before = get_user_meta( $uid, $key, false );
		foreach ( array( 'get', 'update', 'delete' ) as $action ) {
			$r = $run( 'user meta ' . $action, array( 'user_id' => $uid, 'key' => $key, 'value' => 'forbidden' ) );
			$assert( ! $r['ok'] && $before === get_user_meta( $uid, $key, false ), 'User metadata protects authentication and role records: ' . $action . ' ' . $key );
		}
	}
	foreach ( array( 'sessión_tokens', 'session_tokens ', "session_tokens\0" ) as $key ) {
		$r = $run( 'user meta update', array( 'user_id' => $uid, 'key' => $key, 'value' => 'forbidden' ) );
		$assert( ! $r['ok'], 'Noncanonical identifiers cannot alias protected metadata: ' . wp_json_encode( $key ) );
	}
	add_filter( 'user_has_cap', $cap_filter );
	foreach ( array( array( 'user meta get', array( 'user_id' => $owner, 'key' => 'nickname' ) ), array( 'term meta get', array( 'term_id' => $term_id, 'key' => 'test' ) ), array( 'theme mod list', array() ) ) as $item ) {
		$r = $run( $item[0], $item[1] ); $assert( ! $r['ok'], 'Data commands retain object or theme capabilities: ' . $item[0] );
	}
	remove_filter( 'user_has_cap', $cap_filter );
} finally {
	remove_filter( 'user_has_cap', $cap_filter ); remove_filter( 'option_page_capability_' . $prefix, $option_cap ); remove_filter( 'pre_set_theme_mod_' . $mod, $theme_filter );
	foreach ( array( $option, $structured, $autoload, $readonly ) as $name ) { delete_option( $name ); unregister_setting( $prefix, $name ); }
	remove_theme_mod( $mod );
	if ( $term_id ) { wp_delete_term( $term_id, $taxonomy ); }
	unregister_taxonomy( $taxonomy ); wp_delete_user( $uid ); wp_set_current_user( $owner );
	$assert( $grants === Guest_Key_Access::grants(), 'Existing grants remain unchanged after native data tests' );
}
echo "Completed {$checks} native data checks; temporary fixtures removed.\n";
