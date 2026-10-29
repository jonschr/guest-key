<?php
defined( 'ABSPATH' ) || exit;

final class Guest_Key_Admin {

	public static function init() {
		add_action( 'admin_post_guest_key_save_duration', array( __CLASS__, 'save_duration' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'toolbar' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'dependency_notice' ) );
		add_action( 'wp_ajax_guest_key_diagnostics', array( __CLASS__, 'ajax_diagnostics' ) );
	}

	public static function save_duration() {
		if ( Guest_Key_Browser::is_guest_session() || ! Guest_Key_Access::administrator() || ! wp_get_session_token() || rest_get_authenticated_app_password() ) {
			wp_die( esc_html__( 'Use an administrator’s logged-in browser session to manage access.', 'guest-key' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'guest_key_duration' );
		$result = Guest_Key_Access::set_duration( wp_unslash( $_POST['guest_key_duration_minutes'] ?? '' ) );
		wp_safe_redirect( add_query_arg( 'guest-key-duration', is_wp_error( $result ) ? 'invalid' : 'saved', wp_get_referer() ?: self::page_url() ) . '#guest-key-duration-settings' );
		exit;
	}

	public static function page_url() {
		return Guest_Key_Dependency::network() && is_network_admin()
			? network_admin_url( 'settings.php?page=guest-key' ) : admin_url( 'tools.php?page=guest-key' );
	}

	public static function menu() {
		if ( Guest_Key_Access::administrator() ) {
			add_management_page( 'Guest Key', 'Guest Key', 'manage_options', 'guest-key', array( __CLASS__, 'render' ) );
		}
	}

	public static function network_menu() {
		if ( ! Guest_Key_Access::administrator() ) { return; }
		add_submenu_page( 'settings.php', 'Guest Key', 'Guest Key', 'manage_network_options', 'guest-key', array( __CLASS__, 'render' ) );
	}

	private static function duration_label() {
		$minutes = (int) ( Guest_Key_Access::duration() / MINUTE_IN_SECONDS );
		if ( 0 === $minutes % 60 ) {
			$hours = (int) ( $minutes / 60 );
			return sprintf( _n( '%s hour', '%s hours', $hours, 'guest-key' ), number_format_i18n( $hours ) );
		}
		return sprintf( _n( '%s minute', '%s minutes', $minutes, 'guest-key' ), number_format_i18n( $minutes ) );
	}

	public static function toolbar( $bar ) {
		if ( ! Guest_Key_Access::administrator() || Guest_Key_Browser::is_guest_session() ) {
			return;
		}
		$bar->add_node( array(
			'id' => 'guest-key-access', 'href' => self::page_url(),
			'title' => '<span class="ab-icon dashicons dashicons-admin-page" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Copy Guest Key', 'guest-key' ) . '</span>',
			'meta' => array( 'title' => sprintf( __( 'Create and copy Guest Key access for up to %s', 'guest-key' ), self::duration_label() ) ),
		) );
		$bar->add_node( array( 'id' => 'guest-key-settings', 'parent' => 'guest-key-access', 'title' => __( 'Settings', 'guest-key' ), 'href' => self::page_url() ) );
	}

	public static function assets() {
		if ( ! Guest_Key_Access::administrator() || Guest_Key_Browser::is_guest_session() || ( ! is_admin() && ! is_admin_bar_showing() ) ) {
			return;
		}
		$url = plugin_dir_url( GUEST_KEY_FILE );
		$version = GUEST_KEY_VERSION;
		if ( in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { $version .= '.' . max( filemtime( GUEST_KEY_DIR . '/assets/guest-key.js' ), filemtime( GUEST_KEY_DIR . '/assets/guest-key.css' ) ); }
		wp_enqueue_style( 'guest-key', $url . 'assets/guest-key.css', array( 'dashicons' ), $version );
		wp_enqueue_script( 'guest-key', $url . 'assets/guest-key.js', array(), $version, true );
		wp_localize_script( 'guest-key', 'guestKey', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'guest_key_access' ),
			'licensing' => Guest_Key_License::view(),
			'noticeKey' => get_current_blog_id() . ':' . get_current_user_id(), 'settingsUrl' => self::page_url(),
			'strings' => array(
				'creating' => __( 'Preparing Guest Key…', 'guest-key' ), 'created' => __( 'Guest Key access created.', 'guest-key' ),
				'copied' => __( 'Connection details copied. Paste them into your agent.', 'guest-key' ), 'expires' => __( 'Expires: ', 'guest-key' ),
				'fallback' => __( 'Access is ready. Your browser needs another click to copy it.', 'guest-key' ),
				'error' => __( 'Could not prepare access. Please retry.', 'guest-key' ),
				'revoked' => __( 'Your Guest Key access has been revoked.', 'guest-key' ),
				'copy' => __( 'Copy connection details', 'guest-key' ), 'close' => __( 'Close', 'guest-key' ),
				'revoke' => __( 'Revoke now', 'guest-key' ), 'title' => __( 'Guest Key access', 'guest-key' ),
				'active' => __( 'Ready', 'guest-key' ), 'inactive' => __( 'Setup will be attempted when you create access', 'guest-key' ),
				'mcpUnavailable' => __( 'MCP unavailable; REST and browser access are ready.', 'guest-key' ),
				'manualCopy' => __( 'Select and copy these connection details using your browser’s Copy command.', 'guest-key' ),
				'diagnosticsCopied' => __( 'Diagnostics copied. Share this report with support.', 'guest-key' ),
				'diagnosticsSelect' => __( 'Diagnostics are selected. Use your browser’s Copy command.', 'guest-key' ),
				'licenseSettings' => __( 'License', 'guest-key' ), 'settings' => __( 'Guest Key settings', 'guest-key' ),
				'trialTitle' => __( 'Free trial', 'guest-key' ), 'manageBilling' => __( 'Manage billing', 'guest-key' ),
				'automaticLicense' => __( 'Purchasing with %s? Your license will connect automatically.', 'guest-key' ),
				'purchasePending' => __( 'No license is connected yet. Check your purchase email or enter a key below.', 'guest-key' ),
				'checkPurchase' => __( 'Check for my purchase', 'guest-key' ),
				'licenseTitle' => __( 'Unlock Guest Key', 'guest-key' ),
				'trialIntro' => __( 'Try every feature free for 24 hours. No card required.', 'guest-key' ),
				'trialExpired' => __( 'Your free trial has ended. Add a license to keep connecting your agent.', 'guest-key' ),
				'licenseExpired' => __( 'Your license has expired or is inactive. Renew it, then check again.', 'guest-key' ),
				'licenseCheckNeeded' => __( 'Your license needs to be checked before connecting. Please try again.', 'guest-key' ),
				'licenseActive' => __( 'Your license is active.', 'guest-key' ), 'trialActive' => __( 'Your free trial is active.', 'guest-key' ),
				'email' => __( 'Email address', 'guest-key' ), 'sendCode' => __( 'Email me a code', 'guest-key' ),
				'code' => __( 'Six-digit email code', 'guest-key' ), 'verifyCode' => __( 'Verify & start trial', 'guest-key' ),
				'codeSent' => __( 'We sent a six-digit code to %s. Your trial starts after verification.', 'guest-key' ),
				'changeEmail' => __( 'Change email or request a new code', 'guest-key' ),
				'licenseKey' => __( 'License key', 'guest-key' ), 'activateLicense' => __( 'Activate license', 'guest-key' ),
				'showKey' => __( 'Show key', 'guest-key' ), 'hideKey' => __( 'Hide key', 'guest-key' ),
				'showKeyHelp' => __( 'Show the key to enter it using TextExpander.', 'guest-key' ),
				'buyLicense' => __( 'Get a license', 'guest-key' ), 'renewLicense' => __( 'Renew license', 'guest-key' ),
				'checkLicense' => __( 'Check again', 'guest-key' ), 'removeLicense' => __( 'Remove license from this site', 'guest-key' ),
				'continueConnect' => __( 'Connect agent', 'guest-key' ), 'working' => __( 'Working…', 'guest-key' ),
			),
		) );
	}

	public static function dependency_notice() {
		if ( ! Guest_Key_Access::administrator() ) {
			return;
		}
		$message = get_option( 'guest_key_dependency_error' );
		if ( $message ) {
			echo '<div class="notice notice-warning"><p><strong>Guest Key:</strong> ' . esc_html__( 'MCP setup is unavailable. REST commands and browser access can still be created.', 'guest-key' ) . ' ' . esc_html( $message ) . ' <a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Open Guest Key to retry', 'guest-key' ) . '</a></p></div>';
		}
	}

	public static function diagnostics() {
		if ( ! Guest_Key_Access::administrator() ) {
			return new WP_Error( 'guest_key_forbidden', __( 'Administrator access is required.', 'guest-key' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = WP_PLUGIN_DIR . '/' . Guest_Key_Dependency::PLUGIN;
		$installed = file_exists( $file );
		$data = $installed ? get_plugin_data( $file, false, false ) : array();
		$routes = rest_get_server()->get_routes();
		return array(
			'generated_utc' => gmdate( 'c' ), 'guest_key' => GUEST_KEY_VERSION,
			'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'multisite' => is_multisite(),
			'adapter' => array(
				'installed' => $installed, 'version' => $data['Version'] ?? null,
				'active' => is_plugin_active( Guest_Key_Dependency::PLUGIN ),
				'loaded_version' => defined( 'WP_MCP_VERSION' ) ? WP_MCP_VERSION : null,
				'endpoint_registered' => isset( $routes[ Guest_Key_Access::ROUTE ] ),
			),
			'application_passwords_available' => wp_is_application_passwords_available_for_user( wp_get_current_user() ),
			'can_install_plugins' => current_user_can( 'install_plugins' ),
			'file_mods_allowed' => wp_is_file_mod_allowed( 'guest_key_install_plugin' ),
			'filesystem_method' => get_filesystem_method(), 'plugins_directory_writable' => is_writable( WP_PLUGIN_DIR ),
			'update_feed_host' => wp_parse_url( GUEST_KEY_UPDATE_URL, PHP_URL_HOST ),
			'current_mcp_error' => Guest_Key_Dependency::diagnostic_message( get_option( 'guest_key_dependency_error', '' ) ),
			'last_mcp_failure' => get_option( 'guest_key_dependency_last_error', null ),
		);
	}

	public static function ajax_diagnostics() {
		check_ajax_referer( 'guest_key_access', 'nonce' );
		$report = self::diagnostics();
		if ( is_wp_error( $report ) ) { wp_send_json_error( array( 'message' => $report->get_error_message() ), 403 ); }
		wp_send_json_success( $report );
	}

	public static function render() {
		if ( ! Guest_Key_Access::administrator() ) {
			wp_die( esc_html__( 'Administrator access is required.', 'guest-key' ) );
		}
		$rows = Guest_Key_Abilities::inventory();
		$discovery = Guest_Key_Discovery::inventory();
		$grant = Guest_Key_Access::grant( get_current_user_id() );
		$active = $grant && $grant['expires'] > time() && (int) $grant['blog_id'] === get_current_blog_id();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$adapter = is_plugin_active( Guest_Key_Dependency::PLUGIN ) && ! get_option( 'guest_key_dependency_error' );
		$available = wp_is_application_passwords_available_for_user( wp_get_current_user() ) && current_user_can( 'create_app_password', get_current_user_id() );
		?>
		<div class="wrap guest-key-page">
			<h1><?php esc_html_e( 'Guest Key', 'guest-key' ); ?></h1>
			<section class="guest-key-card guest-key-connect" aria-labelledby="guest-key-connect-title">
					<h2 id="guest-key-connect-title"><?php esc_html_e( 'Connect your agent', 'guest-key' ); ?></h2>
					<p class="guest-key-intro"><?php esc_html_e( 'Create connection details, then paste them into your local agent.', 'guest-key' ); ?></p>
					<p><?php echo esc_html( sprintf( __( 'Your agent receives administrator access to this site for up to %s. You can revoke access at any time.', 'guest-key' ), self::duration_label() ) ); ?></p>
					<p id="guest-key-grant-status" role="status" aria-live="polite"><?php echo $active ? esc_html( sprintf( __( 'Active until %s', 'guest-key' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T', $grant['expires'] ) ) ) : esc_html__( 'No active access on this site.', 'guest-key' ); ?></p>
					<?php if ( Guest_Key_Browser::is_guest_session() ) : ?>
						<p class="description"><?php esc_html_e( 'You are using a temporary Guest Key browser session. Sign out when finished. This session cannot issue replacement keys.', 'guest-key' ); ?></p>
					<?php else : ?>
						<button type="button" class="button button-primary" data-guest-key-create<?php echo $available ? '' : ' disabled data-guest-key-unavailable'; ?>><?php esc_html_e( 'Create & copy access', 'guest-key' ); ?></button>
						<button type="button" class="button" data-guest-key-revoke<?php echo $active ? '' : ' hidden'; ?>><?php esc_html_e( 'Revoke access', 'guest-key' ); ?></button>
						<?php if ( ! $available ) : ?><p class="description"><?php esc_html_e( 'Application passwords are unavailable for this account. Check HTTPS and your host or security plugin settings.', 'guest-key' ); ?></p><?php endif; ?>
						<p class="description"><?php esc_html_e( 'Creating access replaces your previous Guest Key. Revoking access ends the connection; changes already made remain.', 'guest-key' ); ?></p>
					<?php endif; ?>
			</section>
			<?php if ( ! Guest_Key_Browser::is_guest_session() ) : ?>
			<section class="guest-key-card guest-key-connect" id="guest-key-duration-settings" aria-labelledby="guest-key-duration-title">
				<h2 id="guest-key-duration-title"><?php esc_html_e( 'Access duration', 'guest-key' ); ?></h2>
				<?php if ( 'saved' === ( $_GET['guest-key-duration'] ?? '' ) ) : ?>
					<div class="notice notice-success inline"><p><?php esc_html_e( 'Access duration saved.', 'guest-key' ); ?></p></div>
				<?php elseif ( 'invalid' === ( $_GET['guest-key-duration'] ?? '' ) ) : ?>
					<div class="notice notice-error inline"><p><?php esc_html_e( 'Enter a whole number of minutes from 30 to 1440 (24 hours). Your previous setting has been kept.', 'guest-key' ); ?></p></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="guest_key_save_duration">
					<?php wp_nonce_field( 'guest_key_duration' ); ?>
					<p><label for="guest-key-duration-minutes"><?php esc_html_e( 'Duration in minutes', 'guest-key' ); ?></label></p>
					<input type="number" class="small-text" id="guest-key-duration-minutes" name="guest_key_duration_minutes" min="30" max="1440" step="1" required value="<?php echo esc_attr( Guest_Key_Access::duration() / MINUTE_IN_SECONDS ); ?>" aria-describedby="guest-key-duration-help">
					<p class="description" id="guest-key-duration-help"><?php esc_html_e( '30 minutes to 24 hours. Default: 120 minutes (2 hours). Applies to new keys; existing keys keep their current expiration. Access always ends by your trial or license expiration.', 'guest-key' ); ?></p>
					<?php submit_button( __( 'Save duration', 'guest-key' ), 'primary', 'submit', false ); ?>
				</form>
			</section>
			<?php endif; ?>
			<section class="guest-key-card guest-key-connect" id="guest-key-license-settings" aria-labelledby="guest-key-license-settings-title">
				<div data-guest-key-license-settings>
					<h2 id="guest-key-license-settings-title"><?php echo 'paid' === Guest_Key_License::view()['kind'] ? esc_html__( 'License', 'guest-key' ) : esc_html__( 'Get started', 'guest-key' ); ?></h2>
					<noscript><p><?php esc_html_e( 'Enable JavaScript to manage your license.', 'guest-key' ); ?></p></noscript>
				</div>
			</section>
			<details class="guest-key-details">
				<summary><?php esc_html_e( 'Connection details', 'guest-key' ); ?></summary>
				<div class="guest-key-details-content">
					<p><strong><?php esc_html_e( 'WordPress REST API:', 'guest-key' ); ?></strong></p>
					<p><code class="guest-key-endpoint"><?php echo esc_html( rest_url() ); ?></code></p>
					<p><strong><?php esc_html_e( 'Optional MCP:', 'guest-key' ); ?></strong> <span id="guest-key-adapter-status"><?php echo $adapter ? esc_html__( 'Ready', 'guest-key' ) : esc_html__( 'Setup will be attempted when you create access', 'guest-key' ); ?></span></p>
					<p id="guest-key-mcp-endpoint"<?php echo $adapter ? '' : ' hidden'; ?>><code class="guest-key-endpoint"><?php echo esc_html( Guest_Key_Access::endpoint() ); ?></code></p>
					<p><a href="<?php echo esc_url( Guest_Key_Browser::url() ); ?>"><?php esc_html_e( 'Guest Key browser sign-in', 'guest-key' ); ?></a></p>
					<p class="description"><?php esc_html_e( 'The copied details include everything your agent needs. REST commands and browser sign-in work independently of optional MCP setup.', 'guest-key' ); ?></p>
					<p class="description"><?php esc_html_e( 'If a connection fails, check the expiry and confirm your host or security plugin allows WordPress REST requests and application passwords.', 'guest-key' ); ?></p>
				</div>
			</details>
			<details id="guest-key-diagnostics" class="guest-key-details">
				<summary><?php esc_html_e( 'Diagnostics', 'guest-key' ); ?></summary>
				<div class="guest-key-details-content">
					<p><?php esc_html_e( 'Copy this report when asking for help. It includes setup status and the most recent MCP setup failure, even after recovery. Passwords and temporary access details are excluded.', 'guest-key' ); ?></p>
					<label for="guest-key-diagnostic-report" class="screen-reader-text"><?php esc_html_e( 'Diagnostic report', 'guest-key' ); ?></label>
					<textarea id="guest-key-diagnostic-report" class="large-text code" rows="14" readonly><?php echo esc_textarea( wp_json_encode( self::diagnostics(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
					<p><button type="button" class="button" data-guest-key-diagnostics><?php esc_html_e( 'Copy diagnostics', 'guest-key' ); ?></button></p>
				</div>
			</details>
			<details id="guest-key-inventory" class="guest-key-details">
				<summary><?php esc_html_e( 'Technical inventory', 'guest-key' ); ?></summary>
			<div class="guest-key-search"><label for="guest-key-search" class="screen-reader-text"><?php esc_html_e( 'Search the inventory', 'guest-key' ); ?></label><input type="search" id="guest-key-search" placeholder="<?php esc_attr_e( 'Search abilities, servers, or source…', 'guest-key' ); ?>"> <span id="guest-key-search-count" role="status" aria-live="polite"><?php echo esc_html( sprintf( __( '%d entries', 'guest-key' ), count( $rows ) + count( $discovery ) ) ); ?></span></div>
			<ul class="guest-key-inventory-list" data-guest-key-section>
			<?php require __DIR__ . '/admin-discovery.php'; ?>
			<?php foreach ( $rows as $row ) : ?>
				<li data-guest-key-ability data-guest-key-item>
					<button type="button" class="button-link" data-guest-key-inspect aria-haspopup="dialog" aria-label="<?php echo esc_attr( sprintf( __( 'Inspect ability: %s', 'guest-key' ), $row['label'] ) ); ?>"><?php echo esc_html( $row['name'] ); ?></button>
					<span class="description"><?php echo esc_html( $row['source']['name'] ); ?></span>
					<template data-guest-key-ability-content>
					<h2><?php echo esc_html( $row['label'] ); ?></h2>
					<p><code><?php echo esc_html( $row['name'] ); ?></code></p>
					<p><?php echo esc_html( $row['description'] ); ?></p>
					<p><strong><?php esc_html_e( 'Registered by:', 'guest-key' ); ?></strong> <?php echo esc_html( $row['source']['name'] . ' (' . $row['source']['type'] . ')' ); ?><?php echo 'callback' === $row['source']['method'] ? ' · ' . esc_html__( 'inferred from callback', 'guest-key' ) : ''; ?></p>
					<p><strong><?php esc_html_e( 'Category:', 'guest-key' ); ?></strong> <?php echo esc_html( $row['category'] ); ?></p>
					<?php if ( $row['source']['file'] ) : ?><p><strong><?php esc_html_e( 'Source:', 'guest-key' ); ?></strong><br><code><?php echo esc_html( $row['source']['file'] . ':' . $row['source']['line'] ); ?></code></p><?php endif; ?>
					<?php foreach ( array( 'input' => __( 'Input schema', 'guest-key' ), 'output' => __( 'Output schema', 'guest-key' ), 'meta' => __( 'Metadata', 'guest-key' ) ) as $key => $label ) : ?>
						<h3><?php echo esc_html( $label ); ?></h3><pre><?php echo esc_html( wp_json_encode( $row[ $key ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
					<?php endforeach; ?>
					</template>
				</li>
			<?php endforeach; ?>
			</ul>
			<p id="guest-key-empty" hidden><?php esc_html_e( 'No matching inventory entries.', 'guest-key' ); ?></p>
			</details>
		</div>
		<?php
	}
}
