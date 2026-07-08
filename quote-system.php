<?php
/**
 * Plugin Name: Quote System
 * Description: Frontend quotation system for Loughlin Furniture.
 * Version: 1.0.0
 * Author: Loughlin Furniture
 * Text Domain: quote-system
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Constants
 */
define( 'QS_VERSION', '1.0.0' );
define( 'QS_PATH', plugin_dir_path( __FILE__ ) );
define( 'QS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Core
 */
require_once QS_PATH . 'post-type.php';
require_once QS_PATH . 'statuses.php';
require_once QS_PATH . 'pricing.php';
require_once QS_PATH . 'quote-number.php';
require_once QS_PATH . 'meta-boxes.php';
require_once QS_PATH . 'pricing.php';
require_once QS_PATH . 'email.php';
require_once QS_PATH . 'pdf.php';
require_once QS_PATH . 'frontend/quote-builder.php';
require_once QS_PATH . 'frontend/quote-review.php';
require_once QS_PATH . 'frontend/admin-dashboard.php';
require_once QS_PATH . 'frontend/my-quotes.php';
require_once QS_PATH . 'frontend/quote-submitted.php';
require_once QS_PATH . 'dompdf/autoload.inc.php';

add_shortcode(
	'quote_builder',
	'qs_quote_builder_shortcode'
);
/**
 * Admin
 */
require_once QS_PATH . 'admin/quotes.php';

/**
 * Enqueue CSS
 */
function qs_enqueue_assets() {

	wp_enqueue_style(
		'qs-base',
		plugin_dir_url( __FILE__ ) .
		'assets/css/base.css',
		array(),
		'1.0'
	);

	wp_enqueue_style(
		'qs-quote-builder',
		plugin_dir_url( __FILE__ ) .
		'assets/css/quote-builder.css',
		array( 'qs-base' ),
		'1.0'
	);

	wp_enqueue_style(
		'qs-quote-review',
		plugin_dir_url( __FILE__ ) .
		'assets/css/quote-review.css',
		array( 'qs-base' ),
		'1.0'
	);

	wp_enqueue_style(
		'qs-quote-submitted',
		plugin_dir_url( __FILE__ ) .
		'assets/css/quote-submitted.css',
		array( 'qs-base' ),
		'1.0'
	);

	wp_enqueue_style(
		'qs-quotation',
		plugin_dir_url( __FILE__ ) .
		'assets/css/quotation.css',
		array( 'qs-base' ),
		'1.0'
	);

	wp_enqueue_style(
		'qs-jobsheet',
		plugin_dir_url( __FILE__ ) .
		'assets/css/jobsheet.css',
		array( 'qs-base' ),
		'1.0'
	);

	wp_enqueue_style(
		'qs-admin-dashboard',
		plugin_dir_url( __FILE__ ) .
		'assets/css/admin-dashboard.css',
		array( 'qs-base' ),
		'1.0'
	);

}
add_action(
	'wp_enqueue_scripts',
	'qs_enqueue_assets',
	9999
);
