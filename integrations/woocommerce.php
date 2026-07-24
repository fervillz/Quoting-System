<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Temporary Product IDs
 * Later these will come from Settings.
 */
define(
	'QS_DEPOSIT_PRODUCT_ID',
	66640
);

define(
	'QS_FINAL_PRODUCT_ID',
	66641
);

/**
 * Create Deposit Order.
 */
function qs_create_deposit_order(
	$quote_id
) {

	if ( ! function_exists( 'wc_create_order' ) ) {
		return false;
	}

	$deposit = qs_calculate_deposit(
		$quote_id
	);

	$order = wc_create_order();

	if ( is_wp_error( $order ) ) {
		return false;
	}

	$product = wc_get_product(
		QS_DEPOSIT_PRODUCT_ID
	);

	if ( ! $product ) {
		return false;
	}

	$item_id = $order->add_product(
		$product,
		1
	);

	$item = $order->get_item(
		$item_id
	);

	$item->set_subtotal(
		$deposit
	);

	$item->set_total(
		$deposit
	);

	$item->save();

	$order->calculate_totals(
		false
	);

	$order->save();

	return $order->get_id();

}

/**
 * Temporary WooCommerce Test
 *
 * Example:
 * ?qs_test_deposit=66628
 */
function qs_test_deposit_order() {

	if (
		! isset(
			$_GET['qs_test_deposit']
		)
	) {
		return;
	}

	$quote_id = absint(
		$_GET['qs_test_deposit']
	);

	$order_id = qs_create_deposit_order(
		$quote_id
	);

	wp_die(
		get_post_meta(
			66628,
			'_subtotal',
			true
		)
	);

}

add_action(
	'init',
	'qs_test_deposit_order'
);