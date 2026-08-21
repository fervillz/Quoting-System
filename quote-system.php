<?php
/**
 * Plugin Name: Quote System
 * Description: Frontend quotation system for Loughlin Furniture.
 * Version: 1.6.0
 * Author: Loughlin Furniture
 * Text Domain: quote-system
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Constants
 */
define( 'QS_VERSION', '1.6.0' );
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
require_once QS_PATH . 'profile-end-panels.php';
require_once QS_PATH . 'meta-boxes.php';
require_once QS_PATH . 'email.php';
require_once QS_PATH . 'template-functions.php';
require_once QS_PATH . 'pdf.php';
require_once QS_PATH . 'estimated-lead-time.php';
require_once QS_PATH . 'setup/defaults.php';
require_once QS_PATH . 'setup/setup.php';

/**
 * Integrations
 */
require_once QS_PATH . 'integrations/woocommerce.php';

require_once QS_PATH . 'frontend/quote-builder.php';
require_once QS_PATH . 'item-configurations.php';
require_once QS_PATH . 'frontend/quote-review.php';
require_once QS_PATH . 'item-configurations-compat.php';
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
		'qs-quantity-fields',
		QS_URL . 'assets/css/quantity-fields.css',
		array( 'qs-quote-builder' ),
		$asset_version( 'assets/css/quantity-fields.css' )
	);

	wp_enqueue_style(
		'qs-quote-builder-ux',
		QS_URL . 'assets/css/quote-builder-ux.css',
		array( 'qs-quote-builder', 'qs-quantity-fields' ),
		$asset_version( 'assets/css/quote-builder-ux.css' )
	);

	wp_enqueue_script(
		'qs-quantity-fields',
		QS_URL . 'assets/js/quantity-fields.js',
		array(),
		$asset_version( 'assets/js/quantity-fields.js' ),
		true
	);

	wp_enqueue_script(
		'qs-quote-builder-ux',
		QS_URL . 'assets/js/quote-builder-ux.js',
		array( 'qs-quantity-fields' ),
		$asset_version( 'assets/js/quote-builder-ux.js' ),
		true
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
		qs_create_default_product_types();
		add_option( 'qs_setup_completed_version', '' );
		flush_rewrite_rules();
	}
);
