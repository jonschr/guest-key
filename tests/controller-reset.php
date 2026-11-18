<?php
/** Run as an administrator on an isolated local single site with its web server running. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || is_multisite() || 'local' !== wp_get_environment_type() || ! Guest_Key_Access::administrator() ) { throw new RuntimeException( 'Isolated local administrator test only.' ); }
error_reporting( E_ALL & ~E_DEPRECATED );
require_once ABSPATH . 'wp-admin/includes/user.php';
$original = get_option( Guest_Key_Site_Access::OPTION ); $original_user = get_current_user_id(); $cookies = $_COOKIE; $ids = array(); $checks = 0;
$assert = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; echo "PASS: {$message}\n"; };
$session = static function ( $id ) { wp_set_current_user( $id ); $until = time() + HOUR_IN_SECONDS; $token = WP_Session_Tokens::get_instance( $id )->create( $until ); $_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $id, $until, 'logged_in', $token ); };
$refresh = static function () { wp_cache_delete( Guest_Key_Site_Access::OPTION, 'options' ); foreach ( get_users( array( 'fields' => 'ID' ) ) as $id ) { wp_cache_delete( $id, 'user_meta' ); } };
$http = static function ( $url, $args = array() ) { $r = wp_remote_request( $url, array_merge( array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 15, 'user-agent' => 'GuestKeyRegression/1.0 (user-directed local reset test)' ), $args ) ); if ( is_wp_error( $r ) || 429 === wp_remote_retrieve_response_code( $r ) ) { throw new RuntimeException( is_wp_error( $r ) ? $r->get_error_message() : 'HTTP rate limited; stop testing.' ); } return $r; };
$rest = static function ( $key ) use ( $http ) { return wp_remote_retrieve_response_code( $http( rest_url( 'wp/v2/users/me' ), array( 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $key['username'] . ':' . $key['password'] ) ) ) ) ); };
$block_cleanup = static function ( $pre, $id, $key ) { return WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS === $key ? false : $pre; };
add_filter( 'pre_wp_mail', '__return_true' );
try {
 foreach ( range( 1, 3 ) as $unused ) { $id = wp_insert_user( array( 'user_login' => 'gk-reset-' . strtolower( wp_generate_password( 10, false ) ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'administrator' ) ); if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Fixture creation failed.' ); } $ids[] = $id; }
 list( $a, $b, $c ) = $ids;
 $state = array( 'owner' => $a, 'users' => array( $b ), 'site' => Guest_Key_Site_Access::site(), 'legacy_grants' => array() ); update_option( Guest_Key_Site_Access::OPTION, $state, false );
 $session( $b ); $key = Guest_Key_Access::create( $b ); $assert( ! is_wp_error( $key ) && 200 === $rest( $key ), 'Shared administrator has a working credential before controller departure' );
 $form = $http( Guest_Key_Browser::url() ); preg_match( '/name="guest_key_nonce"\s+value="([^"]+)"/', wp_remote_retrieve_body( $form ), $nonce );
 $login = $http( Guest_Key_Browser::url(), array( 'method' => 'POST', 'body' => array( 'log' => $key['username'], 'pwd' => $key['password'], 'guest_key_nonce' => $nonce[1] ?? '' ) ) ); $guest = wp_remote_retrieve_cookies( $login );
 $assert( 302 === wp_remote_retrieve_response_code( $login ), 'Shared administrator has a temporary browser session before reset' );
 get_userdata( $a )->set_role( 'subscriber' );
 $assert( ! Guest_Key_Site_Access::allowed( $b ), 'Controller role loss immediately denies existing shared authorization' );
 $assert( 401 === $rest( $key ), 'Next request resets access and stops the shared REST credential' ); $refresh();
 $assert( get_option( Guest_Key_Site_Access::OPTION )['owner'] === 0 && get_option( Guest_Key_Site_Access::OPTION )['users'] === array(), 'Reset clears controller and the shared list' );
 $assert( 302 === wp_remote_retrieve_response_code( $http( admin_url(), array( 'cookies' => $guest ) ) ), 'Reset ends the temporary browser session' );
 $assert( ! Guest_Key_Access::grant( $b ) && Guest_Key_Site_Access::view()['needsSetup'], 'Reset removes stored credentials and requires explicit setup' );
 get_userdata( $a )->set_role( 'administrator' ); Guest_Key_Site_Access::initialize();
 $assert( ! Guest_Key_Site_Access::allowed( $a ) && ! Guest_Key_Site_Access::allowed( $b ), 'Restoring a role does not restore the old access list' );
 $session( $b ); $assert( true === Guest_Key_Site_Access::claim_control() && Guest_Key_Site_Access::view()['isInstaller'], 'Another administrator explicitly sets up Guest Key' );
 $assert( 401 === $rest( $key ), 'Setting up again cannot revive the prior credential' );
 $new_key = Guest_Key_Access::create( $b ); $assert( ! is_wp_error( $new_key ) && 200 === $rest( $new_key ), 'The new controller can create fresh access' );
 $session( $b ); Guest_Key_Site_Access::set_access( $c, true ); $session( $c ); $shared_key = Guest_Key_Access::create( $c );
 $legacy = array( 'kind' => 'paid', 'site' => Guest_Key_Site_Access::site(), 'email' => get_userdata( $a )->user_email ); update_option( 'guest_key_license_user_' . $a, $legacy, false );
 wp_delete_user( $b );
 add_filter( 'update_user_metadata', $block_cleanup, 10, 3 );
 Guest_Key_Site_Access::initialize();
 $assert( Guest_Key_Site_Access::view()['needsSetup'] && ! Guest_Key_Site_Access::allowed( $c ), 'Controller deletion resets authorization even if credential cleanup fails' );
 $assert( is_wp_error( Guest_Key_Site_Access::claim_control() ) && Guest_Key_Site_Access::view()['needsSetup'], 'Interrupted cleanup blocks setup until stale credentials can be removed' );
 remove_filter( 'update_user_metadata', $block_cleanup, 10 );
 Guest_Key_Site_Access::initialize();
 $assert( Guest_Key_Site_Access::view()['needsSetup'] && get_option( 'guest_key_license_user_' . $a ) === $legacy, 'Reset preserves old licensing data without migrating it again' );
 $assert( true === Guest_Key_Site_Access::claim_control() && 401 === $rest( $shared_key ), 'Setup retries cleanup and does not revive credentials after controller deletion' );
} finally {
 remove_filter( 'update_user_metadata', $block_cleanup, 10 ); $_COOKIE = $cookies; wp_set_current_user( $original_user );
 foreach ( $ids as $id ) { Guest_Key_Access::revoke( $id ); delete_option( 'guest_key_license_user_' . $id ); if ( get_userdata( $id ) ) { wp_delete_user( $id ); } }
 update_option( Guest_Key_Site_Access::OPTION, $original, false ); remove_filter( 'pre_wp_mail', '__return_true' );
}
echo "Completed {$checks} controller-reset checks; fixtures removed.\n";
