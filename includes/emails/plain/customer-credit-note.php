<?php
/**
 * Customer invoice email (plain text)
 *
 * @author		WooThemes
 * @package		WooCommerce/Templates/Emails/Plain
 * @version		2.2.0
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

// do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text );

echo "\n";

/* HIDDEN BY DEFAULT! Woocommerce order items table

$refunds = $order->get_refunds();
foreach ($refunds as $refund) {

	switch ( $refund->get_status() ) {
		case "completed" :
			echo $refund->email_order_items_table( $order->is_download_permitted(), false, true, '', '', true );
		break;
		case "processing" :
			echo $refund->email_order_items_table( $order->is_download_permitted(), true, true, '', '', true );
		break;
		default :
			echo $refund->email_order_items_table( $order->is_download_permitted(), true, false, '', '', true );
		break;
	}

	echo "----------\n\n";

	if ( $totals = $refund->get_order_item_totals() ) {
		foreach ( $totals as $total ) {
			echo $total['label'] . "\t " . $total['value'] . "\n";
		}
	}
	echo "\n****************************************************\n\n";
}

*/

// do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text );

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
