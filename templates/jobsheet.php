<?php
/** Internal production job-sheet PDF with room grouping. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quote_id   = isset( $quote_id ) ? absint( $quote_id ) : 0;
$data       = isset( $data ) && is_array( $data ) ? $data : qs_get_quote_data( $quote_id );
$rooms      = qs_quote_rooms( $quote_id, true );
$created_by = $data['created_by'] ? $data['created_by'] : $data['customer_name'];
?>
<div class="qs-pdf qs-jobsheet-pdf">
	<header class="qs-pdf-header"><table class="qs-pdf-header-table"><tr><td><strong>Loughlin Furniture</strong></td><td><span>Job Sheet</span></td></tr></table></header>
	<main class="qs-pdf-page">
		<table class="qs-pdf-project-header">
			<tr><td><p><strong>Project Name:</strong> <?php echo esc_html( $data['project_name'] ); ?></p><p><strong>Company:</strong> <?php echo esc_html( $data['company_name'] ); ?></p><p><strong>Created By:</strong> <?php echo esc_html( $created_by ); ?></p><p><strong>Pricing Type:</strong> <?php echo esc_html( $data['pricing_type'] ); ?></p><p><strong>Date Created:</strong> <?php echo esc_html( $data['date_created'] ); ?></p><p><strong>Delivery Address:</strong> <?php echo nl2br( esc_html( $data['delivery_address'] ) ); ?></p><p><strong>Estimated Lead Time:</strong> <?php echo esc_html( qs_quote_lead_time( $quote_id ) ); ?></p></td><td class="qs-pdf-quote-number"><strong>Quote Number</strong><span><?php echo esc_html( $data['quote_number'] ); ?></span></td></tr>
		</table>

		<?php foreach ( $rooms as $room ) :
			$rows = $room['components'];
			$fronts = array_values( array_filter( $rows['doors_drawers'], static function ( $row ) { return empty( $row['type'] ) || 'Drawer Bank' !== $row['type']; } ) );
			$drawer_banks = qs_component_rows_by_type( $rows['doors_drawers'], 'Drawer Bank' );
			$specs = array(
				array( 'Timber', qs_quote_product_label( $room['timber'] ), $room['paint_colour'] ? 'Paint Colour: ' . $room['paint_colour'] : '' ),
				array( 'Profile', qs_quote_product_label( $room['door_profile'] ), '' ),
				array( 'Finish', qs_quote_product_label( $room['finish'] ), '' ),
				array( 'Door / Drawer Handle Profile', qs_quote_product_label( $room['handle_profile'] ), '' ),
			);
			?>
			<section class="qs-pdf-section qs-pdf-room">
				<h2><?php echo esc_html( $room['name'] ); ?></h2>
				<table class="qs-pdf-specifications"><tr><?php foreach ( $specs as $spec ) : ?><td class="qs-pdf-swatch-cell"><span class="qs-pdf-swatch"></span></td><td><strong><?php echo esc_html( $spec[0] ); ?>:</strong><span><?php echo esc_html( $spec[1] ? $spec[1] : '-' ); ?></span><?php if ( $spec[2] ) : ?><small><?php echo esc_html( $spec[2] ); ?></small><?php endif; ?></td><?php endforeach; ?></tr></table>
			</section>
			<section class="qs-pdf-section"><h3>Doors, Drawers &amp; Profile End Panels</h3><?php qs_render_quote_component_table( $fronts, array( 'type' => 'Item Type', 'width' => 'Width', 'height' => 'Height', 'quantity' => 'Quantity' ), 'qs-pdf-table' ); ?><?php if ( $drawer_banks ) : ?><h3>Drawer Banks</h3><?php qs_render_quote_component_table( $drawer_banks, array( 'type' => 'Item Type', 'configuration' => 'Configuration', 'width' => 'Width', 'height_details' => 'Height', 'quantity' => 'Quantity' ), 'qs-pdf-table', 'No drawer banks supplied.', count( $fronts ) + 1 ); ?><?php endif; ?></section>
			<section class="qs-pdf-section"><h3>End Panels</h3><?php qs_render_quote_component_table( $rows['end_panels'], array( 'width' => 'Width', 'height' => 'Height', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity' ), 'qs-pdf-table' ); ?><h3>Fillers</h3><?php qs_render_quote_component_table( $rows['fillers'], array( 'width' => 'Width', 'height' => 'Height', 'faces_seen' => 'Face Seen', 'edges_seen' => 'Edge/s Seen', 'quantity' => 'Quantity' ), 'qs-pdf-table' ); ?></section>
			<section class="qs-pdf-section"><h3>Kickboards</h3><?php qs_render_quote_component_table( $rows['kickboards'], array( 'material' => 'Kick Material', 'height' => 'Kick Height', 'length' => 'Kick Length', 'quantity' => 'Quantity' ), 'qs-pdf-table' ); ?></section>
		<?php endforeach; ?>

		<?php if ( $data['custom_requests'] ) : ?><section class="qs-pdf-section"><h2>Custom Requests</h2><p><?php echo nl2br( esc_html( $data['custom_requests'] ) ); ?></p></section><?php endif; ?>
		<section class="qs-pdf-section qs-pdf-notes"><h2>Project Notes</h2><p><?php echo nl2br( esc_html( $data['project_notes'] ? $data['project_notes'] : '-' ) ); ?></p></section>
	</main>
</div>
