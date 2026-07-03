# Warehouse Picklist for WooCommerce

Printable pick lists and a **tablet-friendly pick mode** for WooCommerce orders, **grouped and ordered by product category to match your warehouse layout**. Add your logo and a footer note, and the printout doubles as a simple shipment insert.

Most packing-slip plugins print order items in whatever order WooCommerce stores them. This one prints them in the order you actually walk your warehouse — drag your product categories into your shelf order once, and every pick list follows it. Or skip paper entirely: open the pick mode on a tablet, tap items as you pick them, and get a verifiable audit trail on the order.

## Features

- **Pick order that matches your warehouse** — drag & drop your product categories into shelf order (WooCommerce → Picklist → Category order). New categories are appended automatically.
- **Tablet pick mode** — a full-screen, touch-friendly picking view (WooCommerce → Picking): a queue of orders to pick, tap-to-check rows in your warehouse order, mark items as missing, and complete the order with one tap.
- **Audit trail** — every pick is stored per item (who, when, status), and completing an order writes a WooCommerce order note ("Picking completed: 11/12 rows picked, missing: X · 4 min · Name"). Printed lists show the verified state (✓ / !) and who picked the order.
- **Picker role** — a lightweight *Warehouse Picker* role with just the `whpl_pick` capability, so warehouse staff don't need shop manager access. Admins and shop managers can pick out of the box.
- **One-click printing** — a *Print pick list* button on every order edit screen opens a clean, print-ready view.
- **Grouped by category** — items are bucketed under category headings in your pick order; a product in several categories is placed under the earliest one. Items without a category (or with a deleted product) end up under *Other*.
- **Branding** — upload a logo (or fall back to a text logo), set your business name, and add a free-form footer note.
- **Configurable columns** — toggle SKU and pick checkboxes; optionally show a package size column read from any product meta key (ACF field names work as-is).
- **HPOS compatible**, works with classic and High-Performance Order Storage.
- **Auto-updates from GitHub** — new releases show up on your Plugins/Updates screen like any other plugin (native WP `Update URI` mechanism, no updater library).
- Translation-ready; Finnish translation included.

## Pick mode on a tablet

1. Create a user with the *Warehouse Picker* role (or use a shop manager account).
2. On the tablet, log in to WordPress once (tick "Remember me") and open **WooCommerce → Picking** — or bookmark the queue URL (`wp-admin/admin-post.php?action=whpl_pick_queue`) to the home screen.
3. Open an order from the queue, tap rows as you pick them, use the **!** button for missing items, and finish with **Mark order as picked**.
4. The completion is recorded on the order as a note, and the printable pick list shows the verified state.

Orders shown in the queue default to the *processing* status; filter with `whpl_pick_queue_statuses`. Hook into `whpl_pick_completed` to automate e.g. a status change when picking finishes.

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
