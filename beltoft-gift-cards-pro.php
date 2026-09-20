<?php
/**
 * Plugin Name:       Beltoft Gift Cards for WooCommerce - Pro
 * Plugin URI:        https://beltoft.net/beltoft-gift-cards-pro
 * Description:       Premium add-on for Beltoft Gift Cards for WooCommerce — scheduled delivery, PDF gift cards, store credit, bulk generation, BOGO promotions, and analytics. Requires the free core plugin.
 * Version:           1.5.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            beltoft.net
 * Author URI:        https://beltoft.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       beltoft-gift-cards-pro
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce, beltoft-gift-cards
 * WC requires at least: 6.0
 * WC tested up to:   9.6
 */

defined( 'ABSPATH' ) || exit;

define( 'BGCW_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'BGCW_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'BGCW_PRO_VER', '1.5.0' );
define( 'BGCW_PRO_FILE', __FILE__ );
define( 'BGCW_PRO_LICENSE_SERVER', 'https://beltoft.net' );

/**
 * PSR-4 style autoloader for BgcwPro namespace.
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'BgcwPro\\';
	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}
	$relative = substr( $class, strlen( $prefix ) );
	$file     = BGCW_PRO_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Activation / Deactivation.
 */
register_activation_hook( __FILE__, function () {
	if ( ! defined( 'BGCW_VERSION' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'Beltoft Gift Cards for WooCommerce - Pro requires the free "Beltoft Gift Cards for WooCommerce" plugin to be installed and activated.', 'beltoft-gift-cards-pro' ),
			'Plugin dependency check',
			[ 'back_link' => true ]
		);
	}
	\BgcwPro\Support\Installer::activate();
} );

register_deactivation_hook( __FILE__, function () {
	\BgcwPro\Support\Installer::deactivate();
} );

/**
 * Declare HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Bootstrap — runs after core plugin (priority 20 vs core's default 10).
 */
add_action( 'plugins_loaded', function () {

	// Check WooCommerce.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Beltoft Gift Cards for WooCommerce - Pro requires WooCommerce to be installed and activated.', 'beltoft-gift-cards-pro' );
			echo '</p></div>';
		} );
		return;
	}

	// Check core plugin exists.
	if ( ! defined( 'BGCW_VERSION' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Beltoft Gift Cards for WooCommerce - Pro requires the free "Beltoft Gift Cards for WooCommerce" plugin to be installed and activated.', 'beltoft-gift-cards-pro' );
			echo '</p></div>';
		} );
		return;
	}

	// Check minimum version.
	if ( version_compare( BGCW_VERSION, '1.6.0', '<' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'Beltoft Gift Cards for WooCommerce - Pro requires version 1.6.0 or higher of the free plugin. Please update.', 'beltoft-gift-cards-pro' );
			echo '</p></div>';
		} );
		return;
	}

	\BgcwPro\Support\Installer::maybe_upgrade();
	\BgcwPro\Plugin::init();
}, 20 );

/**
 * Settings link on plugins page.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$url = admin_url( 'admin.php?page=bgcw-gift-cards&tab=license' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'beltoft-gift-cards-pro' ) . '</a>' );
	return $links;
} );
