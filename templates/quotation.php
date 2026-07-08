<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quote_id = isset( $quote_id )
	? absint( $quote_id )
	: 0;

$data = qs_get_quote_data(
	$quote_id
);

$total = qs_calculate_total(
	$quote_id
);

$quote_number = get_post_meta(
	$quote_id,
	'_quote_number',
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

$end_panel_height   = $end_panel_parts[0] ?? '';
$end_panel_width    = $end_panel_parts[1] ?? '';
$end_panel_quantity = $end_panel_parts[2] ?? '';

$filler_parts = explode(
	'|',
	$fillers
);

$filler_width    = $filler_parts[0] ?? '';
$filler_quantity = $filler_parts[1] ?? '';

$kick_parts = explode(
	'|',
	$kickboards
);

$kick_height   = $kick_parts[0] ?? '';
$kick_length   = $kick_parts[1] ?? '';
$kick_quantity = $kick_parts[2] ?? '';

?>



<div class="qs-quotation qs-container">
	<div class="qs-quotation">

	<div class="qs-quotation-header">

		<div class="qs-quotation-logo">

			Loughlin Furniture

		</div>

	</div>

	<div class="qs-quotation-top">

		<div class="qs-quotation-details">

			<p>
				<strong>Project Name:</strong>
				<?php echo esc_html(
					$data['project_name']
				); ?>
			</p>

			<p>
				<strong>Company:</strong>
				<?php echo esc_html(
					$company_name
				); ?>
			</p>

			<p>
				<strong>Created By:</strong>
				<?php echo esc_html(
					$data['customer_name']
				); ?>
			</p>

			<p>
				<strong>Date Created:</strong>
				<?php echo esc_html(
					get_the_date(
						'd F Y',
						$quote_id
					)
				); ?>
			</p>

		</div>

		<div class="qs-quotation-number">

			<strong>
				Quote Number
			</strong>

			<div>
				<?php echo esc_html(
					$quote_number
				); ?>
			</div>

		</div>

	</div>

	<hr>

	<div class="qs-quotation-body">

		<div class="qs-quotation-content">

			<section class="qs-section">

				<h3>
					Project Details
				</h3>

				<div class="qs-spec-grid">

					<div class="qs-spec-item">
						<span>Profile:</span>
						<strong>
							<?php echo esc_html(
								$door_profile
							); ?>
						</strong>
					</div>

					<div class="qs-spec-item">
						<span>Finish:</span>
						<strong>
							<?php echo esc_html(
								$finish
							); ?>
						</strong>
					</div>

					<div class="qs-spec-item">
						<span>Timber:</span>
						<strong>
							<?php echo esc_html(
								$timber
							); ?>
						</strong>
					</div>

					<div class="qs-spec-item">
						<span>Door / Drawer Handle Profile:</span>
						<strong>
							<?php echo esc_html(
								$handle_profile
							); ?>
						</strong>
					</div>

				</div>

			</section>

			<section class="qs-section">

				<h3>
					Delivery Address
				</h3>

				<p>
					<?php echo nl2br(
						esc_html(
							$delivery_address
						)
					); ?>
				</p>

			</section>

			<hr>

			<section class="qs-section">

				<h3>
					Doors & Drawers
				</h3>

				<table class="qs-table">

					<thead>

						<tr>
							<th>#</th>
							<th>Item Type</th>
							<th>Width</th>
							<th>Height</th>
							<th>Quantity</th>
							<th>Edge Profile</th>
						</tr>

					</thead>

					<tbody>

						<tr>
							<td>1</td>

							<td>
								<?php echo esc_html(
									$item_type
								); ?>
							</td>

							<td>
								<?php echo esc_html(
									$item_width
								); ?> mm
							</td>

							<td>
								<?php echo esc_html(
									$item_height
								); ?> mm
							</td>

							<td>
								<?php echo esc_html(
									$item_quantity
								); ?>
							</td>

							<td>
								<?php echo esc_html(
									$item_edge_profile
								); ?>
							</td>

						</tr>

					</tbody>

				</table>

			</section>

			<hr>

			<section class="qs-section">

				<h3>
					End Panels & Fillers
				</h3>

				<h4>
					End Panels
				</h4>

				<table class="qs-table">

					<tr>
						<th>#</th>
						<th>Height</th>
						<th>Width</th>
						<th>Quantity</th>
					</tr>

					<tr>
						<td>1</td>

						<td>
							<?php echo esc_html(
								$end_panel_height
							); ?> mm
						</td>

						<td>
							<?php echo esc_html(
								$end_panel_width
							); ?> mm
						</td>

						<td>
							<?php echo esc_html(
								$end_panel_quantity
							); ?>
						</td>

					</tr>

				</table>

				<h4>
					Fillers
				</h4>

				<table class="qs-table">

					<tr>
						<th>#</th>
						<th>Width</th>
						<th>Quantity</th>
					</tr>

					<tr>
						<td>1</td>

						<td>
							<?php echo esc_html(
								$filler_width
							); ?> mm
						</td>

						<td>
							<?php echo esc_html(
								$filler_quantity
							); ?>
						</td>

					</tr>

				</table>

			</section>

			<hr>

			<section class="qs-section">

				<h3>
					Kickboards
				</h3>

				<table class="qs-table">

					<tr>
						<th>#</th>
						<th>Kick Height</th>
						<th>Kick Length</th>
						<th>Quantity</th>
					</tr>

					<tr>
						<td>1</td>

						<td>
							<?php echo esc_html(
								$kick_height
							); ?> mm
						</td>

						<td>
							<?php echo esc_html(
								$kick_length
							); ?> mm
						</td>

						<td>
							<?php echo esc_html(
								$kick_quantity
							); ?>
						</td>

					</tr>

				</table>

			</section>

			<hr>

			<section class="qs-section">

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

			</section>

		</div>

		<div class="qs-quotation-summary">

			<h2>
				Quote Summary
			</h2>

			<h4>Items Breakdown</h4>

			<div class="qs-summary-section">

				<div class="qs-summary-row">
					<span>Doors</span>
					<span>
						<?php echo esc_html(
							$item_quantity
						); ?>
					</span>
				</div>

				<div class="qs-summary-row">
					<span>End Panels</span>
					<span>
						<?php echo esc_html(
							$end_panel_quantity
						); ?>
					</span>
				</div>

				<div class="qs-summary-row">
					<span>Fillers</span>
					<span>
						<?php echo esc_html(
							$filler_quantity
						); ?>
					</span>
				</div>

				<div class="qs-summary-row">
					<span>Kickboards</span>
					<span>
						<?php echo esc_html(
							$kick_quantity
						); ?>
					</span>
				</div>

			</div>

			<hr>

			<div class="qs-summary-row">

				<strong>
					Estimated Lead Time
				</strong>

				<span>
					4–6 Weeks
				</span>

			</div>

			<hr>

			<div class="qs-summary-total">

				<strong>
					Subtotal
				</strong>

				<span>
					$
					<?php echo number_format(
						$total,
						2
					); ?>
					AUD
				</span>

			</div>

		</div>

	</div>

	<div class="qs-quotation-disclaimer">

		<p>
			This quotation is valid for
			30 days from the issue date.
		</p>

		<p>
			Final pricing may vary depending
			on confirmed specifications.
		</p>

	</div>
</div>