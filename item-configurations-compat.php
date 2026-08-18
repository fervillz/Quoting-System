<?php
/**
 * Compatibility and presentation helpers for per-item quote configurations.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Only replace pricing when the Quote System is performing its normal product
 * recalculation. Direct office edits to the Pricing & Workflow subtotal remain
 * manual overrides, as they were before per-item configurations were added.
 */
remove_action( 'added_post_meta', 'qs_item_config_sync_pricing', 1200 );
remove_action( 'updated_post_meta', 'qs_item_config_sync_pricing', 1200 );

function qs_item_config_sync_recalculated_pricing( $meta_id, $quote_id, $meta_key, $meta_value ) {
	if ( '_subtotal' !== $meta_key || 'quote' !== get_post_type( $quote_id ) ) {
		return;
	}

	$from_recalculation = false;
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		if ( isset( $frame['function'] ) && 'qs_recalculate_quote_pricing' === $frame['function'] ) {
			$from_recalculation = true;
			break;
		}
	}

	if ( $from_recalculation ) {
		qs_item_config_sync_pricing( $meta_id, $quote_id, $meta_key, $meta_value );
	}
}
add_action( 'added_post_meta', 'qs_item_config_sync_recalculated_pricing', 1200, 4 );
add_action( 'updated_post_meta', 'qs_item_config_sync_recalculated_pricing', 1200, 4 );

/**
 * Keep Profile End Panel item types intact when the legacy wp-admin Components
 * table is saved. That table predates the Profile End Panel option.
 */
function qs_item_config_capture_admin_types( $post_id ) {
	if ( ! is_admin() || empty( $_POST['components'] ) ) {
		return;
	}

	$rows = get_post_meta( $post_id, '_qs_doors_drawers', true );
	$GLOBALS['qs_item_config_admin_types'][ $post_id ] = is_array( $rows ) ? $rows : array();
}
add_action( 'save_post_quote', 'qs_item_config_capture_admin_types', 2 );

function qs_item_config_restore_admin_types( $post_id ) {
	if ( ! is_admin() || empty( $GLOBALS['qs_item_config_admin_types'][ $post_id ] ) || empty( $_POST['components'] ) ) {
		return;
	}

	$old_rows = $GLOBALS['qs_item_config_admin_types'][ $post_id ];
	$new_rows = get_post_meta( $post_id, '_qs_doors_drawers', true );
	$new_rows = is_array( $new_rows ) ? $new_rows : array();
	$changed  = false;

	foreach ( $new_rows as $index => &$row ) {
		if (
			isset( $old_rows[ $index ]['type'] ) &&
			'Profile End Panel' === $old_rows[ $index ]['type'] &&
			( empty( $row['type'] ) || 'Door' === $row['type'] )
		) {
			$row['type'] = 'Profile End Panel';
			$changed     = true;
		}
	}
	unset( $row );

	if ( $changed ) {
		update_post_meta( $post_id, '_qs_doors_drawers', qs_sanitise_component_rows( 'doors_drawers', $new_rows ) );
		qs_recalculate_quote_pricing( $post_id );
	}

	unset( $GLOBALS['qs_item_config_admin_types'][ $post_id ] );
}
add_action( 'save_post_quote', 'qs_item_config_restore_admin_types', 40 );

/**
 * Row-level specifications supersede the old quote-level specification cards.
 * Keep those cards for legacy quotes only, while the item breakdown becomes
 * the source of truth for mixed-configuration quotes.
 */
function qs_item_config_review_shortcode() {
	$html = function_exists( 'qs_estimated_lead_time_review_shortcode' )
		? qs_estimated_lead_time_review_shortcode()
		: qs_quote_review_shortcode();

	$quote_id = function_exists( 'qs_current_quote_id' ) ? qs_current_quote_id() : 0;
	if ( ! $quote_id || ! qs_item_config_has_row_configuration( $quote_id ) ) {
		return $html;
	}

	$html = preg_replace(
		'/<section class="qs-review-section">\s*<h3>Selected Specifications<\/h3>.*?<\/section>/s',
		'',
		$html,
		1
	);

	$html = preg_replace(
		'/<h3>Selected Specifications<\/h3>\s*<dl>.*?<\/dl>/s',
		'',
		$html,
		1
	);

	return $html;
}

function qs_item_config_register_review_shortcode() {
	remove_shortcode( 'quote_review' );
	remove_shortcode( 'my_quote_review' );
	add_shortcode( 'quote_review', 'qs_item_config_review_shortcode' );
	add_shortcode( 'my_quote_review', 'qs_item_config_review_shortcode' );
}
add_action( 'init', 'qs_item_config_register_review_shortcode', 300 );

/**
 * Small follow-up script runs after the main item editor to remove browser
 * validation from the hidden legacy specification controls and to ensure Notes
 * never carry into the next newly-added item.
 */
function qs_enqueue_item_configuration_compat_assets() {
	$relative = 'assets/js/item-configurations-compat.js';
	wp_enqueue_script(
		'qs-item-configurations-compat',
		QS_URL . $relative,
		array( 'qs-item-configurations' ),
		file_exists( QS_PATH . $relative ) ? (string) filemtime( QS_PATH . $relative ) : QS_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'qs_enqueue_item_configuration_compat_assets', 10003 );
