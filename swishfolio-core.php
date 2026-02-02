<?php
/**
 * Plugin Name: Swishfolio Core
 * Plugin URI: https://swishfolio.com
 * Description: Core functionality for Swishfolio theme - custom blocks, post types, and taxonomies for building stunning portfolios.
 * Version: 1.0.0
 * Author: Swishfolio
 * Author URI: https://swishfolio.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: swishfolio-core
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SFCORE_VERSION', '1.0.0' );
define( 'SFCORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SFCORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SFCORE_PLUGIN_FILE', __FILE__ );

// Autoloader.
if ( file_exists( SFCORE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once SFCORE_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	// Simple autoloader fallback for development without Composer.
	spl_autoload_register( function ( $class ) {
		$prefix = 'SwishfolioCore\\';
		$base_dir = SFCORE_PLUGIN_DIR . 'src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	} );
}

// Boot the plugin.
add_action( 'plugins_loaded', function () {
	$plugin = new Plugin();
	$plugin->boot();
} );

// Activation hook.
register_activation_hook( __FILE__, function () {
	// Flush rewrite rules on activation.
	flush_rewrite_rules();
} );

// Deactivation hook.
register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
