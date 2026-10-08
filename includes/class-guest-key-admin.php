<?php
defined( 'ABSPATH' ) || exit;

final class Guest_Key_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'toolbar' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'dependency_notice' ) );
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
		add_submenu_page( 'settings.php', 'Guest Key', 'Guest Key', 'manage_network_options', 'guest-key', array( __CLASS__, 'render' ) );
	}

	public static function toolbar( $bar ) {
		if ( ! Guest_Key_Access::administrator() || Guest_Key_Browser::is_guest_session() ) {
			return;
		}
		$bar->add_node( array(
			'id' => 'guest-key-access', 'href' => self::page_url(),
			'title' => '<span class="ab-icon dashicons dashicons-admin-page" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'MCP access', 'guest-key' ) . '</span>',
			'meta' => array( 'title' => __( 'Create and copy six-hour MCP access', 'guest-key' ) ),
		) );
		$bar->add_node( array( 'id' => 'guest-key-abilities', 'parent' => 'guest-key-access', 'title' => __( 'Registered abilities', 'guest-key' ), 'href' => self::page_url() ) );
		$bar->add_node( array( 'id' => 'guest-key-revoke', 'parent' => 'guest-key-access', 'title' => __( 'Revoke my access', 'guest-key' ), 'href' => self::page_url() ) );
	}

	public static function assets() {
		if ( ! Guest_Key_Access::administrator() || ( ! is_admin() && ! is_admin_bar_showing() ) ) {
			return;
		}
		$url = plugin_dir_url( GUEST_KEY_FILE );
		wp_enqueue_style( 'guest-key', $url . 'assets/guest-key.css', array( 'dashicons' ), GUEST_KEY_VERSION );
		wp_enqueue_script( 'guest-key', $url . 'assets/guest-key.js', array(), GUEST_KEY_VERSION, true );
		wp_localize_script( 'guest-key', 'guestKey', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'guest_key_access' ),
			'strings' => array(
				'creating' => __( 'Preparing MCP access…', 'guest-key' ), 'created' => __( 'MCP access created.', 'guest-key' ),
				'copied' => __( 'Guest key issued', 'guest-key' ), 'expires' => __( 'Expires: ', 'guest-key' ),
				'fallback' => __( 'Access is ready. Your browser needs another click to copy it.', 'guest-key' ),
				'error' => __( 'Could not prepare access. Please retry.', 'guest-key' ),
				'revoked' => __( 'Your Guest Key access has been revoked.', 'guest-key' ),
				'copy' => __( 'Copy connection details', 'guest-key' ), 'close' => __( 'Close', 'guest-key' ),
				'revoke' => __( 'Revoke now', 'guest-key' ), 'title' => __( 'Guest Key MCP access', 'guest-key' ),
				'active' => __( 'Active', 'guest-key' ), 'inactive' => __( 'Will be enabled when you create access', 'guest-key' ),
				'manualCopy' => __( 'Select and copy these connection details using your browser’s Copy command.', 'guest-key' ),
			),
		) );
	}

	public static function dependency_notice() {
		if ( ! Guest_Key_Access::administrator() ) {
			return;
		}
		$message = get_option( 'guest_key_dependency_error' );
		if ( $message ) {
			echo '<div class="notice notice-error"><p><strong>Guest Key:</strong> ' . esc_html( $message ) . ' <a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Open Guest Key to retry', 'guest-key' ) . '</a></p></div>';
		}
	}

	public static function render() {
		if ( ! Guest_Key_Access::administrator() ) {
			wp_die( esc_html__( 'Administrator access is required.', 'guest-key' ) );
		}
		$rows = Guest_Key_Abilities::inventory();
		$grant = Guest_Key_Access::grant( get_current_user_id() );
		$active = $grant && $grant['expires'] > time() && (int) $grant['blog_id'] === get_current_blog_id();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$adapter = is_plugin_active( Guest_Key_Dependency::PLUGIN );
		?>
		<div class="wrap guest-key-page">
			<h1><?php esc_html_e( 'Guest Key', 'guest-key' ); ?></h1>
			<p class="guest-key-intro"><?php esc_html_e( 'Give your agent six hours of MCP and browser access to this site.', 'guest-key' ); ?></p>
			<div class="guest-key-cards">
				<div class="guest-key-card"><h2><?php esc_html_e( 'Temporary access', 'guest-key' ); ?></h2>
					<p id="guest-key-grant-status"><?php echo $active ? esc_html( sprintf( __( 'Active until %s', 'guest-key' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T', $grant['expires'] ) ) ) : esc_html__( 'No active access on this site.', 'guest-key' ); ?></p>
					<?php if ( Guest_Key_Browser::is_guest_session() ) : ?>
						<p class="description"><?php esc_html_e( 'You are using a temporary Guest Key browser session. Sign out when finished. This session cannot issue replacement keys.', 'guest-key' ); ?></p>
					<?php else : ?>
						<button type="button" class="button button-primary" data-guest-key-create><?php esc_html_e( 'Create & copy MCP access', 'guest-key' ); ?></button>
						<button type="button" class="button" data-guest-key-revoke><?php esc_html_e( 'Revoke my access', 'guest-key' ); ?></button>
						<p class="description"><?php esc_html_e( 'Creating access replaces your previous Guest Key password and its browser sessions. Other application passwords and normal browser sessions are unaffected.', 'guest-key' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="guest-key-card"><h2><?php esc_html_e( 'Connection', 'guest-key' ); ?></h2>
					<p><strong><?php esc_html_e( 'MCP Adapter:', 'guest-key' ); ?></strong> <span id="guest-key-adapter-status"><?php echo $adapter ? esc_html__( 'Active', 'guest-key' ) : esc_html__( 'Will be enabled when you create access', 'guest-key' ); ?></span></p>
					<p><code class="guest-key-endpoint"><?php echo esc_html( Guest_Key_Access::endpoint() ); ?></code></p>
					<p><a href="<?php echo esc_url( Guest_Key_Browser::url() ); ?>"><?php esc_html_e( 'Guest Key browser sign-in', 'guest-key' ); ?></a></p>
					<p class="description"><?php esc_html_e( 'Connection details include MCP and browser sign-in instructions using the same username and temporary password.', 'guest-key' ); ?></p>
				</div>
			</div>
			<h2><?php echo esc_html( sprintf( __( 'Registered abilities (%d)', 'guest-key' ), count( $rows ) ) ); ?></h2>
			<p><?php esc_html_e( 'All registered abilities are included automatically. Each ability keeps its own permission and input checks. Abilities marked private are available only through Guest Key’s authenticated endpoint; their public exposure settings stay unchanged.', 'guest-key' ); ?></p>
			<div class="guest-key-search"><label for="guest-key-search"><?php esc_html_e( 'Find an ability', 'guest-key' ); ?></label> <input type="search" id="guest-key-search" placeholder="<?php esc_attr_e( 'Search name, description, or source…', 'guest-key' ); ?>"> <span id="guest-key-search-count" role="status" aria-live="polite"></span></div>
			<table class="widefat striped guest-key-table"><thead><tr><th scope="col"><?php esc_html_e( 'Ability', 'guest-key' ); ?></th><th scope="col"><?php esc_html_e( 'Registered by', 'guest-key' ); ?></th><th scope="col"><?php esc_html_e( 'Category', 'guest-key' ); ?></th><th scope="col"><?php esc_html_e( 'Details', 'guest-key' ); ?></th></tr></thead><tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr data-guest-key-ability>
					<td><strong><?php echo esc_html( $row['label'] ); ?></strong><br><code><?php echo esc_html( $row['name'] ); ?></code><p><?php echo esc_html( $row['description'] ); ?></p></td>
					<td><strong><?php echo esc_html( $row['source']['name'] ); ?></strong><br><span class="description"><?php echo esc_html( $row['source']['type'] ); ?><?php echo 'callback' === $row['source']['method'] ? ' · ' . esc_html__( 'inferred from callback', 'guest-key' ) : ''; ?></span></td>
					<td><?php echo esc_html( $row['category'] ); ?></td>
					<td><button type="button" class="button" data-guest-key-inspect aria-haspopup="dialog" aria-label="<?php echo esc_attr( sprintf( __( 'Inspect ability: %s', 'guest-key' ), $row['label'] ) ); ?>"><?php esc_html_e( 'Inspect ability', 'guest-key' ); ?></button>
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
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<p id="guest-key-empty" <?php echo $rows ? 'hidden' : ''; ?>><?php esc_html_e( 'No matching abilities. Plugins and themes can register additional abilities using the WordPress Abilities API.', 'guest-key' ); ?></p>
		</div>
		<?php
	}
}
