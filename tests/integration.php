<?php
/**
 * Local integration checks: run with wp eval-file after loading Local's environment.
 * Creates and deletes only its own draft products.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || '1' !== get_option( 'mhsto_test_installation' ) ) {
	WP_CLI::error( 'Use an isolated test installation with mhsto_test_installation set to 1.' );
}

require_once dirname( __DIR__ ) . '/includes/class-plugin.php';
$integration = new \MEH\StockThresholdOverride\Plugin();
$integration->register_hooks();

add_filter( 'pre_option_woocommerce_notify_no_stock_amount', 'mhsto_test_threshold' );
function mhsto_test_threshold() { return '3'; }

global $fixture_ids, $checks;

$fixture_ids = [];
$checks = 0;
$original_post = $_POST;
$original_user = get_current_user_id();
$original_product_object = $GLOBALS['product_object'] ?? null;
$threshold_before = get_option( 'woocommerce_notify_no_stock_amount' );

function mhsto_test_check( $expected, $actual, string $label ): void {
	global $checks;
	if ( $expected !== $actual ) {
		throw new RuntimeException( $label . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
	++$checks;
}

function mhsto_test_fixture( WC_Product $product, ?int $quantity = 2 ): WC_Product {
	global $fixture_ids;
	$product->set_name( 'Temporary stock threshold override integration fixture' );
	$product->set_status( $product instanceof WC_Product_Variation ? 'publish' : 'draft' );
	$product->set_regular_price( '10' );
	$product->set_manage_stock( null !== $quantity );
	if ( null !== $quantity ) {
		$product->set_stock_quantity( $quantity );
	}
	$product->save();
	$fixture_ids[] = $product->get_id();
	return $product;
}

function mhsto_test_toggle( WC_Product $product, bool $enabled ): WC_Product {
	$_POST = [ 'mhsto_stock_threshold_nonce' => wp_create_nonce( 'mhsto_save_stock_threshold' ) ];
	if ( $enabled ) {
		$_POST['_mhsto_ignore_stock_threshold'] = 'yes';
	}
	do_action( 'woocommerce_admin_process_product_object', $product );
	$product->save();
	return wc_get_product( $product->get_id() );
}

function mhsto_test_persisted_status( WC_Product $product, string $expected, string $label ): void {
	global $wpdb;
	mhsto_test_check( $expected, wc_get_product( $product->get_id() )->get_stock_status( 'edit' ), $label . ' CRUD' );
	mhsto_test_check( $expected, get_post_meta( $product->get_id(), '_stock_status', true ), $label . ' postmeta' );
	mhsto_test_check(
		$expected,
		$wpdb->get_var( $wpdb->prepare( "SELECT stock_status FROM {$wpdb->wc_product_meta_lookup} WHERE product_id = %d", $product->get_id() ) ),
		$label . ' lookup'
	);
}

try {
	$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
	if ( ! $admins ) {
		throw new RuntimeException( 'No administrator available for the form-save test.' );
	}
	wp_set_current_user( (int) $admins[0] );
	mhsto_test_check( '3', $threshold_before, 'Store threshold before checks' );

	$product = mhsto_test_fixture( new WC_Product_Simple() );
	mhsto_test_persisted_status( $product, 'outofstock', 'Default threshold at two' );
	$product = mhsto_test_toggle( $product, true );
	mhsto_test_persisted_status( $product, 'instock', 'Opted out at two' );
	mhsto_test_check( 'yes', $product->get_meta( '_mhsto_ignore_stock_threshold' ), 'Setting persisted' );
	mhsto_test_check( false, has_term( 'outofstock', 'product_visibility', $product->get_id() ), 'Opted out product visible in stock-filtered catalog' );
	mhsto_test_check( true, $product->has_enough_stock( 2 ), 'Remaining two units can be bought' );
	mhsto_test_check( false, $product->has_enough_stock( 3 ), 'Cannot buy more than available' );

	foreach ( [ [ 4, 'instock' ], [ 3, 'instock' ], [ 1, 'instock' ], [ 0, 'outofstock' ], [ -1, 'outofstock' ] ] as [ $quantity, $expected ] ) {
		wc_update_product_stock( $product, $quantity );
		mhsto_test_persisted_status( $product, $expected, 'Stock set to ' . $quantity );
	}
	wc_update_product_stock( $product, 4 );
	wc_update_product_stock( $product, 1, 'decrease' );
	mhsto_test_persisted_status( $product, 'instock', 'Order stock reduction crosses three' );
	wc_update_product_stock( $product, 3, 'decrease' );
	mhsto_test_persisted_status( $product, 'outofstock', 'Order stock reduction reaches zero' );
	wc_update_product_stock( $product, 2, 'increase' );
	mhsto_test_persisted_status( $product, 'instock', 'Refund/restock to two' );

	foreach ( [ 'yes', 'notify' ] as $backorders ) {
		$product->set_backorders( $backorders );
		$product->set_stock_quantity( 0 );
		$product->save();
		mhsto_test_persisted_status( $product, 'onbackorder', 'Backorders ' . $backorders . ' at zero' );
		$product->set_stock_quantity( 2 );
		$product->save();
		mhsto_test_persisted_status( $product, 'instock', 'Backorders ' . $backorders . ' with real stock' );
	}
	$product->set_backorders( 'no' );
	$product->save();
	$product = mhsto_test_toggle( $product, false );
	mhsto_test_persisted_status( $product, 'outofstock', 'Unchecking restores threshold immediately' );
	mhsto_test_check( '', $product->get_meta( '_mhsto_ignore_stock_threshold' ), 'Unchecked meta removed' );
	mhsto_test_check( true, has_term( 'outofstock', 'product_visibility', $product->get_id() ), 'Default out-of-stock catalog term restored' );

	$product = mhsto_test_toggle( $product, true );
	$_POST = [];
	do_action( 'woocommerce_admin_process_product_object', $product );
	$product->save();
	mhsto_test_persisted_status( $product, 'instock', 'Non-form save preserves setting' );
	$_POST = [ 'mhsto_stock_threshold_nonce' => 'invalid' ];
	do_action( 'woocommerce_admin_process_product_object', $product );
	mhsto_test_check( 'yes', $product->get_meta( '_mhsto_ignore_stock_threshold' ), 'Invalid nonce cannot clear setting' );
	wp_set_current_user( 0 );
	$_POST = [ 'mhsto_stock_threshold_nonce' => wp_create_nonce( 'mhsto_save_stock_threshold' ) ];
	do_action( 'woocommerce_admin_process_product_object', $product );
	mhsto_test_check( 'yes', $product->get_meta( '_mhsto_ignore_stock_threshold' ), 'Unauthorized user cannot clear setting' );
	wp_set_current_user( (int) $admins[0] );

	$unmanaged = mhsto_test_fixture( new WC_Product_Simple(), null );
	$unmanaged->set_stock_status( 'outofstock' );
	$unmanaged->save();
	$unmanaged = mhsto_test_toggle( $unmanaged, true );
	mhsto_test_persisted_status( $unmanaged, 'outofstock', 'Manual status preserved without quantity tracking' );

	$parent = mhsto_test_fixture( new WC_Product_Variable(), null );
	$variation = new WC_Product_Variation();
	$variation->set_parent_id( $parent->get_id() );
	$variation = mhsto_test_fixture( $variation, 2 );
	$zero_variation = new WC_Product_Variation();
	$zero_variation->set_parent_id( $parent->get_id() );
	$zero_variation = mhsto_test_fixture( $zero_variation, 0 );
	$manual_variation = new WC_Product_Variation();
	$manual_variation->set_parent_id( $parent->get_id() );
	$manual_variation = mhsto_test_fixture( $manual_variation, null );
	$manual_variation->set_stock_status( 'outofstock' );
	$manual_variation->save();
	wc_delete_product_transients( $parent->get_id() );
	$parent = wc_get_product( $parent->get_id() );
	$parent = mhsto_test_toggle( $parent, true );
	mhsto_test_persisted_status( $variation, 'instock', 'Tracked variation updated on parent opt-out' );
	mhsto_test_persisted_status( $zero_variation, 'outofstock', 'Zero-stock variation stays unavailable' );
	mhsto_test_persisted_status( $manual_variation, 'outofstock', 'Manual variation unaffected' );
	mhsto_test_persisted_status( $parent, 'instock', 'Variable parent synchronized immediately' );
	$variation = wc_get_product( $variation->get_id() );
	wc_update_product_stock( $variation, 1 );
	mhsto_test_persisted_status( $variation, 'instock', 'Variation inherits exemption on later stock updates' );
	$parent = mhsto_test_toggle( $parent, false );
	mhsto_test_persisted_status( $variation, 'outofstock', 'Variation returns to global threshold' );
	mhsto_test_persisted_status( $parent, 'outofstock', 'Variable parent returns to out of stock' );

	$shared_parent = mhsto_test_fixture( new WC_Product_Variable(), 2 );
	$shared_variation = new WC_Product_Variation();
	$shared_variation->set_parent_id( $shared_parent->get_id() );
	$shared_variation = mhsto_test_fixture( $shared_variation, null );
	wc_delete_product_transients( $shared_parent->get_id() );
	$shared_parent = wc_get_product( $shared_parent->get_id() );
	$shared_parent = mhsto_test_toggle( $shared_parent, true );
	mhsto_test_persisted_status( $shared_parent, 'instock', 'Shared parent opted out' );
	mhsto_test_persisted_status( $shared_variation, 'instock', 'Shared variation synchronized' );
	$shared_variation = wc_get_product( $shared_variation->get_id() );
	wc_update_product_stock( $shared_variation, 1, 'decrease' );
	mhsto_test_persisted_status( $shared_parent, 'instock', 'Shared stock reduction leaves one available' );
	wc_update_product_stock( $shared_variation, 1, 'decrease' );
	mhsto_test_persisted_status( $shared_parent, 'outofstock', 'Shared stock reduction reaches zero' );

	require_once ABSPATH . 'wp-admin/includes/template.php';
	require_once WC_ABSPATH . 'includes/admin/wc-meta-box-functions.php';
	$GLOBALS['product_object'] = $product;
	ob_start();
	do_action( 'woocommerce_product_options_inventory_product_data' );
	$html = ob_get_clean();
	mhsto_test_check( true, false !== strpos( $html, 'Ignore out-of-stock threshold' ), 'Inventory checkbox rendered' );
	mhsto_test_check( true, false !== strpos( $html, 'name="mhsto_stock_threshold_nonce"' ), 'Inventory nonce rendered' );
	mhsto_test_check( 1, preg_match( '/name="_mhsto_ignore_stock_threshold"[^>]+checked=/', $html ), 'Saved checkbox renders checked' );
	mhsto_test_check( $threshold_before, get_option( 'woocommerce_notify_no_stock_amount' ), 'Store threshold unchanged' );
	WP_CLI::success( $checks . ' integration checks passed.' );
} finally {
	remove_filter( 'pre_option_woocommerce_notify_no_stock_amount', 'mhsto_test_threshold' );
	$_POST = $original_post;
	wp_set_current_user( $original_user );
	$GLOBALS['product_object'] = $original_product_object;
	foreach ( array_reverse( $fixture_ids ) as $fixture_id ) {
		$fixture = wc_get_product( $fixture_id );
		if ( $fixture && 'Temporary stock threshold override integration fixture' === get_post_field( 'post_title', $fixture_id ) ) {
			$fixture->delete( true );
		} elseif ( $fixture instanceof WC_Product_Variation ) {
			$fixture->delete( true );
		}
	}
	WP_CLI::log( 'Temporary products cleaned up.' );
}
