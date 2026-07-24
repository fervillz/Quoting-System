Exit code: 0
Wall time: 0.5 seconds
Output:
<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Apply retail markup.
 *
 * Retail pricing = Trade price + 22.22%
 */
function qs_apply_retail_markup( $amount ) {

	return round(
		$amount * 1.2222,
		2
	);

}

/**
 * Calculate quote total.
 */
function qs_calculate_total( $quote_id ) {

	$subtotal = (float) get_post_meta(
		$quote_id,
		'_subtotal',
		true
	);

	$discount = (float) get_post_meta(
		$quote_id,
		'_discount',
		true
	);

	$additional_charges = (float) get_post_meta(
		$quote_id,
		'_additional_charges',
		true
	);
	// Shipping is entered by the office before a deposit is requested.  Keeping
	// it separate from "additional charges" makes it clear on both PDFs and the
	// WooCommerce order where the extra amount came from.
	$shipping = (float) get_post_meta( $quote_id, '_shipping', true );

	$total = $subtotal + $shipping - $discount + $additional_charges;

	return round( $total, 2 );

}

/**
 * Calculate deposit amount.
 *
 * Deposit = 30%
 */
function qs_calculate_deposit( $quote_id ) {
	// Once an order is made, its amount must never move when an administrator
	// later adjusts the balance.  The saved snapshot is the amount the customer
	// was actually asked to pay.
	$locked_deposit = get_post_meta( $quote_id, '_qs_locked_deposit_amount', true );
	if ( '' !== $locked_deposit && false !== $locked_deposit ) {
		return round( (float) $locked_deposit, 2 );
	}

	$total = qs_calculate_total(
		$quote_id
	);

	return round(
		$total * 0.30,
		2
	);

}

/**
 * Calculate balance amount.
 */
function qs_calculate_balance( $quote_id ) {

	$total = qs_calculate_total(
		$quote_id
	);

	$deposit = qs_calculate_deposit( $quote_id );

	return round(
		$total - $deposit,
		2
	);

}

