<?php
defined( 'ABSPATH' ) || exit;

final class Guest_Key_Browser {

	const ACTION = 'guest-key';
	const SESSION = 'guest_key';
	private static $validated_tokens = array();

	public static function init() {
		add_action( 'login_form_' . self::ACTION, array( __CLASS__, 'login' ) );
		add_action( 'auth_cookie_valid', array( __CLASS__, 'capture_cookie' ), 10, 2 );
		add_filter( 'determine_current_user', array( __CLASS__, 'validate_session' ), 30 );
		add_action( 'set_current_user', array( __CLASS__, 'authorize_session' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'restrict_capabilities' ), 99, 4 );
		add_filter( 'attach_session_information', array( __CLASS__, 'inherit_session' ), 90, 2 );
		add_filter( 'auth_cookie_expiration', array( __CLASS__, 'limit_expiration' ), 99, 2 );
	}

	public static function url() {
		return add_query_arg( 'action', self::ACTION, site_url( 'wp-login.php', 'login' ) );
	}

	public static function is_login_request() {
		global $pagenow;
		return 'wp-login.php' === $pagenow && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' )
			&& self::ACTION === ( $_GET['action'] ?? '' );
	}

	public static function capture_cookie( $cookie, $user ) {
		self::$validated_tokens[ $user->ID ] = $cookie['token'];
	}

	private static function cookie_token( $user_id ) {
		// Follow the cookie WordPress actually validated. An invalid second cookie
		// must not hide a guest binding when core falls back to the logged-in cookie.
		if ( isset( self::$validated_tokens[ $user_id ] ) ) { return self::$validated_tokens[ $user_id ]; }
		$cookie = wp_parse_auth_cookie();
		return $cookie['token'] ?? wp_get_session_token();
	}

	public static function session( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		$token = self::cookie_token( $user_id );
		return $user_id && $token ? WP_Session_Tokens::get_instance( $user_id )->get( $token ) : null;
	}

	public static function is_guest_session() {
		$session = self::session();
		return ! empty( $session[ self::SESSION ] );
	}

	/** This runs during user resolution: never invoke capability filters here. */
	public static function validate_session( $user_id ) {
		if ( ! $user_id ) { return $user_id; }
		$session = self::session( $user_id );
		if ( empty( $session[ self::SESSION ] ) ) { return $user_id; }
		$binding = $session[ self::SESSION ];
		$grant = Guest_Key_Access::grant( $user_id );
		$item = WP_Application_Passwords::get_user_application_password( $user_id, $binding['uuid'] ?? '' );
		if ( ! $grant || ! $item || Guest_Key_Access::APP_ID !== ( $item['app_id'] ?? '' )
			|| ( $binding['uuid'] ?? '' ) !== $grant['uuid'] || $grant['expires'] <= time()
			|| $item['created'] + Guest_Key_Access::TTL <= time()
			|| (int) $grant['blog_id'] !== get_current_blog_id()
			|| (int) $grant['network_id'] !== get_current_network_id()
			|| (int) ( $binding['blog_id'] ?? 0 ) !== get_current_blog_id()
			|| (int) ( $binding['network_id'] ?? 0 ) !== get_current_network_id() ) {
			WP_Session_Tokens::get_instance( $user_id )->destroy( self::cookie_token( $user_id ) );
			return 0;
		}
		return $user_id;
	}

	/** The current user now exists, so Yoast and other capability filters are safe. */
	public static function authorize_session() {
		$user_id = get_current_user_id();
		if ( $user_id && self::is_guest_session() && ! Guest_Key_Access::administrator( $user_id ) ) {
			WP_Session_Tokens::get_instance( $user_id )->destroy( self::cookie_token( $user_id ) );
			wp_set_current_user( 0 );
		}
	}

	public static function restrict_capabilities( $caps, $cap, $user_id, $args ) {
		// Use the provided user ID; this filter must not resolve the current user.
		if ( ! in_array( $cap, array( 'edit_files', 'edit_plugins', 'edit_themes', 'create_app_password' ), true ) ) { return $caps; }
		if ( Guest_Key_Access::is_guest_request( $user_id ) ) { return array( 'do_not_allow' ); }
		$session = self::session( $user_id );
		if ( ! empty( $session[ self::SESSION ] ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	public static function inherit_session( $information, $user_id ) {
		$session = self::session( $user_id );
		if ( ! empty( $session[ self::SESSION ] ) ) {
			$information[ self::SESSION ] = $session[ self::SESSION ];
		}
		return $information;
	}

	public static function limit_expiration( $length, $user_id ) {
		$session = self::session( $user_id );
		if ( ! empty( $session[ self::SESSION ] ) ) {
			$grant = Guest_Key_Access::grant( $user_id );
			return min( $length, max( 1, ( $grant['expires'] ?? 0 ) - time() ) );
		}
		return $length;
	}

	/** Verify only the dedicated Guest Key credential, then establish authorization. */
	public static function authenticate( $username, $password ) {
		if ( ! self::is_login_request() || ! is_ssl() ) {
			return new WP_Error( 'guest_key_browser_scope', __( 'Guest Key browser sign-in requires its HTTPS sign-in page.', 'guest-key' ) );
		}
		$original_user = get_current_user_id();
		// Explicit native authentication, without enabling application passwords on normal logins.
		$api = static function () { return true; };
		add_filter( 'application_password_is_api_request', $api );
		try { $user = wp_authenticate_application_password( null, $username, $password ); }
		finally { remove_filter( 'application_password_is_api_request', $api ); }
		if ( ! $user instanceof WP_User ) { return new WP_Error( 'guest_key_browser_invalid', __( 'This Guest Key is invalid, expired, or revoked.', 'guest-key' ) ); }
		wp_set_current_user( $user->ID );
		$grant = Guest_Key_Access::grant( $user->ID );
		if ( ! Guest_Key_Access::can_connect() || ! $grant || (int) $grant['network_id'] !== get_current_network_id() ) {
			wp_set_current_user( $original_user );
			return new WP_Error( 'guest_key_browser_invalid', __( 'Use an active Guest Key issued by an administrator on this site.', 'guest-key' ) );
		}
		return $user;
	}

	public static function create_session( $user_id ) {
		$grant = Guest_Key_Access::grant( $user_id );
		if ( get_current_user_id() !== (int) $user_id || ! Guest_Key_Access::can_connect() ) {
			return new WP_Error( 'guest_key_browser_invalid', __( 'An authenticated Guest Key is required.', 'guest-key' ) );
		}
		$manager = WP_Session_Tokens::get_instance( $user_id );
		if ( 'WP_User_Meta_Session_Tokens' !== get_class( $manager ) ) {
			return new WP_Error( 'guest_key_browser_storage', __( 'Guest Key browser access requires WordPress’s native session storage. MCP access remains available.', 'guest-key' ) );
		}
		$attach = static function ( $session, $id ) use ( $user_id, $grant ) {
			if ( (int) $id === (int) $user_id ) {
				$session[ self::SESSION ] = array( 'uuid' => $grant['uuid'], 'blog_id' => $grant['blog_id'], 'network_id' => $grant['network_id'] );
			}
			return $session;
		};
		add_filter( 'attach_session_information', $attach, 99, 2 );
		try { $token = $manager->create( $grant['expires'] ); }
		finally { remove_filter( 'attach_session_information', $attach, 99 ); }
		if ( ! $manager->verify( $token ) ) {
			return new WP_Error( 'guest_key_browser_session', __( 'WordPress could not create the browser session. Please retry.', 'guest-key' ) );
		}
		return $token;
	}

	public static function login() {
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		$errors = new WP_Error();
		if ( ! is_ssl() ) {
			$errors->add( 'guest_key_https', __( 'Use HTTPS to sign in with Guest Key.', 'guest-key' ) );
		} elseif ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$origin = wp_parse_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ?? self::url() ) );
			$expected = wp_parse_url( self::url() );
			$same_origin = is_array( $origin ) && ( $origin['scheme'] ?? '' ) === ( $expected['scheme'] ?? '' )
				&& ( $origin['host'] ?? '' ) === ( $expected['host'] ?? '' ) && ( $origin['port'] ?? null ) === ( $expected['port'] ?? null );
			if ( ! $same_origin || ! isset( $_POST['guest_key_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['guest_key_nonce'] ) ), 'guest_key_browser_login' ) ) {
				$errors->add( 'guest_key_nonce', __( 'Reload this page and try again.', 'guest-key' ) );
			} else {
				$user = self::authenticate( sanitize_user( wp_unslash( $_POST['log'] ?? '' ) ), wp_unslash( $_POST['pwd'] ?? '' ) );
				if ( is_wp_error( $user ) ) { $errors = $user; }
				else {
					$token = self::create_session( $user->ID );
					if ( is_wp_error( $token ) ) { $errors = $token; }
					else {
						$grant = Guest_Key_Access::grant( $user->ID );
						$expiration = static function () use ( $grant ) { return max( 1, $grant['expires'] - time() ); };
						add_filter( 'auth_cookie_expiration', $expiration, PHP_INT_MAX );
						try { wp_set_auth_cookie( $user->ID, false, true, $token ); }
						finally { remove_filter( 'auth_cookie_expiration', $expiration, PHP_INT_MAX ); }
						do_action( 'wp_login', $user->user_login, $user );
						wp_safe_redirect( admin_url() );
						exit;
					}
				}
			}
		}
		login_header( __( 'Guest Key sign-in', 'guest-key' ), '<p class="message">' . esc_html__( 'Sign in with the username and temporary application password from your Guest Key connection instructions. Browser access ends when that key expires or is revoked.', 'guest-key' ) . '</p>', $errors );
		?>
		<form name="loginform" id="loginform" action="<?php echo esc_url( self::url() ); ?>" method="post">
			<p><label for="user_login"><?php esc_html_e( 'Username', 'guest-key' ); ?></label><input type="text" name="log" id="user_login" class="input" autocomplete="username" required></p>
			<p><label for="user_pass"><?php esc_html_e( 'Guest Key application password', 'guest-key' ); ?></label><input type="password" name="pwd" id="user_pass" class="input" autocomplete="off" required></p>
			<?php wp_nonce_field( 'guest_key_browser_login', 'guest_key_nonce', false ); ?>
			<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Sign in with Guest Key', 'guest-key' ); ?>"></p>
		</form>
		<?php
		login_footer( 'user_login' );
		exit;
	}
}
