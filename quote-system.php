<?php
/**
 * Plugin Name: Quote System
 * Description: Frontend quotation system for Loughlin Furniture.
 * Version: 1.6.7
 * Author: Loughlin Furniture
 * Text Domain: quote-system
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Constants
 */
define( 'QS_VERSION', '1.6.7' );
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
 * Return true when the current request is one of the Quote System frontend
 * screens. The installer stores page IDs, but the shortcode check also keeps
 * manually-created/legacy pages working.
 */
function qs_is_frontend_ui_page() {
	if ( is_admin() || ! is_singular() ) {
		return false;
	}

	$queried_id = get_queried_object_id();
	if ( $queried_id && function_exists( 'qs_setup_page_definitions' ) && function_exists( 'qs_setup_get_page_id' ) ) {
		foreach ( array_keys( qs_setup_page_definitions() ) as $page_key ) {
			if ( $queried_id === (int) qs_setup_get_page_id( $page_key ) ) {
				return true;
			}
		}
	}

	$post = get_post( $queried_id );
	if ( ! $post ) {
		return false;
	}

	$shortcodes = array(
		'quote_builder',
		'quote_review',
		'quote_admin_dashboard',
		'admin_dashboard',
		'my_quotes',
		'quote_submitted',
		'quote_login',
		'joiner_login',
	);

	foreach ( $shortcodes as $shortcode ) {
		if ( has_shortcode( (string) $post->post_content, $shortcode ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Frontend CSS files that make up the complete Quote System UI.
 *
 * These are still enqueued as normal cacheable stylesheets below. On Quote
 * System pages we also append the same CSS inline to qs-base as a defensive
 * fallback. This avoids a partially-unformatted UI when a theme/optimizer
 * drops, delays or combines one of the dependent stylesheet handles.
 */
function qs_frontend_css_files() {
	return array(
		'assets/css/quote-builder.css',
		'assets/css/quantity-fields.css',
		'assets/css/quote-builder-ux.css',
		'assets/css/item-configurations.css',
		'assets/css/quote-review.css',
		'assets/css/quote-review-admin-actions.css',
		'assets/css/quote-submitted.css',
		'assets/css/quotation.css',
		'assets/css/jobsheet.css',
		'assets/css/admin-dashboard.css',
		'assets/css/payment-priority.css',
		'assets/css/my-quotes.css',
		'assets/css/login.css',
	);
}

/**
 * Add a complete scoped CSS fallback after base.css on Quote System pages.
 * The files are local plugin assets; no remote request is made here.
 */
function qs_add_frontend_css_fallback() {
	if ( ! qs_is_frontend_ui_page() || ! wp_style_is( 'qs-base', 'enqueued' ) ) {
		return;
	}

	$css = '';
	foreach ( qs_frontend_css_files() as $relative_path ) {
		$path = QS_PATH . ltrim( $relative_path, '/' );
		if ( ! is_readable( $path ) ) {
			continue;
		}

		$contents = file_get_contents( $path );
		if ( false === $contents || '' === trim( $contents ) ) {
			continue;
		}

		$css .= "\n/* Quote System fallback: " . sanitize_text_field( $relative_path ) . " */\n" . $contents . "\n";
	}

	if ( '' !== $css ) {
		wp_add_inline_style( 'qs-base', $css );
	}
}

/**
 * Enqueue CSS and scripts.
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

	qs_add_frontend_css_fallback();
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
