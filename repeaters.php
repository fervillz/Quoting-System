<?php
/**
 * Structured quote component repeaters stored as native WordPress post meta.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
function qs_component_definitions() {
	return array(
		'doors_drawers' => array( 'type' => 'select', 'width' => 'positive_int', 'height' => 'positive_int', 'quantity' => 'positive_int', 'edge_profile' => 'text', 'drawer_count' => 'positive_int', 'top_height' => 'positive_int', 'middle_height' => 'positive_int', 'bottom_height' => 'positive_int' ),
		'end_panels' => array( 'height' => 'positive_int', 'width' => 'positive_int', 'quantity' => 'positive_int', 'faces_seen' => 'text', 'edges_seen' => 'text' ),
		'fillers' => array( 'height' => 'positive_int', 'width' => 'positive_int', 'quantity' => 'positive_int', 'faces_seen' => 'text', 'edges_seen' => 'text' ),
		'kickboards' => array( 'material' => 'text', 'height' => 'positive_int', 'length' => 'positive_int', 'quantity' => 'positive_int' ),
	);
}
function qs_component_rows( $quote_id, $component ) { $definitions = qs_component_definitions(); if ( ! isset( $definitions[ $component ] ) ) { return array(); } $rows = get_post_meta( $quote_id, '_qs_' . $component, true ); return is_array( $rows ) ? array_values( $rows ) : array(); }
function qs_sanitise_component_rows( $component, $raw_rows ) { $definitions = qs_component_definitions(); if ( ! isset( $definitions[ $component ] ) || ! is_array( $raw_rows ) ) { return array(); } $clean_rows = array(); foreach ( $raw_rows as $raw_row ) { if ( ! is_array( $raw_row ) ) { continue; } $row = array(); foreach ( $definitions[ $component ] as $key => $rule ) { $value = isset( $raw_row[ $key ] ) ? $raw_row[ $key ] : ''; $row[ $key ] = 'positive_int' === $rule ? absint( $value ) : ( 'select' === $rule ? ( in_array( $value, array( 'Door', 'Drawer', 'Drawer Bank' ), true ) ? $value : 'Door' ) : sanitize_text_field( $value ) ); } if ( empty( $row['quantity'] ) || ( empty( $row['width'] ) && empty( $row['length'] ) ) ) { continue; } $clean_rows[] = $row; } return $clean_rows; }
function qs_save_component_rows( $quote_id, $posted_components ) { foreach ( array_keys( qs_component_definitions() ) as $component ) { $raw_rows = isset( $posted_components[ $component ] ) ? wp_unslash( $posted_components[ $component ] ) : array(); update_post_meta( $quote_id, '_qs_' . $component, qs_sanitise_component_rows( $component, $raw_rows ) ); } }
function qs_quote_component_count( $quote_id, $component, $type = '' ) { $count = 0; foreach ( qs_component_rows( $quote_id, $component ) as $row ) { if ( $type && ( ! isset( $row['type'] ) || $type !== $row['type'] ) ) { continue; } $count += isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0; } return $count; }
