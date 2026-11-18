<?php
/** Local-only access, migration, and live credential/session regression checks. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Run as an administrator on an isolated local single site.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/user.php';
$original_user = get_current_user_id(); $original_cookies = $_COOKIE; $ids = array(); $checks = 0; $network_calls = 0;
$names = array( Guest_Key_Site_Access::OPTION, 'guest_key_hidden_toolbar_users', 'guest_key_shared_users', 'guest_key_license' ); $saved = array();
foreach ( $names as $name ) { $saved[ $name ] = get_option( $name, null ); }
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo "PASS: {$message}\n"; };
$local_only = static function ( $pre, $args, $url ) use ( &$network_calls ) {
 if ( wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) { $network_calls++; return new WP_Error( 'fixture_no_external_network', 'No external requests during access management.' ); } return $pre;
};
$http = static function ( $url, $args = array() ) {
 $r = wp_remote_request( $url, array_merge( array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 15, 'user-agent' => 'GuestKeyRegression/1.0 (user-directed local test)' ), $args ) );
 if ( is_wp_error( $r ) || 429 === wp_remote_retrieve_response_code( $r ) ) { throw new RuntimeException( 'HTTP test failed or rate limited.' ); } return $r;
};
$session = static function ( $id ) {
 wp_set_current_user( $id ); $expiry = time() + HOUR_IN_SECONDS;
 $token = WP_Session_Tokens::get_instance( $id )->create( $expiry );
 $_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $id, $expiry, 'logged_in', $token );
 return array( new WP_Http_Cookie( array( 'name' => LOGGED_IN_COOKIE, 'value' => $_COOKIE[ LOGGED_IN_COOKIE ], 'path' => '/' ) ), new WP_Http_Cookie( array( 'name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $id, $expiry, 'secure_auth', $token ), 'path' => '/' ) ) );
};
$rest = static function ( $key ) use ( $http ) { return wp_remote_retrieve_response_code( $http( rest_url( 'wp/v2/users/me' ), array( 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $key['username'] . ':' . $key['password'] ) ) ) ) ); };
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'pre_http_request', $local_only, 10, 3 );
try {
 foreach ( array( 'administrator', 'administrator', 'administrator', 'editor' ) as $role ) {
  $id = wp_insert_user( array( 'user_login' => 'gk-free-' . strtolower( wp_generate_password( 10, false ) ), 'user_email' => 'gk-free-' . strtolower( wp_generate_password( 10, false ) ) . '@example.test', 'user_pass' => wp_generate_password( 40 ), 'role' => $role ) );
  if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Fixture creation failed.' ); } $ids[] = $id;
 }
 list( $a, $b, $c, $editor ) = $ids; $owner_cookies = $session( $a ); $owner_nonce = wp_create_nonce( 'guest_key_access' );
 foreach ( $names as $name ) { delete_option( $name ); }
 Guest_Key_Site_Access::activate();
 $assert( Guest_Key_Site_Access::allowed( $a ) && ! Guest_Key_Site_Access::allowed( $b ) && ! Guest_Key_Site_Access::allowed( $original_user ), 'Fresh activation enables only the installing administrator' );
 $assert( Guest_Key_Site_Access::view()['contactEmail'] === get_userdata( $a )->user_email, 'Contact uses the controlling WordPress administrator email' );
 $state = get_option( Guest_Key_Site_Access::OPTION ); Guest_Key_Site_Access::activate(); Guest_Key_Site_Access::initialize();
 $assert( $state === get_option( Guest_Key_Site_Access::OPTION ), 'Reactivation and later initialization preserve controller and access choices' );
 $assert( is_wp_error( Guest_Key_Site_Access::set_access( $a, false ) ) && is_wp_error( Guest_Key_Site_Access::claim_control() ), 'Installer cannot be revoked or replaced through claiming' );
 $assert( is_wp_error( Guest_Key_Site_Access::set_access( $editor, true ) ) && is_wp_error( Guest_Key_Site_Access::set_access( (string) $b, true ) ), 'Targets must be typed local administrator IDs' );
 $assert( true === Guest_Key_Site_Access::set_access( $b, true ), 'Installer grants access without nested-lock failure' );
 $normal = $session( $b );
 $assert( Guest_Key_Site_Access::allowed( $b ), 'Preload shared administrator authorization before a concurrent change' );
 $r = $http( admin_url( 'admin-ajax.php' ), array( 'method' => 'POST', 'cookies' => $owner_cookies, 'body' => array( 'action' => 'guest_key_set_access', 'nonce' => $owner_nonce, 'user' => (string) $b, 'enabled' => '0' ) ) );
 $assert( ! empty( json_decode( wp_remote_retrieve_body( $r ), true )['success'] ) && is_wp_error( Guest_Key_Site_Access::set_access( $c, true ) ), 'Transaction rechecks fresh authorization after another request revokes the acting administrator' );
 $session( $a ); Guest_Key_Site_Access::set_access( $b, true ); $normal = $session( $b );
 $assert( true === Guest_Key_Site_Access::set_access( $c, true ) && is_wp_error( Guest_Key_Site_Access::set_access( $a, false ) ), 'Shared administrator grants access while installer stays protected' );
 $session( $c ); $key = Guest_Key_Access::create( $c );
 $assert( ! is_wp_error( $key ) && 200 === $rest( $key ), 'Enabled shared administrator issues a working native REST credential' );
 $form = $http( Guest_Key_Browser::url() ); preg_match( '/name="guest_key_nonce"\s+value="([^"]+)"/', wp_remote_retrieve_body( $form ), $nonce );
 $login = $http( Guest_Key_Browser::url(), array( 'method' => 'POST', 'body' => array( 'log' => $key['username'], 'pwd' => $key['password'], 'guest_key_nonce' => $nonce[1] ?? '' ) ) );
 $guest = wp_remote_retrieve_cookies( $login );
 $assert( 302 === wp_remote_retrieve_response_code( $login ) && 200 === wp_remote_retrieve_response_code( $http( admin_url(), array( 'cookies' => $guest ) ) ), 'The same credential opens a temporary browser session' );
 $normal = $session( $b );
 $r = $http( admin_url( 'admin-ajax.php' ), array( 'method' => 'POST', 'cookies' => $normal, 'body' => array( 'action' => 'guest_key_set_access', 'nonce' => wp_create_nonce( 'guest_key_access' ), 'user' => (string) $c, 'enabled' => '0' ) ) );
 $assert( 200 === wp_remote_retrieve_response_code( $r ) && ! empty( json_decode( wp_remote_retrieve_body( $r ), true )['success'] ), 'Shared administrator revokes another administrator through real AJAX' );
 wp_cache_delete( Guest_Key_Site_Access::OPTION, 'options' ); wp_cache_delete( $c, 'user_meta' );
 $assert( ! Guest_Key_Site_Access::allowed( $c ) && 401 === $rest( $key ) && 302 === wp_remote_retrieve_response_code( $http( admin_url(), array( 'cookies' => $guest ) ) ), 'Revocation stops existing REST credentials and browser sessions' );
 $assert( true === Guest_Key_Site_Access::set_access( $c, true ) && 401 === $rest( $key ), 'Regranting access never revives a revoked credential' );
 $assert( true === Guest_Key_Site_Access::set_access( $b, false ) && ! Guest_Key_Site_Access::view()['active'], 'A shared administrator may revoke their own access' );
 $assert( is_wp_error( Guest_Key_Site_Access::set_access( $c, false ) ) && is_wp_error( Guest_Key_Access::create( $b ) ) && is_wp_error( Guest_Key_Access::set_duration( 1440 ) ) && is_wp_error( Guest_Key_Admin::diagnostics() ), 'Hidden administrator cannot bypass access management or settings restrictions' );
 ob_start(); Guest_Key_Admin::render(); $html = ob_get_clean();
 $assert( strpos( $html, get_userdata( $a )->user_email ) !== false && strpos( $html, 'data-guest-key-create' ) === false && strpos( $html, 'license key' ) === false, 'Restricted settings show controller contact with no key or connection form' );
 $session( $a ); unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
 $assert( is_wp_error( Guest_Key_Site_Access::set_access( $b, true ) ), 'A command-line identity without a browser session cannot grant access' ); $session( $a );
 $record = array( 'kind' => 'paid', 'site' => Guest_Key_Site_Access::site(), 'status' => 'expired', 'expires' => time() - 100, 'shared_users' => array( $c ) );
 update_option( 'guest_key_license_user_' . $a, $record, false );
 update_option( 'guest_key_license_user_' . $b, array_merge( $record, array( 'kind' => 'trial' ) ), false );
 update_option( 'guest_key_hidden_toolbar_users', array( $b ), false );
 delete_option( Guest_Key_Site_Access::OPTION ); Guest_Key_Site_Access::initialize();
 $assert( Guest_Key_Site_Access::allowed( $a ) && Guest_Key_Site_Access::allowed( $c ) && ! Guest_Key_Site_Access::allowed( $b ), 'Migration removes commercial expiration while preserving grants and hidden administrators' );
 $assert( get_option( Guest_Key_Site_Access::OPTION )['owner'] === $a && ! wp_next_scheduled( 'guest_key_license_refresh' ), 'Identifiable prior ownership becomes the protected controller without licensing cron' );
 $session( $c ); $assert( is_wp_error( Guest_Key_Site_Access::change_owner( $b ) ), 'Shared administrators cannot replace the owner' );
 $session( $a ); $assert( is_wp_error( Guest_Key_Site_Access::change_owner( $editor ) ) && is_wp_error( Guest_Key_Site_Access::change_owner( (string) $b ) ), 'Changing owner rejects non-administrators and malformed IDs' );
 $normal = $session( $a );
 $r = $http( admin_url( 'admin-ajax.php' ), array( 'method' => 'POST', 'cookies' => $normal, 'body' => array( 'action' => 'guest_key_change_owner', 'nonce' => 'bad-nonce', 'user' => (string) $b ) ) );
 $assert( 403 === wp_remote_retrieve_response_code( $r ) && get_option( Guest_Key_Site_Access::OPTION )['owner'] === $a, 'Ownership transfer rejects a bad CSRF nonce' );
 $r = $http( admin_url( 'admin-ajax.php' ), array( 'method' => 'POST', 'cookies' => $normal, 'body' => array( 'action' => 'guest_key_change_owner', 'nonce' => wp_create_nonce( 'guest_key_access' ), 'user' => (string) $b ) ) );
 wp_cache_delete( Guest_Key_Site_Access::OPTION, 'options' );
 $assert( ! empty( json_decode( wp_remote_retrieve_body( $r ), true )['success'] ) && Guest_Key_Site_Access::allowed( $b ) && Guest_Key_Site_Access::allowed( $a ), 'Owner transfers through real AJAX to a hidden administrator while retaining ordinary shared access' );
 $assert( Guest_Key_Site_Access::view()['contactEmail'] === get_userdata( $b )->user_email && ! Guest_Key_Site_Access::view()['isInstaller'], 'Owner contact and controls follow the new owner' );
 $assert( is_wp_error( Guest_Key_Site_Access::change_owner( $c ) ) && is_wp_error( Guest_Key_Site_Access::set_access( $b, false ) ), 'Previous owner can neither change ownership again nor hide the new owner' );
 $session( $b ); $assert( true === Guest_Key_Site_Access::set_access( $a, false ) && ! Guest_Key_Site_Access::allowed( $a ), 'New owner can remove the previous owner’s shared access' );
 unset( $_COOKIE[ LOGGED_IN_COOKIE ] ); $assert( is_wp_error( Guest_Key_Site_Access::change_owner( $c ) ), 'Ownership changes require a native browser session' ); $session( $a );
 delete_option( 'guest_key_license_user_' . $a ); delete_option( 'guest_key_license_user_' . $b );
 delete_option( Guest_Key_Site_Access::OPTION ); delete_option( 'guest_key_hidden_toolbar_users' );
 update_option( 'guest_key_license', array_merge( $record, array( 'kind' => 'trial', 'email' => get_userdata( $b )->user_email ) ), false );
 Guest_Key_Site_Access::initialize();
 $assert( get_option( Guest_Key_Site_Access::OPTION )['owner'] === $b, 'Legacy site-wide ownership matches the existing administrator email locally' );
 delete_option( Guest_Key_Site_Access::OPTION ); update_option( 'guest_key_license', array_merge( $record, array( 'email' => get_userdata( $b )->user_email ) ), false );
 update_option( 'guest_key_license_user_' . $a, $record, false ); Guest_Key_Site_Access::initialize();
 $assert( get_option( Guest_Key_Site_Access::OPTION )['owner'] === $b, 'Older email-matched site-wide owner precedes a newer per-user owner' );
 delete_option( 'guest_key_license_user_' . $a );
 delete_option( Guest_Key_Site_Access::OPTION ); update_option( 'guest_key_license', array_merge( $record, array( 'email' => 'unmatched@example.test' ) ), false ); Guest_Key_Site_Access::activate();
 $assert( Guest_Key_Site_Access::view()['needsSetup'] && ! Guest_Key_Site_Access::allowed( $a ), 'Unmatched ownership enables nobody until an explicit claim' );
 $assert( true === Guest_Key_Site_Access::claim_control() && Guest_Key_Site_Access::allowed( $a ), 'Normal administrator explicitly claims an unconfigured installation' );
 $assert( is_wp_error( Guest_Key_Data_Abilities::options( array( 'action' => 'get', 'key' => Guest_Key_Site_Access::OPTION ) ) ) && is_wp_error( Guest_Key_Data_Abilities::options( array( 'action' => 'update', 'key' => Guest_Key_Site_Access::OPTION, 'value' => array( 'owner' => $b ) ) ) ), 'Generic option tools cannot read or replace Guest Key access policy' );
 $state = get_option( Guest_Key_Site_Access::OPTION ); $state['site'] = 'https://copied.example.test/'; update_option( Guest_Key_Site_Access::OPTION, $state, false );
 $assert( ! Guest_Key_Site_Access::allowed( $a ), 'Copied access state cannot authorize another site' );
 foreach ( array( 'status', 'email', 'verify', 'activate', 'remove', 'share', 'visibility', 'claim_existing' ) as $action ) { $assert( false === has_action( 'wp_ajax_guest_key_license_' . $action ), 'Removed commercial AJAX handler: ' . $action ); }
 $assert( ! isset( rest_get_server()->get_routes()['/guest-key/v1/trial-proof'] ) && 0 === $network_calls, 'Access management and migration use no licensing route, provider requests, or telemetry' );
} finally {
 $_COOKIE = $original_cookies; wp_set_current_user( $original_user );
 foreach ( $ids as $id ) { Guest_Key_Access::revoke( $id ); delete_option( 'guest_key_license_user_' . $id ); wp_delete_user( $id ); }
 foreach ( $saved as $name => $value ) { if ( null === $value ) { delete_option( $name ); } else { update_option( $name, $value, false ); } }
 remove_filter( 'pre_http_request', $local_only, 10 ); remove_filter( 'pre_wp_mail', '__return_true' );
}
echo "Completed {$checks} free-access checks; original state restored.\n";
