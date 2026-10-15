<?php
/**
 * Plugin Name: Stock Threshold Override for WooCommerce
 * Description: Let selected products and their variations sell down to zero stock instead of using the store-wide out-of-stock threshold.
 * Version: 1.0.0
 * Author: Marty Helmick
 * Author URI: https://github.com/m-e-h
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 9.0
 * WC tested up to: 11.2.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: stock-threshold-override-for-woocommerce
 *
 * @package MEH\StockThresholdOverride
 */

namespace MEH\StockThresholdOverride;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Load the integration once WooCommerce is available.
 *
 * @since 1.0.0
 */
function bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '9.0', '<' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\dependency_notice' );
		return;
	}

	require_once __DIR__ . '/includes/class-plugin.php';
	$plugin = new Plugin();
	$plugin->register_hooks();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );

/**
 * Explain an unmet WooCommerce requirement to plugin administrators.
 *
 * @since 1.0.0
 */
function dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	esc_html_e( 'Stock Threshold Override for WooCommerce requires WooCommerce 9.0 or newer. Install or update WooCommerce to use the product inventory setting.', 'stock-threshold-override-for-woocommerce' );
	echo '</p></div>';
}

/**
 * Declare compatibility with WooCommerce order storage and checkout features.
 *
 * @since 1.0.0
 */
function declare_compatibility() {
	if ( class_exists( FeaturesUtil::class ) ) {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', __NAMESPACE__ . '\\declare_compatibility' );
