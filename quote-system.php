<?php
/**
 * Plugin Name: Quote System
 * Description: Frontend quotation system for Loughlin Furniture.
 * Version: 1.4.1
 * Author: Loughlin Furniture
 * Text Domain: quote-system
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Constants
 */
define( 'QS_VERSION', '1.4.1' );
define( 'QS_PATH', plugin_dir_path( __FILE__ ) );
define( 'QS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Core
 */
require_once QS_PATH . 'post-type.php';
require_once QS_PATH . 'taxonomies.php';
require_once QS_PATH . 'statuses.php';
require_once QS_PATH . 'pricing.php';
require_once QS_PATH . 'quote-number.php';
require_once QS_PATH . 'repeaters.php';
require_once QS_PATH . 'meta-boxes.php';
require_once QS_PATH . 'email.php';
require_once QS_PATH . 'template-functions.php';
require_once QS_PATH . 'pdf.php';

/**
 * Integrations
 */
require_once QS_PATH . 'integrations/woocommerce.php';

require_once QS_PATH . 'frontend/quote-builder.php';
require_once QS_PATH . 'frontend/quote-review.php';
require_once QS_PATH . 'frontend/admin-dashboard.php';
require_once QS_PATH . 'frontend/payment-priority.php';
require_once QS_PATH . 'frontend/my-quotes.php';
require_once QS_PATH . 'frontend/quote-submitted.php';
require_once QS_PATH . 'frontend/login.php';
require_once QS_PATH . 'dompdf/autoload.inc.php';

add_shortcode(
	'quote_builder',
	'qs_quote_builder_shortcode'
);
/**
 * Admin
 */
require_once QS_PATH . 'admin/quotes.php';
require_once QS_PATH . 'admin/pricing-settings.php';

/**
 * Enqueue CSS
 */
function qs_enqueue_assets() {

	$asset_version = static function ( $relative_path ) {
		$path = QS_PATH . ltrim( $relative_path, '/' );
		return file_exists( $path ) ? (string) filemtime( $path ) : QS_VERSION;
	};

	wp_enqueue_style(
		'qs-base',
		QS_URL . 'assets/css/base.css',
		array(),
		$asset_version( 'assets/css/base.css' )
	);

	wp_enqueue_style(
		'qs-quote-builder',
		QS_URL . 'assets/css/quote-builder.css',
		array( 'qs-base' ),
		$asset_version( 'assets/css/quote-builder.css' )
	);

	wp_enqueue_style(
		'qs-quote-review',
		QS_URL . 'assets/css/quote-review.css',
		array( 'qs-base' ),
		$asset_version( 'assets/css/quote-review.css' )
	);

	wp_enqueue_style(
		'qs-quote-review-admin-actions',
		QS_URL . 'assets/css/quote-review-admin-actions.css',
		array( 'qs-quote-review' ),
		$asset_version( 'assets/css/quote-review-admin-actions.css' )
	);

	wp_enqueue_style(
		'qs-quote-submitted',
		QS_URL . 'assets/css/quote-submitted.css',
		array( 'qs-base' ),
		$asset_version( 'assets/css/quote-submitted.css' )
	);

	wp_enqueue_style(
		'qs-quotation',
		QS_URL . 'assets/css/quotation.css',
		array( 'qs-base' ),
		$asset_version( 'assets/css/quotation.css' )
	);

	wp_enqueue_style(
		'qs-jobsheet',
		QS_URL . 'assets/css/jobsheet.css',
		array( 'qs-base' ),
		$asset_version( 'assets/css/jobsheet.css' )
	);

	wp_enqueue_style(
		'qs-admin-dashboard',
		QS_URL . 'assets/css/admin-dashboard.css',
		array( 'qs-base' ),
		$asset_version( 'assets/css/admin-dashboard.css' )
	);

	wp_enqueue_style(
		'qs-my-quotes',
		QS_URL . 'assets/css/my-quotes.css',
		array( 'qs-base' ),
		$asset_version( 'assets/css/my-quotes.css' )
	);

	wp_enqueue_style(
		'qs-login',
		QS_URL . 'assets/css/login.css',
		array( 'qs-base' ),
		$asset_version( 'assets/css/login.css' )
	);

}
add_action(
	'wp_enqueue_scripts',
	'qs_enqueue_assets',
	9999
);

register_activation_hook(
	__FILE__,
	function () {

		qs_register_post_type();

		flush_rewrite_rules();

		qs_create_default_product_types();
	}
);
