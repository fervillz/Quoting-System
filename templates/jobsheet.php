<?php
/**
 * Internal production job-sheet PDF.
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
$created_by   = $data['created_by'] ? $data['created_by'] : $data['customer_name'];
$specs        = array(
	array( 'Timber', $data['timber'], $data['paint_colour'] ? 'Paint Colour: ' . $data['paint_colour'] : '' ),
	array( 'Profile', $data['door_profile'], '' ),
	array( 'Finish', $data['finish'], '' ),
	array( 'Door / Drawer Handle Profile', $data['handle_profile'], '' ),
);
$has_legacy_specs = (bool) array_filter(
	array( $data['timber'], $data['finish'], $data['door_profile'], $data['handle_profile'], $data['paint_colour'] )
);
?>
<div class="qs-pdf qs-jobsheet-pdf">
	<header class="qs-pdf-header">
		<table class="qs-pdf-header-table">
			<tr><td><strong>Loughlin Furniture</strong></td><td><span>Job Sheet</span></td></tr>
		</table>
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
					<p><strong>Delivery Address:</strong> <?php echo nl2br( esc_html( $data['delivery_address'] ) ); ?></p>
				</td>
				<td class="qs-pdf-quote-number">
					<strong>Quote Number</strong>
					<span><?php echo esc_html( $data['quote_number'] ); ?></span>
				</td>
			</tr>
		</table>

		<section class="qs-pdf-section qs-pdf-project-details">
			<h2>Project Details</h2>
			<?php if ( $has_legacy_specs ) : ?>
				<table class="qs-pdf-specifications">
					<tr>
						<?php foreach ( $specs as $spec ) : ?>
							<td class="qs-pdf-swatch-cell"><span class="qs-pdf-swatch"></span></td>
							<td>
								<strong><?php echo esc_html( $spec[0] ); ?>:</strong>
								<span><?php echo esc_html( $spec[1] ? $spec[1] : '-' ); ?></span>
								<?php if ( $spec[2] ) : ?><small><?php echo esc_html( $spec[2] ); ?></small><?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				</table>
			<?php endif; ?>
		</section>

		<section class="qs-pdf-section">
			<h2>Doors &amp; Drawers</h2>
			<?php qs_render_quote_component_table( $fronts, array( 'type' => 'Item Type', 'item_specifications' => 'Specifications', 'width' => 'Width', 'height' => 'Height', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table qs-jobsheet-doors-table' ); ?>
			<?php if ( $drawer_banks ) : ?>
				<h3>Drawer Banks</h3>
				<?php qs_render_quote_component_table( $drawer_banks, array( 'type' => 'Item Type', 'item_specifications' => 'Specifications', 'configuration' => 'Configuration', 'width' => 'Width', 'height_details' => 'Height', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table', 'No drawer banks supplied.', count( $fronts ) + 1 ); ?>
			<?php endif; ?>
		</section>

		<section class="qs-pdf-section">
			<h2>End Panels &amp; Fillers</h2>
			<h3>End Panels</h3>
			<p><em>Flat panels / no profile</em></p>
			<?php qs_render_quote_component_table( $rows['end_panels'], array( 'item_specifications' => 'Specifications', 'width' => 'Width', 'height' => 'Height', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table qs-jobsheet-drawer-bank-table' ); ?>
			<h3>Fillers</h3>
			<?php qs_render_quote_component_table( $rows['fillers'], array( 'item_specifications' => 'Specifications', 'width' => 'Width', 'height' => 'Height', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table' ); ?>
		</section>

		<section class="qs-pdf-section">
			<h2>Kickboards</h2>
			<?php qs_render_quote_component_table( $rows['kickboards'], array( 'material' => 'Kick Material', 'item_specifications' => 'Specifications', 'height' => 'Kick Height', 'length' => 'Kick Length', 'quantity' => 'Quantity', 'notes' => 'Notes' ), 'qs-pdf-table' ); ?>
		</section>

		<?php if ( $data['custom_requests'] ) : ?>
			<section class="qs-pdf-section"><h2>Custom Requests</h2><p><?php echo nl2br( esc_html( $data['custom_requests'] ) ); ?></p></section>
		<?php endif; ?>

		<section class="qs-pdf-section qs-pdf-notes">
			<h2>Project Notes</h2>
			<p><?php echo nl2br( esc_html( $data['project_notes'] ? $data['project_notes'] : '-' ) ); ?></p>
		</section>
	</main>
</div>
