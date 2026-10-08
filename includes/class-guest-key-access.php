<?php
defined( 'ABSPATH' ) || exit;

final class Guest_Key_Access {

	const APP_ID = '8bc1d546-6f74-4f3a-91a3-6ed7ed9fc551';
	const META = '_guest_key_access';
	const TTL = 6 * HOUR_IN_SECONDS;
	const ROUTE = '/guest-key/v1/mcp';
	const CRON = 'guest_key_expire';

	public static function init() {
		add_action( 'wp_authenticate_application_password_errors', array( __CLASS__, 'authenticate' ), 10, 3 );
		add_action( self::CRON, array( __CLASS__, 'expire' ), 10, 2 );
		add_action( 'wp_ajax_guest_key_create', array( __CLASS__, 'ajax_create' ) );
		add_action( 'wp_ajax_guest_key_revoke', array( __CLASS__, 'ajax_revoke' ) );
	}

	public static function administrator( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( ! $user_id ) {
			return false;
		}
		return is_multisite() ? is_super_admin( $user_id ) : user_can( $user_id, 'manage_options' );
	}

	public static function endpoint() {
		return rest_url( ltrim( self::ROUTE, '/' ) );
	}

	public static function grant( $user_id ) {
		$value = get_user_meta( $user_id, self::META, true );
		return is_array( $value ) ? $value : array();
	}

	public static function grants() {
		$grants = array();
		foreach ( get_users( array( 'blog_id' => 0, 'meta_key' => self::META, 'fields' => 'ID' ) ) as $user_id ) {
			$grant = self::grant( $user_id );
			if ( isset( $grant['uuid'], $grant['expires'], $grant['blog_id'], $grant['network_id'] ) && (int) $grant['network_id'] === get_current_network_id() ) {
				$grants[ $user_id ] = $grant;
			}
		}
		return $grants;
	}

	/** Serialize credential and adapter changes across users and subsites. */
	public static function locked( $user_id, $callback ) {
		$switched = is_multisite() && get_current_blog_id() !== get_main_site_id();
		if ( $switched ) {
			switch_to_blog( get_main_site_id() );
		}
		$key = 'guest_key_access_lock';
		$token = wp_generate_uuid4() . '|' . ( time() + 10 * MINUTE_IN_SECONDS );
		$previous = get_option( $key );
		if ( is_string( $previous ) && (int) substr( $previous, strrpos( $previous, '|' ) + 1 ) < time() ) {
			self::release_lock( $key, $previous );
		}
		$acquired = add_option( $key, $token, '', false );
		if ( $switched ) {
			restore_current_blog();
		}
		if ( ! $acquired ) {
			return new WP_Error( 'guest_key_busy', __( 'Another access request is running for your account. Retry in a moment.', 'guest-key' ) );
		}
		try {
			return $callback();
		} finally {
			if ( $switched ) {
				switch_to_blog( get_main_site_id() );
			}
			self::release_lock( $key, $token );
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	private static function release_lock( $key, $token ) {
		global $wpdb;
		// Compare-and-delete prevents a crashed or expired holder from deleting a new lock.
		$wpdb->delete( $wpdb->options, array( 'option_name' => $key, 'option_value' => $token ) );
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	public static function create( $user_id ) {
		if ( Guest_Key_Browser::is_guest_session() || ! self::administrator( $user_id ) || ! user_can( $user_id, 'create_app_password', $user_id ) ) {
			return new WP_Error( 'guest_key_forbidden', __( 'Only an administrator may create Guest Key access.', 'guest-key' ) );
		}
		if ( ! wp_is_application_passwords_available_for_user( get_userdata( $user_id ) ) ) {
			return new WP_Error( 'guest_key_passwords_disabled', __( 'Application passwords are unavailable. Use HTTPS and check your site’s application-password policy.', 'guest-key' ) );
		}
		return self::locked( $user_id, static function () use ( $user_id ) {
			$ready = Guest_Key_Dependency::ensure();
			if ( is_wp_error( $ready ) ) {
				return $ready;
			}
			// Revoke all passwords with our app identifier, including any orphan from an interrupted request.
			$old = self::grant( $user_id );
			$revoked = self::remove_passwords( $user_id );
			if ( is_wp_error( $revoked ) ) {
				return $revoked;
			}
			if ( $old ) {
				self::clear_schedule( $user_id, $old );
			}
			delete_user_meta( $user_id, self::META );
			$created = WP_Application_Passwords::create_new_application_password( $user_id, array(
				'app_id' => self::APP_ID,
				'name' => 'Guest Key — temporary MCP access',
			) );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			list( $password, $item ) = $created;
			$grant = array( 'uuid' => $item['uuid'], 'expires' => $item['created'] + self::TTL, 'blog_id' => get_current_blog_id(), 'network_id' => get_current_network_id() );
			if ( ! update_user_meta( $user_id, self::META, $grant ) ) {
				WP_Application_Passwords::delete_application_password( $user_id, $item['uuid'] );
				return new WP_Error( 'guest_key_storage', __( 'Could not save temporary access. No credential was issued.', 'guest-key' ) );
			}
			$scheduled = wp_schedule_single_event( $grant['expires'], self::CRON, array( $user_id, $grant['uuid'] ), true );
			if ( is_wp_error( $scheduled ) || ! $scheduled ) {
				self::remove_passwords( $user_id );
				delete_user_meta( $user_id, self::META );
				return new WP_Error( 'guest_key_schedule', __( 'WordPress could not schedule access cleanup. Please retry.', 'guest-key' ) );
			}
			$user = get_userdata( $user_id );
			if ( $old && (int) $old['blog_id'] !== get_current_blog_id() ) {
				switch_to_blog( $old['blog_id'] );
				Guest_Key_Dependency::maybe_disable();
				restore_current_blog();
			}
			$expires = gmdate( 'c', $grant['expires'] );
			$user_agent = '<client>/<version> (user-directed AI agent; agent=<agent>; model=<model>) GuestKey/' . GUEST_KEY_VERSION . ' WordPress/' . get_bloginfo( 'version' );
			return array(
				'endpoint' => self::endpoint(),
				'browser_login' => Guest_Key_Browser::url(),
				'username' => $user->user_login,
				'password' => $password,
				'expires' => $expires,
				'expires_label' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T', $grant['expires'] ),
				'bundle' => "Connect to this WordPress site using MCP (Streamable HTTP).\nEndpoint: " . self::endpoint() . "\nAuthentication: HTTP Basic\nUsername: " . $user->user_login . "\nApplication password: " . $password . "\nExpires: " . $expires . "\nUser-Agent: " . $user_agent . "\nFor programmatic HTTP/MCP requests, set this actual User-Agent header. Replace the placeholders with your client name/version, agent name, and model; omit unknown fields rather than inventing them. Retain the phrase user-directed AI agent and reuse the same identifier for retries and diagnostics. Keep interactive browser user agents unchanged. If your MCP client cannot set headers, report that limitation instead of claiming the header was sent.\nBrowser sign-in: " . Guest_Key_Browser::url() . "\nFor browser skills, open that sign-in page and enter the same username and application password in its form. The resulting browser session works on the site backend and frontend and ends when this key expires, is revoked, or is replaced. Do not put the password in a URL. Direct file editing and issuing replacement Guest Keys or application passwords are disabled in these sessions.\nThis credential works on the Guest Key MCP endpoint and its browser sign-in page. Use this administrator’s existing permissions.",
			);
		} );
	}

	private static function remove_passwords( $user_id ) {
		foreach ( WP_Application_Passwords::get_user_application_passwords( $user_id ) as $item ) {
			if ( self::APP_ID === ( $item['app_id'] ?? '' ) ) {
				$result = WP_Application_Passwords::delete_application_password( $user_id, $item['uuid'] );
				if ( is_wp_error( $result ) || ! $result ) {
					return new WP_Error( 'guest_key_revoke_failed', __( 'Could not revoke the previous Guest Key password. Please retry.', 'guest-key' ) );
				}
			}
		}
		return true;
	}

	private static function clear_schedule( $user_id, $grant ) {
		$switched = (int) $grant['blog_id'] !== get_current_blog_id();
		if ( $switched ) {
			switch_to_blog( $grant['blog_id'] );
		}
		wp_clear_scheduled_hook( self::CRON, array( (int) $user_id, $grant['uuid'] ) );
		if ( $switched ) {
			restore_current_blog();
		}
	}

	public static function revoke( $user_id, $uuid = null ) {
		return self::locked( $user_id, static function () use ( $user_id, $uuid ) {
			$grant = self::grant( $user_id );
			// An old cron event must never delete a replacement credential.
			if ( $uuid && ( ! $grant || $grant['uuid'] !== $uuid ) ) {
				return true;
			}
			$result = self::remove_passwords( $user_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( $grant ) {
				self::clear_schedule( $user_id, $grant );
			}
			delete_user_meta( $user_id, self::META );
			if ( $grant && (int) $grant['blog_id'] !== get_current_blog_id() ) {
				switch_to_blog( $grant['blog_id'] );
				Guest_Key_Dependency::maybe_disable();
				restore_current_blog();
			}
			Guest_Key_Dependency::maybe_disable();
			return true;
		} );
	}

	public static function expire( $user_id, $uuid ) {
		$grant = self::grant( $user_id );
		if ( ! $grant || $grant['uuid'] !== $uuid || $grant['expires'] > time() ) {
			return;
		}
		$result = self::revoke( $user_id, $uuid );
		if ( is_wp_error( $result ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON, array( $user_id, $uuid ) );
			return;
		}
	}

	/** Match both pretty REST URLs (including subdirectory installs) and ?rest_route=. */
	private static function is_endpoint_request() {
		if ( isset( $_GET['rest_route'] ) ) {
			return self::ROUTE === untrailingslashit( '/' . ltrim( sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ), '/' ) );
		}
		$path = rawurldecode( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
		$expected = rawurldecode( (string) wp_parse_url( self::endpoint(), PHP_URL_PATH ) );
		return untrailingslashit( $path ) === untrailingslashit( $expected );
	}

	public static function authenticate( $error, $user, $item ) {
		if ( self::APP_ID !== ( $item['app_id'] ?? '' ) ) {
			return;
		}
		$grant = self::grant( $user->ID );
		if ( ! $grant || $grant['uuid'] !== $item['uuid'] || $grant['expires'] <= time() || $item['created'] + self::TTL <= time() ) {
			$error->add( 'guest_key_expired', __( 'Guest Key access has expired or been revoked.', 'guest-key' ) );
			return;
		}
		// The current user is not established yet. Capability filters (including Yoast's)
		// may call wp_get_current_user(), recursively starting authentication again.
		// Check administrator permissions in can_connect(), after authentication finishes.
		if ( (int) $grant['blog_id'] !== get_current_blog_id() || ( ! self::is_endpoint_request() && ! Guest_Key_Browser::is_login_request() ) ) {
			$error->add( 'guest_key_scope', __( 'This credential is restricted to this site’s Guest Key MCP endpoint and browser sign-in page.', 'guest-key' ) );
		}
	}

	public static function can_connect() {
		// Authorization belongs here, where WordPress has established the current user.
		$grant = self::grant( get_current_user_id() );
		return self::administrator() && $grant && $grant['expires'] > time()
			&& (int) $grant['blog_id'] === get_current_blog_id()
			&& $grant['uuid'] === rest_get_authenticated_app_password();
	}

	private static function browser_request() {
		check_ajax_referer( 'guest_key_access', 'nonce' );
		if ( Guest_Key_Browser::is_guest_session() || ! self::administrator() || ! wp_get_session_token() || rest_get_authenticated_app_password() ) {
			wp_send_json_error( array( 'message' => __( 'Use an administrator’s logged-in browser session to manage access.', 'guest-key' ) ), 403 );
		}
		nocache_headers();
	}

	public static function ajax_create() {
		self::browser_request();
		$result = self::create( get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( $result );
	}

	public static function ajax_revoke() {
		self::browser_request();
		$result = self::revoke( get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		wp_send_json_success( array( 'adapter_active' => is_plugin_active( Guest_Key_Dependency::PLUGIN ) ) );
	}

	public static function deactivate( $network_wide = false ) {
		foreach ( self::grants() as $user_id => $grant ) {
			if ( $network_wide || (int) $grant['blog_id'] === get_current_blog_id() ) {
				$result = self::revoke( $user_id, $grant['uuid'] );
				if ( is_wp_error( $result ) ) {
					wp_die( esc_html__( 'Guest Key could not revoke temporary access. Retry deactivation after the current access request finishes.', 'guest-key' ) );
				}
			}
		}
		Guest_Key_Dependency::maybe_disable();
	}
}
