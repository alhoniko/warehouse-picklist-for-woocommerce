<?php
/**
 * Pick order of product categories: default order, drag-and-drop UI, AJAX save.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the category pick order as an array of term IDs.
 *
 * Uses the saved order if one exists (appending any categories created since),
 * otherwise falls back to a depth-first hierarchical order (parents followed
 * by their children, alphabetical within each level).
 *
 * @return int[]
 */
function whpl_get_category_order() {
	$saved = get_option( WHPL_ORDER_OPTION );
	$saved = is_array( $saved ) ? array_values( array_map( 'intval', $saved ) ) : array();

	$terms = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
	) );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return $saved;
	}

	if ( empty( $saved ) ) {
		return whpl_default_category_order( $terms );
	}

	foreach ( $terms as $term ) {
		if ( ! in_array( (int) $term->term_id, $saved, true ) ) {
			$saved[] = (int) $term->term_id;
		}
	}

	return $saved;
}

/**
 * Build the default order: depth-first walk of the category tree,
 * alphabetical within each level.
 *
 * @param WP_Term[] $terms Product category terms.
 * @return int[]
 */
function whpl_default_category_order( $terms ) {
	$children = array();
	foreach ( $terms as $term ) {
		$children[ (int) $term->parent ][] = $term;
	}
	foreach ( $children as &$group ) {
		usort( $group, function ( $a, $b ) {
			return strcasecmp( $a->name, $b->name );
		} );
	}
	unset( $group );

	$order = array();
	$walk  = function ( $parent_id ) use ( &$walk, &$order, $children ) {
		if ( empty( $children[ $parent_id ] ) ) {
			return;
		}
		foreach ( $children[ $parent_id ] as $term ) {
			$order[] = (int) $term->term_id;
			$walk( (int) $term->term_id );
		}
	};
	$walk( 0 );

	return $order;
}

/**
 * Render the "Category order" tab.
 */
function whpl_render_category_order_tab() {
	$order = whpl_get_category_order();

	$terms       = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
	$terms_by_id = array();
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$terms_by_id[ (int) $term->term_id ] = $term;
		}
	}
	?>
	<p><?php esc_html_e( 'Drag categories into the order you want them to appear on printed pick lists — typically the order you walk your warehouse.', 'warehouse-picklist' ); ?></p>
	<ul id="whpl-category-sortable" style="max-width:480px;list-style:none;margin:20px 0;padding:0;">
		<?php foreach ( $order as $term_id ) :
			if ( ! isset( $terms_by_id[ $term_id ] ) ) {
				continue;
			}
			$term   = $terms_by_id[ $term_id ];
			$parent = $term->parent && isset( $terms_by_id[ (int) $term->parent ] ) ? $terms_by_id[ (int) $term->parent ]->name : '';
			?>
			<li data-term-id="<?php echo esc_attr( $term_id ); ?>" style="padding:10px 14px;margin-bottom:6px;background:#fff;border:1px solid #ccd0d4;cursor:move;">
				<?php echo esc_html( $term->name ); ?>
				<?php if ( $parent ) : ?>
					<span style="color:#787c82;">&mdash; <?php echo esc_html( $parent ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<p>
		<button type="button" class="button button-primary" id="whpl-category-save"><?php esc_html_e( 'Save order', 'warehouse-picklist' ); ?></button>
		<button type="button" class="button" id="whpl-category-reset"><?php esc_html_e( 'Reset to default', 'warehouse-picklist' ); ?></button>
		<span id="whpl-category-status" style="margin-left:10px;"></span>
	</p>
	<?php
}

add_action( 'wp_ajax_whpl_save_category_order', function () {
	check_ajax_referer( 'whpl_admin', 'nonce' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error();
	}

	$order = isset( $_POST['order'] ) ? array_map( 'intval', (array) $_POST['order'] ) : array();
	update_option( WHPL_ORDER_OPTION, $order );

	wp_send_json_success();
} );

add_action( 'wp_ajax_whpl_reset_category_order', function () {
	check_ajax_referer( 'whpl_admin', 'nonce' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error();
	}

	delete_option( WHPL_ORDER_OPTION );

	wp_send_json_success();
} );
