<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate a Quote Number.
 *
 * Format:
 * Q-YYYY-0001
 *
 * Example:
 * Q-2026-0001
 */
function qs_generate_quote_number() {

	$year = date( 'Y' );

	/**
	 * Store sequence in options table.
	 */
	$sequence = (int) get_option( 'qs_quote_sequence', 0 );

	$sequence++;

	update_option( 'qs_quote_sequence', $sequence );

	return sprintf(
		'Q-%s-%04d',
		$year,
		$sequence
	);

}

/**
 * Assign a Quote Number when a quote
 * is created for the first time.
 */
function qs_assign_quote_number( $post_id, $post, $update ) {

	/**
	 * Only run for Quotes.
	 */
	if ( 'quote' !== $post->post_type ) {
		return;
	}

	/**
	 * Only generate on first save.
	 */
	if ( $update ) {
		return;
	}

	/**
	 * Prevent duplicate numbers.
	 */
	if ( get_post_meta( $post_id, '_quote_number', true ) ) {
		return;
	}

	update_post_meta(
		$post_id,
		'_quote_number',
		qs_generate_quote_number()
	);

}

add_action(
	'save_post_quote',
	'qs_assign_quote_number',
	10,
	3
);