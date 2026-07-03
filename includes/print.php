<?php
/**
 * "Print pick list" button on the order edit screen + the printable view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'woocommerce_admin_order_data_after_order_details', function ( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=whpl_print_picklist&order_id=' . $order->get_id() ),
		'whpl_print_picklist_' . $order->get_id()
	);
	echo '<p><a href="' . esc_url( $url ) . '" class="button" target="_blank">'
		. esc_html__( 'Print pick list', 'warehouse-picklist' )
		. '</a></p>';
} );

add_action( 'admin_post_whpl_print_picklist', function () {
	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

	if ( ! $order_id || ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'warehouse-picklist' ) );
	}

	check_admin_referer( 'whpl_print_picklist_' . $order_id );

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_die( esc_html__( 'Order not found.', 'warehouse-picklist' ) );
	}

	header( 'Content-Type: text/html; charset=utf-8' );
	whpl_render_picklist( $order );
	exit;
} );

/**
 * Render the printable pick list HTML for an order.
 *
 * @param WC_Order $order Order to render.
 */
function whpl_render_picklist( $order ) {
	$settings         = whpl_get_settings();
	$package_meta_key = $settings['package_meta_key'];
	$category_order   = whpl_get_category_order();
	$position_by_term = array_flip( $category_order );

	$buckets      = array(); // term_id => rows.
	$other_bucket = array();

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		$row = array(
			'name'    => $item->get_name(),
			'qty'     => $item->get_quantity(),
			'sku'     => $product ? $product->get_sku() : '',
			'package' => '',
		);

		if ( $product && '' !== $package_meta_key ) {
			$package = get_post_meta( $product->get_id(), $package_meta_key, true );
			if ( '' === $package && $product->get_parent_id() ) {
				// Variations rarely carry the meta themselves; fall back to the parent.
				$package = get_post_meta( $product->get_parent_id(), $package_meta_key, true );
			}
			$row['package'] = is_scalar( $package ) ? (string) $package : '';
		}

		/**
		 * Filter a pick list row before rendering.
		 *
		 * Useful for store-specific tweaks, e.g. deriving a package size
		 * from the product name when the meta field is empty.
		 *
		 * @param array              $row     Row data: name, qty, sku, package.
		 * @param WC_Order_Item      $item    Order line item.
		 * @param WC_Product|false   $product Product, or false if deleted.
		 */
		$row = apply_filters( 'whpl_picklist_row', $row, $item, $product );

		// Categories live on the parent product for variations.
		$term_ids = array();
		if ( $product ) {
			$cat_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
			$term_ids       = wp_get_post_terms( $cat_product_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( is_wp_error( $term_ids ) ) {
				$term_ids = array();
			}
		}

		$best_position = null;
		$best_term_id  = null;
		foreach ( $term_ids as $term_id ) {
			if ( isset( $position_by_term[ $term_id ] ) ) {
				if ( null === $best_position || $position_by_term[ $term_id ] < $best_position ) {
					$best_position = $position_by_term[ $term_id ];
					$best_term_id  = $term_id;
				}
			}
		}

		if ( null !== $best_term_id ) {
			$buckets[ $best_term_id ][] = $row;
		} else {
			$other_bucket[] = $row;
		}
	}

	$sort_by_name = function ( $a, $b ) {
		return strcasecmp( $a['name'], $b['name'] );
	};

	foreach ( $buckets as $term_id => $rows ) {
		usort( $rows, $sort_by_name );
		$buckets[ $term_id ] = $rows;
	}
	usort( $other_bucket, $sort_by_name );

	$terms_by_id = array();
	if ( ! empty( $buckets ) ) {
		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'include'    => array_keys( $buckets ),
		) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$terms_by_id[ $term->term_id ] = $term->name;
			}
		}
	}

	$logo_url = $settings['logo_id'] ? wp_get_attachment_image_url( $settings['logo_id'], 'medium' ) : '';
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="utf-8">
		<title><?php echo esc_html( sprintf(
			/* translators: %s: order number */
			__( 'Pick list — Order #%s', 'warehouse-picklist' ),
			$order->get_order_number()
		) ); ?></title>
		<style>
			body { font-family: sans-serif; padding: 24px; color: #222; }
			.doc { max-width: 720px; }
			.head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 4px; }
			.head img { max-height: 60px; max-width: 240px; }
			h1 { font-size: 20px; margin: 0 0 4px; }
			.text-logo { font-size: 22px; font-weight: 700; white-space: nowrap; }
			.meta { color: #555; margin-bottom: 24px; }
			h2 { font-size: 15px; background: #f0f0f0; padding: 6px 10px; margin-top: 28px; }
			table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
			th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ddd; font-size: 14px; overflow: hidden; }
			th.check, td.check { width: 30px; }
			td.check span { display: inline-block; width: 18px; height: 18px; border: 1px solid #333; }
			th.qty, td.qty { width: 60px; text-align: center; }
			th.package, td.package { width: 110px; }
			th.sku, td.sku { width: 90px; }
			.footer-note { margin-top: 32px; padding-top: 12px; border-top: 1px solid #ddd; color: #555; font-size: 14px; white-space: pre-line; }
			.print-btn { margin-bottom: 20px; }
			/* Zero page margins hide the browser's printed header/footer
			   (URL, date, title live in the margin area); the body padding
			   below provides the visual margins instead. */
			@page { margin: 0; }
			@media print {
				.print-btn { display: none; }
				body { padding: 14mm 16mm; }
			}
		</style>
	</head>
	<body>
		<div class="doc">
			<button class="print-btn" onclick="window.print()"><?php esc_html_e( 'Print', 'warehouse-picklist' ); ?></button>

			<div class="head">
				<h1><?php esc_html_e( 'Pick list', 'warehouse-picklist' ); ?></h1>
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $settings['business_name'] ); ?>">
				<?php elseif ( '' !== $settings['business_name'] ) : ?>
					<div class="text-logo"><?php echo esc_html( $settings['business_name'] ); ?></div>
				<?php endif; ?>
			</div>

			<div class="meta">
				<?php echo esc_html( sprintf(
					/* translators: %s: order number */
					__( 'Order #%s', 'warehouse-picklist' ),
					$order->get_order_number()
				) ); ?>
				&middot; <?php echo esc_html( $order->get_formatted_billing_full_name() ); ?>
				&middot; <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
			</div>

			<?php
			$render_table = function ( $rows ) use ( $settings, $package_meta_key ) {
				?>
				<table>
					<thead>
						<tr>
							<?php if ( $settings['show_checkboxes'] ) : ?>
								<th class="check"></th>
							<?php endif; ?>
							<th class="qty"><?php esc_html_e( 'Qty', 'warehouse-picklist' ); ?></th>
							<th class="product"><?php esc_html_e( 'Product', 'warehouse-picklist' ); ?></th>
							<?php if ( '' !== $package_meta_key ) : ?>
								<th class="package"><?php esc_html_e( 'Package', 'warehouse-picklist' ); ?></th>
							<?php endif; ?>
							<?php if ( $settings['show_sku'] ) : ?>
								<th class="sku"><?php esc_html_e( 'SKU', 'warehouse-picklist' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<?php if ( $settings['show_checkboxes'] ) : ?>
									<td class="check"><span></span></td>
								<?php endif; ?>
								<td class="qty"><?php echo esc_html( $row['qty'] ); ?></td>
								<td class="product"><?php echo esc_html( $row['name'] ); ?></td>
								<?php if ( '' !== $package_meta_key ) : ?>
									<td class="package"><?php echo esc_html( $row['package'] ); ?></td>
								<?php endif; ?>
								<?php if ( $settings['show_sku'] ) : ?>
									<td class="sku"><?php echo esc_html( $row['sku'] ); ?></td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			};

			foreach ( $category_order as $term_id ) :
				if ( empty( $buckets[ $term_id ] ) ) {
					continue;
				}
				?>
				<h2><?php echo esc_html( isset( $terms_by_id[ $term_id ] ) ? $terms_by_id[ $term_id ] : '' ); ?></h2>
				<?php $render_table( $buckets[ $term_id ] ); ?>
			<?php endforeach; ?>

			<?php if ( ! empty( $other_bucket ) ) : ?>
				<h2><?php esc_html_e( 'Other', 'warehouse-picklist' ); ?></h2>
				<?php $render_table( $other_bucket ); ?>
			<?php endif; ?>

			<?php if ( '' !== $settings['footer_note'] ) : ?>
				<div class="footer-note"><?php echo esc_html( $settings['footer_note'] ); ?></div>
			<?php endif; ?>
		</div>
	</body>
	</html>
	<?php
}
