<?php
/**
 * Convert per-room trade totals into the quote's selected pricing mode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_room_display_subtotals( $quote_id, $trade_subtotals, $grand_total = null ) {
	$trade_subtotals = is_array( $trade_subtotals ) ? $trade_subtotals : array();
	$display         = array_map( static function ( $value ) { return round( (float) $value, 2 ); }, $trade_subtotals );

	if ( 'retail' !== get_post_meta( $quote_id, '_pricing_type', true ) ) {
		return $display;
	}

	foreach ( $display as $room_id => $trade_subtotal ) {
		$display[ $room_id ] = round( qs_apply_retail_markup( $trade_subtotal ), 2 );
	}

	if ( null === $grand_total ) {
		$grand_total = qs_apply_retail_markup( array_sum( $trade_subtotals ) );
	}
	$grand_total = round( (float) $grand_total, 2 );
	$difference  = round( $grand_total - array_sum( $display ), 2 );

	if ( $difference && $display ) {
		$last_room = array_key_last( $display );
		$display[ $last_room ] = round( $display[ $last_room ] + $difference, 2 );
	}

	return $display;
}

function qs_room_display_subtotal( $quote_id, $room_id ) {
	$result     = qs_calculate_rooms_pricing( $quote_id );
	$subtotals  = qs_room_display_subtotals( $quote_id, $result['room_subtotals'], $result['subtotal'] );
	return isset( $subtotals[ $room_id ] ) ? (float) $subtotals[ $room_id ] : 0;
}
