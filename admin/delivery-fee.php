<?php
/**
 * Delivery Fee field and manual office pricing overrides for Quotes.
 *
 * The Quote System already uses _shipping in qs_calculate_total(). This file
 * exposes that existing adjustment as the office-facing "Delivery Fee" field
 * instead of introducing a second source of truth for the same charge.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace the original Pricing & Workflow box with the same content plus the
 * Delivery Fee immediately after Additional Charges.
 */
function qs_register_delivery_fee_metabox() {
	remove_meta_box( 'qs_pricing_workflow', 'quote', 'side' );

	add_meta_box(
		'qs_pricing_workflow',
		'Pricing & Workflow',
		'qs_pricing_workflow_with_delivery_metabox',
		'quote',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'qs_register_delivery_fee_metabox', 20 );

function qs_pricing_workflow_with_delivery_metabox( $post ) {
	$delivery_fee = (float) get_post_meta( $post->ID, '_shipping', true );

	ob_start();
	qs_pricing_workflow_metabox( $post );
	$pricing_html = ob_get_clean();

	ob_start();
	wp_nonce_field( 'qs_save_delivery_fee_' . $post->ID, 'qs_delivery_fee_nonce' );
	?>
	<p class="qs-delivery-fee-field">
		<label for="qs_delivery_fee_amount"><strong>Delivery Fee</strong></label>
		<input
			type="number"
			min="0"
			step="0.01"
			id="qs_delivery_fee_amount"
			name="qs_delivery_fee_amount"
			value="<?php echo esc_attr( $delivery_fee ); ?>"
			class="widefat"
		/>
		<span class="description">Added to the quote total and included in deposit/final-balance calculations.</span>
	</p>
	<?php
	$delivery_html = ob_get_clean();

	$pattern = '/(<p>\s*<label for="additional_charges"><strong>Additional Charges<\/strong><\/label>.*?<\/p>)/s';
	if ( preg_match( $pattern, $pricing_html ) ) {
		$pricing_html = preg_replace( $pattern, '$1' . $delivery_html, $pricing_html, 1 );
	} else {
		$pricing_html .= $delivery_html;
	}

	echo $pricing_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Original metabox HTML plus escaped local field markup.
}

function qs_save_delivery_fee( $post_id ) {
	if (
		empty( $_POST['qs_delivery_fee_nonce'] ) ||
		! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['qs_delivery_fee_nonce'] ) ),
			'qs_save_delivery_fee_' . $post_id
		)
	) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$delivery_fee = isset( $_POST['qs_delivery_fee_amount'] )
		? max( 0, (float) wp_unslash( $_POST['qs_delivery_fee_amount'] ) )
		: 0;

	update_post_meta( $post_id, '_shipping', $delivery_fee );

	// Keep the legacy display-only amount fields in sync for administrators.
	update_post_meta( $post_id, '_total', qs_calculate_total( $post_id ) );
	update_post_meta( $post_id, '_deposit_amount', qs_calculate_deposit( $post_id ) );
	update_post_meta( $post_id, '_balance_amount', qs_calculate_balance( $post_id ) );
}
add_action( 'save_post_quote', 'qs_save_delivery_fee', 40 );

/**
 * Keep an office-entered Subtotal as the final backend pricing override.
 *
 * item-configurations.php recalculates component pricing after wp-admin rows
 * are restored (priority 30). That is correct for component edits, but it also
 * used to replace the explicit Subtotal entered in the Pricing & Workflow
 * metabox. Re-apply the posted office value afterwards while temporarily
 * suppressing the automatic _subtotal meta synchroniser.
 */
function qs_restore_admin_manual_subtotal( $post_id ) {
	if ( ! is_admin() || ! isset( $_POST['subtotal'] ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if (
		empty( $_POST['qs_project_details_nonce'] ) ||
		! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['qs_project_details_nonce'] ) ),
			'qs_save_project_details'
		)
	) {
		return;
	}

	$manual_subtotal = (float) wp_unslash( $_POST['subtotal'] );
	$sync_was_active = ! empty( $GLOBALS['qs_item_config_pricing_sync'] );

	$GLOBALS['qs_item_config_pricing_sync'] = true;
	update_post_meta( $post_id, '_subtotal', $manual_subtotal );
	$GLOBALS['qs_item_config_pricing_sync'] = $sync_was_active;

	update_post_meta( $post_id, '_total', qs_calculate_total( $post_id ) );
	update_post_meta( $post_id, '_deposit_amount', qs_calculate_deposit( $post_id ) );
	update_post_meta( $post_id, '_balance_amount', qs_calculate_balance( $post_id ) );
}
add_action( 'save_post_quote', 'qs_restore_admin_manual_subtotal', 50 );
