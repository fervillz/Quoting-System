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

			if ( function_exists( 'qs_quote_admin_workflow_status' ) ) {
				list( $workflow_status, $workflow_label ) = qs_quote_admin_workflow_status( $post_id );
				echo '<span class="qs-admin-workflow-status" data-workflow-status="' . esc_attr( $workflow_status ) . '">' . esc_html( $workflow_label ) . '</span>';
			} else {
				$status = get_post_status_object( get_post_status( $post_id ) );
				echo esc_html( $status ? $status->label : '' );
			}

			break;

	}

}

add_action(
	'manage_quote_posts_custom_column',
	'qs_quote_column_content',
	10,
	2
);


/**
 * DEV/STAGING workflow simulator for Quote Quick Edit.
 *
 * This is intentionally disabled on the live Loughlin Furniture hostname.
 * It lets administrators prepare screenshot/test quotes without walking every
 * quote through WooCommerce manually.
 */
function qs_quote_quick_edit_testing_enabled() {
	if ( ! is_admin() || ! current_user_can( 'edit_others_posts' ) ) {
		return false;
	}

	return ! function_exists( 'qs_is_live_loughlin_site' ) || ! qs_is_live_loughlin_site();
}

/**
 * Show the customer-facing workflow label in the custom Status column.
 */
function qs_quote_admin_workflow_status( $post_id ) {
	$status = get_post_status( $post_id );

	if ( 'final_balance' === $status ) {
		return array( 'final_balance', 'Final Payment Required' );
	}

	if ( 'deposit_paid' === $status && get_post_meta( $post_id, '_qs_in_production', true ) ) {
		return array( 'qs_in_production', 'In Production' );
	}

	$labels = array(
		'draft'            => 'Draft',
		'pending_review'   => 'Pending Review',
		'awaiting_deposit' => 'Approved - Awaiting Deposit',
		'deposit_paid'     => 'Deposit Paid',
		'paid_in_full'     => 'Paid In Full',
	);

	return array(
		$status,
		isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( '_', ' ', (string) $status ) ),
	);
}

/**
 * Add an optional payment-order checkbox to Quote Quick Edit.
 */
function qs_quote_quick_edit_test_fields( $column_name, $post_type ) {
	if ( 'quote' !== $post_type || 'status' !== $column_name || ! qs_quote_quick_edit_testing_enabled() ) {
		return;
	}
	?>
	<fieldset class="inline-edit-col-right qs-test-workflow-fields">
		<div class="inline-edit-col">
			<label class="alignleft">
				<input type="checkbox" name="qs_test_create_payment_order" value="1">
				<span class="checkbox-title">Create/re-sync payment order when applicable</span>
			</label>
			<p class="description">Testing only. Creates an unpaid WooCommerce deposit/final-balance order for the selected test status.</p>
		</div>
	</fieldset>
	<?php
}
add_action( 'quick_edit_custom_box', 'qs_quote_quick_edit_test_fields', 10, 2 );

/**
 * Add all Quote System workflow choices to the native Quick Edit Status menu.
 * In Production is a virtual display state: deposit_paid + _qs_in_production.
 */
function qs_quote_quick_edit_statuses_script() {
	if ( ! qs_quote_quick_edit_testing_enabled() ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'edit-quote' !== $screen->id ) {
		return;
	}
	?>
	<script>
	jQuery(function($){
		var statuses = [
			['draft', 'Draft'],
			['pending_review', 'Pending Review'],
			['awaiting_deposit', 'Approved - Awaiting Deposit'],
			['deposit_paid', 'Deposit Paid'],
			['qs_in_production', 'In Production'],
			['final_balance', 'Final Payment Required'],
			['paid_in_full', 'Paid In Full']
		];

		function addWorkflowOptions(select) {
			if (!select || !select.length) return;
			$.each(statuses, function(index, status){
				if (!select.find('option[value="' + status[0] + '"]').length) {
					select.append($('<option>', { value: status[0], text: status[1] }));
				}
			});
		}

		addWorkflowOptions($('#inline-edit select[name="_status"]'));

		var originalEdit = inlineEditPost.edit;
		inlineEditPost.edit = function(id) {
			originalEdit.apply(this, arguments);

			var postId = 0;
			if (typeof id === 'object') {
				postId = parseInt(this.getId(id), 10) || 0;
			} else {
				postId = parseInt(id, 10) || 0;
			}
			if (!postId) return;

			var editRow = $('#edit-' + postId);
			var select  = editRow.find('select[name="_status"]');
			addWorkflowOptions(select);

			var marker = $('#post-' + postId + ' .column-status .qs-admin-workflow-status');
			var workflowStatus = marker.data('workflow-status');
			if (workflowStatus && select.find('option[value="' + workflowStatus + '"]').length) {
				select.val(workflowStatus);
			}
		};
	});
	</script>
	<?php
}
add_action( 'admin_footer-edit.php', 'qs_quote_quick_edit_statuses_script' );

/**
 * Translate the virtual In Production Quick Edit choice into the canonical
 * stored post status before WordPress validates/saves the Quote.
 */
function qs_quote_quick_edit_normalize_virtual_status( $data, $postarr ) {
	if (
		! qs_quote_quick_edit_testing_enabled() ||
		! isset( $data['post_type'], $data['post_status'] ) ||
		'quote' !== $data['post_type']
	) {
		return $data;
	}

	if ( 'qs_in_production' === $data['post_status'] ) {
		$data['post_status'] = 'deposit_paid';
	}

	return $data;
}
add_filter( 'wp_insert_post_data', 'qs_quote_quick_edit_normalize_virtual_status', 40, 2 );

/**
 * Apply the virtual workflow metadata and optionally create the relevant
 * WooCommerce payment order after a Quick Edit save.
 */
function qs_quote_quick_edit_apply_test_state( $post_id, $post ) {
	if (
		! qs_quote_quick_edit_testing_enabled() ||
		! $post instanceof WP_Post ||
		'quote' !== $post->post_type ||
		empty( $_POST['action'] ) ||
		'inline-save' !== sanitize_key( wp_unslash( $_POST['action'] ) )
	) {
		return;
	}

	$requested = isset( $_POST['_status'] ) ? sanitize_key( wp_unslash( $_POST['_status'] ) ) : '';
	$allowed   = array( 'draft', 'pending_review', 'awaiting_deposit', 'deposit_paid', 'qs_in_production', 'final_balance', 'paid_in_full' );

	if ( ! in_array( $requested, $allowed, true ) ) {
		return;
	}

	if ( 'qs_in_production' === $requested ) {
		update_post_meta( $post_id, '_qs_in_production', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_qs_in_production_by', get_current_user_id() );
	} else {
		delete_post_meta( $post_id, '_qs_in_production' );
		delete_post_meta( $post_id, '_qs_in_production_by' );
	}

	if ( 'final_balance' === $requested ) {
		update_post_meta( $post_id, '_qs_final_invoice_created_at', current_time( 'mysql' ) );
	}

	if ( empty( $_POST['qs_test_create_payment_order'] ) || ! function_exists( 'qs_create_payment_order' ) ) {
		return;
	}

	if ( 'awaiting_deposit' === $requested ) {
		qs_create_payment_order( $post_id, 'deposit' );
	} elseif ( 'final_balance' === $requested ) {
		qs_create_payment_order( $post_id, 'balance' );
	}
}
add_action( 'save_post_quote', 'qs_quote_quick_edit_apply_test_state', 120, 2 );


/**
 * Add a frontend View Quote row action for the private Quote CPT.
 *
 * Quotes are intentionally not public WordPress posts, so get_permalink()
 * is not useful here. Link administrators to the Quote System review page
 * instead, using the same frontend screen used throughout the workflow.
 */
function qs_quote_frontend_row_action( $actions, $post ) {
	if ( ! $post instanceof WP_Post || 'quote' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	$review_url = function_exists( 'qs_page_url' )
		? qs_page_url( 'quote_review', array( 'quote_id' => $post->ID ) )
		: add_query_arg( 'quote_id', $post->ID, site_url( '/quote-review/' ) );

	$view_action = '<a href="' . esc_url( $review_url ) . '" target="_blank" rel="noopener">View Quote</a>';

	$new_actions = array();
	foreach ( $actions as $key => $action ) {
		$new_actions[ $key ] = $action;
		if ( 'edit' === $key ) {
			$new_actions['qs_view_quote'] = $view_action;
		}
	}

	if ( ! isset( $new_actions['qs_view_quote'] ) ) {
		$new_actions['qs_view_quote'] = $view_action;
	}

	return $new_actions;
}
add_filter( 'post_row_actions', 'qs_quote_frontend_row_action', 20, 2 );
