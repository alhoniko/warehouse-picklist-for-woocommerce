=== Warehouse Picklist for WooCommerce ===
Contributors: nikoalho
Tags: woocommerce, pick list, picking list, packing slip, warehouse
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Printable pick lists for WooCommerce orders, grouped and ordered by product category to match your warehouse layout.

== Description ==

Most packing-slip plugins print order items in whatever order WooCommerce stores them. Warehouse Picklist prints them in the order you actually walk your warehouse — drag your product categories into your shelf order once, and every pick list follows it.

Add your logo and a footer note, and the same printout doubles as a simple shipment insert.

= Features =

* Drag & drop your product categories into shelf order — every pick list follows it
* One-click *Print pick list* button on every order edit screen
* Items grouped under category headings; uncategorized items under *Other*
* Logo, business name and free-form footer note for branding
* Toggle SKU column and pick checkboxes
* Optional package size column read from any product meta key (ACF field names work as-is)
* HPOS compatible, translation-ready, Finnish translation included
* Auto-updates from GitHub releases (native WP Update URI mechanism)

== Installation ==

1. Upload and activate the plugin. Requires WooCommerce.
2. Configure under WooCommerce → Picklist.
3. Drag your categories into warehouse order under WooCommerce → Picklist → Category order.
4. Open any order and click *Print pick list*.

== Frequently Asked Questions ==

= Does it generate PDFs? =

No — it opens a print-optimized HTML view and uses the browser's print dialog (which can also save as PDF).

= Who can print pick lists? =

Users with the `manage_woocommerce` capability (shop managers and admins).

== Changelog ==

= 1.0.0 =
* Initial release.
