<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Email_PDF_Order_Notification' ) ) :

/**
 * Order Notification
 *
 * An email sent to the customer via admin.
 *
 * @class 		WC_Email_PDF_Order_Notification
 * @author 		WP Overnight
 * @extends 	WC_Email
 */
class WC_Email_PDF_Order_Notification extends WC_Email {

	var $find;
	var $replace;

	/**
	 * Constructor
	 */
	function __construct() {

		$this->id             = 'pdf_order_notification';
		$this->title          = __( 'Order Notification', 'wpo_wcpdf_pro' );
		$this->description    = __( 'Order Notification emails can be sent to specified email addresses, automatically & manually.', 'wpo_wcpdf_pro' );

		$this->template_html  = 'emails/pdf-order-notification.php';
		$this->template_plain = 'emails/plain/pdf-order-notification.php';
		$this->template_base  = trailingslashit( dirname(__FILE__) );

		$this->subject        = __( 'Order Notification for order {order_number} from {order_date}', 'wpo_wcpdf_pro');
		$this->heading        = __( 'Order Notification for order {order_number}', 'wpo_wcpdf_pro');
		$this->body           = __( 'An order has been placed.', 'wpo_wcpdf_pro');

		// Trigger according to settings
		$trigger = $this->get_option( 'trigger' );
		switch ($trigger) {
			case 'new_order':
				add_action( 'woocommerce_order_status_pending_to_processing_notification', array( $this, 'trigger' ) );
				add_action( 'woocommerce_order_status_pending_to_completed_notification', array( $this, 'trigger' ) );
				add_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $this, 'trigger' ) );
				add_action( 'woocommerce_order_status_failed_to_processing_notification', array( $this, 'trigger' ) );
				add_action( 'woocommerce_order_status_failed_to_completed_notification', array( $this, 'trigger' ) );
				add_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $this, 'trigger' ) );
				break;
			case 'processing':
				add_action( 'woocommerce_order_status_processing_notification', array( $this, 'trigger' ) );
				break;
			case 'completed':
				add_action( 'woocommerce_order_status_completed_notification', array( $this, 'trigger' ) );
				break;
			case 'paid':
				// may need to be triggered via woocommerce_email_actions (WC()->send_transactional_email)
				// rather than directly
				add_action( 'woocommerce_payment_complete_notification', array( $this, 'trigger' ) );
				break;
		}

		// Call parent constructor
		parent::__construct();

		$this->body           = $this->get_option( 'body', $this->body );
	}

	/**
	 * trigger function.
	 *
	 * @access public
	 * @return void
	 */
	function trigger( $order ) {
		if ( ! is_object( $order ) ) {
			$order = wc_get_order( absint( $order ) );
		}

		if ( $order ) {
			$this->object                  = $order;
			$this->recipient               = str_replace('{customer}', $this->object->billing_email, $this->get_option( 'recipient' ) );

			$this->find['order-date']      = '{order_date}';
			$this->find['order-number']    = '{order_number}';

			$this->replace['order-date']   = date_i18n( wc_date_format(), strtotime( $this->object->order_date ) );
			$this->replace['order-number'] = $this->object->get_order_number();
		}

		if ( ! $this->get_recipient() ) {
			return;
		}

		$result = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		do_action( 'wpo_wcpdf_pro_email_sent', $result, $this->id, $order );
	}

	/**
	 * get_subject function.
	 *
	 * @access public
	 * @return string
	 */
	function get_subject() {
		return apply_filters( 'woocommerce_email_subject_pdf_order_notification', $this->format_string( $this->subject ), $this->object );
	}

	/**
	 * get_heading function.
	 *
	 * @access public
	 * @return string
	 */
	function get_heading() {
		return apply_filters( 'woocommerce_email_heading_pdf_order_notification', $this->format_string( $this->heading ), $this->object );
	}

	/**
	 * get_body function.
	 *
	 * @access public
	 * @return string
	 */
	function get_body() {
		return apply_filters( 'woocommerce_email_body_pdf_order_notification', $this->format_string( $this->body ), $this->object );
	}

	/**
	 * get_content_html function.
	 *
	 * @access public
	 * @return string
	 */
	function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'order'						=> $this->object,
				'email_heading'				=> $this->get_heading(),
				'email_body'				=> $this->get_body(),
				'sent_to_admin'				=> false,
				'plain_text'				=> false,
				'include_items_table'		=> $this->get_option( 'items_table' ),
				'include_customer_details'	=> $this->get_option( 'customer_details' )
			), '',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * get_content_plain function.
	 *
	 * @access public
	 * @return string
	 */
	function get_content_plain() {
		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'order' 		=> $this->object,
				'email_heading' => $this->get_heading(),
				'email_body'    => $this->get_body(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'include_items_table'		=> $this->get_option( 'items_table' ),
				'include_customer_details'	=> $this->get_option( 'customer_details' )
			), '',
			$this->template_base
		);
		return ob_get_clean();
	}

    /**
     * Initialise Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields() {
    	$this->form_fields = array(
			'trigger' => array(
				'title' 		=> __( 'Trigger', 'wpo_wcpdf_pro' ),
				'type' 			=> 'select',
				'description' 	=> __( "Choose the status that should trigger this email. Note that the 'Paid' status only works for automated payment gateways (Paypal, Stripe, etc), not for BACS, COD & Cheque.", 'wpo_wcpdf_pro' ),
				'default' 		=> 'none',
				'class'			=> 'trigger',
				'options'		=> array(
					'none' 			=> __( 'Manual', 'wpo_wcpdf_pro' ),
					'new_order' 	=> __( 'Order placed', 'wpo_wcpdf_pro' ),
					'processing' 	=> __( 'Order processing', 'wpo_wcpdf_pro' ),
					'completed' 	=> __( 'Order completed', 'wpo_wcpdf_pro' ),
					'paid' 			=> __( 'Order paid', 'wpo_wcpdf_pro' ),
				)
			),
			'recipient' => array(
				'title' 		=> __( 'Recipient(s)', 'wpo_wcpdf_pro' ),
				'type' 			=> 'text',
				'description' 	=> __( 'Enter recipients (comma separated) for this email. Use {customer} to send this email to the customer.', 'wpo_wcpdf_pro' ),
				'placeholder' 	=> '',
				'default' 		=> ''
			),
			'subject' => array(
				'title' 		=> __( 'Email subject', 'wpo_wcpdf_pro' ),
				'type' 			=> 'text',
				'description' 	=> sprintf( __( 'Defaults to <code>%s</code>', 'wpo_wcpdf_pro' ), $this->subject ),
				'placeholder' 	=> '',
				'default' 		=> ''
			),
			'heading' => array(
				'title' 		=> __( 'Email heading', 'wpo_wcpdf_pro' ),
				'type' 			=> 'text',
				'description' 	=> sprintf( __( 'Defaults to <code>%s</code>', 'wpo_wcpdf_pro' ), $this->heading ),
				'placeholder' 	=> '',
				'default' 		=> ''
			),
			'body' => array(
				'title' 		=> __( 'Email body text', 'wpo_wcpdf_pro' ),
				'css' 			=> 'width:100%; height: 75px;',
				'type' 			=> 'textarea',
				'description' 	=> sprintf( __( 'Defaults to <code>%s</code>', 'wpo_wcpdf_pro' ), $this->body ),
				'placeholder' 	=> '',
				'default' 		=> $this->body
			),
			'items_table' => array(
				'title' 		=> __( 'Order items', 'wpo_wcpdf_pro' ),
				'type'          => 'checkbox',
				'label'         => __( 'Include order items table in email', 'wpo_wcpdf_pro' ),
				'default'       => 'yes'
			),
			'customer_details' => array(
				'title' 		=> __( 'Customer details', 'wpo_wcpdf_pro' ),
				'type'          => 'checkbox',
				'label'         => __( 'Include customer details in email', 'wpo_wcpdf_pro' ),
				'default'       => 'yes'
			),
			'email_type' => array(
				'title' 		=> __( 'Email type', 'wpo_wcpdf_pro' ),
				'type' 			=> 'select',
				'description' 	=> __( 'Choose which format of email to send.', 'wpo_wcpdf_pro' ),
				'default' 		=> 'html',
				'class'			=> 'email_type',
				'options'		=> array(
					'plain' 		=> __( 'Plain text', 'wpo_wcpdf_pro' ),
					'html' 			=> __( 'HTML', 'wpo_wcpdf_pro' ),
					'multipart' 	=> __( 'Multipart', 'wpo_wcpdf_pro' ),
				)
			)
		);
    }
}

endif;

return new WC_Email_PDF_Order_Notification();
