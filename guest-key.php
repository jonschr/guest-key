<?php
/**
 * Plugin Name: Guest Key
 * Plugin URI: https://elod.in
 * Description: One-click, six-hour administrator access to WordPress abilities through MCP.
 * Version: 0.1.0
 * Author: Jon Schroeder
 * Author URI: https://elod.in
 * Update URI: https://github.com/jonschr/guest-key
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: guest-key
 */

defined( 'ABSPATH' ) || exit;

define( 'GUEST_KEY_VERSION', '0.1.0' );
define( 'GUEST_KEY_FILE', __FILE__ );
define( 'GUEST_KEY_DIR', __DIR__ );

// Static JSON updates must also be available during cron, AJAX, REST, and WP-CLI.
if ( ! defined( 'GUEST_KEY_UPDATE_URL' ) ) {
	define( 'GUEST_KEY_UPDATE_URL', 'https://raw.githubusercontent.com/jonschr/guest-key/master/update.json' );
}

require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
$guest_key_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	GUEST_KEY_UPDATE_URL,
	__FILE__,
	'guest-key'
);

require_once __DIR__ . '/includes/class-guest-key-dependency.php';
require_once __DIR__ . '/includes/class-guest-key-access.php';
require_once __DIR__ . '/includes/class-guest-key-browser.php';
require_once __DIR__ . '/includes/class-guest-key-abilities.php';
require_once __DIR__ . '/includes/class-guest-key-plugin-abilities.php';
require_once __DIR__ . '/includes/class-guest-key-admin-abilities.php';
require_once __DIR__ . '/includes/class-guest-key-package-abilities.php';
require_once __DIR__ . '/includes/class-guest-key-admin.php';

Guest_Key_Access::init();
Guest_Key_Browser::init();
Guest_Key_Abilities::init();
Guest_Key_Admin::init();
Guest_Key_Dependency::init();

register_activation_hook( __FILE__, array( 'Guest_Key_Dependency', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Guest_Key_Access', 'deactivate' ) );
