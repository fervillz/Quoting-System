<?php
/**
 * Delivery Fee field for Quote pricing.
 *
 * The Quote System already uses _shipping in qs_calculate_total(). This file
 * exposes that existing adjustment as the office-facing "Delivery Fee" field
 * instead of introducing a second source of truth for the same charge.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_register_delivery_fee_metabox() {
	add_meta_box(
		'qs_delivery_fee',
		'Delivery Fee',
		'qs_delivery_fee_metabox',
		'quote',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'qs_register_delivery_fee_metabox', 20 );

function qs_delivery_fee_metabox( $post ) {
	$delivery_fee = (float) get_post_meta( $post->ID, '_shipping', true );

	wp_nonce_field( 'qs_save_delivery_fee_' . $post->ID, 'qs_delivery_fee_nonce' );
	?>
	<p>
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
	</p>
	<p class="description">Added to the quote total and included in deposit/final-balance calculations.</p>
	<?php
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
