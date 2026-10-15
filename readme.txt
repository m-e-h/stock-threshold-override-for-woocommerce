=== Stock Threshold Override for WooCommerce ===
Contributors: m-e-h
Tags: woocommerce, inventory, stock, out of stock
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let selected products and their variations sell down to zero stock, while the rest of your store keeps its usual out-of-stock threshold.

== Description ==

WooCommerce's out-of-stock threshold applies to the whole store. If you reserve the last few units of most products, you may still want to sell every remaining unit of selected products.

Stock Threshold Override for WooCommerce adds an "Ignore out-of-stock threshold" checkbox to Product data > Inventory in the classic product editor. Enable it on a product to keep that product in stock while its tracked quantity is above zero.

For example, with a store-wide threshold of 3, an enabled product with 1, 2, or 3 units remains in stock. At zero, the product becomes out of stock or on backorder according to its existing backorder setting.

= Features =

* Enable the override for individual simple or variable products.
* Apply a variable product's setting to all of its variations.
* Support both shared parent stock and independently tracked variation stock.
* Recalculate stock status and catalog visibility when the setting changes.
* Keep the override during ordinary WooCommerce stock reductions and restocking.
* Preserve existing backorder settings and manually managed stock statuses.
* Use WooCommerce product CRUD APIs and standard inventory controls.

= Requirements =

* WordPress 6.5 or newer.
* WooCommerce 9.0 or newer.
* PHP 7.4 or newer.
* Stock quantity tracking enabled for the product or its variations.

The setting is available in the classic WooCommerce product editor. This plugin does not add a control to alternative product editors, bulk editing, or CSV import screens.

= Scope =

The override changes stock availability. It does not change the store-wide threshold, low-stock email settings, out-of-stock email thresholds, or stock reports that compare quantities against the store-wide threshold.

The plugin makes no external requests, includes no tracking, and requires no account or service subscription.

== Installation ==

1. Install and activate WooCommerce 9.0 or newer.
2. Upload the plugin ZIP through Plugins > Add New Plugin > Upload Plugin, then activate it.
3. Edit a product and open Product data > Inventory.
4. Enable stock quantity tracking for the product or its variations.
5. Check "Ignore out-of-stock threshold" and update the product.

== Frequently Asked Questions ==

= Does this enable backorders? =

No. A product with stock above zero remains in stock. When its quantity reaches zero or below, its existing backorder setting determines whether it is out of stock or on backorder.

= Can I use this on variable products? =

Yes. Enable the checkbox on the parent product. Its variations inherit the setting, whether their stock is tracked individually or by the parent. Variations without quantity tracking keep their manually assigned stock status.

= Does every product use a threshold of zero after activation? =

No. The override is off by default. Only products with the checkbox enabled use it.

= How do I return a product to the store-wide threshold? =

Uncheck "Ignore out-of-stock threshold" and update the product. Its stock status and tracked variation stock statuses are recalculated immediately.

= Are inventory emails and stock reports changed? =

No. WooCommerce's inventory emails and quantity-based stock reports still use their existing store-wide settings. A product can therefore remain available for sale while appearing in a threshold-based report or generating an inventory notification.

= What happens when I deactivate or delete the plugin? =

The plugin stops applying the override. WooCommerce applies its normal threshold the next time a product's stock is saved. To restore availability immediately before deactivating, uncheck the override and update each affected product first.

Product settings are retained on deactivation and deletion so reinstalling the plugin can reuse them. No products, orders, or stock quantities are deleted.

= Is this compatible with HPOS and the Cart and Checkout blocks? =

The plugin declares support for HPOS and the Cart and Checkout blocks. It adjusts saved product stock status through WooCommerce's product APIs and does not access order storage or replace checkout controls.

== Changelog ==

= 1.0.0 =

* Initial release with a per-product zero-stock threshold and inherited variation support.

== Upgrade Notice ==

= 1.0.0 =

Initial release. Enable the override on selected products under Product data > Inventory.
