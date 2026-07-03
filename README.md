# Warehouse Picklist for WooCommerce

Printable pick lists for WooCommerce orders, **grouped and ordered by product category to match your warehouse layout**. Add your logo and a footer note, and the same printout doubles as a simple shipment insert.

Most packing-slip plugins print order items in whatever order WooCommerce stores them. This one prints them in the order you actually walk your warehouse — drag your product categories into your shelf order once, and every pick list follows it.

## Features

- **Pick order that matches your warehouse** — drag & drop your product categories into shelf order (WooCommerce → Picklist → Category order). New categories are appended automatically.
- **One-click printing** — a *Print pick list* button on every order edit screen opens a clean, print-ready view.
- **Grouped by category** — items are bucketed under category headings in your pick order; a product in several categories is placed under the earliest one. Items without a category (or with a deleted product) end up under *Other*.
- **Branding** — upload a logo, set your business name, and add a free-form footer note (e.g. a thank-you message if the list ships with the order).
- **Configurable columns** — toggle SKU and pick checkboxes; optionally show a package size column read from any product meta key (ACF field names work as-is).
- **HPOS compatible**, works with classic and High-Performance Order Storage.
- **Auto-updates from GitHub** — new releases show up on your Plugins/Updates screen like any other plugin (native WP `Update URI` mechanism, no updater library).
- Translation-ready; Finnish translation included.

## Installation

1. Download the latest release ZIP from [Releases](https://github.com/alhoniko/warehouse-picklist-for-woocommerce/releases).
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin** and upload the ZIP.
3. Activate. Requires WooCommerce.
4. Configure under **WooCommerce → Picklist**.

## Usage

1. **WooCommerce → Picklist → Settings**: upload your logo, set the business name, footer note, and column options.
2. **WooCommerce → Picklist → Category order**: drag categories into the order you walk your warehouse and save.
3. Open any order and click **Print pick list**.

### Package size column

If your products have a package size stored in post meta (for example an ACF text field like `product_package_size` with values like `12x65g` or `3,0kg`), enter the meta key in settings and the pick list gains a *Package* column. For variable products the parent product's meta is used as a fallback.

## FAQ

**Does it generate PDFs?**
No — it opens a print-optimized HTML view and uses the browser's print dialog (which can also save as PDF). No PDF library, no bloat.

**Who can print pick lists?**
Users with the `manage_woocommerce` capability (shop managers and admins).

## Development

No build step. Plain PHP + a small jQuery admin script.

```
warehouse-picklist.php    Plugin bootstrap, HPOS declaration
includes/settings.php     Admin page + settings storage
includes/category-order.php  Pick order logic + drag & drop UI
includes/print.php        Order button + printable view
assets/admin.js           Media picker + sortable
languages/                POT + Finnish translation
```

## License

GPLv2 or later. See [LICENSE](LICENSE).
