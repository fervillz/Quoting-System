<?php
/**
 * Temporary per-quote Estimated Lead Time field.
 *
 * Replaces the hard-coded "4-6 Weeks" text while keeping the larger
 * multi-room implementation isolated on its feature branch.
 */

use Dompdf\Dompdf;
use Dompdf\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_estimated_lead_time_default() {
	return '4–6 Weeks';
}

function qs_estimated_lead_time( $quote_id = 0 ) {
	$value = $quote_id ? get_post_meta( $quote_id, '_estimated_lead_time', true ) : '';
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	return '' !== $value ? $value : qs_estimated_lead_time_default();
}

/**
 * Keep the field inside the existing Pricing & Workflow side metabox.
 */
function qs_pricing_workflow_with_estimated_lead_time_metabox( $post ) {
	ob_start();
	qs_pricing_workflow_metabox( $post );
	$html = ob_get_clean();

	ob_start();
	wp_nonce_field( 'qs_save_estimated_lead_time', 'qs_estimated_lead_time_nonce' );
	?>
	<p>
		<label for="estimated_lead_time"><strong>Estimated Lead Time</strong></label>
		<input
			type="text"
			id="estimated_lead_time"
			name="estimated_lead_time"
			value="<?php echo esc_attr( qs_estimated_lead_time( $post->ID ) ); ?>"
			placeholder="<?php echo esc_attr( qs_estimated_lead_time_default() ); ?>"
			class="widefat"
		/>
	</p>
	<?php
	$field = ob_get_clean();

	$updated = preg_replace(
		'/(<p>\s*<label for="total"><strong>Total<\/strong><\/label>)/s',
		$field . '$1',
		$html,
		1
	);

	echo false !== $updated && null !== $updated ? $updated : $html . $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function qs_replace_pricing_workflow_metabox_with_lead_time( $post_type, $post = null ) {
	if ( 'quote' !== $post_type ) {
		return;
	}

	remove_meta_box( 'qs_pricing_workflow', 'quote', 'side' );
	add_meta_box(
		'qs_pricing_workflow',
		'Pricing & Workflow',
		'qs_pricing_workflow_with_estimated_lead_time_metabox',
		'quote',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'qs_replace_pricing_workflow_metabox_with_lead_time', 20, 2 );

function qs_save_estimated_lead_time( $post_id ) {
	if ( ! isset( $_POST['qs_estimated_lead_time_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['qs_estimated_lead_time_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'qs_save_estimated_lead_time' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$value = isset( $_POST['estimated_lead_time'] )
		? sanitize_text_field( wp_unslash( $_POST['estimated_lead_time'] ) )
		: '';

	if ( '' === $value ) {
		delete_post_meta( $post_id, '_estimated_lead_time' );
		return;
	}

	update_post_meta( $post_id, '_estimated_lead_time', $value );
}
add_action( 'save_post_quote', 'qs_save_estimated_lead_time', 20 );

function qs_replace_estimated_lead_time_markup( $html, $quote_id, $class_name ) {
	$value   = esc_html( qs_estimated_lead_time( $quote_id ) );
	$pattern = '/(<div class="' . preg_quote( $class_name, '/' ) . '"><strong>Estimated Lead Time<\/strong><span>).*?(<\/span>)/s';

	return preg_replace_callback(
		$pattern,
		static function ( $matches ) use ( $value ) {
			return $matches[1] . $value . $matches[2];
		},
		$html,
		1
	);
}

function qs_current_quote_id() {
	if ( isset( $_GET['quote_id'] ) ) {
		return absint( $_GET['quote_id'] );
	}
	if ( isset( $_POST['quote_id'] ) ) {
		return absint( $_POST['quote_id'] );
	}

	return 0;
}

function qs_estimated_lead_time_builder_shortcode() {
	$html = function_exists( 'qs_profile_end_panel_builder_shortcode' )
		? qs_profile_end_panel_builder_shortcode()
		: qs_quote_builder_shortcode();

	return qs_replace_estimated_lead_time_markup( $html, qs_current_quote_id(), 'qs-lead-time' );
}

function qs_estimated_lead_time_review_shortcode() {
	$html = qs_quote_review_shortcode();

	return qs_replace_estimated_lead_time_markup( $html, qs_current_quote_id(), 'qs-review-lead-time' );
}

function qs_register_estimated_lead_time_shortcodes() {
	remove_shortcode( 'quote_builder' );
	add_shortcode( 'quote_builder', 'qs_estimated_lead_time_builder_shortcode' );

	remove_shortcode( 'quote_review' );
	remove_shortcode( 'my_quote_review' );
	add_shortcode( 'quote_review', 'qs_estimated_lead_time_review_shortcode' );
	add_shortcode( 'my_quote_review', 'qs_estimated_lead_time_review_shortcode' );

	remove_shortcode( 'quotation' );
	add_shortcode( 'quotation', 'qs_estimated_lead_time_quotation_shortcode' );
}
add_action( 'init', 'qs_register_estimated_lead_time_shortcodes', 200 );

function qs_estimated_lead_time_quotation_html( $quote_id ) {
	$html = qs_generate_quotation_html( $quote_id );

	return qs_replace_estimated_lead_time_markup( $html, $quote_id, 'qs-pdf-lead-time' );
}

function qs_estimated_lead_time_quotation_shortcode() {
	$quote_id = qs_current_quote_id();
	if ( ! $quote_id || ! qs_can_view_quote_document( $quote_id ) ) {
		return '<p>You are not allowed to view this quotation.</p>';
	}

	return qs_estimated_lead_time_quotation_html( $quote_id );
}

function qs_estimated_lead_time_quotation_pdf( $quote_id ) {
	$options = new Options();
	$options->set( 'isRemoteEnabled', true );
	$options->set( 'defaultFont', 'DejaVu Sans' );

	$dompdf = new Dompdf( $options );
	$dompdf->loadHtml( qs_estimated_lead_time_quotation_html( $quote_id ) );
	$dompdf->setPaper( 'A4', 'portrait' );
	$dompdf->render();

	return $dompdf;
}

function qs_estimated_lead_time_download_quotation_pdf() {
	if ( ! isset( $_GET['download_quote_pdf'] ) ) {
		return;
	}

	$quote_id = absint( $_GET['download_quote_pdf'] );
	if ( ! qs_can_view_quote_document( $quote_id ) ) {
		wp_die( esc_html__( 'You are not allowed to download this quotation.', 'quote-system' ), 403 );
	}

	$pdf          = qs_estimated_lead_time_quotation_pdf( $quote_id );
	$quote_number = sanitize_file_name( get_post_meta( $quote_id, '_quote_number', true ) );
	$pdf->stream(
		'quotation-' . ( $quote_number ? $quote_number : $quote_id ) . '.pdf',
		array( 'Attachment' => false )
	);
	exit;
}

remove_action( 'init', 'qs_download_quotation_pdf' );
add_action( 'init', 'qs_estimated_lead_time_download_quotation_pdf' );
