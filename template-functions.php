<?php
/** Shared display helpers for quote review and PDF templates. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function qs_component_rows_by_type( $rows, $type ) {
	return array_values(
		array_filter(
			is_array( $rows ) ? $rows : array(),
			static function ( $row ) use ( $type ) {
				return isset( $row['type'] ) && $type === $row['type'];
			}
		)
	);
}

function qs_component_rows_with_indices( $rows ) {
	$indexed = array();
	foreach ( is_array( $rows ) ? $rows : array() as $index => $row ) {
		$row['_row_index'] = $index;
		$indexed[]         = $row;
	}

	return $indexed;
}

function qs_component_drawer_heights( $row ) {
	$labels = array(
		'top_height'           => 'Top',
		'top_middle_height'    => 'Top Middle',
		'middle_height'        => 'Middle',
		'bottom_middle_height' => 'Bottom Middle',
		'bottom_height'        => 'Bottom',
	);
	$lines = array();
	foreach ( $labels as $key => $label ) {
		if ( ! empty( $row[ $key ] ) ) {
			$lines[] = $label . ': ' . absint( $row[ $key ] ) . 'mm';
		}
	}

	return implode( "\n", $lines );
}

function qs_component_display_value( $row, $key ) {
	if ( 'configuration' === $key ) {
		$count = isset( $row['drawer_count'] ) ? absint( $row['drawer_count'] ) : 0;
		return $count ? $count . ( 1 === $count ? ' Drawer' : ' Drawers' ) : '';
	}
	if ( 'height_details' === $key ) {
		$heights = qs_component_drawer_heights( $row );
		return $heights ? $heights : ( ! empty( $row['height'] ) ? absint( $row['height'] ) . ' mm' : '' );
	}
	if ( 'faces_edges_seen' === $key ) {
		return trim(
			( isset( $row['faces_seen'] ) ? $row['faces_seen'] : '' ) .
			( ! empty( $row['edges_seen'] ) ? ' / ' . $row['edges_seen'] : '' ),
			' /'
		);
	}

	$value = isset( $row[ $key ] ) ? $row[ $key ] : '';
	if ( 'material' === $key ) {
		$value = qs_quote_product_label( $value );
	}
	if ( in_array( $key, array( 'width', 'height', 'length' ), true ) && '' !== $value ) {
		$value = absint( $value ) . ' mm';
	}

	return is_scalar( $value ) ? (string) $value : '';
}

function qs_render_quote_component_table( $rows, $columns, $class = 'qs-table', $empty_message = 'No items supplied.', $start_index = 1 ) {
	echo '<table class="' . esc_attr( $class ) . '"><thead><tr><th>#</th>';
	foreach ( $columns as $label ) {
		echo '<th>' . esc_html( $label ) . '</th>';
	}
	echo '</tr></thead><tbody>';

	foreach ( $rows as $index => $row ) {
		echo '<tr><td>' . esc_html( $index + absint( $start_index ) ) . '</td>';
		foreach ( array_keys( $columns ) as $key ) {
			echo '<td>' . nl2br( esc_html( qs_component_display_value( $row, $key ) ) ) . '</td>';
		}
		echo '</tr>';
	}

	if ( ! $rows ) {
		echo '<tr><td colspan="' . esc_attr( count( $columns ) + 1 ) . '">' . esc_html( $empty_message ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * Prints one structured repeater table.
 * This keeps every PDF row aligned with the quote-builder fields.
 */
function qs_pdf_component_table( $rows, $columns ) {
	qs_render_quote_component_table( $rows, $columns, 'qs-table' );
}

function qs_quote_product_image_url( $product_id ) {
	if ( ! is_numeric( $product_id ) || ! absint( $product_id ) ) {
		return '';
	}

	$image = get_the_post_thumbnail_url( absint( $product_id ), 'thumbnail' );
	return $image ? $image : '';
}

function qs_quote_summary_groups( $quote_id ) {
	$doors_drawers = qs_component_rows_with_indices( qs_component_rows( $quote_id, 'doors_drawers' ) );

	return array(
		array( 'title' => 'Doors', 'component' => 'doors_drawers', 'rows' => qs_component_rows_by_type( $doors_drawers, 'Door' ) ),
		array( 'title' => 'Drawers', 'component' => 'doors_drawers', 'rows' => qs_component_rows_by_type( $doors_drawers, 'Drawer' ) ),
		array( 'title' => 'Drawer Banks', 'component' => 'doors_drawers', 'rows' => qs_component_rows_by_type( $doors_drawers, 'Drawer Bank' ) ),
		array( 'title' => 'Profile End Panels', 'component' => 'doors_drawers', 'rows' => qs_component_rows_by_type( $doors_drawers, 'Profile End Panel' ) ),
		array( 'title' => 'End Panels', 'component' => 'end_panels', 'rows' => qs_component_rows_with_indices( qs_component_rows( $quote_id, 'end_panels' ) ) ),
		array( 'title' => 'Fillers', 'component' => 'fillers', 'rows' => qs_component_rows_with_indices( qs_component_rows( $quote_id, 'fillers' ) ) ),
		array( 'title' => 'Kickboards', 'component' => 'kickboards', 'rows' => qs_component_rows_with_indices( qs_component_rows( $quote_id, 'kickboards' ) ) ),
	);
}

function qs_quote_summary_primary( $component, $row ) {
	if ( 'kickboards' === $component ) {
		return qs_component_display_value( $row, 'material' );
	}
	if ( 'doors_drawers' === $component && isset( $row['type'] ) && 'Drawer Bank' === $row['type'] ) {
		$width  = absint( isset( $row['width'] ) ? $row['width'] : 0 );
		$height = 0;
		foreach ( array( 'top_height', 'top_middle_height', 'middle_height', 'bottom_middle_height', 'bottom_height' ) as $key ) {
			$height += isset( $row[ $key ] ) ? absint( $row[ $key ] ) : 0;
		}
		return $width && $height ? $width . ' x ' . $height : (string) ( $width ? $width : $height );
	}
	if ( in_array( $component, array( 'end_panels', 'fillers' ), true ) ) {
		return absint( isset( $row['height'] ) ? $row['height'] : 0 ) . ' x ' . absint( isset( $row['width'] ) ? $row['width'] : 0 );
	}

	return absint( isset( $row['width'] ) ? $row['width'] : 0 ) . ' x ' . absint( isset( $row['height'] ) ? $row['height'] : 0 );
}

function qs_quote_summary_secondary( $component, $row ) {
	if ( 'kickboards' === $component ) {
		return absint( isset( $row['height'] ) ? $row['height'] : 0 ) . ' x ' . absint( isset( $row['length'] ) ? $row['length'] : 0 );
	}
	if ( 'doors_drawers' === $component && isset( $row['type'] ) && 'Drawer Bank' === $row['type'] ) {
		return qs_component_drawer_heights( $row );
	}

	return '';
}
