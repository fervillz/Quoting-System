<?php
/**
 * Customer quotation PDF.
 *
 * The detailed quotation uses normal block flow so Dompdf can paginate the
 * left column naturally. The summary is floated on the right rather than
 * placing both columns in one large table row, which Dompdf cannot split.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quote_id     = isset( $quote_id ) ? absint( $quote_id ) : 0;
$data         = isset( $data ) && is_array( $data ) ? $data : qs_get_quote_data( $quote_id );
$rows         = $data['component_rows'];
$fronts       = array_values(
	array_filter(
		$rows['doors_drawers'],
		static function ( $row ) {
			return empty( $row['type'] ) || 'Drawer Bank' !== $row['type'];
		}
	)
);
$drawer_banks = qs_component_rows_by_type( $rows['doors_drawers'], 'Drawer Bank' );
$subtotal     = (float) $data['subtotal'];
$created_by   = $data['created_by'] ? $data['created_by'] : $data['customer_name'];
$specs        = array(
	array( 'Timber', $data['timber'], $data['paint_colour'] ? 'Paint Colour: ' . $data['paint_colour'] : '' ),
	array( 'Finish', $data['finish'], '' ),
	array( 'Profile', $data['door_profile'], '' ),
	array( 'Door / Drawer Handle Profile', $data['handle_profile'], '' ),
);
$has_legacy_specs = (bool) array_filter(
	array( $data['timber'], $data['finish'], $data['door_profile'], $data['handle_profile'], $data['paint_colour'] )
);
?>
<div class="qs-pdf qs-quotation-pdf">
	<header class="qs-pdf-header">
		<strong>Loughlin Furniture</strong>
	</header>

	<main class="qs-pdf-page">
		<table class="qs-pdf-project-header">
			<tr>
				<td>
					<p><strong>Project Name:</strong> <?php echo esc_html( $data['project_name'] ); ?></p>
					<p><strong>Company:</strong> <?php echo esc_html( $data['company_name'] ); ?></p>
					<p><strong>Created By:</strong> <?php echo esc_html( $created_by ); ?></p>
					<p><strong>Pricing Type:</strong> <?php echo esc_html( $data['pricing_type'] ); ?></p>
					<p><strong>Date Created:</strong> <?php echo esc_html( $data['date_created'] ); ?></p>
				</td>
				<td class="qs-pdf-quote-number">
					<strong>Quote Number</strong>
					<span><?php echo esc_html( $data['quote_number'] ); ?></span>
				</td>
			</tr>
		</table>

		<div class="qs-pdf-columns">
			<aside class="qs-pdf-summary">
				<h2>Items Breakdown</h2>
				<?php foreach ( qs_quote_summary_groups( $quote_id ) as $group ) : ?>
					<?php if ( ! $group['rows'] ) { continue; } ?>
					<div class="qs-pdf-summary-group">
						<strong><?php echo esc_html( $group['title'] . ' (' . count( $group['rows'] ) . ')' ); ?></strong>
						<?php foreach ( $group['rows'] as $row ) : ?>
							<p>
								<span><?php echo esc_html( qs_quote_summary_primary( $group['component'], $row ) ); ?></span>
								<em><?php echo esc_html( isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0 ); ?></em>
								<?php if ( qs_quote_summary_secondary( $group['component'], $row ) ) : ?>
									<small><?php echo nl2br( esc_html( qs_quote_summary_secondary( $group['component'], $row ) ) ); ?></small>
								<?php endif; ?>
							</p>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>

				<div class="qs-pdf-lead-time"><strong>Estimated Lead Time</strong><span><?php echo esc_html( function_exists( 'qs_get_estimated_lead_time' ) ? qs_get_estimated_lead_time( $quote_id ) : '4–6 Weeks' ); ?></span></div>
				<div class="qs-pdf-subtotal"><strong>Subtotal</strong><span>$<?php echo esc_html( number_format_i18n( $subtotal, 2 ) ); ?> AUD</span></div>
			</aside>

			<div class="qs-pdf-content">
				<section class="qs-pdf-section qs-pdf-project-details">
					<h2>Project Details</h2>
					<?php if ( $has_legacy_specs ) : ?>
						<table class="qs-pdf-specifications">
							<?php for ( $i = 0; $i < count( $specs ); $i += 2 ) : ?>
								<tr>
									<?php for ( $j = $i; $j < $i + 2; $j++ ) : ?>
										<?php if ( isset( $specs[ $j ] ) ) : ?>
											<td class="qs-pdf-swatch-cell"><span class="qs-pdf-swatch"></span></td>
											<td>
												<strong><?php echo esc_html( $specs[ $j ][0] ); ?>:</strong>
												<span><?php echo esc_html( $specs[ $j ][1] ? $specs[ $j ][1] : '-' ); ?></span>
												<?php if ( $specs[ $j ][2] ) : ?><small><?php echo esc_html( $specs[ $j ][2] ); ?></small><?php endif; ?>
											</td>
										<?php else : ?>
											<td></td><td></td>
										<?php endif; ?>
									<?php endfor; ?>
								</tr>
							<?php endfor; ?>
						</table>
					<?php endif; ?>
					<h3>Delivery Address</h3>
					<p><?php echo nl2br( esc_html( $data['delivery_address'] ) ); ?></p>
				</section>

				<section class="qs-pdf-section">
					<h2>Doors &amp; Drawers</h2>
					<p class="qs-pdf-note"><em>Grains run vertical (height)</em></p>
					<?php qs_render_quote_component_table( $fronts, array( 'type' => 'Item Type', 'item_specifications' => 'Specifications', 'width' => 'Width', 'height' => 'Height', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table' ); ?>
					<?php if ( $drawer_banks ) : ?>
						<h3>Drawer Banks</h3>
						<?php qs_render_quote_component_table( $drawer_banks, array( 'type' => 'Item Type', 'item_specifications' => 'Specifications', 'configuration' => 'Configuration', 'width' => 'Width', 'height_details' => 'Height', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table', 'No drawer banks supplied.', count( $fronts ) + 1 ); ?>
					<?php endif; ?>
				</section>

				<section class="qs-pdf-section">
					<h2>End Panels &amp; Fillers</h2>
					<div class="qs-pdf-subsection">
						<h3>End Panels</h3>
						<?php qs_render_quote_component_table( $rows['end_panels'], array( 'item_specifications' => 'Specifications', 'width' => 'Width', 'height' => 'Height', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table' ); ?>
					</div>
					<div class="qs-pdf-subsection">
						<h3>Fillers</h3>
						<?php qs_render_quote_component_table( $rows['fillers'], array( 'item_specifications' => 'Specifications', 'width' => 'Width', 'height' => 'Height', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table' ); ?>
					</div>
				</section>

				<section class="qs-pdf-section qs-pdf-kickboards">
					<h2>Kickboards</h2>
					<div class="qs-pdf-kickboard-intro">
						<p class="qs-pdf-note"><em>* Grain runs long / horizontal<br>* Max 2400mm per piece<br>* 1 face / no edges finished</em></p>
						<?php qs_render_quote_component_table( $rows['kickboards'], array( 'material' => 'Kick Material', 'item_specifications' => 'Specifications', 'height' => 'Kick Height', 'length' => 'Kick Length', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table' ); ?>
					</div>
				</section>

				<?php if ( $data['custom_requests'] ) : ?>
					<section class="qs-pdf-section qs-pdf-small-section"><h2>Custom Requests</h2><p><?php echo nl2br( esc_html( $data['custom_requests'] ) ); ?></p></section>
				<?php endif; ?>

				<section class="qs-pdf-section qs-pdf-notes qs-pdf-small-section">
					<h2>Project Notes</h2>
					<p><?php echo nl2br( esc_html( $data['project_notes'] ? $data['project_notes'] : '-' ) ); ?></p>
				</section>
			</div>
			<div class="qs-pdf-clear"></div>
		</div>
	</main>

	<footer class="qs-pdf-footer">
		<div class="qs-pdf-disclaimer">This quotation is valid for 30 days from the issue date.<br>Final pricing may vary depending on confirmed specifications.</div>
		<table class="qs-pdf-contact">
			<tr>
				<td class="qs-pdf-footer-brand"><strong>Loughlin<br>Furniture</strong></td>
				<td><strong>Location</strong><br>Unit 1, 305 Manns Road<br>West Gosford NSW 2250</td>
				<td><strong>Email</strong><br>info@loughlinfurniture.com.au<br><br><strong>Phone</strong><br>02 4322 2186</td>
				<td><strong>Website</strong><br>www.loughlinfurniture.com.au</td>
			</tr>
		</table>
	</footer>
</div>
