Exit code: 0
Wall time: 0.7 seconds
Output:
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

		'door_profile' => get_post_meta(
			$quote_id,
			'_door_profile',
			true
		),

		'timber' => get_post_meta(
			$quote_id,
			'_timber',
			true
		),

		'finish' => get_post_meta(
			$quote_id,
			'_finish',
			true
		),

		'handle_profile' => get_post_meta(
			$quote_id,
			'_handle_profile',
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

		'shipping' => get_post_meta(
			$quote_id,
			'_shipping',
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
	return current_user_can( 'edit_post', $quote_id ) || (int) $quote->post_author === get_current_user_id();
}

/**
 * TEMPORARY PDF DATA TEST
 * https://staging2.loughlinfurniture.com.au/wp-admin/?qs_test_quote=66610
 */
function qs_test_quote_data() {

	if ( ! isset( $_GET['qs_test_quote'] ) ) {
		return;
	}

	$quote_id = absint(
		$_GET['qs_test_quote']
	);

	echo '<pre>';

	print_r(
		qs_get_quote_data(
			$quote_id
		)
	);

	echo '</pre>';

	exit;

}

add_action(
	'admin_init',
	'qs_test_quote_data'
);

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

	$dompdf->stream(
		'jobsheet-' .
		$quote_id .
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

	return qs_generate_quotation_html(
		$quote_id
	);

}

add_shortcode(
	'quotation',
	'qs_quotation_shortcode'
);

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

	$pdf = qs_generate_quotation_pdf(
		$quote_id
	);

	$pdf->stream(
		'quotation-' .
		$quote_id .
		'.pdf',
		array(
			'Attachment' => false,
		)
	);

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

	ob_start();

	include QS_PATH .
		'templates/jobsheet.php';

	return ob_get_clean();

}

add_shortcode(
	'jobsheet',
	'qs_jobsheet_shortcode'
);

