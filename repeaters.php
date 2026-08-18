<?php
/**
 * Quote repeater data.
 *
 * A repeater is a list of rows. For example, a joiner can add as many doors
 * as needed instead of being limited to one door field. These rows are saved
 * as normal WordPress post meta, so this plugin does not rely on ACF.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists every repeater and the fields each row is allowed to contain.
 *
 * Product configuration now lives on each individual row. Older quotes that
 * only have quote-level Door Specifications remain supported by the pricing
 * and display helpers, which fall back to the legacy quote meta when a row
 * does not yet contain these values.
 */
function qs_component_definitions() {
	return array(
		'doors_drawers' => array(
			'type'                 => 'select',
			'door_profile'         => 'text',
			'timber'               => 'text',
			'handle_profile'       => 'text',
			'finish'               => 'text',
			'paint_colour'         => 'text',
			'width'                => 'positive_int',
			'height'               => 'positive_int',
			'quantity'             => 'positive_int',
			'edge_profile'         => 'text',
			'drawer_count'         => 'positive_int',
			'top_height'           => 'positive_int',
			'top_middle_height'    => 'positive_int',
			'middle_height'        => 'positive_int',
			'bottom_middle_height' => 'positive_int',
			'bottom_height'        => 'positive_int',
			'notes'                => 'textarea',
		),
		'end_panels' => array(
			'timber'       => 'text',
			'finish'       => 'text',
			'paint_colour' => 'text',
			'height'       => 'positive_int',
			'width'        => 'positive_int',
			'quantity'     => 'positive_int',
			'faces_seen'   => 'text',
			'edges_seen'   => 'text',
			'notes'        => 'textarea',
		),
		'fillers' => array(
			'timber'       => 'text',
			'finish'       => 'text',
			'paint_colour' => 'text',
			'height'       => 'positive_int',
			'width'        => 'positive_int',
			'quantity'     => 'positive_int',
			'faces_seen'   => 'text',
			'edges_seen'   => 'text',
			'notes'        => 'textarea',
		),
		'kickboards' => array(
			'material'     => 'text',
			'timber'       => 'text',
			'finish'       => 'text',
			'paint_colour' => 'text',
			'height'       => 'positive_int',
			'length'       => 'positive_int',
			'quantity'     => 'positive_int',
			'notes'        => 'textarea',
		),
	);
}

/**
 * Gets a component list already saved on a quote.
 *
 * An empty array is always returned when there are no rows. This is safer for
 * templates than returning an empty string or an old pipe-separated value.
 */
function qs_component_rows( $quote_id, $component ) {
	$definitions = qs_component_definitions();
	if ( ! isset( $definitions[ $component ] ) ) {
		return array();
	}

	$rows = get_post_meta( $quote_id, '_qs_' . $component, true );
	if ( ! is_array( $rows ) ) {
		return array();
	}

	// Re-sanitise on read as well, so old placeholder/blank rows never appear
	// in the admin screen, frontend review or PDFs.
	return array_values( qs_sanitise_component_rows( $component, $rows ) );
}

/**
 * Cleans rows submitted by the browser before they reach the database.
 *
 * Measurements and quantities are converted to positive whole numbers. Text
 * is cleaned by WordPress. Empty UI placeholder rows are ignored completely.
 */
function qs_sanitise_component_rows( $component, $raw_rows ) {
	$definitions = qs_component_definitions();
	if ( ! isset( $definitions[ $component ] ) || ! is_array( $raw_rows ) ) {
		return array();
	}

	$clean_rows = array();
	foreach ( $raw_rows as $raw_row ) {
		if ( ! is_array( $raw_row ) ) {
			continue;
		}

		$row = array();
		foreach ( $definitions[ $component ] as $key => $rule ) {
			$value = isset( $raw_row[ $key ] ) ? $raw_row[ $key ] : '';
			if ( 'positive_int' === $rule ) {
				$row[ $key ] = absint( $value );
			} elseif ( 'select' === $rule ) {
				$row[ $key ] = in_array( $value, array( 'Door', 'Drawer', 'Drawer Bank', 'Profile End Panel' ), true ) ? $value : 'Door';
			} elseif ( 'textarea' === $rule ) {
				$row[ $key ] = sanitize_textarea_field( $value );
			} else {
				$row[ $key ] = sanitize_text_field( $value );
			}
		}

		if ( empty( $row['quantity'] ) || ( empty( $row['width'] ) && empty( $row['length'] ) ) ) {
			continue;
		}

		$clean_rows[] = $row;
	}

	return $clean_rows;
}

/**
 * Saves all four repeater groups for a quote in one consistent format.
 */
function qs_save_component_rows( $quote_id, $posted_components ) {
	foreach ( array_keys( qs_component_definitions() ) as $component ) {
		$raw_rows = isset( $posted_components[ $component ] ) ? wp_unslash( $posted_components[ $component ] ) : array();
		update_post_meta( $quote_id, '_qs_' . $component, qs_sanitise_component_rows( $component, $raw_rows ) );
	}
}

/**
 * Converts a saved Quote Product post ID into its readable title.
 * Existing text values are returned unchanged for backward compatibility.
 */
function qs_quote_product_label( $value ) {
	if ( is_numeric( $value ) && absint( $value ) ) {
		$title = get_the_title( absint( $value ) );
		if ( is_string( $title ) && '' !== trim( $title ) ) {
			return $title;
		}
	}

	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Adds quantities across a repeater group.
 *
 * Pass "Door" or "Drawer" as $type when a screen needs one specific total.
 */
function qs_quote_component_count( $quote_id, $component, $type = '' ) {
	$count = 0;
	foreach ( qs_component_rows( $quote_id, $component ) as $row ) {
		if ( $type && ( ! isset( $row['type'] ) || $type !== $row['type'] ) ) {
			continue;
		}
		$count += isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0;
	}

	return $count;
}
