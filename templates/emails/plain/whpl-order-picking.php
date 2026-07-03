<?php
/**
 * Order in picking — customer email (plain text).
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

printf(
	/* translators: %s: customer first name */
	esc_html__( 'Hi %s,', 'warehouse-picklist' ),
	esc_html( $order->get_billing_first_name() )
);
echo "\n\n";

echo esc_html__( 'We have started preparing your order at our warehouse. We will notify you when it is on its way.', 'warehouse-picklist' ) . "\n\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n" . esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
