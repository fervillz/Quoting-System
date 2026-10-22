<?php

use Dompdf\Dompdf;
use Dompdf\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get all quote data.
 */
function qs_get_quote_data( $quote_id ) {
	$quote       = get_post( $quote_id );
	$author_name = $quote ? get_the_author_meta( 'display_name', $quote->post_author ) : '';
	$pricing     = get_post_meta( $quote_id, '_pricing_type', true );

	return array(

		'quote_number' => get_post_meta(
			$quote_id,
			'_quote_number',
			true
		),

		'project_name' => get_post_meta(
			$quote_id,
			'_project_name',
			true
		),

		'company_name' => get_post_meta(
			$quote_id,
			'_company_name',
			true
		),

		'customer_name' => get_post_meta(
			$quote_id,
			'_customer_name',
			true
		),

		'customer_email' => get_post_meta(
			$quote_id,
			'_customer_email',
			true
		),

		'customer_phone' => get_post_meta(
			$quote_id,
			'_customer_phone',
			true
		),

		'delivery_address' => get_post_meta(
			$quote_id,
			'_delivery_address',
			true
		),

		'created_by' => $author_name,

		'pricing_type' => 'retail' === $pricing ? 'Retail Pricing' : 'Trade Pricing',

		'date_created' => $quote ? get_the_date( 'd F Y', $quote_id ) : '',

		'date_submitted' => $quote ? get_the_modified_date( 'd F Y', $quote_id ) : '',

		'status' => $quote ? get_post_status( $quote_id ) : '',

		'custom_requests' => get_post_meta(
			$quote_id,
			'_custom_requests',
			true
		),

		'project_notes' => get_post_meta(
			$quote_id,
			'_project_notes',
			true
		),

		'supporting_documents' => get_post_meta(
			$quote_id,
			'_supporting_documents',
			true
		),

		'door_profile' => qs_quote_product_label( get_post_meta(
			$quote_id,
			'_door_profile',
			true
		) ),

		'door_profile_id' => get_post_meta(
			$quote_id,
			'_door_profile',
			true
		),

		'timber' => qs_quote_product_label( get_post_meta(
			$quote_id,
			'_timber',
			true
		) ),

		'timber_id' => get_post_meta(
			$quote_id,
			'_timber',
			true
		),

		'finish' => qs_quote_product_label( get_post_meta(
			$quote_id,
			'_finish',
			true
		) ),

		'finish_id' => get_post_meta(
			$quote_id,
			'_finish',
			true
		),

		'handle_profile' => qs_quote_product_label( get_post_meta(
			$quote_id,
			'_handle_profile',
			true
		) ),

		'handle_profile_id' => get_post_meta(
			$quote_id,
			'_handle_profile',
			true
		),

		'paint_colour' => get_post_meta(
			$quote_id,
			'_paint_colour',
			true
		),

		'doors_drawers' => get_post_meta(
			$quote_id,
			'_doors_drawers',
			true
		),

		'end_panels' => get_post_meta(
			$quote_id,
			'_end_panels',
			true
		),

		'fillers' => get_post_meta(
			$quote_id,
			'_fillers',
			true
		),

		'kickboards' => get_post_meta(
			$quote_id,
			'_kickboards',
			true
		),

		'component_rows' => array(
			'doors_drawers' => qs_component_rows( $quote_id, 'doors_drawers' ),
			'end_panels'    => qs_component_rows( $quote_id, 'end_panels' ),
			'fillers'       => qs_component_rows( $quote_id, 'fillers' ),
			'kickboards'    => qs_component_rows( $quote_id, 'kickboards' ),
		),

		'subtotal' => get_post_meta(
			$quote_id,
			'_subtotal',
			true
		),

		'discount' => get_post_meta(
			$quote_id,
			'_discount',
			true
		),

		'discount_type' => function_exists( 'qs_discount_type' )
			? qs_discount_type( $quote_id )
			: 'fixed',

		'discount_amount' => function_exists( 'qs_discount_amount' )
			? qs_discount_amount( $quote_id )
			: (float) get_post_meta( $quote_id, '_discount', true ),

		'shipping' => get_post_meta(
			$quote_id,
			'_shipping',
			true
		),

		'additional_charges' => get_post_meta(
			$quote_id,
			'_additional_charges',
			true
		),

		'internal_notes' => get_post_meta(
			$quote_id,
			'_internal_notes',
			true
		),

		'total' => qs_calculate_total(
			$quote_id
		),

		'deposit' => qs_calculate_deposit(
			$quote_id
		),

		'balance' => qs_calculate_balance(
			$quote_id
		),

	);

}

/**
 * Checks whether the current person may view a quote document.
 * Administrators/editors can use normal WordPress capabilities; the quote
 * owner may also view their own document from the trade dashboard.
 */
function qs_can_view_quote_document( $quote_id ) {
	$quote = get_post( $quote_id );
	if ( ! $quote || 'quote' !== $quote->post_type || ! is_user_logged_in() ) {
		return false;
	}

	if ( function_exists( 'qs_portal_user_can_access' ) && ! qs_portal_user_can_access() ) {
		return false;
	}

	return current_user_can( 'edit_post', $quote_id ) || (int) $quote->post_author === get_current_user_id();
}

function qs_generate_quotation_html(
	$quote_id
) {

	$data = qs_get_quote_data(
		$quote_id
	);

	$total = qs_calculate_total(
		$quote_id
	);

	ob_start();

	include QS_PATH .
		'templates/quotation.php';

	$html = ob_get_clean();

	$css = file_get_contents(
		QS_PATH .
		'assets/css/quotation-pdf.css'
	);

	return
		'<style>' .
		$css .
		'</style>' .
		$html;

}

/**
 * Generate Job Sheet HTML
 */
function qs_generate_jobsheet_html(
	$quote_id
) {

	$data = qs_get_quote_data( $quote_id );

	ob_start();

	include QS_PATH .
		'templates/jobsheet.php';

	$html = ob_get_clean();

	$css = file_get_contents(
		QS_PATH .
		'assets/css/jobsheet-pdf.css'
	);

	return
		'<style>' .
		$css .
		'</style>' .
		$html;

}

/**
 * Generate Job Sheet PDF
 */
function qs_generate_jobsheet_pdf(
	$quote_id
) {

	$html = qs_generate_jobsheet_html(
		$quote_id
	);

	$options = new Options();

	$options->set(
		'isRemoteEnabled',
		true
	);
	$options->set( 'defaultFont', 'DejaVu Sans' );

	$dompdf = new Dompdf(
		$options
	);

	$dompdf->loadHtml(
		$html
	);

	$dompdf->setPaper(
		'A4',
		'portrait'
	);

	$dompdf->render();

	$quote_number = sanitize_file_name( get_post_meta( $quote_id, '_quote_number', true ) );
	$dompdf->stream(
		'job-sheet-' .
		( $quote_number ? $quote_number : $quote_id ) .
		'.pdf',
		array(
			'Attachment' => false,
		)
	);

	exit;

}

/**
 * Download Job Sheet PDF
 */
function qs_download_jobsheet_pdf() {

	if (
		! isset(
			$_GET['download_jobsheet_pdf']
		)
	) {
		return;
	}

	$quote_id = absint(
		$_GET['download_jobsheet_pdf']
	);

	if ( ! qs_can_view_quote_document( $quote_id ) ) {
		wp_die( esc_html__( 'You are not allowed to download this job sheet.', 'quote-system' ), 403 );
	}

	qs_generate_jobsheet_pdf(
		$quote_id
	);

}

add_action(
	'init',
	'qs_download_jobsheet_pdf'
);

function qs_quotation_shortcode() {

	$quote_id = isset(
		$_GET['quote_id']
	)
		? absint(
			$_GET['quote_id']
		)
		: 0;

	if ( ! $quote_id || ! qs_can_view_quote_document( $quote_id ) ) {
		return '<p>You are not allowed to view this quotation.</p>';
	}

	return qs_generate_quotation_html( $quote_id );

}

add_shortcode(
	'quotation',
	'qs_quotation_shortcode'
);

/**
 * Load the lightweight FPDI + FPDF runtime bundled with Quote System.
 *
 * Dompdf still generates the quotation. FPDI/FPDF are only used afterwards
 * to concatenate the completed quotation with uploaded PDF documents.
 */
function qs_load_pdf_merger_library() {
	if ( class_exists( '\\setasign\\Fpdi\\Fpdi', false ) ) {
		return true;
	}

	$fpdf = QS_PATH . 'lib/pdf-merger/fpdf/fpdf.php';
	$fpdi = QS_PATH . 'lib/pdf-merger/fpdi/src/autoload.php';

	if ( ! is_readable( $fpdf ) || ! is_readable( $fpdi ) ) {
		return false;
	}

	if ( ! class_exists( 'FPDF', false ) ) {
		require_once $fpdf;
	}

	require_once $fpdi;

	return class_exists( '\\setasign\\Fpdi\\Fpdi', false );
}

/**
 * Return uploaded supporting PDFs in their saved order.
 * JPG and PNG supporting documents intentionally remain separate.
 */
function qs_quote_supporting_pdf_paths( $quote_id ) {
	$attachment_ids = get_post_meta( $quote_id, '_supporting_documents', true );
	if ( ! is_array( $attachment_ids ) ) {
		return array();
	}

	$paths = array();

	foreach ( array_unique( array_map( 'absint', $attachment_ids ) ) as $attachment_id ) {
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			continue;
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			continue;
		}

		$mime      = (string) get_post_mime_type( $attachment_id );
		$extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( 'application/pdf' !== $mime && 'pdf' !== $extension ) {
			continue;
		}

		$paths[] = $path;
	}

	return $paths;
}

/**
 * Concatenate PDF files while retaining each imported page's own page size
 * and orientation.
 */
function qs_merge_pdf_paths( $paths ) {
	if ( ! qs_load_pdf_merger_library() ) {
		return new WP_Error(
			'qs_pdf_merger_missing',
			__( 'The PDF merger library is not available.', 'quote-system' )
		);
	}

	$paths = array_values(
		array_filter(
			(array) $paths,
			static function ( $path ) {
				return is_string( $path ) && is_readable( $path );
			}
		)
	);

	if ( ! $paths ) {
		return new WP_Error(
			'qs_pdf_merge_no_sources',
			__( 'No readable PDF files were supplied for merging.', 'quote-system' )
		);
	}

	try {
		$merged = new \setasign\Fpdi\Fpdi();
		$merged->SetAutoPageBreak( false );

		foreach ( $paths as $path ) {
			$page_count = $merged->setSourceFile( $path );

			for ( $page_number = 1; $page_number <= $page_count; $page_number++ ) {
				$template_id = $merged->importPage( $page_number );
				$size        = $merged->getTemplateSize( $template_id );

				if ( empty( $size['width'] ) || empty( $size['height'] ) ) {
					throw new RuntimeException( 'Unable to determine imported PDF page size.' );
				}

				$orientation = ! empty( $size['orientation'] )
					? $size['orientation']
					: ( $size['width'] > $size['height'] ? 'L' : 'P' );

				$merged->AddPage(
					$orientation,
					array( $size['width'], $size['height'] )
				);
				$merged->useTemplate( $template_id );
			}
		}

		return $merged->Output( 'S' );
	} catch ( Throwable $error ) {
		return new WP_Error(
			'qs_pdf_merge_failed',
			sprintf(
				/* translators: %s: PDF merger error. */
				__( 'The quotation was created, but one of the supporting PDFs could not be appended: %s', 'quote-system' ),
				$error->getMessage()
			)
		);
	}
}

/**
 * Return finished quotation bytes, appending uploaded supporting PDFs when
 * requested. The original Dompdf quotation remains untouched before merging.
 */
function qs_generate_quotation_pdf_output( $quote_id, $append_supporting_pdfs = true ) {
	$pdf = qs_generate_quotation_pdf( $quote_id );

	if ( ! $pdf || ! method_exists( $pdf, 'output' ) ) {
		return new WP_Error(
			'qs_quotation_pdf_failed',
			__( 'The quotation PDF could not be generated.', 'quote-system' )
		);
	}

	$quote_bytes = $pdf->output();
	$pdf_paths   = $append_supporting_pdfs ? qs_quote_supporting_pdf_paths( $quote_id ) : array();

	if ( ! $pdf_paths ) {
		return $quote_bytes;
	}

	$temp_dir = trailingslashit( get_temp_dir() ) . 'quote-system-merge-' . wp_generate_uuid4();
	if ( ! wp_mkdir_p( $temp_dir ) ) {
		return new WP_Error(
			'qs_pdf_merge_temp_failed',
			__( 'A temporary folder could not be created for the quotation PDF.', 'quote-system' )
		);
	}

	$quote_path = trailingslashit( $temp_dir ) . 'quotation.pdf';
	$written    = file_put_contents( $quote_path, $quote_bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	if ( false === $written || 0 === $written ) {
		@rmdir( $temp_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return new WP_Error(
			'qs_pdf_merge_temp_write_failed',
			__( 'The temporary quotation PDF could not be written.', 'quote-system' )
		);
	}

	$merged = qs_merge_pdf_paths( array_merge( array( $quote_path ), $pdf_paths ) );

	@unlink( $quote_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@rmdir( $temp_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	return $merged;
}

function qs_generate_quotation_pdf(
	$quote_id
) {

	$html = qs_generate_quotation_html(
		$quote_id
	);

	$options = new Options();

	$options->set(
		'isRemoteEnabled',
		true
	);
	$options->set( 'defaultFont', 'DejaVu Sans' );

	$dompdf = new Dompdf(
		$options
	);

	$dompdf->loadHtml(
		$html
	);

	$dompdf->setPaper(
		'A4',
		'portrait'
	);

	$dompdf->render();

	return $dompdf;

}

function qs_stream_quotation_pdf(
	$quote_id
) {

	$output = qs_generate_quotation_pdf_output(
		$quote_id,
		true
	);

	if ( is_wp_error( $output ) ) {
		wp_die(
			esc_html( $output->get_error_message() ),
			esc_html__( 'Unable to create quotation PDF', 'quote-system' ),
			array( 'response' => 500 )
		);
	}

	$quote_number = sanitize_file_name( get_post_meta( $quote_id, '_quote_number', true ) );
	$filename     = 'quotation-' . ( $quote_number ? $quote_number : $quote_id ) . '.pdf';

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . $filename . '"' );
	header( 'Content-Length: ' . strlen( $output ) );

	echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;

}

function qs_download_quotation_pdf() {

	if (
		! isset(
			$_GET['download_quote_pdf']
		)
	) {
		return;
	}

	$quote_id = absint(
		$_GET['download_quote_pdf']
	);

	if ( ! qs_can_view_quote_document( $quote_id ) ) {
		wp_die( esc_html__( 'You are not allowed to download this quotation.', 'quote-system' ), 403 );
	}

	qs_stream_quotation_pdf(
		$quote_id
	);

}

add_action(
	'init',
	'qs_download_quotation_pdf'
);

/**
 * Job Sheet Shortcode
 */
function qs_jobsheet_shortcode() {

	$quote_id = isset( $_GET['quote_id'] )
		? absint( $_GET['quote_id'] )
		: 0;

	if ( ! $quote_id || ! qs_can_view_quote_document( $quote_id ) ) {
		return '<p>You are not allowed to view this job sheet.</p>';
	}

	return qs_generate_jobsheet_html( $quote_id );

}

add_shortcode(
	'jobsheet',
	'qs_jobsheet_shortcode'
);
