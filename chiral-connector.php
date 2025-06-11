<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
/**
 * Chiral Connector
 *
 * @package   Chiral_Connector
 * @author    评论尸(Pls)
 * @copyright         2025 评论尸(Pls)
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Chiral Connector
 * Plugin URI:        https://ckc.akashio.com
 * Description:       Connects your WordPress site to a Chiral Hub, enabling content synchronization and discovery within the Chiral Network.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            评论尸(Pls)
 * Author URI:        https://1q43.blog
 * License:           GPL v3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: chiral-connector
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Define constants
 */
define( 'CHIRAL_CONNECTOR_VERSION', '1.0.0' );
define( 'CHIRAL_CONNECTOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHIRAL_CONNECTOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CHIRAL_CONNECTOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CHIRAL_CONNECTOR_PLUGIN_NAME', 'chiral-connector' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-chiral-connector-core.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_chiral_connector() {

    $plugin = new Chiral_Connector_Core();
    $plugin->run();

}
run_chiral_connector();

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-chiral-connector-activator.php
 */
function activate_chiral_connector() {
    require_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'includes/class-chiral-connector-utils.php';
	require_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'includes/class-chiral-connector-activator.php';
	Chiral_Connector_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-chiral-connector-deactivator.php
 */
function deactivate_chiral_connector() {
    require_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'includes/class-chiral-connector-utils.php';
	require_once CHIRAL_CONNECTOR_PLUGIN_DIR . 'includes/class-chiral-connector-deactivator.php';
	Chiral_Connector_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_chiral_connector' );
register_deactivation_hook( __FILE__, 'deactivate_chiral_connector' );

?>