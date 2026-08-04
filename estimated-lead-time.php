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
 * Lead time may be changed from the frontend only before the quote is
 * approved. `awaiting_deposit` is the first approved workflow state.
 */
function qs_estimated_lead_time_is_preapproval( $quote_id ) {
	$status = get_post_status( $quote_id );
	$status = 'pending' === $status ? 'pending_review' : $status;

	return in_array( $status, array( 'draft', 'pending_review' ), true );
}

function qs_estimated_lead_time_can_frontend_edit( $quote_id ) {
	$post = get_post( $quote_id );
	if ( ! $post || 'quote' !== $post->post_type ) {
		return false;
	}

	return current_user_can( 'edit_others_posts' )
		&& current_user_can( 'edit_post', $quote_id )
		&& qs_estimated_lead_time_is_preapproval( $quote_id );
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

	$is_frontend_request = ! is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() );
	if ( $is_frontend_request && ! qs_estimated_lead_time_can_frontend_edit( $post_id ) ) {
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

/**
 * Save the standalone lead-time form on the frontend Quote Review page before
 * the page template sends output, then redirect to prevent duplicate submits.
 */
function qs_handle_frontend_estimated_lead_time_update() {
	if ( empty( $_POST['qs_update_estimated_lead_time'] ) ) {
		return;
	}

	$quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
	$nonce    = isset( $_POST['qs_estimated_lead_time_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['qs_estimated_lead_time_nonce'] ) )
		: '';

	if ( ! $quote_id || ! wp_verify_nonce( $nonce, 'qs_save_estimated_lead_time' ) ) {
		wp_die( esc_html__( 'Security check failed. Please reload the page and try again.', 'quote-system' ), 403 );
	}
	if ( ! qs_estimated_lead_time_can_frontend_edit( $quote_id ) ) {
		wp_die( esc_html__( 'This lead time can no longer be changed from the quote page.', 'quote-system' ), 403 );
	}

	$value = isset( $_POST['estimated_lead_time'] )
		? sanitize_text_field( wp_unslash( $_POST['estimated_lead_time'] ) )
		: '';

	if ( '' === $value ) {
		delete_post_meta( $quote_id, '_estimated_lead_time' );
	} else {
		update_post_meta( $quote_id, '_estimated_lead_time', $value );
	}

	$redirect = wp_get_referer();
	if ( ! $redirect ) {
		$redirect = add_query_arg( 'quote_id', $quote_id, site_url( '/quote-review/' ) );
	}
	$redirect = remove_query_arg( 'lead_time_updated', $redirect );

	wp_safe_redirect( add_query_arg( 'lead_time_updated', '1', $redirect ) );
	exit;
}
add_action( 'template_redirect', 'qs_handle_frontend_estimated_lead_time_update' );

function qs_estimated_lead_time_display_markup( $quote_id, $class_name ) {
	return sprintf(
		'<div class="%1$s"><strong>Estimated Lead Time</strong><span>%2$s</span></div>',
		esc_attr( $class_name ),
		esc_html( qs_estimated_lead_time( $quote_id ) )
	);
}

function qs_estimated_lead_time_builder_editor_markup( $quote_id, $class_name ) {
	return sprintf(
		'<div class="%1$s qs-estimated-lead-time-editable"><strong>Estimated Lead Time</strong><span class="qs-estimated-lead-time-control"><input type="text" name="estimated_lead_time" value="%2$s" placeholder="%3$s" aria-label="Estimated Lead Time"><small>Saved with the quote</small></span><input type="hidden" name="qs_estimated_lead_time_nonce" value="%4$s"></div>',
		esc_attr( $class_name ),
		esc_attr( qs_estimated_lead_time( $quote_id ) ),
		esc_attr( qs_estimated_lead_time_default() ),
		esc_attr( wp_create_nonce( 'qs_save_estimated_lead_time' ) )
	);
}

function qs_estimated_lead_time_review_editor_markup( $quote_id, $class_name ) {
	$updated = isset( $_GET['lead_time_updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['lead_time_updated'] ) );

	return sprintf(
		'<form method="post" class="%1$s qs-estimated-lead-time-editable"><strong>Estimated Lead Time</strong><span class="qs-estimated-lead-time-control"><input type="text" name="estimated_lead_time" value="%2$s" placeholder="%3$s" aria-label="Estimated Lead Time"><button type="submit" name="qs_update_estimated_lead_time" value="1">Update</button>%4$s</span><input type="hidden" name="quote_id" value="%5$d"><input type="hidden" name="qs_estimated_lead_time_nonce" value="%6$s"></form>',
		esc_attr( $class_name ),
		esc_attr( qs_estimated_lead_time( $quote_id ) ),
		esc_attr( qs_estimated_lead_time_default() ),
		$updated ? '<small class="qs-estimated-lead-time-updated">Updated</small>' : '',
		absint( $quote_id ),
		esc_attr( wp_create_nonce( 'qs_save_estimated_lead_time' ) )
	);
}

function qs_replace_estimated_lead_time_markup( $html, $quote_id, $class_name, $context = 'display' ) {
	$pattern = '/<div class="' . preg_quote( $class_name, '/' ) . '"><strong>Estimated Lead Time<\/strong><span>.*?<\/span><\/div>/s';

	if ( qs_estimated_lead_time_can_frontend_edit( $quote_id ) ) {
		$replacement = 'builder' === $context
			? qs_estimated_lead_time_builder_editor_markup( $quote_id, $class_name )
			: ( 'review' === $context
				? qs_estimated_lead_time_review_editor_markup( $quote_id, $class_name )
				: qs_estimated_lead_time_display_markup( $quote_id, $class_name ) );
	} else {
		$replacement = qs_estimated_lead_time_display_markup( $quote_id, $class_name );
	}

	return preg_replace( $pattern, $replacement, $html, 1 );
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
	$html     = function_exists( 'qs_profile_end_panel_builder_shortcode' )
		? qs_profile_end_panel_builder_shortcode()
		: qs_quote_builder_shortcode();
	$quote_id = qs_current_quote_id();

	return qs_replace_estimated_lead_time_markup( $html, $quote_id, 'qs-lead-time', 'builder' );
}

function qs_estimated_lead_time_review_shortcode() {
	$html     = qs_quote_review_shortcode();
	$quote_id = qs_current_quote_id();

	return qs_replace_estimated_lead_time_markup( $html, $quote_id, 'qs-review-lead-time', 'review' );
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

function qs_enqueue_estimated_lead_time_assets() {
	$relative_path = 'assets/css/estimated-lead-time.css';
	$path          = QS_PATH . $relative_path;

	wp_enqueue_style(
		'qs-estimated-lead-time',
		QS_URL . $relative_path,
		array(),
		file_exists( $path ) ? (string) filemtime( $path ) : QS_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'qs_enqueue_estimated_lead_time_assets', 10001 );
