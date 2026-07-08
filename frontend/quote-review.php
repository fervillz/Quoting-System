<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quote Review Shortcode
 */
function qs_quote_review_shortcode() {

	$success_message = '';

	/**
	 * Submit Quote
	 */
	if ( isset( $_POST['qs_submit_quote'] ) ) {

		$quote_id = absint(
			$_POST['quote_id']
		);

		qs_update_quote_status(
			$quote_id,
			'pending_review'
		);

		qs_email_quote_submitted(
			$quote_id
		);

		wp_redirect(
			add_query_arg(
				'quote_id',
				$quote_id,
				site_url(
					'/quote-thank-you/'
				)
			)
		);

		exit;

	}

	$quote_id = isset( $_GET['quote_id'] )
		? absint( $_GET['quote_id'] )
		: 0;

	if ( ! $quote_id ) {
		return '<p>Quote not found.</p>';
	}

	$data = qs_get_quote_data(
		$quote_id
	);

	$total = qs_calculate_total(
	$quote_id
);

$door_profile = get_post_meta(
	$quote_id,
	'_door_profile',
	true
);

$timber = get_post_meta(
	$quote_id,
	'_timber',
	true
);

$finish = get_post_meta(
	$quote_id,
	'_finish',
	true
);

$handle_profile = get_post_meta(
	$quote_id,
	'_handle_profile',
	true
);

$company_name = get_post_meta(
	$quote_id,
	'_company_name',
	true
);

$delivery_address = get_post_meta(
	$quote_id,
	'_delivery_address',
	true
);

$project_notes = get_post_meta(
	$quote_id,
	'_project_notes',
	true
);

$doors_drawers = get_post_meta(
	$quote_id,
	'_doors_drawers',
	true
);

$end_panels = get_post_meta(
	$quote_id,
	'_end_panels',
	true
);

$fillers = get_post_meta(
	$quote_id,
	'_fillers',
	true
);

$kickboards = get_post_meta(
	$quote_id,
	'_kickboards',
	true
);

$door_parts = explode(
	'|',
	$doors_drawers
);

$item_type         = $door_parts[0] ?? '';
$item_width        = $door_parts[1] ?? '';
$item_height       = $door_parts[2] ?? '';
$item_quantity     = $door_parts[3] ?? '';
$item_edge_profile = $door_parts[4] ?? '';

$end_panel_parts = explode(
	'|',
	$end_panels
);

$filler_parts = explode(
	'|',
	$fillers
);

$filler_width    = $filler_parts[0] ?? '';
$filler_quantity = $filler_parts[1] ?? '';

$end_panel_height   = $end_panel_parts[0] ?? '';
$end_panel_width    = $end_panel_parts[1] ?? '';
$end_panel_quantity = $end_panel_parts[2] ?? '';

$kick_parts = explode(
	'|',
	$kickboards
);

$kick_height   = $kick_parts[0] ?? '';
$kick_length   = $kick_parts[1] ?? '';
$kick_quantity = $kick_parts[2] ?? '';

	ob_start();

	?>

	<div class="qs-container qs-review-layout">

	<div class="qs-review-main">

		<h2>
			Review Your Quote
		</h2>

		<p>
			Please review the details below before submitting your quote to Loughlin Furniture.
You can edit your selections if changes are required.
		</p>

		<hr>

		<h3>
			Project Details
		</h3>

		<p>
			<strong>Company:</strong>
			<?php echo esc_html( $company_name ); ?>
		</p>

		<p>
			<strong>Project Name:</strong>
			<?php echo esc_html(
				$data['project_name']
			); ?>
		</p>

		<p>
			<strong>Pricing Mode:</strong>
			Trade Pricing
		</p>

		<p>
			<strong>Delivery Address:</strong>
			<?php echo esc_html(
				$delivery_address
			); ?>
		</p>

		<hr>

		<h3>
			Selected Specifications
		</h3>

		<p>
			Profile:
			<?php echo esc_html(
				$door_profile
			); ?>
		</p>

		<p>
			Timber:
			<?php echo esc_html(
				$timber
			); ?>
		</p>

		<p>
			Finish:
			<?php echo esc_html(
				$finish
			); ?>
		</p>

		<p>
			Handle:
			<?php echo esc_html(
				$handle_profile
			); ?>
		</p>

		<hr>

		<h3>
			Doors & Drawers
		</h3>

		<table class="qs-table">

			<tr>
				<th>#</th>
				<th>Type</th>
				<th>Width</th>
				<th>Height</th>
				<th>Qty</th>
				<th>Edge Profile</th>
			</tr>

			<tr>
				<td>1</td>
				<td><?php echo esc_html( $item_type ); ?></td>
				<td><?php echo esc_html( $item_width ); ?> mm</td>
				<td><?php echo esc_html( $item_height ); ?> mm</td>
				<td><?php echo esc_html( $item_quantity ); ?></td>
				<td><?php echo esc_html( $item_edge_profile ); ?></td>
			</tr>

		</table>

		<hr>

		<h3>
			End Panels & Fillers
		</h3>

		<table class="qs-table">

			<tr>
				<th>#</th>
				<th>Height</th>
				<th>Width</th>
				<th>Qty</th>
			</tr>

			<tr>
				<td>1</td>
				<td><?php echo esc_html( $end_panel_height ); ?> mm</td>
				<td><?php echo esc_html( $end_panel_width ); ?> mm</td>
				<td><?php echo esc_html( $end_panel_quantity ); ?></td>
			</tr>

		</table>

		<hr>

		<h3>
			Fillers
		</h3>

		<table class="qs-table">

			<tr>
				<th>#</th>
				<th>Width</th>
				<th>Qty</th>
			</tr>

			<tr>
				<td>1</td>
				<td><?php echo esc_html( $filler_width ); ?> mm</td>
				<td><?php echo esc_html( $filler_quantity ); ?></td>
			</tr>

		</table>

		<hr>

		<h3>
			Kickboards
		</h3>

		<table class="qs-table">

			<tr>
				<th>#</th>
				<th>Kick Height</th>
				<th>Kick Length</th>
				<th>Qty</th>
			</tr>

			<tr>
				<td>1</td>
				<td><?php echo esc_html( $kick_height ); ?> mm</td>
				<td><?php echo esc_html( $kick_length ); ?> mm</td>
				<td><?php echo esc_html( $kick_quantity ); ?></td>
			</tr>

		</table>

		<hr>

		<h3>
			Project Notes
		</h3>

		<p>
			<?php echo nl2br(
				esc_html(
					$project_notes
				)
			); ?>
		</p>

	</div>

	<div class="qs-review-summary">

		<h3>
			Quote Summary
		</h3>

		<h4>Items Breakdown</h4>

		<p>
			Doors:
			<?php echo esc_html( $item_quantity ); ?>
		</p>

		<p>
			End Panels:
			<?php echo esc_html( $end_panel_quantity ); ?>
		</p>

		<p>
			Fillers:
			<?php echo esc_html( $filler_quantity ); ?>
		</p>

		<p>
			Kickboards:
			<?php echo esc_html( $kick_quantity ); ?>
		</p>

		<hr>

		<p>
			Estimated Lead Time:
			4-6 Weeks
		</p>

		<hr>

		<h4>
			Subtotal
		</h4>

		<h3>
			$<?php echo number_format(
				$total,
				2
			); ?> AUD
		</h3>

	<div class="qs-review-buttons">

		<a
			href="<?php echo esc_url(
				add_query_arg(
					'quote_id',
					$quote_id,
					site_url(
						'/quote-builder/'
					)
				)
			); ?>"
			class="qs-btn qs-btn-secondary"
		>
			Edit Quote
		</a>

		<?php if ( 'draft' === get_post_status( $quote_id ) ) : ?>

			<form method="post">

				<input
					type="hidden"
					name="quote_id"
					value="<?php echo esc_attr(
						$quote_id
					); ?>"
				>

				<button
					type="submit"
					name="qs_submit_quote"
					class="qs-btn qs-btn-primary"
				>
					Submit Quote
				</button>

			</form>

		<?php endif; ?>

</div>

	</div>

</div>

	<?php

	return ob_get_clean();

}

add_shortcode(
	'quote_review',
	'qs_quote_review_shortcode'
);