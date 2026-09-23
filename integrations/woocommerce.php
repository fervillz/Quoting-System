<?php
/** WooCommerce payment orders for quote deposits and final balances. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Return the amount an unpaid Quote System order should currently request.
 */
function qs_current_payment_order_amount( $quote_id, $payment_type ) {
	if ( 'deposit' === $payment_type ) {
		// Before payment, the deposit must follow any permitted quote edits.
		return round( qs_calculate_total( $quote_id ) * 0.30, 2 );
	}

	return round( qs_calculate_balance( $quote_id ), 2 );
}

/**
 * Keep an existing unpaid WooCommerce payment order aligned with Quote CPT
 * pricing. Paid orders are immutable; the locked deposit remains the amount
 * actually requested/paid and later admin changes flow into the final balance.
 */
function qs_sync_quote_payment_order( $quote_id, $payment_type ) {
	if ( ! function_exists( 'wc_get_order' ) || ! in_array( $payment_type, array( 'deposit', 'balance' ), true ) ) {
		return false;
	}

	$order_id = absint( get_post_meta( $quote_id, '_qs_' . $payment_type . '_order_id', true ) );
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		return false;
	}

	if ( method_exists( $order, 'is_paid' ) && $order->is_paid() ) {
		return false;
	}
	if ( $order->has_status( array( 'processing', 'completed', 'refunded', 'cancelled' ) ) ) {
		return false;
	}

	$amount = qs_current_payment_order_amount( $quote_id, $payment_type );
	if ( $amount <= 0 ) {
		return false;
	}

	$fee_items = $order->get_items( 'fee' );
	$fee       = $fee_items ? reset( $fee_items ) : false;
	if ( ! $fee instanceof WC_Order_Item_Fee ) {
		$quote_number = get_post_meta( $quote_id, '_quote_number', true );
		$fee          = new WC_Order_Item_Fee();
		$fee->set_name( sprintf( '%s for quote %s', 'deposit' === $payment_type ? '30% deposit' : 'Final balance', $quote_number ) );
		$order->add_item( $fee );
	}

	$fee->set_amount( $amount );
	$fee->set_total( $amount );
	$fee->save();

	$order->set_billing_first_name( get_post_meta( $quote_id, '_customer_name', true ) );
	$order->set_billing_email( get_post_meta( $quote_id, '_customer_email', true ) );
	$order->calculate_totals();
	$order->save();

	update_post_meta( $quote_id, '_qs_' . $payment_type . '_payment_url', $order->get_checkout_payment_url() );

	if ( 'deposit' === $payment_type ) {
		update_post_meta( $quote_id, '_qs_locked_deposit_amount', $amount );
	}

	return true;
}

/**
 * Re-sync any currently unpaid deposit/final-balance order when the Quote CPT
 * price changes. This covers frontend Builder edits and backend office pricing
 * adjustments (Subtotal, Discount, Additional Charges and Delivery Fee).
 */
function qs_sync_open_payment_orders_on_price_change( $meta_id, $quote_id, $meta_key, $meta_value ) {
	static $syncing = false;

	if (
		$syncing ||
		'quote' !== get_post_type( $quote_id ) ||
		! in_array( $meta_key, array( '_subtotal', '_discount', '_additional_charges', '_shipping' ), true )
	) {
		return;
	}

	if (
		! get_post_meta( $quote_id, '_qs_deposit_order_id', true ) &&
		! get_post_meta( $quote_id, '_qs_balance_order_id', true )
	) {
		return;
	}

	$syncing = true;
	qs_sync_quote_payment_order( $quote_id, 'deposit' );
	qs_sync_quote_payment_order( $quote_id, 'balance' );
	$syncing = false;
}
add_action( 'added_post_meta', 'qs_sync_open_payment_orders_on_price_change', 100, 4 );
add_action( 'updated_post_meta', 'qs_sync_open_payment_orders_on_price_change', 100, 4 );

/**
 * Creates a payable WooCommerce order without needing a temporary product.
 * The payment link WooCommerce creates is private to that order and can be
 * safely sent to the quote customer.
 */
function qs_create_payment_order( $quote_id, $payment_type ) {
	if ( ! function_exists( 'wc_create_order' ) || ! in_array( $payment_type, array( 'deposit', 'balance' ), true ) ) { return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not available.' ); }
	if ( ! qs_can_view_quote_document( $quote_id ) ) { return new WP_Error( 'forbidden', 'You cannot create a payment order for this quote.' ); }
	$existing = get_post_meta( $quote_id, '_qs_' . $payment_type . '_order_id', true );
	if ( $existing && wc_get_order( $existing ) ) {
		qs_sync_quote_payment_order( $quote_id, $payment_type );
		return (int) $existing;
	}
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
	$type     = $order->get_meta( '_qs_payment_type' );
	if ( ! $quote_id || ! in_array( $type, array( 'deposit', 'balance' ), true ) ) { return; }

	$target_status = 'deposit' === $type ? 'deposit_paid' : 'paid_in_full';
	qs_update_quote_status( $quote_id, $target_status );

	// WooCommerce can fire payment/status hooks more than once. Keep the
	// Quote System notification emails idempotent for each payment stage.
	$notice_key = '_qs_' . $type . '_payment_notifications_sent';
	if ( get_post_meta( $quote_id, $notice_key, true ) ) {
		return;
	}

	update_post_meta( $quote_id, $notice_key, current_time( 'mysql' ) );
	qs_email_admin_payment_received( $quote_id, $type, $order_id );

	if ( 'balance' === $type ) {
		qs_email_quote_paid_in_full( $quote_id );
	}
}
add_action( 'woocommerce_payment_complete', 'qs_handle_payment_complete' );
add_action( 'woocommerce_order_status_processing', 'qs_handle_payment_complete' );
add_action( 'woocommerce_order_status_completed', 'qs_handle_payment_complete' );

/**
 * Quote System payment orders are fee-only payment vehicles, not normal shop
 * purchases. The Quote System already sends its own branded customer emails
 * for deposit requests, final-balance requests and payment completion.
 *
 * Suppress WooCommerce's automatic customer status emails for these orders so
 * the Joiner does not receive duplicate/mismatched "order received" or
 * "order is on its way" messages with an empty Product table.
 *
 * Normal WooCommerce product orders are completely unaffected.
 */
function qs_is_quote_payment_order( $order ) {
	if ( is_numeric( $order ) && function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( absint( $order ) );
	}

	if ( ! $order instanceof WC_Order ) {
		return false;
	}

	$quote_id     = absint( $order->get_meta( '_qs_quote_id' ) );
	$payment_type = (string) $order->get_meta( '_qs_payment_type' );

	return $quote_id > 0 && in_array( $payment_type, array( 'deposit', 'balance' ), true );
}

function qs_disable_customer_order_email_for_quote_payment( $enabled, $object = null, $email = null ) {
	return qs_is_quote_payment_order( $object ) ? false : $enabled;
}

/*
 * These are the automatic WooCommerce customer emails that duplicate or
 * conflict with the Quote System workflow. customer_invoice is included so an
 * accidental manual Woo "invoice" action cannot send a second payment email.
 */
foreach (
	array(
		'customer_on_hold_order',
		'customer_processing_order',
		'customer_completed_order',
		'customer_invoice',
	) as $qs_customer_email_id
) {
	add_filter(
		'woocommerce_email_enabled_' . $qs_customer_email_id,
		'qs_disable_customer_order_email_for_quote_payment',
		100,
		3
	);
}

/** Australian invoice/cart terminology: GST instead of generic Tax/VAT. */
function qs_woocommerce_gst_tax_label( $label ) {
	return 'GST';
}
add_filter( 'woocommerce_countries_tax_or_vat', 'qs_woocommerce_gst_tax_label' );

function qs_woocommerce_gst_inc_label( $label ) {
	return '(incl. GST)';
}
add_filter( 'woocommerce_countries_inc_tax_or_vat', 'qs_woocommerce_gst_inc_label' );

function qs_woocommerce_gst_ex_label( $label ) {
	return '(ex. GST)';
}
add_filter( 'woocommerce_countries_ex_tax_or_vat', 'qs_woocommerce_gst_ex_label' );

/** Ensure order-detail/email/invoice total rows use GST wording. */
function qs_woocommerce_gst_order_item_totals( $total_rows, $order, $tax_display ) {
	foreach ( $total_rows as &$row ) {
		if ( isset( $row['label'] ) ) {
			$row['label'] = preg_replace( '/\bTaxes?\b/i', 'GST', $row['label'] );
		}
	}
	unset( $row );

	return $total_rows;
}
add_filter( 'woocommerce_get_order_item_totals', 'qs_woocommerce_gst_order_item_totals', 20, 3 );

/** Catch WooCommerce's own translated phrases such as "incl. tax". */
function qs_woocommerce_gst_gettext( $translated, $text, $domain ) {
	if ( 'woocommerce' !== $domain ) {
		return $translated;
	}

	return preg_replace( '/\bTaxes?\b/i', 'GST', $translated );
}
add_filter( 'gettext', 'qs_woocommerce_gst_gettext', 20, 3 );
