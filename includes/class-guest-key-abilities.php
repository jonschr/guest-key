<?php
defined( 'ABSPATH' ) || exit;

final class Guest_Key_Abilities {

	private static $sources = array();

	public static function init() {
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'capture_source' ), PHP_INT_MAX, 2 );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_local_abilities' ) );
		add_action( 'mcp_adapter_init', array( __CLASS__, 'register_server' ), 99 );
	}

	public static function register_local_abilities() {
		foreach ( glob( GUEST_KEY_DIR . '/abilities/*.php' ) as $file ) {
			require $file;
		}
	}

	public static function capture_source( $args, $name ) {
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 24 ) as $frame ) {
			if ( 'wp_register_ability' === ( $frame['function'] ?? '' ) && isset( $frame['file'] ) ) {
				self::$sources[ $name ] = array( 'file' => $frame['file'], 'line' => $frame['line'], 'method' => 'registration' );
				break;
			}
		}
		return $args;
	}

	public static function register_server( $adapter ) {
		// Discover schemas on demand; the command runner still executes native abilities.
		$tools = array( 'guest-key/help', 'guest-key/run' );
		$adapter->create_server(
			'guest-key', 'guest-key/v1', 'mcp', 'Guest Key',
			'Temporary administrator access to all registered WordPress abilities.',
			GUEST_KEY_VERSION, array( \WP\MCP\Transport\HttpTransport::class ),
			null, null, $tools, array(), array(), array( 'Guest_Key_Access', 'can_connect' )
		);
	}

	private static function callback_source( $ability ) {
		// WordPress does not expose callbacks in its public ability getters. Reflection is a
		// read-only fallback for abilities registered before Guest Key's filter was installed.
		try {
			$property = new ReflectionProperty( $ability, 'execute_callback' );
			$property->setAccessible( true );
			$callback = $property->getValue( $ability );
			return self::callback_location( $callback );
		} catch ( Throwable $e ) {
			return array();
		}
	}

	private static function callback_location( $callback ) {
		try {
			if ( is_array( $callback ) ) {
				$reflection = new ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$reflection = new ReflectionMethod( $callback );
			} elseif ( is_object( $callback ) && ! $callback instanceof Closure ) {
				$reflection = new ReflectionMethod( $callback, '__invoke' );
			} else {
				$reflection = new ReflectionFunction( $callback );
			}
			return array( 'file' => $reflection->getFileName(), 'line' => $reflection->getStartLine(), 'method' => 'callback' );
		} catch ( Throwable $e ) {
			return array();
		}
	}

	public static function identify( $file ) {
		$file = wp_normalize_path( $file );
		$relative = ltrim( str_replace( wp_normalize_path( ABSPATH ), '', $file ), '/' );
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		foreach ( array( WP_PLUGIN_DIR => get_plugins(), WPMU_PLUGIN_DIR => get_mu_plugins() ) as $root => $plugins ) {
			$type = WP_PLUGIN_DIR === $root ? 'Plugin' : 'Must-use plugin';
			$root = trailingslashit( wp_normalize_path( $root ) );
			if ( 0 !== strpos( $file, $root ) ) {
				continue;
			}
			$path = substr( $file, strlen( $root ) );
			foreach ( $plugins as $main_file => $data ) {
				$directory = dirname( $main_file );
				if ( $path === $main_file || ( '.' !== $directory && 0 === strpos( $path, $directory . '/' ) ) ) {
					return array( 'name' => $data['Name'], 'type' => $type, 'file' => $relative );
				}
			}
			return array( 'name' => __( 'Unidentified plugin', 'guest-key' ), 'type' => 'Plugin', 'file' => $relative );
		}
		foreach ( wp_get_themes() as $theme ) {
			if ( 0 === strpos( $file, trailingslashit( wp_normalize_path( $theme->get_stylesheet_directory() ) ) ) ) {
				return array( 'name' => $theme->get( 'Name' ), 'type' => 'Theme', 'file' => $relative );
			}
		}
		foreach ( array( ABSPATH . WPINC, ABSPATH . 'wp-admin' ) as $root ) {
			if ( 0 === strpos( $file, trailingslashit( wp_normalize_path( $root ) ) ) ) {
				return array( 'name' => 'WordPress core', 'type' => 'Core', 'file' => $relative );
			}
		}
		return array( 'name' => __( 'Unknown source', 'guest-key' ), 'type' => 'Unknown', 'file' => '' );
	}

	public static function inventory() {
		// Initialize REST integrations as on an MCP request so their abilities appear here too.
		rest_get_server();
		$abilities = wp_get_abilities();
		ksort( $abilities );
		$rows = array();
		foreach ( $abilities as $name => $ability ) {
			$origin = self::$sources[ $name ] ?? self::callback_source( $ability );
			$source = ! empty( $origin['file'] ) ? self::identify( $origin['file'] ) : array( 'name' => __( 'Unknown source', 'guest-key' ), 'type' => 'Unknown', 'file' => '' );
			$source['line'] = $origin['line'] ?? 0;
			$source['method'] = $origin['method'] ?? 'unknown';
			$rows[] = array(
				'name' => $name, 'label' => $ability->get_label(), 'description' => $ability->get_description(),
				'category' => $ability->get_category(), 'source' => $source,
				'input' => $ability->get_input_schema(), 'output' => $ability->get_output_schema(), 'meta' => $ability->get_meta(),
			);
		}
		return $rows;
	}
}
