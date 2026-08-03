<?php
/**
 * Profile End Panel support for single-configuration quotes.
 *
 * Profile End Panels use the selected Door Profile matrix and paint/timber/
 * finish modifiers, but they do not receive a Door / Drawer Handle charge.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Return all Profile End Panel rows currently saved on a quote. */
function qs_profile_end_panel_rows( $quote_id ) {
	return array_values(
		array_filter(
			qs_component_rows( $quote_id, 'doors_drawers' ),
			static function ( $row ) {
				return isset( $row['type'] ) && 'Profile End Panel' === $row['type'];
			}
		)
	);
}

/**
 * Calculate the handle amount that the existing panel calculator added to
 * Profile End Panels so it can be removed without changing normal doors.
 */
function qs_profile_end_panel_handle_correction( $quote_id ) {
	$rows = qs_profile_end_panel_rows( $quote_id );
	if ( ! $rows ) {
		return array( 'base' => 0, 'percentage' => 0, 'total' => 0 );
	}

	$profile_id = qs_resolve_quote_product( get_post_meta( $quote_id, '_door_profile', true ), 'door-profile' );
	$handle_id  = qs_resolve_quote_product( get_post_meta( $quote_id, '_handle_profile', true ), 'accessory' );
	if ( ! $profile_id || ! $handle_id || ! qs_handle_applies_to_profile( $handle_id, $profile_id ) ) {
		return array( 'base' => 0, 'percentage' => 0, 'total' => 0 );
	}

	$handle_price = qs_product_fixed_price( $handle_id );
	if ( ! $handle_price ) {
		return array( 'base' => 0, 'percentage' => 0, 'total' => 0 );
	}

	$quantity = 0;
	foreach ( $rows as $row ) {
		$quantity += isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
	}

	$percentage = 0;
	foreach ( array( '_timber' => 'timber', '_finish' => 'finish' ) as $meta_key => $type ) {
		$product_id = qs_resolve_quote_product( get_post_meta( $quote_id, $meta_key, true ), $type );
		if ( $product_id && 'percentage' === qs_product_pricing_method( $product_id ) ) {
			$percentage += qs_product_percentage( $product_id );
		}
	}

	$base                  = round( $handle_price * $quantity, 2 );
	$percentage_correction = round( $base * ( $percentage / 100 ), 2 );

	return array(
		'base'       => $base,
		'percentage' => $percentage_correction,
		'total'      => round( $base + $percentage_correction, 2 ),
	);
}

/** Keep the unadjusted trade subtotal available during a recalculation. */
function qs_capture_profile_end_panel_raw_trade_subtotal( $meta_id, $quote_id, $meta_key, $meta_value ) {
	if ( ! empty( $GLOBALS['qs_profile_end_panel_adjusting'] ) || '_trade_subtotal' !== $meta_key || 'quote' !== get_post_type( $quote_id ) ) {
		return;
	}

	update_post_meta( $quote_id, '_qs_profile_end_panel_raw_trade_subtotal', (float) $meta_value );
}
add_action( 'added_post_meta', 'qs_capture_profile_end_panel_raw_trade_subtotal', 900, 4 );
add_action( 'updated_post_meta', 'qs_capture_profile_end_panel_raw_trade_subtotal', 900, 4 );

/**
 * Correct stored totals after the standard calculator has processed all
 * Doors & Drawers rows. This leaves normal Door and Drawer pricing unchanged.
 */
function qs_apply_profile_end_panel_pricing_correction( $meta_id, $quote_id, $meta_key, $meta_value ) {
	if ( ! empty( $GLOBALS['qs_profile_end_panel_adjusting'] ) || '_subtotal' !== $meta_key || 'quote' !== get_post_type( $quote_id ) ) {
		return;
	}

	$correction = qs_profile_end_panel_handle_correction( $quote_id );
	if ( empty( $correction['total'] ) ) {
		delete_post_meta( $quote_id, '_qs_profile_end_panel_handle_correction' );
		return;
	}

	$raw_trade = get_post_meta( $quote_id, '_qs_profile_end_panel_raw_trade_subtotal', true );
	if ( '' === $raw_trade ) {
		$raw_trade = get_post_meta( $quote_id, '_trade_subtotal', true );
	}

	$trade_subtotal = max( 0, round( (float) $raw_trade - (float) $correction['total'], 2 ) );
	$subtotal       = 'retail' === get_post_meta( $quote_id, '_pricing_type', true )
		? qs_apply_retail_markup( $trade_subtotal )
		: $trade_subtotal;

	$GLOBALS['qs_profile_end_panel_adjusting'] = true;
	update_post_meta( $quote_id, '_trade_subtotal', $trade_subtotal );
	update_post_meta( $quote_id, '_calculated_subtotal', $subtotal );
	update_post_meta( $quote_id, '_subtotal', $subtotal );
	update_post_meta( $quote_id, '_qs_profile_end_panel_handle_correction', $correction );

	$breakdown = get_post_meta( $quote_id, '_pricing_breakdown', true );
	if ( is_array( $breakdown ) ) {
		if ( isset( $breakdown['doors_drawers'] ) ) {
			$breakdown['doors_drawers'] = max( 0, round( (float) $breakdown['doors_drawers'] - (float) $correction['base'], 2 ) );
		}
		if ( isset( $breakdown['percentage'] ) ) {
			$breakdown['percentage'] = round( (float) $breakdown['percentage'] - (float) $correction['percentage'], 2 );
		}
		update_post_meta( $quote_id, '_pricing_breakdown', $breakdown );
	}
	$GLOBALS['qs_profile_end_panel_adjusting'] = false;
}
add_action( 'added_post_meta', 'qs_apply_profile_end_panel_pricing_correction', 999, 4 );
add_action( 'updated_post_meta', 'qs_apply_profile_end_panel_pricing_correction', 999, 4 );

/** Load the Profile End Panel editor and summary integration. */
function qs_enqueue_profile_end_panel_assets() {
	$relative_path = 'assets/js/profile-end-panels.js';
	$path          = QS_PATH . $relative_path;

	wp_enqueue_script(
		'qs-profile-end-panels',
		QS_URL . $relative_path,
		array( 'qs-quantity-fields', 'qs-quote-builder-ux' ),
		file_exists( $path ) ? (string) filemtime( $path ) : QS_VERSION,
		true
	);

	wp_localize_script(
		'qs-profile-end-panels',
		'QSProfileEndPanels',
		array(
			'editIcon'   => QS_URL . 'assets/images/icon-pen.svg',
			'removeIcon' => QS_URL . 'assets/images/icon-trash.svg',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'qs_enqueue_profile_end_panel_assets', 10000 );

/**
 * The standard Builder returns its local pre-correction subtotal. Replace only
 * that initial rendered figure with the corrected value already stored by the
 * synchronous post-meta hooks above. AJAX calculations already read the meta.
 */
function qs_profile_end_panel_builder_shortcode() {
	$html = qs_quote_builder_shortcode();
	if ( false === strpos( $html, 'data-qs-subtotal' ) ) {
		return $html;
	}

	$quote_id = isset( $_GET['quote_id'] )
		? absint( $_GET['quote_id'] )
		: ( isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0 );
	if ( ! $quote_id ) {
		return $html;
	}

	$subtotal = (float) get_post_meta( $quote_id, '_subtotal', true );
	$display  = '$' . number_format_i18n( $subtotal, 2 ) . ' AUD';

	return preg_replace(
		'/(<strong data-qs-subtotal>).*?(<\/strong>)/s',
		'$1' . esc_html( $display ) . '$2',
		$html,
		1
	);
}

function qs_register_profile_end_panel_builder_shortcode() {
	remove_shortcode( 'quote_builder' );
	add_shortcode( 'quote_builder', 'qs_profile_end_panel_builder_shortcode' );
}
add_action( 'init', 'qs_register_profile_end_panel_builder_shortcode', 100 );
