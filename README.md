# Stock Threshold Override for WooCommerce

Let selected products and their variations sell down to zero stock while the rest of the store uses its normal out-of-stock threshold.

## Usage

In the classic product editor, open **Product data > Inventory**, enable **Ignore out-of-stock threshold**, and update the product. Stock quantity tracking must be enabled for the product or its variations. All variations inherit the parent product's setting. Backorder settings still apply.

This changes stock availability. Inventory notification emails and quantity-based stock reports retain their existing store-wide settings. See [readme.txt](readme.txt) for installation, scope, and deactivation behavior.

## Requirements

- WordPress 6.5 or newer.
- WooCommerce 9.0 or newer.
- PHP 7.4 or newer.

There are no runtime dependencies beyond WordPress and WooCommerce, and no JavaScript or asset build step.

## Development checks

Install the development tools and run the coding standards and PHP compatibility checks:

```sh
composer install
composer lint
```

The functional suite loads the integration class directly into an isolated local WordPress test installation. It does not activate the plugin. Use a disposable site with WooCommerce available; the test runner refuses to run without the explicit test-installation marker:

```sh
wp --path=/path/to/disposable-wordpress option update mhsto_test_installation 1
wp --path=/path/to/disposable-wordpress eval-file /path/to/stock-threshold-override-for-woocommerce/tests/integration.php
```

The test site must use `WP_ENVIRONMENT_TYPE` set to `local` or `development`. Tests use a request-scoped threshold of 3, create their own products, and delete those products on completion or failure. They cover persistence, catalog visibility, stock changes, backorders, permissions, form handling, and variation inheritance. No stock quantity or configuration belonging to an existing product is changed.

## Release package

```sh
python3 tools/build-release.py
```

This creates a versioned ZIP and SHA-256 checksum in `dist/`. Packaging uses an allowlist: the plugin entrypoint, `includes/`, `languages/`, `readme.txt`, and `LICENSE`. Development tools and tests are excluded.
