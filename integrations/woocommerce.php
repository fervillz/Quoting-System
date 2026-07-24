Exit code: 0
Wall time: 0.5 seconds
Output:
<?php
/** WooCommerce payment orders for quote deposits and final balances. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creates a payable WooCommerce order without needing a temporary product.
 * The payment link WooCommerce creates is private to that order and can be
 * safely sent to the quote customer.
 */
function qs_create_payment_order( $quote_id, $payment_type ) {
	if ( ! function_exists( 'wc_create_order' ) || ! in_array( $payment_type, array( 'deposit', 'balance' ), true ) ) { return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not available.' ); }
	if ( ! qs_can_view_quote_document( $quote_id ) ) { return new WP_Error( 'forbidden', 'You cannot create a payment order for this quote.' ); }
	$existing = get_post_meta( $quote_id, '_qs_' . $payment_type . '_order_id', true );
	if ( $existing && wc_get_order( $existing ) ) { return (int) $existing; }
	$amount = 'deposit' === $payment_type ? qs_calculate_deposit( $quote_id ) : qs_calculate_balance( $quote_id );
	if ( $amount <= 0 ) { return new WP_Error( 'invalid_amount', 'This payment amount must be greater than zero.' ); }
	$quote = get_post( $quote_id );
	$order = wc_create_order( array( 'customer_id' => $quote ? (int) $quote->post_author : 0 ) );
	if ( is_wp_error( $order ) ) { return $order; }
	$quote_number = get_post_meta( $quote_id, '_quote_number', true );
	$fee = new WC_Order_Item_Fee();
	$fee->set_name( sprintf( '%s for quote %s', 'deposit' === $payment_type ? '30% deposit' : 'Final balance', $quote_number ) );
	$fee->set_amount( $amount );
	$fee->set_total( $amount );
	$order->add_item( $fee );
	$order->set_billing_first_name( get_post_meta( $quote_id, '_customer_name', true ) );
	$order->set_billing_email( get_post_meta( $quote_id, '_customer_email', true ) );
	$order->update_meta_data( '_qs_quote_id', $quote_id );
	$order->update_meta_data( '_qs_payment_type', $payment_type );
	$order->calculate_totals();
	$order->save();
	update_post_meta( $quote_id, '_qs_' . $payment_type . '_order_id', $order->get_id() );
	update_post_meta( $quote_id, '_qs_' . $payment_type . '_payment_url', $order->get_checkout_payment_url() );
	if ( 'deposit' === $payment_type ) {
		update_post_meta( $quote_id, '_qs_locked_deposit_amount', $amount );
	}
	return $order->get_id();
}

/** Returns the WooCommerce "pay for order" URL for a quote payment. */
function qs_get_quote_payment_url( $quote_id, $payment_type ) {
	$order_id = absint( get_post_meta( $quote_id, '_qs_' . $payment_type . '_order_id', true ) );
	$order = $order_id ? wc_get_order( $order_id ) : false;
	return $order ? $order->get_checkout_payment_url() : '';
}

/** Updates the quote only after WooCommerce confirms a payment. */
function qs_handle_payment_complete( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }
	$quote_id = absint( $order->get_meta( '_qs_quote_id' ) );
	$type = $order->get_meta( '_qs_payment_type' );
	if ( ! $quote_id || ! in_array( $type, array( 'deposit', 'balance' ), true ) ) { return; }
	qs_update_quote_status( $quote_id, 'deposit' === $type ? 'deposit_paid' : 'paid_in_full' );
}
add_action( 'woocommerce_payment_complete', 'qs_handle_payment_complete' );
add_action( 'woocommerce_order_status_processing', 'qs_handle_payment_complete' );
add_action( 'woocommerce_order_status_completed', 'qs_handle_payment_complete' );

