Exit code: 0
Wall time: 0.5 seconds
Output:
<?php
/** WooCommerce payment orders for quote deposits and final balances. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Creates a payable WooCommerce order without needing a temporary product.
 * The order contains one clearly named fee and is permanently linked to quote.
 */
function qs_create_payment_order( $quote_id, $payment_type ) {
	if ( ! function_exists( 'wc_create_order' ) || ! in_array( $payment_type, array( 'deposit', 'balance' ), true ) ) { return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not available.' ); }
	if ( ! qs_can_view_quote_document( $quote_id ) ) { return new WP_Error( 'forbidden', 'You cannot create a payment order for this quote.' ); }
	$existing = get_post_meta( $quote_id, '_qs_' . $payment_type . '_order_id', true );
	if ( $existing && wc_get_order( $existing ) ) { return (int) $existing; }
	$amount = 'deposit' === $payment_type ? qs_calculate_deposit( $quote_id ) : qs_calculate_balance( $quote_id );
	if ( $amount <= 0 ) { return new WP_Error( 'invalid_amount', 'This payment amount must be greater than zero.' ); }
	$order = wc_create_order( array( 'customer_id' => get_current_user_id() ) );
	if ( is_wp_error( $order ) ) { return $order; }
	$quote_number = get_post_meta( $quote_id, '_quote_number', true );
	$order->add_fee( sprintf( '%s for quote %s', 'deposit' === $payment_type ? 'Deposit' : 'Final balance', $quote_number ), $amount, false );
	$order->set_billing_first_name( get_post_meta( $quote_id, '_customer_name', true ) );
	$order->set_billing_email( get_post_meta( $quote_id, '_customer_email', true ) );
	$order->update_meta_data( '_qs_quote_id', $quote_id );
	$order->update_meta_data( '_qs_payment_type', $payment_type );
	$order->calculate_totals();
	$order->save();
	update_post_meta( $quote_id, '_qs_' . $payment_type . '_order_id', $order->get_id() );
	return $order->get_id();
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

