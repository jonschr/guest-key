<?php
defined( 'ABSPATH' ) || exit;

final class Guest_Key_License {

	const OPTION = 'guest_key_license';
	const PENDING = 'guest_key_trial_pending';
	const CHECKOUT = 'https://elodin.lemonsqueezy.com/checkout/buy/40b6382a-e891-418c-bf00-69512a7243dd';
	const RENEW = 'https://app.lemonsqueezy.com/my-orders';

	public static function init() {
		foreach ( array( 'status', 'email', 'verify', 'activate', 'remove', 'share', 'claim_existing' ) as $action ) {
			add_action( 'wp_ajax_guest_key_license_' . $action, static function () use ( $action ) { self::ajax( $action ); } );
		}
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_init', array( __CLASS__, 'migrate_legacy' ) );
		add_action( 'guest_key_license_refresh', static function () {
			foreach ( self::owners() as $owner_id ) {
				self::check( true, $owner_id );
			}
		} );
		add_action( 'init', static function () {
			if ( self::owners() && ! wp_next_scheduled( 'guest_key_license_refresh' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'guest_key_license_refresh' );
			}
		} );
	}


	/** Serialize license changes across owners; nested purchase upgrades reuse the same lock. */
	private static function serialized( $callback ) {
		static $inside = false;
		if ( $inside ) { return $callback(); }
		return Guest_Key_Access::locked( 0, static function () use ( $callback, &$inside ) {
			$inside = true;
			try { return $callback(); } finally { $inside = false; }
		} );
	}

	private static function site() { return trailingslashit( (string) get_option( 'home' ) ); }

	public static function option_name( $user_id = null ) {
		return self::OPTION . '_user_' . ( null === $user_id ? get_current_user_id() : (int) $user_id );
	}

	public static function pending_name( $user_id = null ) {
		return self::PENDING . '_user_' . ( null === $user_id ? get_current_user_id() : (int) $user_id );
	}

	private static function record( $user_id = null ) {
		$value = get_option( self::option_name( $user_id ), array() );
		return is_array( $value ) ? $value : array();
	}

	private static function owners() {
		global $wpdb;
		$prefix = self::OPTION . '_user_';
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
		return array_map( 'intval', array_filter( array_map( static function ( $name ) use ( $prefix ) { return substr( $name, strlen( $prefix ) ); }, $names ), 'ctype_digit' ) );
	}

	/** Raw role metadata only: this must also be safe before WordPress resolves the current user. */
	private static function admin_role( $user_id ) {
		global $wpdb;
		$roles = get_user_meta( $user_id, $wpdb->get_blog_prefix() . 'capabilities', true );
		return is_array( $roles ) && ! empty( $roles['administrator'] );
	}

	private static function own_until( $saved ) {
		if ( ( $saved['site'] ?? '' ) !== self::site() || 'active' !== ( $saved['status'] ?? '' ) ) { return 0; }
		$until = min( (int) ( $saved['checked'] ?? 0 ) + DAY_IN_SECONDS, (int) ( $saved['expires'] ?? 0 ) ?: PHP_INT_MAX );
		return $until > time() ? $until : 0;
	}

	private static function sources( $user_id ) {
		$result = array();
		foreach ( array_unique( array_merge( array( (int) $user_id ), self::owners() ) ) as $owner_id ) {
			if ( ! $owner_id || ( $owner_id !== (int) $user_id && ! self::admin_role( $owner_id ) ) ) { continue; }
			$saved = self::record( $owner_id );
			if ( $owner_id === (int) $user_id || ( 'paid' === ( $saved['kind'] ?? '' ) && in_array( (int) $user_id, $saved['shared_users'] ?? array(), true ) ) ) {
				$result[ $owner_id ] = $saved;
			}
		}
		return $result;
	}

	/** No network or current-user lookup when an explicit ID is provided during authentication. */
	public static function active_until( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		$until = 0;
		foreach ( self::sources( $user_id ) as $saved ) { $until = max( $until, self::own_until( $saved ) ); }
		return $until;
	}

	private static function save( $value, $user_id = null ) { update_option( self::option_name( $user_id ), $value, false ); }

	public static function view() {
		$user_id = get_current_user_id();
		$saved = self::record();
		$pending = get_option( self::pending_name(), array() );
		$bound = ( $saved['site'] ?? '' ) === self::site();
		$own_active = $bound && self::own_until( $saved );
		$shared = array();
		foreach ( self::sources( $user_id ) as $owner_id => $source ) {
			if ( $owner_id !== $user_id && self::own_until( $source ) ) {
				$owner = get_userdata( $owner_id );
				if ( $owner ) { $shared[] = $owner->display_name; }
			}
		}
		$administrators = array();
		if ( $bound && 'paid' === ( $saved['kind'] ?? '' ) && ! Guest_Key_Browser::is_guest_session() ) {
			foreach ( get_users( array( 'role' => 'administrator', 'blog_id' => get_current_blog_id() ) ) as $admin ) {
				if ( (int) $admin->ID !== $user_id ) { $administrators[] = array( 'id' => (int) $admin->ID, 'name' => $admin->display_name, 'shared' => in_array( (int) $admin->ID, $saved['shared_users'] ?? array(), true ) ); }
			}
		}
		return array(
			'active' => (bool) self::active_until( $user_id ), 'ownActive' => (bool) $own_active,
			'kind' => $bound ? ( $saved['kind'] ?? '' ) : ( $shared ? 'shared' : '' ),
			'expired' => $bound && ( ! empty( $saved['expires'] ) && $saved['expires'] <= time() || 'expired' === ( $saved['status'] ?? '' ) ),
			'pending' => ( $pending['site'] ?? '' ) === self::site() && ! empty( $pending['sent'] ) && ( $pending['until'] ?? 0 ) > time(),
			'email' => $bound && 'trial' === ( $saved['kind'] ?? '' ) ? ( $saved['email'] ?? '' ) : ( ( $pending['site'] ?? '' ) === self::site() ? ( $pending['email'] ?? '' ) : '' ),
			'sharedBy' => $shared, 'administrators' => $administrators,
			'legacyClaim' => ! $saved && ( get_option( self::OPTION, array() )['site'] ?? '' ) === self::site(),
			'checkout' => self::CHECKOUT, 'renew' => self::RENEW,
		);
	}

	public static function migrate_legacy() {
		if ( ! Guest_Key_Access::administrator() || Guest_Key_Browser::is_guest_session() ) { return; }
		self::serialized( static function () {
			$pending = get_option( self::PENDING, array() );
			$pending_owner = ! empty( $pending['email'] ) ? get_user_by( 'email', $pending['email'] ) : false;
			if ( ( $pending['site'] ?? '' ) === self::site() && $pending_owner && self::admin_role( $pending_owner->ID ) && ! get_option( self::pending_name( $pending_owner->ID ) ) ) {
				update_option( self::pending_name( $pending_owner->ID ), $pending, false );
				if ( get_option( self::pending_name( $pending_owner->ID ) ) === $pending ) { delete_option( self::PENDING ); }
			}
			$saved = get_option( self::OPTION, array() );
			if ( ! $saved || ( $saved['site'] ?? '' ) !== self::site() ) { return; }
			$email = $saved['email'] ?? '';
			if ( ! $email && 'paid' === ( $saved['kind'] ?? '' ) && ( $saved['migration_checked'] ?? 0 ) < time() - MINUTE_IN_SECONDS ) {
				$saved['migration_checked'] = time();
				$data = self::lemon( 'validate', array( 'license_key' => $saved['key'], 'instance_id' => $saved['instance'] ) );
				if ( ! is_wp_error( $data ) && self::our_product( $data ) && is_email( $data['meta']['user_email'] ?? '' ) ) {
					$email = $data['meta']['user_email'];
					$saved['email'] = $email;
				}
				update_option( self::OPTION, $saved, false );
			}
			$owner = $email ? get_user_by( 'email', $email ) : false;
			if ( $owner && self::admin_role( $owner->ID ) && ! self::record( $owner->ID ) ) { self::move_legacy( $saved, $owner->ID ); }
		} );
	}

	private static function move_legacy( $saved, $owner_id ) {
		$saved['shared_users'] = array();
		self::save( $saved, $owner_id );
		if ( self::record( $owner_id ) !== $saved ) { return new WP_Error( 'guest_key_storage', __( 'Could not assign the existing license. Please retry.', 'guest-key' ) ); }
		delete_option( self::OPTION );
		return true;
	}

	public static function claim_existing() {
		if ( ! Guest_Key_Access::administrator() || Guest_Key_Browser::is_guest_session() ) { return new WP_Error( 'guest_key_forbidden', __( 'Use a normal administrator session to manage licensing.', 'guest-key' ) ); }
		return self::serialized( static function () { return self::do_claim_existing(); } );
	}

	private static function do_claim_existing() {
		$saved = get_option( self::OPTION, array() );
		if ( ! $saved || ( $saved['site'] ?? '' ) !== self::site() || self::record() ) { return new WP_Error( 'guest_key_license_claim', __( 'No unassigned license is available for this account.', 'guest-key' ) ); }
		$moved = self::move_legacy( $saved, get_current_user_id() );
		return is_wp_error( $moved ) ? $moved : self::check( true );
	}

	public static function share( $users ) {
		if ( ! Guest_Key_Access::administrator() || Guest_Key_Browser::is_guest_session() ) { return new WP_Error( 'guest_key_forbidden', __( 'Use a normal administrator session to manage licensing.', 'guest-key' ) ); }
		return self::serialized( static function () use ( $users ) { return self::do_share( $users ); } );
	}

	private static function do_share( $users ) {
		$saved = self::record();
		if ( 'paid' !== ( $saved['kind'] ?? '' ) || ( $saved['site'] ?? '' ) !== self::site() || ! is_array( $users ) ) { return new WP_Error( 'guest_key_license_share', __( 'Only your own paid license can be shared.', 'guest-key' ) ); }
		$ids = array();
		foreach ( $users as $id ) {
			if ( ! is_int( $id ) || $id < 1 || ! self::admin_role( $id ) ) { return new WP_Error( 'guest_key_license_share', __( 'Select only administrators on this site.', 'guest-key' ) ); }
			if ( $id !== get_current_user_id() ) { $ids[] = $id; }
		}
		$saved['shared_users'] = array_values( array_unique( $ids ) );
		self::save( $saved );
		return true;
	}

	private static function service( $path, $body, $token = '' ) {
		$base = defined( 'GUEST_KEY_SERVICE_URL' ) ? untrailingslashit( GUEST_KEY_SERVICE_URL ) : 'https://api.guest-key.com';
		$local = in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) && 'http://127.0.0.1:8791' === $base;
		if ( ! $local && ( 'https' !== wp_parse_url( $base, PHP_URL_SCHEME ) || wp_parse_url( $base, PHP_URL_USER ) || wp_parse_url( $base, PHP_URL_QUERY ) ) ) {
			return new WP_Error( 'guest_key_service', __( 'The trial service is not configured correctly.', 'guest-key' ) );
		}
		$args = array( 'timeout' => 20, 'redirection' => 0, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) );
		if ( $token ) { $args['headers']['Authorization'] = 'Bearer ' . $token; }
		$response = $local ? wp_remote_post( $base . $path, $args ) : wp_safe_remote_post( $base . $path, $args );
		if ( is_wp_error( $response ) ) { return new WP_Error( 'guest_key_service', __( 'Could not reach Guest Key. Please retry.', 'guest-key' ) ); }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) ) {
			$code = is_array( $data ) ? ( $data['error'] ?? '' ) : '';
			$messages = array(
				'trial_already_used' => __( 'This email or site has already used its free trial. Enter a license key to continue.', 'guest-key' ),
				'invalid_verification_code' => __( 'That code does not match. Check your email and try again.', 'guest-key' ),
				'verification_required' => __( 'Enter the six-digit code from your email.', 'guest-key' ),
				'verification_locked' => __( 'Too many incorrect codes. Request a new code after a minute.', 'guest-key' ),
				'challenge_expired' => __( 'Your verification code has expired. Request a new code.', 'guest-key' ),
				'verification_expired' => __( 'Your verification code has expired. Request a new code.', 'guest-key' ),
				'email_rate_limited' => __( 'Please wait before requesting another code. Try again in a few minutes.', 'guest-key' ),
				'site_verification_failed' => __( 'Guest Key could not verify this site. Check that its public REST API is reachable, then retry.', 'guest-key' ),
			);
			return new WP_Error( 'guest_key_' . ( $code ?: 'service' ), $messages[ $code ] ?? __( 'Guest Key could not complete that request. Please retry shortly.', 'guest-key' ), array( 'http_status' => wp_remote_retrieve_response_code( $response ) ) );
		}
		return $data;
	}

	private static function lemon( $action, $body ) {
		$response = wp_remote_post( 'https://api.lemonsqueezy.com/v1/licenses/' . $action, array( 'timeout' => 15, 'redirection' => 0, 'headers' => array( 'Accept' => 'application/json' ), 'body' => $body ) );
		if ( is_wp_error( $response ) ) { return new WP_Error( 'guest_key_license_service', __( 'Could not confirm the license with Lemon Squeezy. Please retry shortly.', 'guest-key' ) ); }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || wp_remote_retrieve_response_code( $response ) >= 500 || 429 === wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'guest_key_license_service', __( 'Lemon Squeezy is temporarily unavailable. Please retry shortly.', 'guest-key' ) );
		}
		return $data;
	}

	private static function our_product( $data ) {
		$variant = (int) ( $data['meta']['variant_id'] ?? 0 );
		$license = $data['license_key'] ?? array();
		return 1457777 === (int) ( $data['meta']['product_id'] ?? 0 ) && (
			in_array( $variant, array( 2274395, 2274401 ), true ) ||
			// The individually overridden owner key is perpetual and has unlimited activations.
			( 2274403 === $variant && array_key_exists( 'expires_at', $license ) && null === $license['expires_at']
				&& array_key_exists( 'activation_limit', $license ) && in_array( $license['activation_limit'], array( 0, null ), true ) )
		);
	}

	private static function expiration( $license ) {
		if ( ! is_array( $license ) || ! array_key_exists( 'expires_at', $license ) ) { return false; }
		if ( null === $license['expires_at'] ) { return 0; }
		return is_string( $license['expires_at'] ) ? strtotime( $license['expires_at'] ) : false;
	}

	public static function check( $force = false, $owner_id = null ) {
		return self::serialized( static function () use ( $force, $owner_id ) { return self::do_check( $force, $owner_id ); } );
	}

	private static function do_check( $force = false, $owner_id = null ) {
		$owner_id = null === $owner_id ? get_current_user_id() : (int) $owner_id;
		if ( ! $owner_id || ! self::admin_role( $owner_id ) ) { return false; }
		$saved = self::record( $owner_id );
		if ( ( $saved['site'] ?? '' ) !== self::site() ) { return false; }
		if ( 'trial' === ( $saved['kind'] ?? '' ) ) {
			// Purchase checks have their own short cache; a trial's four-hour cache must not delay an upgrade.
			$claimed = self::claim( $force, $owner_id );
			if ( true === $claimed ) { return true; }
			$saved = self::record( $owner_id );
		}
		if ( ! $force && ( $saved['checked'] ?? 0 ) > time() - 4 * HOUR_IN_SECONDS
			&& ( self::own_until( self::record( $owner_id ) ) || 'paid' !== ( $saved['kind'] ?? '' ) || ( 'expired' === ( $saved['status'] ?? '' ) && ( $saved['checked'] ?? 0 ) > time() - MINUTE_IN_SECONDS ) ) ) { return (bool) self::own_until( self::record( $owner_id ) ); }
		if ( 'paid' === ( $saved['kind'] ?? '' ) ) {
			$data = self::lemon( 'validate', array( 'license_key' => $saved['key'], 'instance_id' => $saved['instance'] ) );
			if ( is_wp_error( $data ) ) { return $data; }
			$active = true === ( $data['valid'] ?? false ) && self::our_product( $data ) && ( $data['instance']['id'] ?? '' ) === $saved['instance'];
			$expires = self::expiration( $data['license_key'] ?? null );
			if ( false === $expires ) { $active = false; $expires = $saved['expires'] ?? 0; }
		} else {
			$data = self::service( '/v1/trials/status', array( 'site_url' => self::site() ), $saved['token'] ?? '' );
			if ( is_wp_error( $data ) ) {
				if ( 401 !== ( $data->get_error_data()['http_status'] ?? 0 ) ) { return $data; }
				$active = false; $expires = $saved['expires'] ?? 0;
			} else {
				$active = 'active' === ( $data['status'] ?? '' );
				$expires = is_string( $data['expires_at'] ?? null ) ? strtotime( $data['expires_at'] ) : false;
				if ( ! $expires ) { return new WP_Error( 'guest_key_service', __( 'Could not confirm your trial expiration. Please retry.', 'guest-key' ) ); }
			}
		}
		$saved['status'] = $active ? 'active' : 'expired';
		$saved['expires'] = $expires; $saved['checked'] = time();
		self::save( $saved, $owner_id );
		return (bool) self::own_until( self::record( $owner_id ) );
	}

	private static function claim( $force, $owner_id = null ) {
		$saved = self::record( $owner_id );
		if ( ! $force && ( $saved['purchase_checked'] ?? 0 ) > time() - MINUTE_IN_SECONDS ) { return false; }
		$saved['purchase_checked'] = time(); self::save( $saved, $owner_id );
		$data = self::service( '/v1/licenses/claim', array( 'site_url' => self::site() ), $saved['token'] ?? '' );
		// An unavailable connector never removes a working trial or blocks manual license entry.
		if ( is_wp_error( $data ) || ! is_array( $data['licenses'] ?? null ) || count( $data['licenses'] ) > 10 ) { return false; }
		return isset( $data['licenses'][0] ) && true === self::activate( $data['licenses'][0], $owner_id );
	}

	public static function require_access( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		$result = false;
		foreach ( self::sources( $user_id ) as $owner_id => $source ) { $result = self::check( false, $owner_id ); }
		if ( self::active_until( $user_id ) ) { return true; }
		return new WP_Error( 'guest_key_license_required', is_wp_error( $result ) ? $result->get_error_message() : __( 'Start your free trial, enter a license key, or ask a licensed administrator to share access.', 'guest-key' ) );
	}

	public static function email( $email, $fresh = false ) {
		if ( ! Guest_Key_Access::administrator() || Guest_Key_Browser::is_guest_session() ) { return new WP_Error( 'guest_key_forbidden', __( 'Use a normal administrator session to manage licensing.', 'guest-key' ) ); }
		return self::serialized( static function () use ( $email, $fresh ) { return self::do_email( $email, $fresh ); } );
	}

	private static function do_email( $email, $fresh = false ) {
		if ( 'paid' === ( self::record()['kind'] ?? '' ) ) { return new WP_Error( 'guest_key_license_present', __( 'Manage or remove the saved license before starting a trial.', 'guest-key' ) ); }
		if ( ! is_string( $email ) ) { return new WP_Error( 'guest_key_email', __( 'Enter a valid email address.', 'guest-key' ) ); }
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) { return new WP_Error( 'guest_key_email', __( 'Enter a valid email address.', 'guest-key' ) ); }
		$pending = get_option( self::pending_name(), array() );
		$previous = $pending;
		if ( ( $fresh && ! empty( $pending['sent'] ) ) || ( $pending['email'] ?? '' ) !== $email || ( $pending['site'] ?? '' ) !== self::site() || ( $pending['until'] ?? 0 ) <= time() ) {
			$data = self::service( '/v1/trials/challenge', array( 'email' => $email, 'site_url' => self::site(), 'trial_days' => 3 ) );
			if ( is_wp_error( $data ) ) { return $data; }
			if ( ! preg_match( '/^[a-f0-9]{64}$/', $data['proof_id'] ?? '' ) || ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $data['proof'] ?? '' ) || empty( $data['challenge'] ) ) {
				return new WP_Error( 'guest_key_service', __( 'Guest Key returned an invalid verification request.', 'guest-key' ) );
			}
			$pending = array( 'email' => $email, 'site' => self::site(), 'challenge' => $data['challenge'], 'until' => time() + 10 * MINUTE_IN_SECONDS );
			set_transient( 'guest_key_proof_' . $data['proof_id'], $data['proof'], 10 * MINUTE_IN_SECONDS );
			update_option( self::pending_name(), $pending, false );
		}
		$result = self::service( '/v1/trials/email', array( 'email' => $email, 'challenge' => $pending['challenge'] ) );
		if ( is_wp_error( $result ) ) {
			if ( 429 === ( $result->get_error_data()['http_status'] ?? 0 ) && ! empty( $previous['sent'] ) ) { update_option( self::pending_name(), $previous, false ); }
			return $result;
		}
		if ( true !== ( $result['email_sent'] ?? false ) ) { return new WP_Error( 'guest_key_email_delivery', __( 'Could not confirm that the verification email was sent. Please retry.', 'guest-key' ) ); }
		$pending['sent'] = true; update_option( self::pending_name(), $pending, false );
		return true;
	}

	public static function verify( $code ) {
		if ( ! Guest_Key_Access::administrator() || Guest_Key_Browser::is_guest_session() ) { return new WP_Error( 'guest_key_forbidden', __( 'Use a normal administrator session to manage licensing.', 'guest-key' ) ); }
		return self::serialized( static function () use ( $code ) { return self::do_verify( $code ); } );
	}

	private static function do_verify( $code ) {
		if ( 'paid' === ( self::record()['kind'] ?? '' ) ) { return new WP_Error( 'guest_key_license_present', __( 'Manage or remove the saved license before starting a trial.', 'guest-key' ) ); }
		if ( ! is_string( $code ) || ! preg_match( '/^[0-9]{6}$/', trim( $code ) ) ) { return new WP_Error( 'guest_key_verification', __( 'Enter the six-digit code from your email.', 'guest-key' ) ); }
		$pending = get_option( self::pending_name(), array() );
		if ( ( $pending['site'] ?? '' ) !== self::site() || ( $pending['until'] ?? 0 ) <= time() ) { return new WP_Error( 'guest_key_verification', __( 'Request a new email verification code.', 'guest-key' ) ); }
		$data = self::service( '/v1/trials/start', array( 'challenge' => $pending['challenge'], 'verification_code' => trim( $code ) ) );
		if ( is_wp_error( $data ) ) { return $data; }
		$expires = is_string( $data['expires_at'] ?? null ) ? strtotime( $data['expires_at'] ) : false;
		if ( ! is_string( $data['trial_token'] ?? null ) || ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $data['trial_token'] ) || 'active' !== ( $data['status'] ?? '' ) || ! $expires || $expires <= time() || $expires > time() + 3 * DAY_IN_SECONDS + 60 ) { return new WP_Error( 'guest_key_service', __( 'Guest Key returned an invalid trial.', 'guest-key' ) ); }
		self::save( array( 'kind' => 'trial', 'email' => $pending['email'], 'token' => $data['trial_token'], 'site' => self::site(), 'status' => 'active', 'expires' => $expires, 'checked' => time() ) );
		delete_option( self::pending_name() );
		return true;
	}

	public static function activate( $key, $owner_id = null ) {
		if ( null === $owner_id && ( ! Guest_Key_Access::administrator() || Guest_Key_Browser::is_guest_session() ) ) { return new WP_Error( 'guest_key_forbidden', __( 'Use a normal administrator session to manage licensing.', 'guest-key' ) ); }
		return self::serialized( static function () use ( $key, $owner_id ) { return self::do_activate( $key, $owner_id ); } );
	}

	private static function do_activate( $key, $owner_id = null ) {
		if ( ! is_string( $key ) ) { return new WP_Error( 'guest_key_license_invalid', __( 'Enter a valid license key.', 'guest-key' ) ); }
		$key = trim( $key );
		if ( ! $key || strlen( $key ) > 200 || preg_match( '/[\x00-\x20]/', $key ) ) { return new WP_Error( 'guest_key_license_invalid', __( 'Enter a valid license key.', 'guest-key' ) ); }
		$saved = self::record( $owner_id );
		if ( 'paid' === ( $saved['kind'] ?? '' ) ) {
			if ( ( $saved['key'] ?? '' ) !== $key || ( $saved['site'] ?? '' ) !== self::site() ) { return new WP_Error( 'guest_key_license_replace', __( 'Remove the current license before activating a different key or site.', 'guest-key' ) ); }
			$result = self::check( true, $owner_id );
			return is_wp_error( $result ) ? $result : ( $result ? true : new WP_Error( 'guest_key_license_invalid', __( 'This license is expired or inactive. Renew it to continue.', 'guest-key' ) ) );
		}

		foreach ( self::owners() as $other_id ) {
			if ( $other_id === ( null === $owner_id ? get_current_user_id() : (int) $owner_id ) ) { continue; }
			$other = self::record( $other_id );
			if ( ( $other['key'] ?? '' ) === $key && ( $other['site'] ?? '' ) === self::site() ) {
				return new WP_Error( 'guest_key_license_owned', __( 'Another administrator already manages this key on this site. Ask them to share access with your account.', 'guest-key' ) );
			}
		}

		$data = self::lemon( 'validate', array( 'license_key' => $key ) );
		if ( is_wp_error( $data ) ) { return $data; }
		if ( true !== ( $data['valid'] ?? false ) || ! self::our_product( $data ) ) { return new WP_Error( 'guest_key_license_invalid', __( 'This is not an active Guest Key license.', 'guest-key' ) ); }
		$expires = self::expiration( $data['license_key'] ?? null );
		if ( false === $expires || ( $expires && $expires <= time() ) ) { return new WP_Error( 'guest_key_license_invalid', __( 'Could not confirm an active license expiration.', 'guest-key' ) ); }
		$data = self::lemon( 'activate', array( 'license_key' => $key, 'instance_name' => self::site() ) );
		if ( is_wp_error( $data ) ) { return $data; }
		if ( true !== ( $data['activated'] ?? false ) || ! is_string( $data['instance']['id'] ?? null ) || '' === $data['instance']['id'] || ! self::our_product( $data ) ) { return new WP_Error( 'guest_key_license_invalid', __( 'Could not activate this license. Check its site limit in Lemon Squeezy.', 'guest-key' ) ); }
		$expires = self::expiration( $data['license_key'] ?? null );
		if ( false === $expires || ( $expires && $expires <= time() ) ) { return new WP_Error( 'guest_key_license_invalid', __( 'Could not confirm an active license expiration.', 'guest-key' ) ); }
		self::save( array( 'kind' => 'paid', 'key' => $key, 'instance' => $data['instance']['id'], 'site' => self::site(), 'status' => 'active', 'expires' => $expires, 'checked' => time(), 'shared_users' => array(), 'email' => $data['meta']['user_email'] ?? '' ), $owner_id );
		delete_option( self::pending_name( $owner_id ) );
		return true;
	}

	public static function remove() {
		if ( ! Guest_Key_Access::administrator() || Guest_Key_Browser::is_guest_session() ) { return new WP_Error( 'guest_key_forbidden', __( 'Use a normal administrator session to manage licensing.', 'guest-key' ) ); }
		return self::serialized( static function () { return self::do_remove(); } );
	}

	private static function do_remove() {
		$saved = self::record();
		if ( 'paid' === ( $saved['kind'] ?? '' ) ) {
			$data = self::lemon( 'deactivate', array( 'license_key' => $saved['key'], 'instance_id' => $saved['instance'] ) );
			if ( is_wp_error( $data ) ) { return $data; }
			if ( true !== ( $data['deactivated'] ?? false ) ) {
				$valid = self::lemon( 'validate', array( 'license_key' => $saved['key'], 'instance_id' => $saved['instance'] ) );
				if ( is_wp_error( $valid ) || ! array_key_exists( 'valid', $valid ) || false !== $valid['valid'] ) { return new WP_Error( 'guest_key_license_remove', __( 'Lemon Squeezy could not release this activation. Please retry.', 'guest-key' ) ); }
			}
			delete_option( self::option_name() );
		}
		return true;
	}

	public static function routes() {
		register_rest_route( 'guest-key/v1', '/trial-proof', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => static function ( $request ) {
			$id = $request->get_param( 'challenge' );
			$proof = is_string( $id ) && preg_match( '/^[a-f0-9]{64}$/', $id ) ? get_transient( 'guest_key_proof_' . $id ) : false;
			if ( ! $proof ) { return new WP_Error( 'guest_key_proof_missing', 'Not found.', array( 'status' => 404 ) ); }
			$response = new WP_REST_Response( array( 'proof' => $proof ) );
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		} ) );
	}

	public static function ajax( $action ) {
		Guest_Key_Access::browser_request();
		$result = self::serialized( static function () use ( $action ) {
			if ( 'status' === $action ) {
				foreach ( self::sources( get_current_user_id() ) as $owner_id => $source ) { self::check( true, $owner_id ); }
				return (bool) self::active_until();
			}
			if ( 'share' === $action ) {
				$users = wp_unslash( $_POST['users'] ?? '' );
				return self::share( is_string( $users ) ? json_decode( $users, true ) : null );
			}
			if ( 'claim_existing' === $action ) { return self::claim_existing(); }
			if ( 'email' === $action ) { return self::email( wp_unslash( $_POST['email'] ?? '' ), '1' === ( $_POST['fresh'] ?? '' ) ); }
			if ( 'verify' === $action ) { return self::verify( wp_unslash( $_POST['code'] ?? '' ) ); }
			if ( 'activate' === $action ) { return self::activate( wp_unslash( $_POST['key'] ?? '' ) ); }
			return self::remove();
		} );
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message(), 'licensing' => self::view() ), 400 ); }
		wp_send_json_success( array( 'licensing' => self::view() ) );
	}
}
