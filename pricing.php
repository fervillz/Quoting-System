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

	$total = $subtotal - $discount + $additional_charges;

	return round( $total, 2 );

}

/**
 * Calculate deposit amount.
 *
 * Deposit = 30%
 */
function qs_calculate_deposit( $quote_id ) {

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

	$deposit = qs_calculate_deposit(
		$quote_id
	);

	return round(
		$total - $deposit,
		2
	);

}