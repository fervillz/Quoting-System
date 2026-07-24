<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quote_id = $quote->ID;

$shipping = get_post_meta(
	$quote_id,
	'_shipping',
	true
);

$discount = get_post_meta(
	$quote_id,
	'_discount',
	true
);

$internal_notes = get_post_meta(
	$quote_id,
	'_internal_notes',
	true
);

?>

<form method="post" class="qs-admin-review">

	<input
		type="hidden"
		name="quote_id"
		value="<?php echo esc_attr( $quote_id ); ?>"
	>

	<div class="qs-admin-review-grid">

		<div class="qs-admin-review-left">

			<h3>
				Pricing Adjustments
			</h3>

			<div class="qs-form-row">

				<label>
					Shipping
				</label>

				<input
					type="number"
					step="0.01"
					name="shipping"
					value="<?php echo esc_attr( $shipping ); ?>"
				>

			</div>

			<div class="qs-form-row">

				<label>
					Discount
				</label>

				<input
					type="number"
					step="0.01"
					name="discount"
					value="<?php echo esc_attr( $discount ); ?>"
				>

			</div>

			<h3>
				Internal Notes
			</h3>

			<textarea
				name="internal_notes"
				rows="8"
			><?php
				echo esc_textarea(
					$internal_notes
				);
			?></textarea>

		</div>

		<div class="qs-admin-review-right">

			<h3>
				Documents
			</h3>

			<p>

				<a
					class="qs-btn qs-btn-secondary"
					target="_blank"
					href="<?php echo esc_url(
						add_query_arg(
							'download_quote_pdf',
							$quote_id,
							site_url()
						)
					); ?>"
				>
					Quotation PDF
				</a>

			</p>

			<p>

				<a
					class="qs-btn qs-btn-secondary"
					target="_blank"
					href="<?php echo esc_url(
						add_query_arg(
							'download_jobsheet_pdf',
							$quote_id,
							site_url()
						)
					); ?>"
				>
					Job Sheet
				</a>

			</p>

			<hr>

			<p>

				<button
					type="submit"
					name="qs_save_review"
					class="qs-btn qs-btn-secondary"
				>
					Save Changes
				</button>

			</p>

			<p>

				<button
					type="submit"
					name="qs_request_deposit"
					class="qs-btn"
				>
					Request Deposit
				</button>

			</p>

		</div>

	</div>

</form>