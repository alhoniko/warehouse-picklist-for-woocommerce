<?php
/**
 * Plugin Name:       Warehouse Picklist for WooCommerce
 * Plugin URI:        https://github.com/alhoniko/warehouse-picklist-for-woocommerce
 * Description:       Printable pick lists and a tablet-friendly pick mode for WooCommerce orders, grouped and ordered by product category to match your warehouse layout.
 * Version:           1.2.0
 * Author:            Niko Alho
 * Author URI:        https://nikoalho.fi
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       warehouse-picklist
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Update URI:        https://github.com/alhoniko/warehouse-picklist-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WHPL_VERSION', '1.2.0' );
define( 'WHPL_PLUGIN_FILE', __FILE__ );
define( 'WHPL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WHPL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WHPL_SETTINGS_OPTION', 'whpl_settings' );
define( 'WHPL_ORDER_OPTION', 'whpl_category_order' );

// Declare HPOS (custom order tables) compatibility.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'init', function () {
	load_plugin_textdomain( 'warehouse-picklist', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Warehouse Picklist requires WooCommerce to be installed and active.', 'warehouse-picklist' )
				. '</p></div>';
		} );
		return;
	}

	require_once WHPL_PLUGIN_DIR . 'includes/settings.php';
	require_once WHPL_PLUGIN_DIR . 'includes/category-order.php';
	require_once WHPL_PLUGIN_DIR . 'includes/capabilities.php';
	require_once WHPL_PLUGIN_DIR . 'includes/order-status.php';
	require_once WHPL_PLUGIN_DIR . 'includes/picking.php';
	require_once WHPL_PLUGIN_DIR . 'includes/print.php';
	require_once WHPL_PLUGIN_DIR . 'includes/updates.php';
} );
