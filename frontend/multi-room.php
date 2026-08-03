<?php
/**
 * Frontend integration for multi-room quotes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_multi_room_quote_id() {
	if ( isset( $_GET['quote_id'] ) ) {
		return absint( $_GET['quote_id'] );
	}
	if ( isset( $_POST['quote_id'] ) ) {
		return absint( $_POST['quote_id'] );
	}
	return 0;
}

function qs_multi_room_builder_config( $quote_id ) {
	$rooms          = qs_quote_rooms( $quote_id, true );
	$active_room_id = $quote_id ? sanitize_key( get_post_meta( $quote_id, '_qs_active_room_id', true ) ) : '';
	$room_subtotals = array();

	if ( $quote_id ) {
		$result         = qs_calculate_rooms_pricing( $quote_id );
		$room_subtotals = $result['room_subtotals'];
	}
	if ( ! $active_room_id || ! wp_list_filter( $rooms, array( 'id' => $active_room_id ) ) ) {
		$active_room_id = $rooms[0]['id'];
	}

	return array(
		'quoteId'             => $quote_id,
		'rooms'               => array_values( $rooms ),
		'activeRoomId'        => $active_room_id,
		'roomSubtotals'       => $room_subtotals,
		'leadTime'            => qs_quote_lead_time( $quote_id ),
		'canEditLeadTime'     => $quote_id ? current_user_can( 'edit_post', $quote_id ) && current_user_can( 'edit_others_posts' ) : current_user_can( 'edit_others_posts' ),
		'editIcon'            => QS_URL . 'assets/images/icon-pen.svg',
		'removeIcon'          => QS_URL . 'assets/images/icon-trash.svg',
		'profileEndPanelType' => 'Profile End Panel',
	);
}

function qs_multi_room_builder_shortcode() {
	$html = qs_quote_builder_shortcode();
	if ( false === strpos( $html, 'qs-builder-form' ) ) {
		return $html;
	}

	$quote_id = qs_multi_room_quote_id();
	$config   = qs_multi_room_builder_config( $quote_id );
	wp_localize_script( 'qs-multi-room', 'QSMultiRoom', $config );

	$lead_time = esc_html( $config['leadTime'] );
	$html = preg_replace(
		'/(<div class="qs-lead-time"><strong>Estimated Lead Time<\/strong><span>).*?(<\/span>)/s',
		'$1' . $lead_time . '$2',
		$html,
		1
	);

	if ( $quote_id ) {
		$pricing = qs_calculate_rooms_pricing( $quote_id );
		$total   = '$' . number_format_i18n( $pricing['subtotal'], 2 ) . ' AUD';
		$html    = preg_replace(
			'/(<strong data-qs-subtotal>).*?(<\/strong>)/s',
			'$1' . esc_html( $total ) . '$2',
			$html,
			1
		);
	}

	return str_replace( 'class="qs-builder-shell"', 'class="qs-builder-shell qs-multi-room-enabled"', $html );
}

function qs_multi_room_review_shortcode() {
	$html     = qs_quote_review_shortcode();
	$quote_id = qs_multi_room_quote_id();
	if ( ! $quote_id || false === strpos( $html, 'qs-review-page' ) ) {
		return $html;
	}

	$rooms = qs_quote_rooms( $quote_id, false );
	if ( $rooms ) {
		$overview = qs_rooms_summary_markup( $quote_id, true );
		$html = preg_replace( '/<\/main>/', $overview . '</main>', $html, 1 );
	}

	$html = preg_replace(
		'/(<div class="qs-review-lead-time"><strong>Estimated Lead Time<\/strong><span>).*?(<\/span>)/s',
		'$1' . esc_html( qs_quote_lead_time( $quote_id ) ) . '$2',
		$html,
		1
	);

	if ( count( $rooms ) > 1 ) {
		$html = str_replace( 'class="qs-container qs-review-page"', 'class="qs-container qs-review-page qs-has-multiple-rooms"', $html );
	}

	return $html;
}

function qs_register_multi_room_shortcodes() {
	remove_shortcode( 'quote_builder' );
	add_shortcode( 'quote_builder', 'qs_multi_room_builder_shortcode' );

	remove_shortcode( 'quote_review' );
	remove_shortcode( 'my_quote_review' );
	add_shortcode( 'quote_review', 'qs_multi_room_review_shortcode' );
	add_shortcode( 'my_quote_review', 'qs_multi_room_review_shortcode' );
}
add_action( 'init', 'qs_register_multi_room_shortcodes', 100 );
