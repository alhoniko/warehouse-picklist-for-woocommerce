<?php
/**
 * Customer email: "your order is being prepared" — sent when picking starts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WHPL_Email_Order_Picking extends WC_Email {

	public function __construct() {
		$this->id             = 'whpl_order_picking';
		$this->customer_email = true;
		$this->title          = __( 'Order in picking', 'warehouse-picklist' );
		$this->description    = __( 'Sent to the customer when picking of their order starts.', 'warehouse-picklist' );
		$this->template_html  = 'emails/whpl-order-picking.php';
		$this->template_plain = 'emails/plain/whpl-order-picking.php';
		$this->template_base  = WHPL_PLUGIN_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_number}' => '',
		);

		add_action( 'whpl_order_status_picking_notification', array( $this, 'trigger' ), 10, 1 );

		parent::__construct();
	}

	public function get_default_subject() {
		return __( '[{site_title}] Your order #{order_number} is being prepared', 'warehouse-picklist' );
	}

	public function get_default_heading() {
		return __( 'Your order is being prepared', 'warehouse-picklist' );
	}

	/**
	 * @param int $order_id Order ID.
	 */
	public function trigger( $order_id ) {
		$this->setup_locale();

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->object                         = $order;
			$this->recipient                      = $order->get_billing_email();
			$this->placeholders['{order_number}'] = $order->get_order_number();
		}

		if ( $this->object && $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this,
		), '', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this,
		), '', $this->template_base );
	}
}
