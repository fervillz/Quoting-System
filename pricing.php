Exit code: 0
Wall time: 0.5 seconds
Output:
<?php

if ( ! defined( 'ABSPATH' ) ) {
	 exit;
}

/**
 * Returns the price list entered by the office on the Quote Pricing page.
 *
 * Rates are trade rates in Australian dollars.  Area based components use
 * dollars per square metre; kickboards use dollars per linear metre.  This
 * keeps the information in one editable place instead of burying prices in
 * the PHP code or in each quote.
 */
function qs_get_pricing_settings() {
	$defaults = array(
		'door_rate'          => 0,
		'drawer_rate'        => 0,
		'drawer_bank_rate'   => 0,
		'end_panel_rate'     => 0,
		'filler_rate'        => 0,
		'kickboard_rate'     => 0,
		'profile_surcharge'  => 0,
		'timber_surcharge'   => 0,
		'finish_surcharge'   => 0,
		'handle_surcharge'   => 0,
	);
	$saved = get_option( 'qs_pricing_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/** Calculate a component's area price from millimetre dimensions. */
function qs_area_price( $width, $height, $quantity, $rate ) {
	$area = ( (float) $width / 1000 ) * ( (float) $height / 1000 );
	return $area * max( 0, (int) $quantity ) * (float) $rate;
}

/**
 * Calculates a quote's trade subtotal directly from its repeater rows.
 * Empty rows naturally cost nothing, so draft quotes remain safe to save.
 */
function qs_calculate_component_subtotal( $quote_id ) {
	$prices = qs_get_pricing_settings();
	$subtotal = 0;

	foreach ( qs_component_rows( $quote_id, 'doors_drawers' ) as $row ) {
		$type = isset( $row['type'] ) ? strtolower( $row['type'] ) : 'door';
		$rate = 'drawer' === $type ? $prices['drawer_rate'] : ( 'drawer bank' === $type ? $prices['drawer_bank_rate'] : $prices['door_rate'] );
		$subtotal += qs_area_price( isset( $row['width'] ) ? $row['width'] : 0, isset( $row['height'] ) ? $row['height'] : 0, isset( $row['quantity'] ) ? $row['quantity'] : 0, $rate );
	}
	foreach ( qs_component_rows( $quote_id, 'end_panels' ) as $row ) {
		$subtotal += qs_area_price( isset( $row['width'] ) ? $row['width'] : 0, isset( $row['height'] ) ? $row['height'] : 0, isset( $row['quantity'] ) ? $row['quantity'] : 0, $prices['end_panel_rate'] );
	}
	foreach ( qs_component_rows( $quote_id, 'fillers' ) as $row ) {
		$subtotal += qs_area_price( isset( $row['width'] ) ? $row['width'] : 0, isset( $row['height'] ) ? $row['height'] : 0, isset( $row['quantity'] ) ? $row['quantity'] : 0, $prices['filler_rate'] );
	}
	foreach ( qs_component_rows( $quote_id, 'kickboards' ) as $row ) {
		$subtotal += ( (float) ( isset( $row['length'] ) ? $row['length'] : 0 ) / 1000 ) * max( 0, (int) ( isset( $row['quantity'] ) ? $row['quantity'] : 0 ) ) * (float) $prices['kickboard_rate'];
	}

	// These fixed amounts cover the selected cabinet specifications.  The
	// office can leave any of them at $0 when they are already included above.
	foreach ( array( 'door_profile' => 'profile_surcharge', 'timber' => 'timber_surcharge', 'finish' => 'finish_surcharge', 'handle_profile' => 'handle_surcharge' ) as $meta_key => $price_key ) {
		if ( '' !== (string) get_post_meta( $quote_id, '_' . $meta_key, true ) ) {
			$subtotal += (float) $prices[ $price_key ];
		}
	}

	return round( $subtotal, 2 );
}

/** Saves the current repeater calculation so PDFs and the admin screen agree. */
function qs_recalculate_quote_pricing( $quote_id ) {
	$trade_subtotal = qs_calculate_component_subtotal( $quote_id );
	$pricing_type = get_post_meta( $quote_id, '_pricing_type', true );
	$subtotal = 'retail' === $pricing_type ? qs_apply_retail_markup( $trade_subtotal ) : $trade_subtotal;
	update_post_meta( $quote_id, '_calculated_subtotal', $subtotal );
	update_post_meta( $quote_id, '_subtotal', $subtotal );
	return $subtotal;
}

/** Retail pricing = the trade price plus 22.22%. */
function qs_apply_retail_markup( $amount ) {
	return round( (float) $amount * 1.2222, 2 );
}

/** Calculates the customer-facing total after the office adjustments. */
function qs_calculate_total( $quote_id ) {
	$subtotal = (float) get_post_meta( $quote_id, '_subtotal', true );
	$discount = (float) get_post_meta( $quote_id, '_discount', true );
	$additional_charges = (float) get_post_meta( $quote_id, '_additional_charges', true );
	$shipping = (float) get_post_meta( $quote_id, '_shipping', true );
	return round( $subtotal + $shipping - $discount + $additional_charges, 2 );
}

/** Deposit is 30% until the office creates the order and locks that amount. */
function qs_calculate_deposit( $quote_id ) {
	$locked_deposit = get_post_meta( $quote_id, '_qs_locked_deposit_amount', true );
	if ( '' !== $locked_deposit && false !== $locked_deposit ) {
		return round( (float) $locked_deposit, 2 );
	}
	return round( qs_calculate_total( $quote_id ) * 0.30, 2 );
}

/** The balance is always today's total less the already requested deposit. */
function qs_calculate_balance( $quote_id ) {
	return round( qs_calculate_total( $quote_id ) - qs_calculate_deposit( $quote_id ), 2 );
}

