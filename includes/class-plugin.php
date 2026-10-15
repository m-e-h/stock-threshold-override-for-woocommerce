<?php
/**
 * Per-product stock availability overrides.
 *
 * @package MEH\StockThresholdOverride
 * @license GPL-2.0-or-later
 */

namespace MEH\StockThresholdOverride;

use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates a zero-stock threshold with the classic product editor and CRUD saves.
 *
 * @since 1.0.0
 */
final class Plugin {
	/**
	 * Product metadata storing the opt-in setting.
	 *
	 * @var string
	 */
	const META_KEY = '_mhsto_ignore_stock_threshold';

	/**
	 * Variable products whose variation stock statuses need recalculating.
	 *
	 * @var array<int, bool>
	 */
	private $products_to_sync = array();

	/**
	 * Register the inventory field and stock status hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks() {
		add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'render_inventory_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_inventory_field' ) );
		add_action( 'woocommerce_before_product_object_save', array( $this, 'apply_stock_threshold' ), 20 );
		add_action( 'woocommerce_after_product_object_save', array( $this, 'sync_variation_stock' ) );
	}

	/**
	 * Display the product's threshold override in the Inventory tab.
	 *
	 * @internal
	 */
	public function render_inventory_field() {
		global $product_object;

		if ( ! $product_object instanceof WC_Product ) {
			return;
		}

		echo '<div class="options_group show_if_simple show_if_variable">';
		wp_nonce_field( 'mhsto_save_stock_threshold', 'mhsto_stock_threshold_nonce' );
		woocommerce_wp_checkbox(
			array(
				'id'          => self::META_KEY,
				'value'       => $product_object->get_meta( self::META_KEY, true, 'edit' ),
				'label'       => __( 'Ignore out-of-stock threshold', 'stock-threshold-override-for-woocommerce' ),
				'description' => __( 'Keep this product and its variations in stock while tracked quantity is above 0, instead of using the store-wide threshold. Backorder settings still apply.', 'stock-threshold-override-for-woocommerce' ),
				'desc_tip'    => true,
			)
		);
		echo '</div>';
	}

	/**
	 * Save the checkbox through WooCommerce's product CRUD API.
	 *
	 * @internal
	 *
	 * @param WC_Product $product Product being edited.
	 */
	public function save_inventory_field( WC_Product $product ) {
		if (
			! isset( $_POST['mhsto_stock_threshold_nonce'] )
			|| ! is_string( $_POST['mhsto_stock_threshold_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mhsto_stock_threshold_nonce'] ) ), 'mhsto_save_stock_threshold' )
			|| ! current_user_can( 'edit_post', $product->get_id() )
		) {
			return;
		}

		$was_enabled = 'yes' === $product->get_meta( self::META_KEY, true, 'edit' );
		$is_enabled  = isset( $_POST[ self::META_KEY ] )
			&& is_string( $_POST[ self::META_KEY ] )
			&& 'yes' === sanitize_text_field( wp_unslash( $_POST[ self::META_KEY ] ) );

		if ( $is_enabled ) {
			$product->update_meta_data( self::META_KEY, 'yes' );
		} else {
			$product->delete_meta_data( self::META_KEY );
		}

		if ( $was_enabled !== $is_enabled && $product instanceof WC_Product_Variable ) {
			$this->products_to_sync[ $product->get_id() ] = true;
		}
	}

	/**
	 * Override the status after WooCommerce validates stock, before persisting it.
	 *
	 * @internal
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public function apply_stock_threshold( WC_Product $product ) {
		if ( ! $product->get_manage_stock() || ! $this->ignores_threshold( $product ) ) {
			return;
		}

		// Literal status values retain compatibility with WooCommerce versions before its enum classes.
		if ( (float) $product->get_stock_quantity() > 0 ) {
			$status = 'instock';
		} else {
			$status = 'no' !== $product->get_backorders() ? 'onbackorder' : 'outofstock';
		}

		$product->set_stock_status( $status );
	}

	/**
	 * Recalculate tracked variations when their parent's setting changes.
	 *
	 * @internal
	 *
	 * @param WC_Product $product Product just saved.
	 */
	public function sync_variation_stock( WC_Product $product ) {
		if ( ! $product instanceof WC_Product_Variable || ! isset( $this->products_to_sync[ $product->get_id() ] ) ) {
			return;
		}

		unset( $this->products_to_sync[ $product->get_id() ] );

		$variation_ids = $product->get_children();
		if ( ! empty( $variation_ids ) ) {
			// Prime caches to reduce future queries.
			_prime_post_caches( $variation_ids );
			foreach ( $variation_ids as $variation_id ) {
				$variation = wc_get_product( $variation_id );

				if ( $variation instanceof WC_Product_Variation && true === $variation->get_manage_stock( 'edit' ) ) {
					$variation->save();
				}
			}
		}

		WC_Product_Variable::sync_stock_status( $product );
	}

	/**
	 * Check the setting, inheriting it from the parent for variations.
	 *
	 * @param WC_Product $product Product to check.
	 * @return bool Whether this product uses the zero-stock threshold.
	 */
	private function ignores_threshold( WC_Product $product ) {
		if ( $product instanceof WC_Product_Variation ) {
			$product = wc_get_product( $product->get_parent_id() );
		}

		return $product instanceof WC_Product && 'yes' === $product->get_meta( self::META_KEY, true, 'edit' );
	}
}
