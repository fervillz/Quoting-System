<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once QS_PATH . 'admin/item-configurations.php';
require_once QS_PATH . 'admin/item-configurations-force.php';

/**
 * Customize Quote admin columns.
 */
function qs_quote_columns( $columns ) {

	return array(
		'cb'           => $columns['cb'],
		'quote_number' => 'Quote Number',
		'title'        => 'Title',
		'status'       => 'Status',
		'date'         => 'Date',
	);

}

add_filter(
	'manage_quote_posts_columns',
	'qs_quote_columns'
);

/**
 * Populate custom Quote columns.
 */
function qs_quote_column_content( $column, $post_id ) {

	switch ( $column ) {

		case 'quote_number':

			echo esc_html(
				get_post_meta(
					$post_id,
					'_quote_number',
					true
				)
			);

			break;

		case 'status':

			$status = get_post_status_object(
				get_post_status( $post_id )
			);

			echo esc_html(
				$status ? $status->label : ''
			);

			break;

	}

}

add_action(
	'manage_quote_posts_custom_column',
	'qs_quote_column_content',
	10,
	2
);
