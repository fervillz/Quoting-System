Exit code: 0
Wall time: 0.7 seconds
Output:
<?php
/** Shared, print-safe tables for quotation and job-sheet templates. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Prints one structured repeater table.
 * This keeps every PDF row aligned with the quote-builder fields.
 */
function qs_pdf_component_table( $rows, $columns ) {
	echo '<table class="qs-table"><thead><tr><th>#</th>';
	foreach ( $columns as $label ) { echo '<th>' . esc_html( $label ) . '</th>'; }
	echo '</tr></thead><tbody>';
	foreach ( $rows as $index => $row ) {
		echo '<tr><td>' . esc_html( $index + 1 ) . '</td>';
		foreach ( array_keys( $columns ) as $key ) {
			$value = isset( $row[ $key ] ) ? $row[ $key ] : '';
			echo '<td>' . esc_html( $value ) . ( in_array( $key, array( 'width', 'height', 'length' ), true ) && '' !== $value ? ' mm' : '' ) . '</td>';
		}
		echo '</tr>';
	}
	if ( ! $rows ) { echo '<tr><td colspan="' . esc_attr( count( $columns ) + 1 ) . '">No items supplied.</td></tr>'; }
	echo '</tbody></table>';
}

