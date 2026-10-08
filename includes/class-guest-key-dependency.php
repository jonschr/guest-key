<?php
defined( 'ABSPATH' ) || exit;

final class Guest_Key_Dependency {

	const PLUGIN = 'mcp-adapter/mcp-adapter.php';
	private static $changing = false;

	public static function init() {
		add_action( 'activated_plugin', array( __CLASS__, 'external_activation' ), 10, 2 );
		add_action( 'deactivated_plugin', array( __CLASS__, 'external_activation' ), 10, 2 );
		add_filter( 'mcp_adapter_create_default_server', array( __CLASS__, 'default_server' ) );
	}

	public static function network() {
		$active = (array) get_site_option( 'active_sitewide_plugins', array() );
		return is_multisite() && isset( $active[ plugin_basename( GUEST_KEY_FILE ) ] );
	}

	public static function managed() {
		return (bool) get_option( 'guest_key_adapter_managed' ) || ( is_multisite() && get_site_option( 'guest_key_adapter_network_managed' ) );
	}

	public static function default_server( $enabled ) {
		// Leave separately managed MCP installations and their default endpoint alone.
		return self::managed() ? false : $enabled;
	}

	public static function external_activation( $plugin, $network_wide ) {
		if ( self::PLUGIN !== $plugin || self::$changing ) {
			return;
		}
		if ( $network_wide ) {
			delete_site_option( 'guest_key_adapter_network_managed' );
		} else {
			delete_option( 'guest_key_adapter_managed' );
		}
	}

	public static function activate( $network_wide = false ) {
		$result = Guest_Key_Access::locked( 0, static function () use ( $network_wide ) {
			return self::ensure( $network_wide );
		} );
		if ( is_wp_error( $result ) ) {
			update_option( 'guest_key_dependency_error', $result->get_error_message(), false );
		}
	}

	public static function ensure( $network_wide = null ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$network_wide = null === $network_wide ? self::network() : $network_wide;
		if ( is_plugin_active( self::PLUGIN ) && ( ! $network_wide || is_plugin_active_for_network( self::PLUGIN ) ) ) {
			$data = get_plugin_data( WP_PLUGIN_DIR . '/' . self::PLUGIN, false, false );
			if ( version_compare( $data['Version'], '0.7.0', '<' ) ) {
				return new WP_Error( 'guest_key_adapter_old', __( 'Update the official MCP Adapter to version 0.7.0 or newer.', 'guest-key' ) );
			}
			delete_option( 'guest_key_dependency_error' );
			return true;
		}
		$cli = defined( 'WP_CLI' ) && WP_CLI;
		if ( ! $cli && ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'guest_key_activate_denied', __( 'Your account cannot activate the MCP Adapter.', 'guest-key' ) );
		}
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . self::PLUGIN ) ) {
			if ( ( ! $cli && ! current_user_can( 'install_plugins' ) ) || ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) {
				return new WP_Error( 'guest_key_install_denied', __( 'This site does not allow automatic plugin installation. Install the official MCP Adapter, then retry.', 'guest-key' ) );
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			$info = plugins_api( 'plugin_information', array( 'slug' => 'mcp-adapter', 'fields' => array( 'sections' => false ) ) );
			if ( is_wp_error( $info ) ) {
				return new WP_Error( 'guest_key_download', __( 'Could not retrieve MCP Adapter from WordPress.org. ', 'guest-key' ) . $info->get_error_message() );
			}
			if ( 'mcp-adapter' !== ( $info->slug ?? '' ) || ! preg_match( '#^https://downloads\.wordpress\.org/plugin/mcp-adapter\.[a-zA-Z0-9.-]+\.zip$#', $info->download_link ?? '' ) ) {
				return new WP_Error( 'guest_key_package', __( 'WordPress.org did not return an official MCP Adapter package.', 'guest-key' ) );
			}
			$skin = new Automatic_Upgrader_Skin();
			$installer = new Plugin_Upgrader( $skin );
			$result = $installer->install( $info->download_link );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! $result ) {
				$errors = $skin->get_errors();
				return new WP_Error( 'guest_key_install', __( 'WordPress could not install the MCP Adapter. Check filesystem permissions and retry. ', 'guest-key' ) . $errors->get_error_message() );
			}
		}
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . self::PLUGIN, false, false );
		if ( version_compare( $data['Version'], '0.7.0', '<' ) ) {
			return new WP_Error( 'guest_key_adapter_old', __( 'Update the official MCP Adapter to version 0.7.0 or newer.', 'guest-key' ) );
		}
		self::$changing = true;
		try {
			$result = activate_plugin( self::PLUGIN, '', $network_wide );
		} finally {
			self::$changing = false;
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $network_wide ) {
			update_site_option( 'guest_key_adapter_network_managed', true );
		} else {
			update_option( 'guest_key_adapter_managed', true, false );
		}
		delete_option( 'guest_key_dependency_error' );
		return true;
	}

	public static function maybe_disable() {
		$network_wide = is_multisite() && (bool) get_site_option( 'guest_key_adapter_network_managed' );
		if ( ! self::managed() ) {
			return;
		}
		foreach ( Guest_Key_Access::grants() as $grant ) {
			if ( $grant['expires'] > time() && ( $network_wide || (int) $grant['blog_id'] === get_current_blog_id() ) ) {
				return;
			}
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		self::$changing = true;
		try {
			deactivate_plugins( self::PLUGIN, false, $network_wide );
		} finally {
			self::$changing = false;
		}
		// Keep ownership so the next toolbar click can re-enable the adapter.
	}
}
