<?php
/**
 * Order in picking — customer email (HTML).
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php printf(
	/* translators: %s: customer first name */
	esc_html__( 'Hi %s,', 'warehouse-picklist' ),
	esc_html( $order->get_billing_first_name() )
); ?></p>

<p><?php esc_html_e( 'We have started preparing your order at our warehouse. We will notify you when it is on its way.', 'warehouse-picklist' ); ?></p>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_footer', $email );
