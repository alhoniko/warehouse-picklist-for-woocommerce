<?php
/**
 * Optional order status automation: Processing → In picking → Picked,
 * plus a customer email when picking starts.
 *
 * The statuses are always registered (so historical orders keep rendering
 * even if automation is later switched off); the transitions themselves run
 * only when the "status_automation" setting is enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is status automation enabled in settings?
 *
 * @return bool
 */
function whpl_status_automation_enabled() {
	$settings = whpl_get_settings();

	return ! empty( $settings['status_automation'] );
}

add_action( 'init', function () {
	register_post_status( 'wc-picking', array(
		'label'                     => __( 'In picking', 'warehouse-picklist' ),
		'public'                    => false,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		/* translators: %s: number of orders */
		'label_count'               => _n_noop( 'In picking <span class="count">(%s)</span>', 'In picking <span class="count">(%s)</span>', 'warehouse-picklist' ),
	) );
	register_post_status( 'wc-picked', array(
		'label'                     => __( 'Picked', 'warehouse-picklist' ),
		'public'                    => false,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		/* translators: %s: number of orders */
		'label_count'               => _n_noop( 'Picked <span class="count">(%s)</span>', 'Picked <span class="count">(%s)</span>', 'warehouse-picklist' ),
	) );
} );

add_filter( 'wc_order_statuses', function ( $statuses ) {
	$updated = array();
	foreach ( $statuses as $key => $label ) {
		$updated[ $key ] = $label;
		if ( 'wc-processing' === $key ) {
			$updated['wc-picking'] = __( 'In picking', 'warehouse-picklist' );
			$updated['wc-picked']  = __( 'Picked', 'warehouse-picklist' );
		}
	}

	return isset( $updated['wc-picking'] ) ? $updated : array_merge( $updated, array(
		'wc-picking' => __( 'In picking', 'warehouse-picklist' ),
		'wc-picked'  => __( 'Picked', 'warehouse-picklist' ),
	) );
} );

// Status badge colours on the admin order list.
add_action( 'admin_head', function () {
	echo '<style>.order-status.status-picking{background:#dbe4ff;color:#1d39c4}.order-status.status-picked{background:#e7f6ec;color:#1a7a2e}</style>';
} );

/**
 * Transition helpers — called from the picking flow when automation is on.
 */
function whpl_status_on_pick_started( $order ) {
	if ( whpl_status_automation_enabled() && 'processing' === $order->get_status() ) {
		$order->update_status( 'picking' );
	}
}

function whpl_status_on_pick_completed( $order ) {
	if ( whpl_status_automation_enabled() && in_array( $order->get_status(), array( 'processing', 'picking' ), true ) ) {
		$order->update_status( 'picked' );
	}
}

function whpl_status_on_pick_reopened( $order ) {
	if ( whpl_status_automation_enabled() && 'picked' === $order->get_status() ) {
		$order->update_status( 'picking' );
	}
}

// Register the customer email with WooCommerce (it appears under
// WooCommerce → Settings → Emails with its own enable toggle).
add_filter( 'woocommerce_email_classes', function ( $emails ) {
	require_once WHPL_PLUGIN_DIR . 'includes/emails/class-whpl-email-order-picking.php';
	$emails['WHPL_Email_Order_Picking'] = new WHPL_Email_Order_Picking();

	return $emails;
} );

// Fire the email trigger when an order moves into picking.
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to ) {
	if ( 'picking' === $to ) {
		WC()->mailer(); // Ensure email classes are hooked before the action fires.
		do_action( 'whpl_order_status_picking_notification', $order_id );
	}
}, 10, 3 );
