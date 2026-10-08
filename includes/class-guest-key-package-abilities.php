<?php
defined( 'ABSPATH' ) || exit;

final class Guest_Key_Package_Abilities {

	public static function args( $operation, $label, $description, $properties, $required, $capability, $readonly = false ) {
		return array(
			'label' => $label, 'description' => $description, 'category' => 'site',
			'input_schema' => array( 'type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false ),
			'execute_callback' => array( __CLASS__, $operation ),
			'permission_callback' => static function () use ( $capability ) { return Guest_Key_Access::administrator() && current_user_can( $capability ); },
			'meta' => array( 'public' => false, 'annotations' => array( 'readonly' => $readonly, 'destructive' => ! $readonly, 'idempotent' => $readonly ) ),
		);
	}

	private static function filesystem() {
		if ( ! wp_is_file_mod_allowed( 'guest_key_package_management' ) ) {
			return new WP_Error( 'guest_key_file_mods_disabled', 'Package changes are disabled by this site’s filesystem policy.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( 'direct' === get_filesystem_method() ) { return true; }
		ob_start();
		$credentials = request_filesystem_credentials( self_admin_url() );
		ob_end_clean();
		return $credentials ? true : new WP_Error( 'guest_key_filesystem_unavailable', 'WordPress needs configured filesystem credentials to manage packages.' );
	}

	private static function upgrade_result( $result, $skin ) {
		if ( is_wp_error( $result ) ) { return $result; }
		if ( $skin->get_errors()->has_errors() ) { return $skin->get_errors(); }
		return $result ? true : new WP_Error( 'guest_key_package_failed', 'WordPress could not complete the package operation.' );
	}

	public static function install_theme( $input ) {
		$ready = self::filesystem();
		if ( is_wp_error( $ready ) ) { return $ready; }
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		if ( wp_get_theme( $input['slug'] )->exists() ) {
			return new WP_Error( 'guest_key_theme_exists', 'The theme is already installed; use apply-update to update it.' );
		}
		$info = themes_api( 'theme_information', array( 'slug' => $input['slug'], 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $info ) ) { return $info; }
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result = self::upgrade_result( $upgrader->install( $info->download_link ), $skin );
		if ( is_wp_error( $result ) ) { return $result; }
		wp_clean_themes_cache();
		$theme = wp_get_theme( $input['slug'] );
		return array( 'stylesheet' => $theme->get_stylesheet(), 'name' => $theme->get( 'Name' ), 'version' => $theme->get( 'Version' ), 'activated' => false );
	}

	public static function activate_theme( $input ) {
		$theme = wp_get_theme( $input['stylesheet'] );
		if ( ! $theme->exists() || $theme->errors() ) {
			return new WP_Error( 'guest_key_theme_invalid', 'Choose a valid installed theme.' );
		}
		if ( is_multisite() && ! $theme->is_allowed() ) {
			return new WP_Error( 'guest_key_theme_not_allowed', 'This theme is not enabled for this site on the network.' );
		}
		$requirements = validate_theme_requirements( $theme->get_stylesheet() );
		if ( is_wp_error( $requirements ) ) { return $requirements; }
		if ( get_stylesheet() !== $theme->get_stylesheet() ) { switch_theme( $theme->get_stylesheet() ); }
		return array( 'stylesheet' => get_stylesheet(), 'activated' => true );
	}

	public static function delete_theme( $input ) {
		$ready = self::filesystem();
		if ( is_wp_error( $ready ) ) { return $ready; }
		$theme = wp_get_theme( $input['stylesheet'] );
		if ( ! $theme->exists() ) { return new WP_Error( 'guest_key_theme_missing', 'Theme not found.' ); }
		if ( in_array( $theme->get_stylesheet(), array( get_stylesheet(), get_template() ), true ) ) {
			return new WP_Error( 'guest_key_theme_active', 'The active theme or its parent cannot be deleted.' );
		}
		if ( is_multisite() ) {
			foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
				if ( in_array( $theme->get_stylesheet(), array( get_blog_option( $blog_id, 'stylesheet' ), get_blog_option( $blog_id, 'template' ) ), true ) ) {
					return new WP_Error( 'guest_key_theme_in_use', 'A site on this network uses the theme or its parent.' );
				}
			}
		}
		// Protect parent themes required by other installed child themes.
		foreach ( wp_get_themes() as $installed ) {
			if ( $installed->get_stylesheet() !== $theme->get_stylesheet() && $installed->get_template() === $theme->get_stylesheet() ) {
				return new WP_Error( 'guest_key_theme_dependency', 'An installed child theme depends on this theme.' );
			}
		}
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		$result = delete_theme( $theme->get_stylesheet() );
		return is_wp_error( $result ) ? $result : array( 'deleted' => (bool) $result, 'stylesheet' => $theme->get_stylesheet() );
	}

	public static function list_updates( $input ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		if ( ! empty( $input['refresh'] ) ) {
			wp_version_check(); wp_update_plugins(); wp_update_themes();
		}
		$plugins = get_site_transient( 'update_plugins' );
		$themes = get_site_transient( 'update_themes' );
		$core = get_core_updates( array( 'dismissed' => false ) );
		return array( 'plugins' => (array) ( $plugins->response ?? array() ), 'themes' => (array) ( $themes->response ?? array() ), 'core' => is_array( $core ) ? $core : array() );
	}

	public static function apply_update( $input ) {
		$type = $input['type'];
		if ( ! current_user_can( 'update_' . ( 'core' === $type ? 'core' : $type . 's' ) ) ) {
			return new WP_Error( 'guest_key_update_denied', 'Your account cannot perform this type of update.' );
		}
		$ready = self::filesystem();
		if ( is_wp_error( $ready ) ) { return $ready; }
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$skin = new Automatic_Upgrader_Skin();
		$target = $input['target'];
		if ( 'plugin' === $type ) {
			$plugins = get_plugins();
			$target = preg_replace( '/\.php$/', '', $target ) . '.php';
			if ( ! isset( $plugins[ $target ] ) ) { return new WP_Error( 'guest_key_plugin_missing', 'Installed plugin not found.' ); }
			$updates = get_site_transient( 'update_plugins' );
			$offer = $updates->response[ $target ] ?? null;
			$version = $offer->new_version ?? '';
			$upgrader = new Plugin_Upgrader( $skin );
		} elseif ( 'theme' === $type ) {
			if ( ! wp_get_theme( $target )->exists() ) { return new WP_Error( 'guest_key_theme_missing', 'Installed theme not found.' ); }
			$updates = get_site_transient( 'update_themes' );
			$offer = $updates->response[ $target ] ?? null;
			$version = $offer['new_version'] ?? '';
			$upgrader = new Theme_Upgrader( $skin );
		} else {
			if ( 'wordpress' !== $target || empty( $input['version'] ) ) { return new WP_Error( 'guest_key_core_version', 'Core updates require target=wordpress and an explicit version from list-updates.' ); }
			$offer = null;
			foreach ( (array) get_core_updates( array( 'dismissed' => false ) ) as $candidate ) {
				if ( $candidate->current === $input['version'] && 'upgrade' === $candidate->response ) { $offer = $candidate; break; }
			}
			$version = $offer->current ?? '';
			$upgrader = new Core_Upgrader( $skin );
		}
		if ( ! $offer ) { return new WP_Error( 'guest_key_no_update', 'No cached update is available. Use list-updates with refresh=true first.' ); }
		if ( ! empty( $input['version'] ) && $input['version'] !== $version ) { return new WP_Error( 'guest_key_update_changed', 'The available update differs from the requested version; refresh the update list.' ); }
		$result = self::upgrade_result( $upgrader->upgrade( 'core' === $type ? $offer : $target ), $skin );
		return is_wp_error( $result ) ? $result : array( 'updated' => true, 'type' => $type, 'target' => $target, 'version' => $version );
	}
}
