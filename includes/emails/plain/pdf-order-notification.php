<?php
/**
 * Order Notification email (plain text)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

echo $email_heading . "\n\n";

echo "****************************************************\n\n";

// Some of the default actions are disabled in this email because they may result in unexpected output.
// For example when credit notes are issued for unpaid orders/invoices, woocommerce will still display
// payment instructions!
// do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text );

echo sprintf( __( 'Order number: %s', 'woocommerce'), $order->get_order_number() ) . "\n";
echo sprintf( __( 'Order date: %s', 'woocommerce'), date_i18n( wc_date_format(), strtotime( $order->order_date ) ) ) . "\n";

echo "\n";

echo $email_body;

echo "\n";

do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text );

echo "\n";

if ( $include_customer_details == 'yes' ) {
	switch ( $order->get_status() ) {
		case "completed" :
			echo $order->email_order_items_table( $order->is_download_permitted(), false, true, '', '', true );
		break;
		case "processing" :
			echo $order->email_order_items_table( $order->is_download_permitted(), true, true, '', '', true );
		break;
		default :
			echo $order->email_order_items_table( $order->is_download_permitted(), true, false, '', '', true );
		break;
	}

	echo "----------\n\n";

	if ( $totals = $order->get_order_item_totals() ) {
		foreach ( $totals as $total ) {
			echo $total['label'] . "\t " . $total['value'] . "\n";
		}
	}
}


echo "\n****************************************************\n\n";

// do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text );

if ( $include_customer_details == 'yes' ) {
	echo __( 'Customer details', 'woocommerce' ) . "\n";

	if ( $order->billing_email )
		echo __( 'Email:', 'woocommerce' ); echo $order->billing_email . "\n";

	if ( $order->billing_phone )
		echo __( 'Tel:', 'woocommerce' ); ?> <?php echo $order->billing_phone . "\n";

	wc_get_template( 'emails/plain/email-addresses.php', array( 'order' => $order ) );

	echo "\n****************************************************\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
