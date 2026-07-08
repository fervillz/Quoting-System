<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_quote_submitted_shortcode() {

	$quote_id = isset( $_GET['quote_id'] )
		? absint( $_GET['quote_id'] )
		: 0;

	if ( ! $quote_id ) {
		return '<p>Quote not found.</p>';
	}

	$quote_number = get_post_meta(
		$quote_id,
		'_quote_number',
		true
	);

	$project_name = get_post_meta(
		$quote_id,
		'_project_name',
		true
	);

	$customer_name = get_post_meta(
		$quote_id,
		'_customer_name',
		true
	);

	$status = get_post_status(
		$quote_id
	);

	$statuses = qs_get_quote_statuses();

	$status_label = isset(
		$statuses[ $status ]
	)
		? $statuses[ $status ]
		: ucfirst( $status );

	ob_start();

	?>

	<div class="quote-sub-wrap qs-container">
		<h2>
			Quote Submitted Successfully
		</h2>

		<p>
			<b>Thank you for submitting your quote.</b>
		</p>

		<p>
			Your quote has been sent to the
			Loughlin Furniture team for review.
		</p>

		<div
			style="
				background:#f8f1f1;
				padding:30px;
				margin:30px 0;
				text-align:center;
			"
		>

			<p>
				<strong>Quote Reference</strong>
			</p>

			<h2>
				<?php echo esc_html( $quote_number ); ?>
			</h2>

			<p>
				<strong>Project Name</strong>
			</p>

			<h3>
				<?php echo esc_html( $project_name ); ?>
			</h3>

			<p>
				<strong>Submitted By</strong>
			</p>

			<h3>
				<?php echo esc_html( $customer_name ); ?>
			</h3>

			<p>
				<strong>Date Submitted</strong>
			</p>

			<h3>
				<?php echo esc_html(
					get_the_date(
						'd F Y',
						$quote_id
					)
				); ?>
			</h3>

			<p>
				<strong>Status</strong>
			</p>

			<h3>
				<?php echo esc_html(
					$status_label
				); ?>
			</h3>

		</div>

		<h2>
			What Happens Next
		</h2>

		<ul>

			<li>
				Our team will review your quote
			</li>

			<li>
				You will receive an invoice once
				the quote is approved
			</li>

			<li>
				Production begins after deposit
				confirmation
			</li>

		</ul>

		<div class="quote-actions">

			<a
				href="<?php echo esc_url(
					site_url(
						'/quote-builder/'
					)
				); ?>"
				class="qs-btn-outline"
			>
				Create Another Quote
			</a>
			
			<a
				href="<?php echo esc_url(
					add_query_arg(
						'download_quote_pdf',
						$quote_id,
						home_url( '/' )
					)
				); ?>"
				class="qs-btn-solid"
				target="_blank"
			>
				Download Quote PDF
			</a>
			
			<a
				href="<?php echo esc_url(
					site_url(
						'/my-quotes/'
					)
				); ?>"
			>
				View My Quotes
			</a>

		</div>

		<div class="quote-help">

			<h2>Need Help?</h2>

			<p>
				Contact us at
				<a href="mailto:info@loughlinfurniture.com.au">
					info@loughlinfurniture.com.au
				</a>
				or call
				<a href="tel:0243222186">
					(02) 4322 2186
				</a>.
			</p>

			<p>
				We're happy to assist with any questions about your order.
			</p>

		</div>
	</div>

	<?php

	return ob_get_clean();

}

add_shortcode(
	'quote_submitted',
	'qs_quote_submitted_shortcode'
);