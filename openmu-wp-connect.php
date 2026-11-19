<?php
/**
 * Plugin Name: OpenMU WP Connect
 * Plugin URI: https://github.com/nolt/openmu-wp-connect
 * Description: Blocks that show live data from an OpenMU game server through its website API: server status, character ranking, mini game rankings, event schedule, the armory and a player account. The site never touches the game database. Until the API is connected the blocks show demo data.
 * Version: 0.1.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: Nolt
 * Author URI: https://github.com/nolt
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: openmu-wp-connect
 * Domain Path: /languages
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/** Absolute path of the plugin folder, with a trailing slash. */
define( 'OPENMU_WP_CONNECT_DIR', plugin_dir_path( __FILE__ ) );

/** Plugin version; used to bust browser caches of the plugin's CSS and JS. */
define( 'OPENMU_WP_CONNECT_VERSION', '0.1.0' );

require_once OPENMU_WP_CONNECT_DIR . 'includes/interface-data-source.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/class-demo-source.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/client-assets.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/item-options.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/item-data.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/class-api-source.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/settings.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/functions.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/blocks.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/blocks-extra.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/event-names.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/account.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/account-menu.php';
require_once OPENMU_WP_CONNECT_DIR . 'includes/character-view.php';

/**
 * Loads the plugin translations from languages/.
 */
function openmu_wp_connect_load_textdomain(): void {
	load_plugin_textdomain( 'openmu-wp-connect', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'openmu_wp_connect_load_textdomain' );

register_activation_hook( __FILE__, 'openmu_wp_connect_migrate_legacy_options' );
