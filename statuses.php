<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all custom Quote workflow statuses.
 *
 * Workflow:
 * Draft
 * → Pending Review
 * → Approved - Awaiting Deposit
 * → Deposit Paid
 * → Final Balance
 * → Paid In Full
 */
function qs_register_post_statuses() {

	$statuses = array(
		'pending_review'            => 'Pending Review',
		'awaiting_deposit'			=> 'Approved - Awaiting Deposit',
		'deposit_paid'              => 'Deposit Paid',
		'final_balance'             => 'Final Balance',
		'paid_in_full'              => 'Paid In Full',
	);

	foreach ( $statuses as $status => $label ) {

		register_post_status(
			$status,
			array(
				'label'                     => $label,
				'public'                    => true,
				'show_in_admin_status_list' => true,
				'show_in_admin_all_list'    => true,
				'label_count'               => _n_noop(
					$label . ' <span class="count">(%s)</span>',
					$label . ' <span class="count">(%s)</span>'
				),
			)
		);

	}

}

add_action( 'init', 'qs_register_post_statuses' );

/**
 * Add custom Quote statuses to the Status dropdown.
 *
 * WordPress registers custom statuses but does not always
 * display them in the Publish meta box dropdown.
 *
 * This function appends any missing Quote statuses
 * while preventing duplicates.
 */
function qs_append_statuses_to_dropdown() {

	global $post;

	/**
	 * Only run on Quote edit screens.
	 */
	if ( ! $post || 'quote' !== $post->post_type ) {
		return;
	}

	?>
	<script>
	jQuery(function($){

		/**
		 * Quote workflow statuses.
		 */
		var currentStatus = <?php echo wp_json_encode( 'pending' === $post->post_status ? 'pending_review' : $post->post_status ); ?>;
		var statuses = [
			['pending_review', 'Quote — Pending Review'],
			['awaiting_deposit', 'Approved - Awaiting Deposit'],
			['deposit_paid', 'Deposit Paid'],
			['final_balance', 'Final Balance'],
			['paid_in_full', 'Paid In Full']
		];

		/**
		 * Add any missing statuses.
		 */
		$.each(statuses, function(index, status){

			if (
				$('#post_status option[value="' + status[0] + '"]').length === 0
			) {

				$('#post_status').append(
					$('<option>', {
						value: status[0],
						text: status[1]
					})
				);

			}

		});

		/**
		 * WordPress can fall back to `publish` when the current custom status
		 * was not present when the Publish box was rendered. Restore the real
		 * workflow selection after all custom options have been added.
		 */
		if ($('#post_status option[value="' + currentStatus + '"]').length) {
			$('#post_status').val(currentStatus);
			$('#post-status-display').text($('#post_status option:selected').text());
		}

	});
	</script>
	<?php

}

add_action( 'admin_footer-post.php', 'qs_append_statuses_to_dropdown' );
add_action( 'admin_footer-post-new.php', 'qs_append_statuses_to_dropdown' );

/**
 * Return all Quote statuses.
 */
function qs_get_quote_statuses() {

	return array(

		'draft'                     => 'Draft',

		'pending_review'            => 'Pending Review',

		'awaiting_deposit'			=> 'Approved - Awaiting Deposit',

		'deposit_paid'              => 'Deposit Paid',

		'final_balance'             => 'Final Balance',

		'paid_in_full'              => 'Paid In Full',

	);

}

/**
 * Keep WordPress's built-in "Save as Pending" action aligned with the
 * Quote System workflow. WordPress submits `pending`, while this plugin uses
 * `pending_review` for quotes awaiting administrator review.
 */
function qs_normalize_pending_quote_status( $data, $postarr ) {
	if ( ! isset( $data['post_type'], $data['post_status'] ) || 'quote' !== $data['post_type'] ) {
		return $data;
	}

	if ( 'pending' === $data['post_status'] ) {
		$data['post_status'] = 'pending_review';
	}

	/**
	 * A normal edit in wp-admin must not move a quote out of its workflow.
	 * This specifically protects pricing and internal-note updates when the
	 * Publish box incorrectly submits WordPress's generic `publish` status.
	 */
	$post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
	if ( $post_id && 'publish' === $data['post_status'] ) {
		$current_status = get_post_status( $post_id );
		$current_status = 'pending' === $current_status ? 'pending_review' : $current_status;

		if ( isset( qs_get_quote_statuses()[ $current_status ] ) ) {
			$data['post_status'] = $current_status;
		}
	}

	return $data;
}
add_filter( 'wp_insert_post_data', 'qs_normalize_pending_quote_status', 10, 2 );

/**
 * Update Quote status.
 */
function qs_update_quote_status(
	$quote_id,
	$status
) {
 
	$post = get_post( $quote_id );

	if ( ! $post || 'quote' !== $post->post_type ) {
		return false;
	}

	$statuses = qs_get_quote_statuses();

	if ( ! isset( $statuses[ $status ] ) ) {
		return false;
	}

	$result = wp_update_post(
		array(
			'ID'          => $quote_id,
			'post_status' => $status,
		),
		true
	);

	error_log(
		print_r( $result, true )
	);

	return $result;

}