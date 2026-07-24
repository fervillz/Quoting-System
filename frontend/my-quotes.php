Exit code: 0
Wall time: 0.5 seconds
Output:
<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * My Quotes Shortcode
 */
function qs_my_quotes_shortcode() {
	

	$args = array(
			'post_type'      => 'quote',
			'post_status'    => array(
				'publish',
				'draft',
				'pending_review',
				'awaiting_deposit',
				'deposit_paid',
				'final_balance',
				'paid_in_full',
			),
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
	// This shortcode is the customer-facing dashboard.  An administrator can
	// see all quotes, while every other user only sees their own work.
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		$args['author'] = get_current_user_id();
	}
	$quotes = get_posts( $args );

	$draft_count = 0;
	$pending_count = 0;
	$deposit_count = 0;
	$approved_count = 0;
	$completed_count = 0;

	foreach ( $quotes as $quote ) {

		$status = get_post_status(
			$quote->ID
		);

		switch ( $status ) {

			case 'draft':
				$draft_count++;
				break;

			case 'pending_review':
				$pending_count++;
				break;

			case 'awaiting_deposit':
				$deposit_count++;
				break;

			case 'deposit_paid':
				$approved_count++;
				break;

			case 'paid_in_full':
				$completed_count++;
				break;

		}

	}

	ob_start();

	?>

	<h2>My Quotes</h2>

		<div
		style="
			display:flex;
			gap:15px;
			margin-bottom:30px;
			flex-wrap:wrap;
		"
	>

		<div style="border:1px solid #ccc;padding:20px;min-width:150px;text-align:center;">
			<strong>Draft Quotes</strong>
			<br>
			<?php echo esc_html( $draft_count ); ?>
		</div>

		<div style="border:1px solid #ccc;padding:20px;min-width:150px;text-align:center;">
			<strong>Pending Review</strong>
			<br>
			<?php echo esc_html( $pending_count ); ?>
		</div>

		<div style="border:1px solid #ccc;padding:20px;min-width:150px;text-align:center;">
			<strong>Deposit Requested</strong>
			<br>
			<?php echo esc_html( $deposit_count ); ?>
		</div>

		<div style="border:1px solid #ccc;padding:20px;min-width:150px;text-align:center;">
			<strong>Approved Quotes</strong>
			<br>
			<?php echo esc_html( $approved_count ); ?>
		</div>

		<div style="border:1px solid #ccc;padding:20px;min-width:150px;text-align:center;">
			<strong>Completed</strong>
			<br>
			<?php echo esc_html( $completed_count ); ?>
		</div>

	</div>

	<?php

	$draft_quotes = array();
	$submitted_quotes = array();

	foreach ( $quotes as $quote ) {

		$status = get_post_status( $quote->ID );

		if ( 'draft' === $status ) {
			$draft_quotes[] = $quote;
		} else {
			$submitted_quotes[] = $quote;
		}

	}

	?>

	<h3>Saved Drafts</h3>

	<?php if ( empty( $draft_quotes ) ) : ?>

		<p>No draft quotes found.</p>

	<?php else : ?>

		<table style="width:100%;">

			<tr>
				<th>Quote Ref</th>
				<th>Project Name</th>
				<th>Created By</th>
				<th>Last Updated</th>
				<th>Status</th>
				<th>Action</th>
			</tr>

			<?php foreach ( $draft_quotes as $quote ) : ?>

				<?php

				$quote_number = get_post_meta(
					$quote->ID,
					'_quote_number',
					true
				);

				$created_by = get_the_author_meta(
					'display_name',
					$quote->post_author
				);

				$updated_date = get_the_modified_date(
					'd M Y',
					$quote->ID
				);

				?>

				<tr>

					<td><?php echo esc_html( $quote_number ); ?></td>

					<td><?php echo esc_html( $quote->post_title ); ?></td>

					<td><?php echo esc_html( $created_by ); ?></td>

					<td><?php echo esc_html( $updated_date ); ?></td>

					<td>Draft</td>

					<td>

						<a href="<?php echo esc_url(
							add_query_arg(
								'quote_id',
								$quote->ID,
								site_url( '/quote-builder/' )
							)
						); ?>">
							Continue Quote
						</a>

					</td>

				</tr>

			<?php endforeach; ?>

		</table>

	<?php endif; ?>

	<br><br>

	<h3>Submitted Quotes</h3>

	<?php if ( empty( $submitted_quotes ) ) : ?>

		<p>No submitted quotes found.</p>

	<?php else : ?>

		<table style="width:100%;">

			<tr>
				<th>Quote Ref</th>
				<th>Project Name</th>
				<th>Created By</th>
				<th>Last Updated</th>
				<th>Status</th>
				<th>Action</th>
			</tr>

			<?php foreach ( $submitted_quotes as $quote ) : ?>

				<?php

				$quote_number = get_post_meta(
					$quote->ID,
					'_quote_number',
					true
				);

				$created_by = get_the_author_meta(
					'display_name',
					$quote->post_author
				);

				$updated_date = get_the_modified_date(
					'd M Y',
					$quote->ID
				);

				$status = get_post_status(
					$quote->ID
				);

				$statuses = qs_get_quote_statuses();

				$status_label = isset(
					$statuses[ $status ]
				)
					? $statuses[ $status ]
					: ucfirst( $status );

				?>

				<tr>

					<td><?php echo esc_html( $quote_number ); ?></td>

					<td><?php echo esc_html( $quote->post_title ); ?></td>

					<td><?php echo esc_html( $created_by ); ?></td>

					<td><?php echo esc_html( $updated_date ); ?></td>

					<td><?php echo esc_html( $status_label ); ?></td>

					<td>

						<?php if ( 'pending_review' === $status ) : ?>

							<a href="<?php echo esc_url(
								add_query_arg(
									'quote_id',
									$quote->ID,
									site_url( '/quote-review/' )
								)
							); ?>">
								View
							</a>

						<?php elseif ( 'awaiting_deposit' === $status ) : ?>

							<?php $payment_url = qs_get_quote_payment_url( $quote->ID, 'deposit' ); ?>
							<?php if ( $payment_url ) : ?><a href="<?php echo esc_url( $payment_url ); ?>">Pay Deposit</a><?php else : ?>Deposit link being prepared<?php endif; ?>

						<?php elseif ( 'deposit_paid' === $status ) : ?>

							View

						<?php elseif ( 'final_balance' === $status ) : ?>

							<?php $payment_url = qs_get_quote_payment_url( $quote->ID, 'balance' ); ?>
							<?php if ( $payment_url ) : ?><a href="<?php echo esc_url( $payment_url ); ?>">Pay Balance</a><?php else : ?>Balance link being prepared<?php endif; ?>

						<?php elseif ( 'paid_in_full' === $status ) : ?>

							Download PDF

						<?php else : ?>

							View

						<?php endif; ?>

					</td>

				</tr>

			<?php endforeach; ?>

		</table>

	<?php endif; ?>

	<?php

	return ob_get_clean();

}

add_shortcode(
	'my_quotes',
	'qs_my_quotes_shortcode'
);

