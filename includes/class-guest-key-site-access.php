<?php
defined( 'ABSPATH' ) || exit;

/** Site-scoped, local authorization. No licensing, provider calls or telemetry. */
final class Guest_Key_Site_Access {
	const OPTION = 'guest_key_site_access';
	const NETWORK_OWNER = 'guest_key_network_installer';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'initialize' ), 5 );
		add_action( 'wp_ajax_guest_key_set_access', array( __CLASS__, 'ajax_set_access' ) );
		add_action( 'wp_ajax_guest_key_change_owner', array( __CLASS__, 'ajax_change_owner' ) );
		add_action( 'wp_ajax_guest_key_claim_control', array( __CLASS__, 'ajax_claim_control' ) );
	}

	public static function site() { return trailingslashit( (string) get_option( 'home' ) ); }

	private static function state() {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/** A request can have cached an older access list before acquiring the global lock. */
	private static function refresh_state() {
		wp_cache_delete( self::OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/** Raw metadata only: safe before WordPress resolves the authenticated user. */
	private static function administrator( $user_id ) {
		global $wpdb;
		$roles = get_user_meta( (int) $user_id, $wpdb->get_blog_prefix() . 'capabilities', true );
		return is_array( $roles ) && ! empty( $roles['administrator'] );
	}

	public static function allowed( $user_id ) {
		$user_id = (int) $user_id; $state = self::state();
		return $user_id > 0 && ( $state['site'] ?? '' ) === self::site() && self::administrator( $user_id )
			&& ( empty( $state['owner'] ) || self::administrator( $state['owner'] ) )
			&& ( $user_id === ( $state['owner'] ?? 0 ) || in_array( $user_id, $state['users'] ?? array(), true ) );
	}

	public static function toolbar_hidden( $user_id ) { return ! self::allowed( $user_id ); }

	public static function normal_session() {
		return Guest_Key_Access::administrator() && ! Guest_Key_Browser::is_guest_session()
			&& ! Guest_Key_Access::is_guest_request( get_current_user_id() ) && ! rest_get_authenticated_app_password() && wp_get_session_token();
	}

	public static function activate( $network_wide = false ) {
		if ( ! self::state() ) {
			if ( self::legacy_records() || null !== get_option( 'guest_key_access_defaults', null ) || null !== get_option( 'guest_key_license', null ) ) {
				self::initialize();
			} else {
				$user_id = get_current_user_id();
				$owner = $user_id && Guest_Key_Access::administrator( $user_id ) ? $user_id : 0;
				add_option( self::OPTION, array( 'owner' => $owner, 'users' => array(), 'site' => self::site(), 'legacy_grants' => array() ), '', false );
			}
		}
		$state = self::state();
		if ( $network_wide && ! empty( $state['owner'] ) ) { add_site_option( self::NETWORK_OWNER, (int) $state['owner'] ); }
	}

	/** Legacy state is read locally and retained inert for rollback; never contact a provider. */
	private static function legacy_records() {
		global $wpdb;
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name = %s OR option_name LIKE %s ORDER BY option_id ASC", 'guest_key_license', $wpdb->esc_like( 'guest_key_license_user_' ) . '%' ) );
		$records = array();
		foreach ( $names as $name ) {
			$record = get_option( $name, array() );
			if ( ! is_array( $record ) || ( $record['site'] ?? '' ) !== self::site() ) { continue; }
			if ( 'guest_key_license' === $name ) {
				$user = ! empty( $record['email'] ) ? get_user_by( 'email', $record['email'] ) : false;
				if ( ! $user || isset( $records[ $user->ID ] ) ) { continue; }
				$id = (int) $user->ID;
			} else {
				$id = substr( $name, strlen( 'guest_key_license_user_' ) );
				if ( ! ctype_digit( $id ) ) { continue; }
				$id = (int) $id;
			}
			// Replacing an older site-wide record keeps that owner's original position.
			if ( self::administrator( $id ) ) { $records[ $id ] = $record; }
		}
		return $records;
	}

	public static function initialize() {
		if ( self::state() ) { self::reset_departed_controller(); return; }
		Guest_Key_Access::locked( 0, static function () {
			self::refresh_state();
			if ( self::state() ) { return true; }
			$records = self::legacy_records(); $owner = 0; $users = array(); $legacy_grants = array();
			$hidden = (array) get_option( 'guest_key_hidden_toolbar_users', array() );
			$shared = get_option( 'guest_key_shared_users', null );
			// Prefer the oldest identifiable paid owner, then a verified trial owner.
			foreach ( array( 'paid', 'trial' ) as $kind ) {
				foreach ( $records as $id => $record ) {
					if ( $kind !== ( $record['kind'] ?? '' ) || ( 'trial' === $kind && in_array( $id, $hidden, true ) ) ) { continue; }
					if ( ! $owner ) { $owner = $id; }
					if ( 'paid' === $kind || ! in_array( $id, $hidden, true ) ) { $users[] = $id; }
					$recipients = is_array( $shared ) ? $shared : ( $record['shared_users'] ?? array() );
					if ( 'paid' === $kind ) {
						foreach ( $recipients as $recipient ) {
							if ( is_int( $recipient ) && ! in_array( $recipient, $hidden, true ) && self::administrator( $recipient ) ) { $users[] = $recipient; }
						}
					}
				}
			}
			if ( ! $owner && Guest_Key_Dependency::network() ) {
				$installer = (int) get_site_option( self::NETWORK_OWNER, 0 );
				if ( self::administrator( $installer ) ) { $owner = $installer; }
			}
			$users = array_values( array_unique( $users ) );
			// Preserve only existing, site-bound credentials; a copied database must not re-enable keys.
			foreach ( array_unique( array_merge( $users, array( $owner ) ) ) as $id ) {
				$grant = get_user_meta( $id, Guest_Key_Access::META, true );
				if ( $records && is_array( $grant ) && ! empty( $grant['uuid'] ) ) { $legacy_grants[ $id ] = $grant['uuid']; }
			}
			$value = array( 'owner' => $owner, 'users' => $users, 'site' => self::site(), 'legacy_grants' => $legacy_grants );
			add_option( self::OPTION, $value, '', false );
			if ( self::state() !== $value ) { return new WP_Error( 'guest_key_access_storage', 'Could not initialize Guest Key access.' ); }
			wp_clear_scheduled_hook( 'guest_key_license_refresh' );
			return true;
		} );
	}

	/** Leave an explicit unconfigured state so old licensing records are not migrated again. */
	private static function reset_departed_controller() {
		$state = self::state();
		if ( empty( $state['owner'] ) || self::administrator( $state['owner'] ) ) { return; }
		Guest_Key_Access::locked( 0, static function () {
			self::refresh_state(); $state = self::state();
			if ( empty( $state['owner'] ) || self::administrator( $state['owner'] ) ) { return true; }
			$state = array( 'owner' => 0, 'users' => array(), 'site' => self::site(), 'legacy_grants' => array() );
			update_option( self::OPTION, $state, false );
			if ( self::state() !== $state ) { return new WP_Error( 'guest_key_access_storage', 'Could not reset Guest Key access.' ); }
			return self::revoke_site_grants();
		} );
	}

	private static function revoke_site_grants() {
		foreach ( Guest_Key_Access::grants() as $user_id => $grant ) {
			if ( (int) $grant['blog_id'] !== get_current_blog_id() ) { continue; }
			$result = self::revoke_current_site( (int) $user_id );
			if ( is_wp_error( $result ) ) { return $result; }
		}
		return true;
	}

	public static function grant_matches_site( $grant, $user_id ) {
		if ( isset( $grant['site'] ) ) { return $grant['site'] === self::site(); }
		$state = self::state();
		return ( $state['site'] ?? '' ) === self::site() && ! empty( $grant['uuid'] )
			&& ( $state['legacy_grants'][ (int) $user_id ] ?? '' ) === $grant['uuid'];
	}

	public static function view() {
		$state = self::state(); $user_id = get_current_user_id(); $owner_id = (int) ( $state['owner'] ?? 0 );
		$owner = $owner_id ? get_userdata( $owner_id ) : false;
		$active = self::allowed( $user_id ); $rows = array();
		if ( $active && ! Guest_Key_Browser::is_guest_session() ) {
			foreach ( get_users( array( 'role' => 'administrator', 'blog_id' => get_current_blog_id() ) ) as $admin ) {
				$rows[] = array( 'id' => (int) $admin->ID, 'name' => $admin->display_name, 'enabled' => self::allowed( $admin->ID ), 'canOwn' => Guest_Key_Access::administrator( $admin->ID ), 'installer' => (int) $admin->ID === $owner_id );
			}
		}
		return array( 'userId' => $user_id, 'active' => $active, 'restricted' => ! $active, 'needsSetup' => ! $owner_id,
			'isInstaller' => $owner_id === $user_id, 'contactEmail' => $owner ? $owner->user_email : '',
			'canManageAccess' => $active && ! Guest_Key_Browser::is_guest_session(), 'administrators' => $rows );
	}

	public static function claim_control() {
		if ( ! self::normal_session() ) { return new WP_Error( 'guest_key_access_denied', 'Use a normal administrator browser session to manage Guest Key.' ); }
		return Guest_Key_Access::locked( 0, static function () {
			self::refresh_state();
			$state = self::state();
			if ( ! empty( $state['owner'] ) ) { return new WP_Error( 'guest_key_installer_protected', 'Guest Key already has an owner.' ); }
			// Also retry any interrupted reset cleanup before enabling a new controller.
			$revoked = self::revoke_site_grants();
			if ( is_wp_error( $revoked ) ) { return $revoked; }
			$state = array( 'owner' => get_current_user_id(), 'users' => array(), 'site' => self::site(), 'legacy_grants' => array() );
			update_option( self::OPTION, $state, false );
			return self::state() === $state ? true : new WP_Error( 'guest_key_access_storage', 'Could not set up Guest Key.' );
		} );
	}

	public static function set_access( $user_id, $enabled ) {
		if ( ! self::normal_session() || ! self::allowed( get_current_user_id() ) ) { return new WP_Error( 'guest_key_access_denied', 'Use an enabled administrator’s normal browser session to manage Guest Key.' ); }
		return Guest_Key_Access::locked( 0, static function () use ( $user_id, $enabled ) {
			self::refresh_state();
			if ( ! self::allowed( get_current_user_id() ) ) { return new WP_Error( 'guest_key_access_denied', 'Your Guest Key access has been removed.' ); }
			$state = self::state();
			if ( ! is_int( $user_id ) || ! is_bool( $enabled ) || ! self::administrator( $user_id ) ) { return new WP_Error( 'guest_key_access_invalid', 'Select an administrator on this site.' ); }
			if ( $user_id === ( $state['owner'] ?? 0 ) ) { return new WP_Error( 'guest_key_installer_protected', 'Change the owner before hiding this account.' ); }
			if ( self::allowed( $user_id ) === $enabled ) { return true; }
			if ( $enabled ) {
				$revoked = self::revoke_current_site( $user_id );
				if ( is_wp_error( $revoked ) ) { return $revoked; }
				$state['users'][] = $user_id;
			} else { $state['users'] = array_values( array_diff( $state['users'] ?? array(), array( $user_id ) ) ); }
			unset( $state['legacy_grants'][ $user_id ] );
			update_option( self::OPTION, $state, false );
			if ( self::state() !== $state ) { return new WP_Error( 'guest_key_access_storage', 'Could not save Guest Key access.' ); }
			return $enabled ? true : self::revoke_current_site( $user_id );
		} );
	}

	public static function change_owner( $user_id ) {
		if ( ! self::normal_session() ) { return new WP_Error( 'guest_key_access_denied', 'Use a normal administrator browser session to change the owner.' ); }
		return Guest_Key_Access::locked( 0, static function () use ( $user_id ) {
			self::refresh_state(); $state = self::state(); $actor = get_current_user_id();
			if ( $actor !== ( $state['owner'] ?? 0 ) || ! self::allowed( $actor ) ) { return new WP_Error( 'guest_key_access_denied', 'Only the owner can change ownership.' ); }
			if ( ! is_int( $user_id ) || $user_id === $actor || ! Guest_Key_Access::administrator( $user_id ) ) { return new WP_Error( 'guest_key_access_invalid', 'Select another administrator on this site.' ); }
			if ( ! self::allowed( $user_id ) ) {
				$revoked = self::revoke_current_site( $user_id );
				if ( is_wp_error( $revoked ) ) { return $revoked; }
				unset( $state['legacy_grants'][ $user_id ] );
			}
			$state['users'] = array_values( array_unique( array_merge( $state['users'] ?? array(), array( $actor, $user_id ) ) ) );
			$state['owner'] = $user_id;
			update_option( self::OPTION, $state, false );
			if ( self::state() !== $state ) { return new WP_Error( 'guest_key_access_storage', 'Could not change the owner.' ); }
			if ( Guest_Key_Dependency::network() && get_current_blog_id() === get_main_site_id() ) { update_site_option( self::NETWORK_OWNER, $user_id ); }
			return true;
		} );
	}

	public static function ajax_change_owner() {
		Guest_Key_Access::browser_request();
		$id = wp_unslash( $_POST['user'] ?? '' );
		$result = self::change_owner( is_string( $id ) && ctype_digit( $id ) ? (int) $id : null );
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message(), 'access' => self::view() ), 400 ); }
		wp_send_json_success( array( 'access' => self::view() ) );
	}

	private static function revoke_current_site( $user_id ) {
		$grant = Guest_Key_Access::grant( $user_id );
		if ( $grant && ( (int) ( $grant['blog_id'] ?? 0 ) !== get_current_blog_id() || (int) ( $grant['network_id'] ?? 0 ) !== get_current_network_id() ) ) { return true; }
		return Guest_Key_Access::revoke_in_transaction( $user_id );
	}

	public static function ajax_set_access() {
		Guest_Key_Access::browser_request();
		$id = wp_unslash( $_POST['user'] ?? '' ); $enabled = wp_unslash( $_POST['enabled'] ?? '' );
		$result = self::set_access( is_string( $id ) && ctype_digit( $id ) ? (int) $id : null, '1' === $enabled ? true : ( '0' === $enabled ? false : null ) );
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message(), 'access' => self::view() ), 400 ); }
		wp_send_json_success( array( 'access' => self::view() ) );
	}

	public static function ajax_claim_control() {
		Guest_Key_Access::browser_request();
		$result = self::claim_control();
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message(), 'access' => self::view() ), 400 ); }
		wp_send_json_success( array( 'access' => self::view() ) );
	}
}
