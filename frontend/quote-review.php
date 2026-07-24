Exit code: 0
Wall time: 0.5 seconds
Output:
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function qs_render_component_rows( $quote_id, $component, $columns ) {
	$rows = qs_component_rows( $quote_id, $component );
	echo '<table class="qs-table"><thead><tr><th>#</th>';
	foreach ( $columns as $label ) { echo '<th>' . esc_html( $label ) . '</th>'; }
	echo '</tr></thead><tbody>';
	foreach ( $rows as $index => $row ) { echo '<tr><td>' . esc_html( $index + 1 ) . '</td>'; foreach ( array_keys( $columns ) as $key ) { $value = isset( $row[ $key ] ) ? $row[ $key ] : ''; echo '<td>' . esc_html( $value ) . ( in_array( $key, array( 'width', 'height', 'length' ), true ) && '' !== $value ? ' mm' : '' ) . '</td>'; } echo '</tr>'; }
	if ( ! $rows ) { echo '<tr><td colspan="' . esc_attr( count( $columns ) + 1 ) . '">No items added.</td></tr>'; }
	echo '</tbody></table>';
}
function qs_quote_review_shortcode() {
	if ( isset( $_POST['qs_submit_quote'] ) ) {
		if ( ! isset( $_POST['qs_submit_quote_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qs_submit_quote_nonce'] ) ), 'qs_submit_quote' ) ) { return '<p>Security check failed. Please try again.</p>'; }
		$submitted_id = absint( $_POST['quote_id'] );
		if ( ! current_user_can( 'edit_post', $submitted_id ) && (int) get_post_field( 'post_author', $submitted_id ) !== get_current_user_id() ) { return '<p>You cannot submit this quote.</p>'; }
		qs_update_quote_status( $submitted_id, 'pending_review' ); qs_email_quote_submitted( $submitted_id ); wp_safe_redirect( add_query_arg( 'quote_id', $submitted_id, site_url( '/quote-thank-you/' ) ) ); exit;
	}
	$quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
	if ( ! $quote_id || ( ! current_user_can( 'edit_post', $quote_id ) && (int) get_post_field( 'post_author', $quote_id ) !== get_current_user_id() ) ) { return '<p>Quote not found.</p>'; }
	$data = qs_get_quote_data( $quote_id ); $total = qs_calculate_total( $quote_id );
	ob_start(); ?>
	<div class="qs-container qs-review-layout"><main class="qs-review-main"><h2>Review your quote</h2><h3>Project details</h3><p><strong>Company:</strong> <?php echo esc_html( $data['company_name'] ); ?></p><p><strong>Project:</strong> <?php echo esc_html( $data['project_name'] ); ?></p><p><strong>Delivery:</strong><br><?php echo nl2br( esc_html( $data['delivery_address'] ) ); ?></p><h3>Specifications</h3><p><?php echo esc_html( $data['door_profile'] . ' | ' . $data['timber'] . ' | ' . $data['finish'] . ' | ' . $data['handle_profile'] ); ?></p><h3>Doors, drawers and drawer banks</h3><?php qs_render_component_rows( $quote_id, 'doors_drawers', array( 'type' => 'Type', 'width' => 'Width', 'height' => 'Height', 'quantity' => 'Qty', 'edge_profile' => 'Edge profile', 'drawer_count' => 'Drawers' ) ); ?><h3>End panels</h3><?php qs_render_component_rows( $quote_id, 'end_panels', array( 'height' => 'Height', 'width' => 'Width', 'quantity' => 'Qty', 'faces_seen' => 'Faces seen', 'edges_seen' => 'Edges seen' ) ); ?><h3>Fillers</h3><?php qs_render_component_rows( $quote_id, 'fillers', array( 'height' => 'Height', 'width' => 'Width', 'quantity' => 'Qty', 'faces_seen' => 'Faces seen', 'edges_seen' => 'Edges seen' ) ); ?><h3>Kickboards</h3><?php qs_render_component_rows( $quote_id, 'kickboards', array( 'material' => 'Material', 'height' => 'Height', 'length' => 'Length', 'quantity' => 'Qty' ) ); ?></main><aside class="qs-review-summary"><h3>Quote summary</h3><p>Doors: <?php echo esc_html( qs_quote_component_count( $quote_id, 'doors_drawers', 'Door' ) ); ?></p><p>Drawers: <?php echo esc_html( qs_quote_component_count( $quote_id, 'doors_drawers', 'Drawer' ) ); ?></p><p>Subtotal: <strong>$<?php echo esc_html( number_format( $total, 2 ) ); ?> AUD</strong></p><a class="qs-btn qs-btn-secondary" href="<?php echo esc_url( add_query_arg( 'quote_id', $quote_id, site_url( '/quote-builder/' ) ) ); ?>">Edit quote</a><?php if ( 'draft' === get_post_status( $quote_id ) ) : ?><form method="post"><?php wp_nonce_field( 'qs_submit_quote', 'qs_submit_quote_nonce' ); ?><input type="hidden" name="quote_id" value="<?php echo esc_attr( $quote_id ); ?>"><button class="qs-btn" type="submit" name="qs_submit_quote">Submit quote</button></form><?php endif; ?></aside></div>
	<?php return ob_get_clean();
}
add_shortcode( 'quote_review', 'qs_quote_review_shortcode' );

