Exit code: 0
Wall time: 0.7 seconds
Output:
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$quote_id = isset( $quote_id ) ? absint( $quote_id ) : 0;
$data = qs_get_quote_data( $quote_id );
$rows = $data['component_rows'];
?>
<div class="qs-quotation qs-container">
	<header class="qs-quotation-header"><strong>Loughlin Furniture</strong><span>Quotation</span></header>
	<section class="qs-quotation-top"><div><p><strong>Project:</strong> <?php echo esc_html( $data['project_name'] ); ?></p><p><strong>Company:</strong> <?php echo esc_html( $data['company_name'] ); ?></p><p><strong>Created by:</strong> <?php echo esc_html( $data['customer_name'] ); ?></p><p><strong>Delivery:</strong><br><?php echo nl2br( esc_html( $data['delivery_address'] ) ); ?></p></div><div><strong>Quote number</strong><p><?php echo esc_html( $data['quote_number'] ); ?></p></div></section>
	<section class="qs-section"><h3>Selected specifications</h3><p><strong>Profile:</strong> <?php echo esc_html( $data['door_profile'] ); ?> &nbsp; <strong>Timber:</strong> <?php echo esc_html( $data['timber'] ); ?></p><p><strong>Finish:</strong> <?php echo esc_html( $data['finish'] ); ?> &nbsp; <strong>Handle profile:</strong> <?php echo esc_html( $data['handle_profile'] ); ?></p></section>
	<section class="qs-section"><h3>Doors, drawers and drawer banks</h3><?php qs_pdf_component_table( $rows['doors_drawers'], array( 'type' => 'Type', 'width' => 'Width', 'height' => 'Height', 'quantity' => 'Qty', 'edge_profile' => 'Edge profile', 'drawer_count' => 'Drawers' ) ); ?></section>
	<section class="qs-section"><h3>End panels</h3><?php qs_pdf_component_table( $rows['end_panels'], array( 'height' => 'Height', 'width' => 'Width', 'quantity' => 'Qty', 'faces_seen' => 'Faces seen', 'edges_seen' => 'Edges seen' ) ); ?></section>
	<section class="qs-section"><h3>Fillers</h3><?php qs_pdf_component_table( $rows['fillers'], array( 'height' => 'Height', 'width' => 'Width', 'quantity' => 'Qty', 'faces_seen' => 'Faces seen', 'edges_seen' => 'Edges seen' ) ); ?></section>
	<section class="qs-section"><h3>Kickboards</h3><?php qs_pdf_component_table( $rows['kickboards'], array( 'material' => 'Material', 'height' => 'Height', 'length' => 'Length', 'quantity' => 'Qty' ) ); ?></section>
	<section class="qs-section"><h3>Project notes</h3><p><?php echo nl2br( esc_html( get_post_meta( $quote_id, '_project_notes', true ) ) ); ?></p></section>
	<aside class="qs-quotation-summary"><h3>Quote summary</h3><p>Doors: <?php echo esc_html( qs_quote_component_count( $quote_id, 'doors_drawers', 'Door' ) ); ?></p><p>Drawers: <?php echo esc_html( qs_quote_component_count( $quote_id, 'doors_drawers', 'Drawer' ) ); ?></p><p>End panels: <?php echo esc_html( qs_quote_component_count( $quote_id, 'end_panels' ) ); ?></p><p>Fillers: <?php echo esc_html( qs_quote_component_count( $quote_id, 'fillers' ) ); ?></p><p>Kickboards: <?php echo esc_html( qs_quote_component_count( $quote_id, 'kickboards' ) ); ?></p><hr><p><strong>Subtotal: $<?php echo esc_html( number_format( $data['total'], 2 ) ); ?> AUD</strong></p></aside>
	<footer class="qs-quotation-disclaimer">This quotation is valid for 30 days from the issue date. Final pricing may change if specifications change.</footer>
</div>

