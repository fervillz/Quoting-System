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
	$result    = qs_calculate_rooms_pricing( $quote_id );
	$subtotals = qs_room_display_subtotals( $quote_id, $result['room_subtotals'], $result['subtotal'] );
	return isset( $subtotals[ $room_id ] ) ? (float) $subtotals[ $room_id ] : 0;
}

/**
 * `_room_subtotals` is calculated from trade pricing by rooms.php. Store the
 * display-mode equivalent so review screens and documents agree with the
 * selected Trade/Retail quote mode.
 */
function qs_normalise_stored_room_subtotals( $meta_id, $quote_id, $meta_key, $meta_value ) {
	static $normalising = false;

	if ( $normalising || '_room_subtotals' !== $meta_key || 'quote' !== get_post_type( $quote_id ) ) {
		return;
	}
	if ( ! is_array( $meta_value ) || 'retail' !== get_post_meta( $quote_id, '_pricing_type', true ) ) {
		return;
	}

	$grand_total = (float) get_post_meta( $quote_id, '_subtotal', true );
	if ( ! $grand_total ) {
		$grand_total = qs_apply_retail_markup( array_sum( $meta_value ) );
	}

	$normalising = true;
	update_post_meta( $quote_id, '_room_subtotals', qs_room_display_subtotals( $quote_id, $meta_value, $grand_total ) );
	$normalising = false;
}
add_action( 'added_post_meta', 'qs_normalise_stored_room_subtotals', 20, 4 );
add_action( 'updated_post_meta', 'qs_normalise_stored_room_subtotals', 20, 4 );
