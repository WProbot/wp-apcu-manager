<?php
/**
 * Main plugin file.
 *
 * @package Bootstrap
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       APCu Manager
 * Plugin URI:        https://github.com/Pierre-Lannoy/wp-apcu-manager
 * Description:       APCu statistics and management right in the WordPress admin dashboard.
 * Version:           1.2.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Pierre Lannoy
 * Author URI:        https://pierre.lannoy.fr
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Network:           true
 * Text Domain:       apcu-manager
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/system/class-option.php';
require_once __DIR__ . '/includes/system/class-environment.php';
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/libraries/class-libraries.php';
require_once __DIR__ . '/includes/libraries/autoload.php';

/**
 * The code that runs during plugin activation.
 *
 * @since 1.0.0
 */
function apcm_activate() {
	APCuManager\Plugin\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 *
 * @since 1.0.0
 */
function apcm_deactivate() {
	APCuManager\Plugin\Deactivator::deactivate();
}

/**
 * The code that runs during plugin uninstallation.
 *
 * @since 1.0.0
 */
function apcm_uninstall() {
	APCuManager\Plugin\Uninstaller::uninstall();
}

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function apcm_run() {
	\APCuManager\System\Logger::init();
	$plugin = new APCuManager\Plugin\Core();
	$plugin->run();
}

register_activation_hook( __FILE__, 'apcm_activate' );
register_deactivation_hook( __FILE__, 'apcm_deactivate' );
register_uninstall_hook( __FILE__, 'apcm_uninstall' );
apcm_run();
