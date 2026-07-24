Exit code: 0
Wall time: 0.5 seconds
Output:
<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_builder_quote_is_editable( $quote_id ) {
	if ( ! $quote_id ) {
		return is_user_logged_in();
	}
	$quote = get_post( $quote_id );
	if ( ! $quote || 'quote' !== $quote->post_type ) {
		return false;
	}
	// The customer can change a draft, but a submitted quote is deliberately
	// locked so that its review, deposit and production records stay aligned.
	if ( current_user_can( 'edit_post', $quote_id ) ) {
		return true;
	}
	return 'draft' === get_post_status( $quote_id ) && (int) $quote->post_author === get_current_user_id();
}

function qs_builder_save_quote( $quote_id ) {
	if ( ! isset( $_POST['qs_builder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qs_builder_nonce'] ) ), 'qs_save_quote' ) ) {
		return new WP_Error( 'invalid_nonce', __( 'Your session expired. Please reload the page and try again.', 'quote-system' ) );
	}
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'not_logged_in', __( 'Please log in before saving a quote.', 'quote-system' ) );
	}

	$project_name = isset( $_POST['project_name'] ) ? sanitize_text_field( wp_unslash( $_POST['project_name'] ) ) : '';
	if ( '' === $project_name ) {
		return new WP_Error( 'missing_project_name', __( 'Project name is required.', 'quote-system' ) );
	}
	if ( $quote_id && ! qs_builder_quote_is_editable( $quote_id ) ) {
		return new WP_Error( 'forbidden', __( 'You cannot edit this quote.', 'quote-system' ) );
	}

	if ( ! $quote_id ) {
		$quote_id = wp_insert_post( array( 'post_type' => 'quote', 'post_status' => 'draft', 'post_title' => $project_name, 'post_author' => get_current_user_id() ), true );
	} else {
		$quote_id = wp_update_post( array( 'ID' => $quote_id, 'post_title' => $project_name ), true );
	}
	if ( is_wp_error( $quote_id ) ) {
		return $quote_id;
	}

	$fields = array(
		'project_name' => 'sanitize_text_field', 'company_name' => 'sanitize_text_field', 'customer_name' => 'sanitize_text_field',
		'customer_email' => 'sanitize_email', 'customer_phone' => 'sanitize_text_field', 'delivery_address' => 'sanitize_textarea_field',
		'door_profile' => 'sanitize_text_field', 'timber' => 'sanitize_text_field', 'finish' => 'sanitize_text_field',
		'handle_profile' => 'sanitize_text_field', 'project_notes' => 'sanitize_textarea_field',
	);
	foreach ( $fields as $field => $sanitiser ) {
		$value = isset( $_POST[ $field ] ) ? call_user_func( $sanitiser, wp_unslash( $_POST[ $field ] ) ) : '';
		update_post_meta( $quote_id, '_' . $field, $value );
	}
	qs_save_component_rows( $quote_id, isset( $_POST['components'] ) ? $_POST['components'] : array() );
	return $quote_id;
}

function qs_builder_input( $name, $label, $value = '', $type = 'text', $required = false ) {
	printf( '<p class="qs-form-group"><label class="qs-label" for="%1$s">%2$s</label><input class="qs-input" id="%1$s" type="%3$s" name="%1$s" value="%4$s" %5$s></p>', esc_attr( $name ), esc_html( $label ), esc_attr( $type ), esc_attr( $value ), $required ? 'required' : '' );
}

function qs_builder_component_table( $component, $rows ) {
	$columns = array(
		'doors_drawers' => array( 'type' => 'Type', 'width' => 'Width (mm)', 'height' => 'Height (mm)', 'quantity' => 'Qty', 'edge_profile' => 'Edge profile', 'drawer_count' => 'Drawers' ),
		'end_panels' => array( 'height' => 'Height (mm)', 'width' => 'Width (mm)', 'quantity' => 'Qty', 'faces_seen' => 'Faces seen', 'edges_seen' => 'Edges seen' ),
		'fillers' => array( 'height' => 'Height (mm)', 'width' => 'Width (mm)', 'quantity' => 'Qty', 'faces_seen' => 'Faces seen', 'edges_seen' => 'Edges seen' ),
		'kickboards' => array( 'material' => 'Material', 'height' => 'Height (mm)', 'length' => 'Length (mm)', 'quantity' => 'Qty' ),
	);
	$rows = $rows ? $rows : array( array() );
	?>
	<section class="qs-component" data-component="<?php echo esc_attr( $component ); ?>">
		<table class="qs-table qs-repeater"><thead><tr><?php foreach ( $columns[ $component ] as $label ) : ?><th><?php echo esc_html( $label ); ?></th><?php endforeach; ?><th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'quote-system' ); ?></span></th></tr></thead><tbody>
		<?php foreach ( $rows as $index => $row ) : ?><tr><?php foreach ( $columns[ $component ] as $key => $label ) : ?><td><?php if ( 'type' === $key ) : ?><select class="qs-input" name="components[<?php echo esc_attr( $component ); ?>][<?php echo esc_attr( $index ); ?>][type]"><option value="Door" <?php selected( isset( $row['type'] ) ? $row['type'] : '', 'Door' ); ?>>Door</option><option value="Drawer" <?php selected( isset( $row['type'] ) ? $row['type'] : '', 'Drawer' ); ?>>Drawer</option><option value="Drawer Bank" <?php selected( isset( $row['type'] ) ? $row['type'] : '', 'Drawer Bank' ); ?>>Drawer bank</option></select><?php else : ?><input class="qs-input" type="<?php echo in_array( $key, array( 'width', 'height', 'length', 'quantity', 'drawer_count' ), true ) ? 'number' : 'text'; ?>" min="<?php echo in_array( $key, array( 'width', 'height', 'length', 'quantity', 'drawer_count' ), true ) ? '1' : ''; ?>" name="components[<?php echo esc_attr( $component ); ?>][<?php echo esc_attr( $index ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( isset( $row[ $key ] ) ? $row[ $key ] : '' ); ?>"><?php endif; ?></td><?php endforeach; ?><td><button class="qs-btn qs-remove-row" type="button">Remove</button></td></tr><?php endforeach; ?>
		</tbody></table><p><button class="qs-btn qs-add-row" type="button">Add item</button></p>
	</section>
	<?php
}

function qs_quote_builder_shortcode() {
	$quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
	$error = '';
	if ( isset( $_POST['qs_save_draft'] ) || isset( $_POST['qs_review_quote'] ) ) {
		$saved = qs_builder_save_quote( $quote_id );
		if ( is_wp_error( $saved ) ) {
			$error = $saved->get_error_message();
		} else {
			$destination = isset( $_POST['qs_review_quote'] ) ? '/quote-review/' : '/quote-builder/';
			wp_safe_redirect( add_query_arg( 'quote_id', $saved, site_url( $destination ) ) );
			exit;
		}
	}
	if ( ! qs_builder_quote_is_editable( $quote_id ) ) {
		return '<p>' . esc_html__( 'You cannot view this quote.', 'quote-system' ) . '</p>';
	}
	$meta = array();
	foreach ( array( 'project_name', 'company_name', 'customer_name', 'customer_email', 'customer_phone', 'delivery_address', 'door_profile', 'timber', 'finish', 'handle_profile', 'project_notes' ) as $key ) {
		$meta[ $key ] = $quote_id ? get_post_meta( $quote_id, '_' . $key, true ) : '';
	}
	ob_start();
	?>
	<div class="qs-container"><h2><?php esc_html_e( 'Quote Builder', 'quote-system' ); ?></h2><?php if ( $error ) : ?><div class="qs-notice qs-notice-error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
	<form method="post" class="qs-wrapper" novalidate><?php wp_nonce_field( 'qs_save_quote', 'qs_builder_nonce' ); ?><div class="form">
	<h3>Project details</h3><?php qs_builder_input( 'project_name', 'Project name', $meta['project_name'], 'text', true ); qs_builder_input( 'company_name', 'Company', $meta['company_name'] ); qs_builder_input( 'customer_name', 'Contact name', $meta['customer_name'], 'text', true ); qs_builder_input( 'customer_email', 'Email', $meta['customer_email'], 'email', true ); qs_builder_input( 'customer_phone', 'Phone', $meta['customer_phone'] ); ?>
	<p class="qs-form-group"><label class="qs-label" for="delivery_address">Delivery address</label><textarea class="qs-textarea" id="delivery_address" name="delivery_address"><?php echo esc_textarea( $meta['delivery_address'] ); ?></textarea></p>
	<h3>Specifications</h3><?php qs_builder_input( 'door_profile', 'Profile', $meta['door_profile'] ); qs_builder_input( 'timber', 'Timber', $meta['timber'] ); qs_builder_input( 'finish', 'Finish', $meta['finish'] ); qs_builder_input( 'handle_profile', 'Door / drawer handle profile', $meta['handle_profile'] ); ?>
	<h3>Doors, drawers and drawer banks</h3><?php qs_builder_component_table( 'doors_drawers', qs_component_rows( $quote_id, 'doors_drawers' ) ); ?><h3>End panels</h3><?php qs_builder_component_table( 'end_panels', qs_component_rows( $quote_id, 'end_panels' ) ); ?><h3>Fillers</h3><?php qs_builder_component_table( 'fillers', qs_component_rows( $quote_id, 'fillers' ) ); ?><h3>Kickboards</h3><?php qs_builder_component_table( 'kickboards', qs_component_rows( $quote_id, 'kickboards' ) ); ?>
	<p class="qs-form-group"><label class="qs-label" for="project_notes">Project notes</label><textarea class="qs-textarea" id="project_notes" name="project_notes"><?php echo esc_textarea( $meta['project_notes'] ); ?></textarea></p>
	<p><button class="qs-btn qs-btn-secondary" type="submit" name="qs_save_draft">Save draft</button> <button class="qs-btn" type="submit" name="qs_review_quote">Review quote</button></p></div>
	<aside class="qs-summary"><h3>Quote summary</h3><p>Doors: <?php echo esc_html( qs_quote_component_count( $quote_id, 'doors_drawers', 'Door' ) ); ?></p><p>Drawers: <?php echo esc_html( qs_quote_component_count( $quote_id, 'doors_drawers', 'Drawer' ) ); ?></p><p>Panels: <?php echo esc_html( qs_quote_component_count( $quote_id, 'end_panels' ) ); ?></p><p>Fillers: <?php echo esc_html( qs_quote_component_count( $quote_id, 'fillers' ) ); ?></p><p>Kickboards: <?php echo esc_html( qs_quote_component_count( $quote_id, 'kickboards' ) ); ?></p></aside></form></div>
	<script>(function(){function addRow(section){var body=section.querySelector('tbody'),prototype=body.rows[0];if(!prototype)return;var row=prototype.cloneNode(true),index=body.rows.length;row.querySelectorAll('input,select').forEach(function(input){input.name=input.name.replace(/\[\d+\]/, '['+index+']');input.value='';});body.appendChild(row);}document.querySelectorAll('.qs-component').forEach(function(section){section.querySelector('.qs-add-row').addEventListener('click',function(){addRow(section);});section.addEventListener('click',function(event){if(event.target.classList.contains('qs-remove-row')&&section.querySelectorAll('tbody tr').length>1){event.target.closest('tr').remove();}});});}());</script>
	<?php
	return ob_get_clean();
}

add_shortcode( 'quote_builder', 'qs_quote_builder_shortcode' );

