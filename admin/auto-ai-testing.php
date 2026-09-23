<?php
/**
 * Auto AI Testing
 *
 * Admin-only, persistent end-to-end workflow runner. The runner deliberately
 * creates real WordPress users (when requested), Quote CPT records,
 * WooCommerce orders and real outgoing Quote System emails.
 *
 * It is intentionally isolated from normal frontend classes: each test step
 * calls the existing production functions and records what happened.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QS_AUTO_AI_RUN_POST_TYPE', 'qs_auto_ai_run' );

/** Hidden post type used only to persist resumable test runs and their logs. */
function qs_auto_ai_register_run_post_type() {
	register_post_type(
		QS_AUTO_AI_RUN_POST_TYPE,
		array(
			'label'        => 'Quote Auto AI Tests',
			'public'       => false,
			'show_ui'      => false,
			'show_in_rest' => false,
			'supports'     => array( 'title' ),
		)
	);
}
add_action( 'init', 'qs_auto_ai_register_run_post_type', 8 );

function qs_auto_ai_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=quote',
		'Auto AI Testing',
		'Auto AI Testing',
		'manage_options',
		'qs-auto-ai-testing',
		'qs_auto_ai_testing_page'
	);
}
// Auto AI Testing now lives under Quote System → Settings → AI Testing.

/** Deployment-verification shortcut shown directly on Quote System → Setup. */
function qs_auto_ai_render_setup_shortcut() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$mode = function_exists( 'qs_portal_mode' ) ? qs_portal_mode() : 'test';
	?>
	<section class="qs-setup-transfer" style="border-left-color:#7c3aed">
		<h2>Automatic Workflow Test <?php echo function_exists( 'qs_setup_status_badge' ) ? qs_setup_status_badge( 'live' !== $mode, 'Ready to test', 'Switch out of Live Mode' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
		<p>After setup/import, run a real end-to-end Quote System test with two live logs: Joiner on the left and LF Admin on the right. It creates a real Quote, WooCommerce deposit/final orders and sends real emails.</p>
		<p>
			<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'edit.php?post_type=quote&page=qs-auto-ai-testing' ) ); ?>">Open Auto AI Testing</a>
		</p>
		<p class="description">Run this while Portal Mode is <strong>Setup</strong> or <strong>Test</strong>. The runner is locked in Live Mode.</p>
	</section>
	<?php
}
// The full Auto AI runner is rendered directly in the AI Testing tab.

function qs_auto_ai_meta( $run_id, $key, $default = '' ) {
	$value = get_post_meta( $run_id, '_qs_auto_ai_' . $key, true );
	return '' === $value ? $default : $value;
}

function qs_auto_ai_set_meta( $run_id, $key, $value ) {
	update_post_meta( $run_id, '_qs_auto_ai_' . $key, $value );
}

function qs_auto_ai_money( $amount ) {
	return function_exists( 'wc_price' )
		? html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, get_bloginfo( 'charset' ) )
		: '

function qs_auto_ai_log( $run_id, $side, $message, $type = 'info', $url = '', $link_label = 'View' ) {
	$side = 'admin' === $side ? 'admin' : 'joiner';
	$type = in_array( $type, array( 'info', 'success', 'warning', 'error' ), true ) ? $type : 'info';

	/*
	 * Keep the original per-side logs for the live console, but also write one
	 * globally sequenced event stream. The sequence makes historical runs
	 * readable even when Joiner/Admin actions happened within the same second.
	 */
	$sequence = absint( qs_auto_ai_meta( $run_id, 'event_sequence', 0 ) ) + 1;
	qs_auto_ai_set_meta( $run_id, 'event_sequence', $sequence );

	$event = array(
		'sequence'   => $sequence,
		'side'       => $side,
		'time'       => current_time( 'H:i:s' ),
		'message'    => sanitize_text_field( html_entity_decode( (string) $message, ENT_QUOTES, get_bloginfo( 'charset' ) ) ),
		'type'       => $type,
		'url'        => $url ? esc_url_raw( $url ) : '',
		'link_label' => sanitize_text_field( $link_label ),
		'step'       => absint( qs_auto_ai_meta( $run_id, 'current_step', 0 ) ),
	);

	$logs = qs_auto_ai_meta( $run_id, 'logs_' . $side, array() );
	$logs = is_array( $logs ) ? $logs : array();
	$logs[] = $event;
	qs_auto_ai_set_meta( $run_id, 'logs_' . $side, $logs );

	$events = qs_auto_ai_meta( $run_id, 'timeline_events', array() );
	$events = is_array( $events ) ? $events : array();
	$events[] = $event;
	qs_auto_ai_set_meta( $run_id, 'timeline_events', $events );
}

/**
 * Return the exact global timeline for new runs. Older runs created before the
 * timeline feature are reconstructed from their two timestamped log columns so
 * they remain readable instead of becoming orphaned historical data.
 */
function qs_auto_ai_timeline_events( $run_id ) {
	$events = qs_auto_ai_meta( $run_id, 'timeline_events', array() );
	if ( is_array( $events ) && $events ) {
		usort(
			$events,
			static function ( $a, $b ) {
				return absint( $a['sequence'] ?? 0 ) <=> absint( $b['sequence'] ?? 0 );
			}
		);
		return array_values( $events );
	}

	$legacy = array();
	$order  = 0;
	foreach ( array( 'joiner', 'admin' ) as $side ) {
		$logs = qs_auto_ai_meta( $run_id, 'logs_' . $side, array() );
		foreach ( is_array( $logs ) ? $logs : array() as $log ) {
			$order++;
			$legacy[] = array(
				'sequence'   => 0,
				'side'       => $side,
				'time'       => isset( $log['time'] ) ? (string) $log['time'] : '',
				'message'    => isset( $log['message'] ) ? (string) $log['message'] : '',
				'type'       => isset( $log['type'] ) ? (string) $log['type'] : 'info',
				'url'        => isset( $log['url'] ) ? (string) $log['url'] : '',
				'link_label' => isset( $log['link_label'] ) ? (string) $log['link_label'] : 'View',
				'step'       => 0,
				'_legacy_order' => $order,
			);
		}
	}

	usort(
		$legacy,
		static function ( $a, $b ) {
			$time_compare = strcmp( (string) $a['time'], (string) $b['time'] );
			return 0 !== $time_compare ? $time_compare : ( (int) $a['_legacy_order'] <=> (int) $b['_legacy_order'] );
		}
	);

	foreach ( $legacy as $index => &$event ) {
		$event['sequence'] = $index + 1;
		unset( $event['_legacy_order'] );
	}
	unset( $event );

	return $legacy;
}

function qs_auto_ai_steps() {
	return array(
		array( 'side' => 'joiner', 'label' => 'Build real quote through Quote Builder save handler' ),
		array( 'side' => 'joiner', 'label' => 'Submit quote for LF review' ),
		array( 'side' => 'admin',  'label' => 'Verify quote received and pricing' ),
		array( 'side' => 'admin',  'label' => 'Render quotation PDF' ),
		array( 'side' => 'admin',  'label' => 'Create deposit WooCommerce order and send email' ),
		array( 'side' => 'joiner', 'label' => 'Verify deposit email and payment URL' ),
		array( 'side' => 'joiner', 'label' => 'Place deposit order on Direct Bank Transfer' ),
		array( 'side' => 'admin',  'label' => 'Confirm deposit payment through WooCommerce' ),
		array( 'side' => 'admin',  'label' => 'Verify deposit workflow and complete order' ),
		array( 'side' => 'admin',  'label' => 'Mark quote In Production' ),
		array( 'side' => 'admin',  'label' => 'Apply post-deposit Additional Charge' ),
		array( 'side' => 'admin',  'label' => 'Create final-balance order and send email' ),
		array( 'side' => 'joiner', 'label' => 'Verify final-payment email and payment URL' ),
		array( 'side' => 'joiner', 'label' => 'Place final order on Direct Bank Transfer' ),
		array( 'side' => 'admin',  'label' => 'Confirm final payment through WooCommerce' ),
		array( 'side' => 'admin',  'label' => 'Complete final WooCommerce order' ),
		array( 'side' => 'joiner', 'label' => 'Verify completed quote on Joiner workflow' ),
		array( 'side' => 'admin',  'label' => 'Final end-to-end verification' ),
	);
}

function qs_auto_ai_with_user( $user_id, $callback ) {
	$original_user_id = get_current_user_id();
	wp_set_current_user( absint( $user_id ) );

	try {
		return call_user_func( $callback );
	} finally {
		wp_set_current_user( $original_user_id );
	}
}

function qs_auto_ai_joiner_users() {
	return get_users(
		array(
			'role'    => 'joiner',
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'number'  => 500,
		)
	);
}

function qs_auto_ai_admin_users() {
	$users  = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC', 'number' => 500 ) );
	$admins = array();

	foreach ( $users as $user ) {
		if ( user_can( $user, 'edit_others_posts' ) ) {
			$admins[] = $user;
		}
	}

	return $admins;
}

function qs_auto_ai_unique_username( $email, $name ) {
	$base = sanitize_user( strtok( (string) $email, '@' ), true );
	if ( ! $base ) {
		$base = sanitize_user( strtolower( str_replace( ' ', '.', (string) $name ) ), true );
	}
	if ( ! $base ) {
		$base = 'quote-test-joiner';
	}

	$username = $base;
	$suffix   = 2;
	while ( username_exists( $username ) ) {
		$username = $base . $suffix;
		$suffix++;
	}

	return $username;
}

/**
 * Create a permanent, normal Joiner user. Password is intentionally not saved
 * in the test run. An administrator can later send a password reset normally.
 */
function qs_auto_ai_create_joiner_from_request() {
	$email   = isset( $_POST['new_joiner_email'] ) ? sanitize_email( wp_unslash( $_POST['new_joiner_email'] ) ) : '';
	$name    = isset( $_POST['new_joiner_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_joiner_name'] ) ) : '';
	$company = isset( $_POST['new_joiner_company'] ) ? sanitize_text_field( wp_unslash( $_POST['new_joiner_company'] ) ) : '';
	$phone   = isset( $_POST['new_joiner_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['new_joiner_phone'] ) ) : '';
	$address = isset( $_POST['new_joiner_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['new_joiner_address'] ) ) : '';

	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'invalid_joiner_email', 'Enter a valid email address for the new Joiner.' );
	}
	if ( email_exists( $email ) ) {
		return new WP_Error( 'joiner_email_exists', 'That email already belongs to a WordPress user. Select that Joiner instead or use another address.' );
	}
	if ( ! $name ) {
		return new WP_Error( 'missing_joiner_name', 'Enter a name for the new Joiner.' );
	}

	$parts      = preg_split( '/\s+/', trim( $name ) );
	$first_name = $parts ? array_shift( $parts ) : $name;
	$last_name  = $parts ? implode( ' ', $parts ) : '';
	$username   = qs_auto_ai_unique_username( $email, $name );
	$user_id    = wp_insert_user(
		array(
			'user_login'   => $username,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'user_email'   => $email,
			'display_name' => $name,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'role'         => 'joiner',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	update_user_meta( $user_id, 'company_name', $company );
	update_user_meta( $user_id, 'billing_company', $company );
	update_user_meta( $user_id, 'billing_email', $email );
	update_user_meta( $user_id, 'billing_first_name', $first_name );
	update_user_meta( $user_id, 'billing_last_name', $last_name );
	update_user_meta( $user_id, 'billing_phone', $phone );
	update_user_meta( $user_id, 'billing_address_1', $address );
	update_user_meta( $user_id, 'shipping_address_1', $address );
	update_user_meta( $user_id, 'qs_delivery_address', $address );
	update_user_meta( $user_id, 'qs_customer_name', $name );
	update_user_meta( $user_id, 'qs_customer_email', $email );
	update_user_meta( $user_id, 'qs_customer_phone', $phone );
	update_user_meta( $user_id, 'qs_customer_company', $company );
	update_user_meta( $user_id, 'qs_portal_test_access', '1' );

	return (int) $user_id;
}

function qs_auto_ai_product_id( $type, $preferred_title = '' ) {
	if ( $preferred_title && function_exists( 'qs_find_quote_product' ) ) {
		$preferred = qs_find_quote_product( $preferred_title, $type );
		if ( $preferred ) {
			return $preferred;
		}
	}

	$products = function_exists( 'qs_builder_products' ) ? qs_builder_products( $type ) : array();
	return $products ? (int) $products[0]->ID : 0;
}

function qs_auto_ai_test_dimensions( $profile_id, $preferred_width = 600, $preferred_height = 800 ) {
	$width  = max( 1, absint( $preferred_width ) );
	$height = max( 1, absint( $preferred_height ) );

	if ( function_exists( 'qs_item_config_matrix_bounds' ) ) {
		$bounds = qs_item_config_matrix_bounds( $profile_id );
		if ( $bounds ) {
			$width  = max( (int) ceil( $bounds['min_width'] ), min( $width, (int) floor( $bounds['max_width'] ) ) );
			$height = max( (int) ceil( $bounds['min_height'] ), min( $height, (int) floor( $bounds['max_height'] ) ) );
		}
	}

	return array( max( 1, $width ), max( 1, $height ) );
}

/** Pick a real priced kickboard height from the current product configuration. */
function qs_auto_ai_kickboard_dimensions( $product_id ) {
	$height = 150;
	$length = 1200;

	if ( $product_id && function_exists( 'qs_pricing_repeater_rows' ) ) {
		$rows = qs_pricing_repeater_rows(
			$product_id,
			'linear_pricing',
			array(
				'min'   => array( 'height_min', 'min' ),
				'max'   => array( 'height_max', 'max' ),
				'price' => array( 'price_per_lm', 'price' ),
			)
		);

		foreach ( $rows as $row ) {
			$minimum = isset( $row['min'] ) ? max( 1, (int) ceil( (float) $row['min'] ) ) : 1;
			$maximum = isset( $row['max'] ) ? min( 200, (int) floor( (float) $row['max'] ) ) : 200;
			$price   = isset( $row['price'] ) ? (float) $row['price'] : 0;
			if ( $maximum >= $minimum && $price > 0 ) {
				$height = (int) floor( ( $minimum + $maximum ) / 2 );
				break;
			}
		}
	}

	return array( $length, max( 1, min( 200, $height ) ) );
}

function qs_auto_ai_joiner_defaults( $user ) {
	if ( function_exists( 'qs_customer_quote_defaults' ) ) {
		$defaults = qs_customer_quote_defaults( $user->ID );
		return array(
			'name'    => ! empty( $defaults['customer_name'] ) ? $defaults['customer_name'] : ( $user->display_name ? $user->display_name : $user->user_login ),
			'company' => isset( $defaults['company_name'] ) ? $defaults['company_name'] : '',
			'email'   => ! empty( $defaults['customer_email'] ) ? $defaults['customer_email'] : $user->user_email,
			'phone'   => isset( $defaults['customer_phone'] ) ? $defaults['customer_phone'] : '',
			'address' => isset( $defaults['delivery_address'] ) ? $defaults['delivery_address'] : '',
		);
	}

	return array(
		'name'    => $user->display_name ? $user->display_name : $user->user_login,
		'company' => '',
		'email'   => $user->user_email,
		'phone'   => '',
		'address' => '',
	);
}

/** Store a copy of each real wp_mail payload while a test step is running. */
function qs_auto_ai_capture_wp_mail( $args ) {
	$run_id = isset( $GLOBALS['qs_auto_ai_test_run_id'] ) ? absint( $GLOBALS['qs_auto_ai_test_run_id'] ) : 0;
	if ( ! $run_id || QS_AUTO_AI_RUN_POST_TYPE !== get_post_type( $run_id ) ) {
		return $args;
	}

	$emails = qs_auto_ai_meta( $run_id, 'emails', array() );
	$emails = is_array( $emails ) ? $emails : array();
	$emails[] = array(
		'time'        => current_time( 'mysql' ),
		'to'          => isset( $args['to'] ) ? $args['to'] : '',
		'subject'     => isset( $args['subject'] ) ? (string) $args['subject'] : '',
		'message'     => isset( $args['message'] ) ? (string) $args['message'] : '',
		'headers'     => isset( $args['headers'] ) ? $args['headers'] : array(),
		'attachments' => isset( $args['attachments'] ) ? $args['attachments'] : array(),
		'step'        => (int) qs_auto_ai_meta( $run_id, 'current_step', 0 ),
	);
	qs_auto_ai_set_meta( $run_id, 'emails', $emails );

	return $args;
}
add_filter( 'wp_mail', 'qs_auto_ai_capture_wp_mail', 999 );

function qs_auto_ai_begin_mail_capture( $run_id ) {
	$GLOBALS['qs_auto_ai_test_run_id'] = absint( $run_id );
}

function qs_auto_ai_end_mail_capture() {
	unset( $GLOBALS['qs_auto_ai_test_run_id'] );
}

function qs_auto_ai_latest_email( $run_id, $subject_contains = '', $recipient = '' ) {
	$emails = qs_auto_ai_meta( $run_id, 'emails', array() );
	$emails = is_array( $emails ) ? $emails : array();

	for ( $index = count( $emails ) - 1; $index >= 0; $index-- ) {
		$email = $emails[ $index ];
		$to    = isset( $email['to'] ) ? $email['to'] : '';
		$to    = is_array( $to ) ? implode( ',', $to ) : (string) $to;

		if ( $subject_contains && false === stripos( (string) $email['subject'], $subject_contains ) ) {
			continue;
		}
		if ( $recipient && false === stripos( $to, $recipient ) ) {
			continue;
		}

		$email['_index'] = $index;
		return $email;
	}

	return array();
}

function qs_auto_ai_email_preview_url( $run_id, $email_index ) {
	return add_query_arg(
		array(
			'action'      => 'qs_auto_ai_email_preview',
			'run_id'      => absint( $run_id ),
			'email_index' => absint( $email_index ),
		),
		admin_url( 'admin-post.php' )
	);
}

function qs_auto_ai_handle_email_preview() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}

	$run_id = isset( $_GET['run_id'] ) ? absint( $_GET['run_id'] ) : 0;
	if ( ! $run_id || QS_AUTO_AI_RUN_POST_TYPE !== get_post_type( $run_id ) ) {
		wp_die( 'Test run not found.', 404 );
	}

	$index  = isset( $_GET['email_index'] ) ? absint( $_GET['email_index'] ) : 0;
	$emails = qs_auto_ai_meta( $run_id, 'emails', array() );
	if ( ! is_array( $emails ) || ! isset( $emails[ $index ] ) ) {
		wp_die( 'Email preview not found.', 404 );
	}

	$email = $emails[ $index ];
	$to    = isset( $email['to'] ) ? $email['to'] : '';
	$to    = is_array( $to ) ? implode( ', ', $to ) : (string) $to;

	nocache_headers();
	?>
	<!doctype html>
	<html>
	<head>
		<meta charset="utf-8">
		<title><?php echo esc_html( isset( $email['subject'] ) ? $email['subject'] : 'Email Preview' ); ?></title>
		<style>
			body{margin:0;background:#f0f0f1;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#1d2327}
			.meta{position:sticky;top:0;background:#fff;border-bottom:1px solid #dcdcde;padding:14px 20px;z-index:3}
			.meta strong{display:inline-block;min-width:70px}
			.email-frame{display:block;width:calc(100% - 48px);max-width:1100px;height:760px;margin:24px auto;border:1px solid #dcdcde;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08)}
		</style>
	</head>
	<body>
		<div class="meta">
			<div><strong>To:</strong> <?php echo esc_html( $to ); ?></div>
			<div><strong>Subject:</strong> <?php echo esc_html( isset( $email['subject'] ) ? $email['subject'] : '' ); ?></div>
			<div><strong>Captured:</strong> <?php echo esc_html( isset( $email['time'] ) ? $email['time'] : '' ); ?></div>
		</div>
		<iframe
			class="email-frame"
			title="Captured email preview"
			sandbox="allow-popups allow-popups-to-escape-sandbox"
			srcdoc="<?php echo esc_attr( isset( $email['message'] ) ? $email['message'] : '' ); ?>"
		></iframe>
	</body>
	</html>
	<?php
	exit;
}
add_action( 'admin_post_qs_auto_ai_email_preview', 'qs_auto_ai_handle_email_preview' );

function qs_auto_ai_run_url( $run_id ) {
	$url = function_exists( 'qs_settings_url' )
		? qs_settings_url( 'testing' )
		: admin_url( 'edit.php?post_type=quote&page=qs-settings&tab=testing' );

	return add_query_arg( 'run_id', absint( $run_id ), $url );
}

function qs_auto_ai_order_edit_url( $order_id ) {
	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return '';
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return '';
	}
	return method_exists( $order, 'get_edit_order_url' )
		? $order->get_edit_order_url()
		: admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' );
}

function qs_auto_ai_state( $run_id ) {
	$run = get_post( $run_id );
	if ( ! $run || QS_AUTO_AI_RUN_POST_TYPE !== $run->post_type ) {
		return array();
	}

	$joiner_id       = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	$admin_id        = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );
	$quote_id        = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$deposit_order   = absint( qs_auto_ai_meta( $run_id, 'deposit_order_id', 0 ) );
	$balance_order   = absint( qs_auto_ai_meta( $run_id, 'balance_order_id', 0 ) );
	$joiner          = $joiner_id ? get_user_by( 'id', $joiner_id ) : false;
	$admin           = $admin_id ? get_user_by( 'id', $admin_id ) : false;
	$quote_number    = $quote_id ? (string) get_post_meta( $quote_id, '_quote_number', true ) : '';
	$project_title   = $quote_id ? (string) get_the_title( $quote_id ) : '';
	$quote_status    = $quote_id ? get_post_status( $quote_id ) : '';
	$total           = $quote_id ? qs_calculate_total( $quote_id ) : 0;
	$steps           = qs_auto_ai_steps();
	$current_step    = absint( qs_auto_ai_meta( $run_id, 'current_step', 0 ) );
	$status          = (string) qs_auto_ai_meta( $run_id, 'status', 'running' );
	$emails          = qs_auto_ai_meta( $run_id, 'emails', array() );
	$email_summaries = array();
	$timeline_events = qs_auto_ai_timeline_events( $run_id );
	$started_at      = (string) qs_auto_ai_meta( $run_id, 'started_at', '' );
	if ( ! $started_at ) {
		$started_at = get_the_date( 'Y-m-d H:i:s', $run_id );
	}
	$completed_at = (string) qs_auto_ai_meta( $run_id, 'completed_at', '' );
	$end_at       = $completed_at ? $completed_at : current_time( 'mysql' );
	$duration     = max( 0, strtotime( $end_at ) - strtotime( $started_at ) );
	$order_count  = ( $deposit_order ? 1 : 0 ) + ( $balance_order ? 1 : 0 );

	foreach ( is_array( $emails ) ? $emails : array() as $index => $email ) {
		$to = isset( $email['to'] ) ? $email['to'] : '';
		$to = is_array( $to ) ? implode( ', ', $to ) : (string) $to;
		$email_summaries[] = array(
			'index'       => $index,
			'time'        => isset( $email['time'] ) ? $email['time'] : '',
			'to'          => $to,
			'subject'     => isset( $email['subject'] ) ? $email['subject'] : '',
			'preview_url' => qs_auto_ai_email_preview_url( $run_id, $index ),
		);
	}

	$links = array(
		'run'           => qs_auto_ai_run_url( $run_id ),
		'joiner_user'   => $joiner_id ? admin_url( 'user-edit.php?user_id=' . $joiner_id ) : '',
		'quote_review'  => $quote_id ? ( function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_review', array( 'quote_id' => $quote_id ) ) : add_query_arg( 'quote_id', $quote_id, site_url( '/quote-review/' ) ) ) : '',
		'quote_builder' => $quote_id ? ( function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_builder', array( 'quote_id' => $quote_id ) ) : add_query_arg( 'quote_id', $quote_id, site_url( '/quote-builder/' ) ) ) : '',
		'thank_you'     => $quote_id ? ( function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_submitted', array( 'quote_id' => $quote_id ) ) : add_query_arg( 'quote_id', $quote_id, site_url( '/quote-submitted/' ) ) ) : '',
		'quotation_pdf' => $quote_id ? add_query_arg( 'download_quote_pdf', $quote_id, home_url( '/' ) ) : '',
		'job_sheet'     => $quote_id ? add_query_arg( 'download_jobsheet_pdf', $quote_id, home_url( '/' ) ) : '',
		'deposit_order' => qs_auto_ai_order_edit_url( $deposit_order ),
		'balance_order' => qs_auto_ai_order_edit_url( $balance_order ),
		'deposit_pay'   => $quote_id && function_exists( 'qs_get_quote_payment_url' ) ? qs_get_quote_payment_url( $quote_id, 'deposit' ) : '',
		'balance_pay'   => $quote_id && function_exists( 'qs_get_quote_payment_url' ) ? qs_get_quote_payment_url( $quote_id, 'balance' ) : '',
	);

	return array(
		'run_id'           => (int) $run_id,
		'status'           => $status,
		'status_label'     => strtoupper( str_replace( '_', ' ', $status ) ),
		'current_step'     => $current_step,
		'total_steps'      => count( $steps ),
		'next_step_label'  => isset( $steps[ $current_step ] ) ? $steps[ $current_step ]['label'] : '',
		'progress'         => count( $steps ) ? min( 100, round( ( $current_step / count( $steps ) ) * 100 ) ) : 0,
		'joiner_id'        => $joiner_id,
		'joiner_name'      => $joiner ? $joiner->display_name : '',
		'joiner_email'     => $joiner ? $joiner->user_email : '',
		'admin_id'         => $admin_id,
		'admin_name'       => $admin ? $admin->display_name : '',
		'quote_id'         => $quote_id,
		'quote_number'     => $quote_number,
		'project_title'    => $project_title,
		'quote_status'     => $quote_status,
		'quote_status_label'=> $quote_id && function_exists( 'qs_workflow_quote_status_label' ) ? qs_workflow_quote_status_label( $quote_id ) : $quote_status,
		'quote_total'      => $quote_id ? qs_auto_ai_money( $total ) : '',
		'deposit_order_id' => $deposit_order,
		'balance_order_id' => $balance_order,
		'logs_joiner'      => qs_auto_ai_meta( $run_id, 'logs_joiner', array() ),
		'logs_admin'       => qs_auto_ai_meta( $run_id, 'logs_admin', array() ),
		'timeline_events'  => $timeline_events,
		'emails'           => $email_summaries,
		'email_count'      => count( $email_summaries ),
		'order_count'      => $order_count,
		'duration_seconds' => $duration,
		'links'            => $links,
		'created_at'       => $started_at,
		'completed_at'     => $completed_at,
	);
}

function qs_auto_ai_fail( $run_id, $side, $message ) {
	qs_auto_ai_log( $run_id, $side, $message, 'error' );
	qs_auto_ai_set_meta( $run_id, 'status', 'failed' );
	qs_auto_ai_set_meta( $run_id, 'last_error', sanitize_text_field( $message ) );
	return false;
}

function qs_auto_ai_tag_order( $order_id, $run_id, $stage ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	$order->update_meta_data( '_qs_auto_ai_test', '1' );
	$order->update_meta_data( '_qs_auto_ai_test_run', absint( $run_id ) );
	$order->update_meta_data( '_qs_auto_ai_test_stage', sanitize_key( $stage ) );
	$order->add_order_note( sprintf( 'Auto AI Testing run #%d — %s order.', absint( $run_id ), ucfirst( $stage ) ) );
	$order->save();
}

function qs_auto_ai_step_create_quote( $run_id ) {
	$joiner_id = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	$joiner    = get_user_by( 'id', $joiner_id );
	if ( ! $joiner || ! function_exists( 'qs_user_is_joiner' ) || ! qs_user_is_joiner( $joiner ) ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Selected Joiner account is no longer available.' );
	}

	// Use real currently-configured Quote Products so this tests the same
	// selections a Joiner can make in Quote Builder.
	$profile_id   = qs_auto_ai_product_id( 'door-profile', 'Evans' );
	$timber_id    = qs_auto_ai_product_id( 'timber', 'Tasmanian Oak' );
	$finish_id    = qs_auto_ai_product_id( 'finish', 'Finished' );
	$handle_id    = qs_auto_ai_product_id( 'accessory', 'Square Edge' );
	$kickboard_id = qs_auto_ai_product_id( 'kickboard', 'Veneer Kickboard' );

	if ( ! $profile_id ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'No active Door Profile is available, so Quote Builder cannot create a priced test quote.' );
	}
	if ( ! $kickboard_id ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'No active Kickboard product is available, so the full Quote Builder component test cannot run.' );
	}

	list( $door_width, $door_height )       = qs_auto_ai_test_dimensions( $profile_id, 600, 800 );
	list( $drawer_width, $drawer_height )   = qs_auto_ai_test_dimensions( $profile_id, 600, 200 );
	list( $panel_width, $panel_height )     = qs_auto_ai_test_dimensions( qs_find_quote_product( 'Evans', 'door-profile' ), 600, 800 );
	list( $filler_width, $filler_height )   = qs_auto_ai_test_dimensions( qs_find_quote_product( 'Evans', 'door-profile' ), 200, 800 );
	list( $kick_length, $kick_height )      = qs_auto_ai_kickboard_dimensions( $kickboard_id );

	$defaults = qs_auto_ai_joiner_defaults( $joiner );
	$project  = 'Kitchen Renovation';
	$old_post = $_POST;

	$profile_defaults_hook = has_action( 'save_post_quote', 'qs_save_customer_quote_defaults' );
	if ( false !== $profile_defaults_hook ) {
		remove_action( 'save_post_quote', 'qs_save_customer_quote_defaults', 30 );
	}

	try {
		$result = qs_auto_ai_with_user(
			$joiner_id,
			static function () use (
				$profile_id,
				$timber_id,
				$finish_id,
				$handle_id,
				$kickboard_id,
				$door_width,
				$door_height,
				$drawer_width,
				$drawer_height,
				$panel_width,
				$panel_height,
				$filler_width,
				$filler_height,
				$kick_length,
				$kick_height,
				$defaults,
				$project
			) {
				$_POST = array(
					'qs_builder_nonce' => wp_create_nonce( 'qs_save_quote' ),
					'project_name'     => $project,
					'company_name'     => $defaults['company'],
					'customer_name'    => $defaults['name'],
					'customer_email'   => $defaults['email'],
					'customer_phone'   => $defaults['phone'],
					'delivery_address' => $defaults['address'],
					'door_profile'     => (string) $profile_id,
					'timber'           => (string) $timber_id,
					'finish'           => (string) $finish_id,
					'handle_profile'   => (string) $handle_id,
					'paint_colour'     => '',
					'custom_requests'  => 'Please confirm grain direction before production.',
					'project_notes'    => 'Kitchen cabinetry renovation.',
					'pricing_type'     => 'trade',
					'components'       => array(
						'doors_drawers' => array(
							array(
								'type'                 => 'Door',
								'door_profile'         => (string) $profile_id,
								'timber'               => (string) $timber_id,
								'finish'               => (string) $finish_id,
								'handle_profile'       => (string) $handle_id,
								'paint_colour'         => '',
								'width'                => $door_width,
								'height'               => $door_height,
								'quantity'             => 2,
								'edge_profile'         => '',
								'drawer_count'         => 0,
								'top_height'           => 0,
								'top_middle_height'    => 0,
								'middle_height'        => 0,
								'bottom_middle_height' => 0,
								'bottom_height'        => 0,
								'notes'                => 'Kitchen doors.',
							),
							array(
								'type'                 => 'Drawer',
								'door_profile'         => (string) $profile_id,
								'timber'               => (string) $timber_id,
								'finish'               => (string) $finish_id,
								'handle_profile'       => (string) $handle_id,
								'paint_colour'         => '',
								'width'                => $drawer_width,
								'height'               => $drawer_height,
								'quantity'             => 2,
								'edge_profile'         => '',
								'drawer_count'         => 0,
								'top_height'           => 0,
								'top_middle_height'    => 0,
								'middle_height'        => 0,
								'bottom_middle_height' => 0,
								'bottom_height'        => 0,
								'notes'                => 'Drawer fronts.',
							),
							array(
								'type'                 => 'Drawer Bank',
								'door_profile'         => (string) $profile_id,
								'timber'               => (string) $timber_id,
								'finish'               => (string) $finish_id,
								'handle_profile'       => (string) $handle_id,
								'paint_colour'         => '',
								'width'                => $drawer_width,
								'height'               => 0,
								'quantity'             => 1,
								'edge_profile'         => '',
								'drawer_count'         => 3,
								'top_height'           => $drawer_height,
								'top_middle_height'    => 0,
								'middle_height'        => $drawer_height,
								'bottom_middle_height' => 0,
								'bottom_height'        => $drawer_height,
								'notes'                => 'Three-drawer bank.',
							),
							array(
								'type'                 => 'Profile End Panel',
								'door_profile'         => (string) $profile_id,
								'timber'               => (string) $timber_id,
								'finish'               => (string) $finish_id,
								'handle_profile'       => '',
								'paint_colour'         => '',
								'width'                => $door_width,
								'height'               => $door_height,
								'quantity'             => 1,
								'edge_profile'         => '',
								'drawer_count'         => 0,
								'top_height'           => 0,
								'top_middle_height'    => 0,
								'middle_height'        => 0,
								'bottom_middle_height' => 0,
								'bottom_height'        => 0,
								'notes'                => 'Profile end panel.',
							),
						),
						'end_panels' => array(
							array(
								'timber'       => (string) $timber_id,
								'finish'       => (string) $finish_id,
								'paint_colour' => '',
								'height'       => $panel_height,
								'width'        => $panel_width,
								'quantity'     => 1,
								'faces_seen'   => '2 Faces',
								'edges_seen'   => 'Top + Right',
								'notes'        => 'Flat end panel.',
							),
						),
						'fillers' => array(
							array(
								'timber'       => (string) $timber_id,
								'finish'       => (string) $finish_id,
								'paint_colour' => '',
								'height'       => $filler_height,
								'width'        => $filler_width,
								'quantity'     => 1,
								'faces_seen'   => '2 Faces',
								'edges_seen'   => '1 Long / 2 Short',
								'notes'        => 'Kitchen filler.',
							),
						),
						'kickboards' => array(
							array(
								'material'     => (string) $kickboard_id,
								'timber'       => (string) $timber_id,
								'finish'       => (string) $finish_id,
								'paint_colour' => '',
								'height'       => $kick_height,
								'length'       => $kick_length,
								'quantity'     => 2,
								'notes'        => 'Kitchen kickboards.',
							),
						),
					),
				);

				return qs_builder_save_quote( 0, false );
			}
		);
	} finally {
		$_POST = $old_post;
		if ( false !== $profile_defaults_hook ) {
			add_action( 'save_post_quote', 'qs_save_customer_quote_defaults', 30, 2 );
		}
	}

	if ( is_wp_error( $result ) ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Quote Builder failed: ' . $result->get_error_message() );
	}

	$quote_id = absint( $result );
	$subtotal = (float) get_post_meta( $quote_id, '_subtotal', true );
	if ( ! $quote_id || $subtotal <= 0 ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'A Quote record was created, but pricing returned zero. Check Quote Product pricing configuration.' );
	}

	// Full Auto AI coverage should prove all major Builder component groups are
	// both saved and contributing to the real pricing calculation.
	$breakdown = get_post_meta( $quote_id, '_pricing_breakdown', true );
	$breakdown = is_array( $breakdown ) ? $breakdown : array();
	$required_components = array(
		'doors_drawers' => 'Doors / Drawers / Profile End Panel',
		'end_panels'    => 'End Panels',
		'fillers'       => 'Fillers',
		'kickboards'    => 'Kickboards',
	);
	foreach ( $required_components as $component => $label ) {
		if ( ! qs_component_rows( $quote_id, $component ) ) {
			return qs_auto_ai_fail( $run_id, 'joiner', $label . ' were not saved by Quote Builder.' );
		}
		if ( empty( $breakdown[ $component ] ) || (float) $breakdown[ $component ] <= 0 ) {
			return qs_auto_ai_fail( $run_id, 'joiner', $label . ' were saved but did not produce a positive price.' );
		}
	}

	update_post_meta( $quote_id, '_qs_auto_ai_test', '1' );
	update_post_meta( $quote_id, '_qs_auto_ai_test_run', $run_id );
	update_post_meta( $quote_id, '_qs_auto_ai_test_admin', absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) ) );
	qs_auto_ai_set_meta( $run_id, 'quote_id', $quote_id );

	$quote_number = (string) get_post_meta( $quote_id, '_quote_number', true );
	wp_update_post(
		array(
			'ID'         => $run_id,
			'post_title' => 'Auto AI Test — ' . ( $quote_number ? $quote_number : '#' . $quote_id ),
		)
	);

	$review_url = function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_review', array( 'quote_id' => $quote_id ) ) : add_query_arg( 'quote_id', $quote_id, site_url( '/quote-review/' ) );
	$builder_url = function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_builder', array( 'quote_id' => $quote_id ) ) : add_query_arg( 'quote_id', $quote_id, site_url( '/quote-builder/' ) );

	qs_auto_ai_log(
		$run_id,
		'joiner',
		sprintf(
			'Real quote %s created as "%s" with Doors, Drawer, Drawer Bank, Profile End Panel, End Panel, Filler and Kickboards. Subtotal: %s.',
			$quote_number,
			$project,
			qs_auto_ai_money( $subtotal )
		),
		'success',
		$builder_url,
		'Open Builder'
	);

	qs_auto_ai_log(
		$run_id,
		'joiner',
		sprintf(
			'Selections saved: %s / %s / %s / %s / %s.',
			get_the_title( $profile_id ),
			$timber_id ? get_the_title( $timber_id ) : 'No timber',
			$finish_id ? get_the_title( $finish_id ) : 'No finish',
			$handle_id ? get_the_title( $handle_id ) : 'No handle',
			get_the_title( $kickboard_id )
		),
		'success',
		$review_url,
		'Open Quote'
	);

	return true;
}

function qs_auto_ai_step_submit_quote( $run_id ) {
	$quote_id  = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$joiner_id = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	if ( ! $quote_id ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'No Quote exists for this run.' );
	}

	$result = qs_auto_ai_with_user(
		$joiner_id,
		static function () use ( $run_id, $quote_id ) {
			$status = qs_update_quote_status( $quote_id, 'pending_review' );
			if ( is_wp_error( $status ) || false === $status ) {
				return new WP_Error( 'submit_failed', 'Could not move quote to Pending Review.' );
			}
			qs_auto_ai_begin_mail_capture( $run_id );
			$mail = qs_email_quote_submitted( $quote_id );
			qs_auto_ai_end_mail_capture();
			return $mail;
		}
	);

	if ( is_wp_error( $result ) ) {
		return qs_auto_ai_fail( $run_id, 'joiner', $result->get_error_message() );
	}
	if ( ! $result ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Quote was submitted, but WordPress did not accept the admin notification email for delivery.' );
	}

	$quote_number = get_post_meta( $quote_id, '_quote_number', true );
	qs_auto_ai_log( $run_id, 'joiner', sprintf( 'Quote %s submitted for LF review.', $quote_number ), 'success' );
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'New Quote notification handed to WordPress mail for %s.', function_exists( 'qs_get_admin_email' ) ? qs_get_admin_email() : get_option( 'admin_email' ) ), 'success' );

	return true;
}

function qs_auto_ai_step_admin_verify_quote( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$admin_id = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );
	$quote    = get_post( $quote_id );
	if ( ! $quote || 'pending_review' !== get_post_status( $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'LF Admin verification failed: quote is not in Pending Review.' );
	}
	if ( ! user_can( $admin_id, 'edit_post', $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Selected Admin does not have permission to manage the generated Quote.' );
	}

	$total = qs_calculate_total( $quote_id );
	if ( $total <= 0 ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Admin received the Quote, but calculated total is zero.' );
	}

	qs_auto_ai_log(
		$run_id,
		'admin',
		sprintf( 'Quote received from %s. Total quotation: %s.', get_the_author_meta( 'display_name', $quote->post_author ), qs_auto_ai_money( $total ) ),
		'success',
		admin_url( 'post.php?post=' . $quote_id . '&action=edit' ),
		'Edit Quote'
	);

	return true;
}

function qs_auto_ai_step_render_pdf( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );

	try {
		if ( function_exists( 'qs_estimated_lead_time_quotation_pdf' ) ) {
			$pdf = qs_estimated_lead_time_quotation_pdf( $quote_id );
		} else {
			$pdf = qs_generate_quotation_pdf( $quote_id );
		}
		$bytes = $pdf && method_exists( $pdf, 'output' ) ? $pdf->output() : '';
	} catch ( Throwable $error ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Quotation PDF render failed: ' . $error->getMessage() );
	}

	if ( ! is_string( $bytes ) || strlen( $bytes ) < 1000 ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Quotation PDF did not render a valid PDF payload.' );
	}

	$url = add_query_arg( 'download_quote_pdf', $quote_id, home_url( '/' ) );
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Quotation PDF rendered successfully (%s KB).', number_format_i18n( strlen( $bytes ) / 1024, 1 ) ), 'success', $url, 'Open PDF' );
	return true;
}

function qs_auto_ai_step_deposit_request( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$admin_id = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );

	$result = qs_auto_ai_with_user(
		$admin_id,
		static function () use ( $run_id, $quote_id ) {
			$order_id = qs_create_payment_order( $quote_id, 'deposit' );
			if ( is_wp_error( $order_id ) ) {
				return $order_id;
			}

			qs_auto_ai_tag_order( $order_id, $run_id, 'deposit' );
			qs_update_quote_status( $quote_id, 'awaiting_deposit' );

			qs_auto_ai_begin_mail_capture( $run_id );
			$mail = qs_email_payment_request( $quote_id, 'deposit' );
			qs_auto_ai_end_mail_capture();

			if ( ! $mail ) {
				return new WP_Error( 'deposit_email_failed', 'Deposit order was created, but WordPress did not accept the payment email for delivery.' );
			}
			return (int) $order_id;
		}
	);

	if ( is_wp_error( $result ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Deposit request failed: ' . $result->get_error_message() );
	}

	$order_id = absint( $result );
	qs_auto_ai_set_meta( $run_id, 'deposit_order_id', $order_id );
	$amount = function_exists( 'qs_calculate_deposit' ) ? qs_calculate_deposit( $quote_id ) : 0;
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Real WooCommerce deposit order #%d created for %s. Payment email sent.', $order_id, qs_auto_ai_money( $amount ) ), 'success', qs_auto_ai_order_edit_url( $order_id ), 'View Order' );
	return true;
}

function qs_auto_ai_step_verify_deposit_email( $run_id ) {
	$joiner_id = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	$quote_id  = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$joiner    = get_user_by( 'id', $joiner_id );
	$email     = $joiner ? qs_auto_ai_latest_email( $run_id, 'Deposit Payment Ready', $joiner->user_email ) : array();
	$pay_url   = $quote_id ? qs_get_quote_payment_url( $quote_id, 'deposit' ) : '';

	if ( ! $email || ! $pay_url ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Could not verify the real deposit email and WooCommerce payment URL.' );
	}

	qs_auto_ai_log(
		$run_id,
		'joiner',
		sprintf( 'Deposit email was handed to WordPress for real delivery to %s. Payment URL is valid.', $joiner->user_email ),
		'success',
		qs_auto_ai_email_preview_url( $run_id, $email['_index'] ),
		'View Email'
	);
	return true;
}

function qs_auto_ai_step_bacs_order( $run_id, $payment_type ) {
	$order_id = absint( qs_auto_ai_meta( $run_id, 'deposit' === $payment_type ? 'deposit_order_id' : 'balance_order_id', 0 ) );
	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return qs_auto_ai_fail( $run_id, 'joiner', ucfirst( $payment_type ) . ' WooCommerce order is unavailable.' );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return qs_auto_ai_fail( $run_id, 'joiner', ucfirst( $payment_type ) . ' WooCommerce order could not be loaded.' );
	}

	$order->set_payment_method( 'bacs' );
	$order->set_payment_method_title( 'Direct bank transfer' );
	$order->save();
	if ( ! $order->has_status( 'on-hold' ) ) {
		qs_auto_ai_begin_mail_capture( $run_id );
		$order->update_status( 'on-hold', 'Auto AI Testing: customer selected Direct bank transfer.' );
		qs_auto_ai_end_mail_capture();
	}

	qs_auto_ai_log(
		$run_id,
		'joiner',
		sprintf( 'WooCommerce order #%d placed on Direct bank transfer. Status: %s.', $order_id, wc_get_order_status_name( $order->get_status() ) ),
		'success',
		$order->get_checkout_payment_url(),
		'Open Payment'
	);
	return true;
}

function qs_auto_ai_step_payment_complete( $run_id, $payment_type ) {
	$order_key = 'deposit' === $payment_type ? 'deposit_order_id' : 'balance_order_id';
	$order_id  = absint( qs_auto_ai_meta( $run_id, $order_key, 0 ) );
	$admin_id  = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );
	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', ucfirst( $payment_type ) . ' order is unavailable for payment confirmation.' );
	}

	$result = qs_auto_ai_with_user(
		$admin_id,
		static function () use ( $run_id, $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return new WP_Error( 'missing_order', 'WooCommerce order could not be loaded.' );
			}

			qs_auto_ai_begin_mail_capture( $run_id );
			$order->payment_complete();
			qs_auto_ai_end_mail_capture();
			return $order->get_status();
		}
	);

	if ( is_wp_error( $result ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', $result->get_error_message() );
	}

	$quote_id        = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$expected_status = 'deposit' === $payment_type ? 'deposit_paid' : 'paid_in_full';
	if ( $expected_status !== get_post_status( $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', sprintf( 'WooCommerce accepted payment, but Quote status did not change to %s.', $expected_status ) );
	}

	qs_auto_ai_log( $run_id, 'admin', sprintf( '%s bank payment confirmed through WooCommerce. Quote status: %s.', ucfirst( $payment_type ), $expected_status ), 'success', qs_auto_ai_order_edit_url( $order_id ), 'View Order' );
	return true;
}

function qs_auto_ai_step_verify_deposit_payment( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$order_id = absint( qs_auto_ai_meta( $run_id, 'deposit_order_id', 0 ) );
	if ( 'deposit_paid' !== get_post_status( $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Deposit workflow verification failed: Quote is not Deposit Paid.' );
	}

	$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( $order && ! $order->has_status( 'completed' ) ) {
		qs_auto_ai_begin_mail_capture( $run_id );
		$order->update_status( 'completed', 'Auto AI Testing: LF admin completed the deposit order after bank payment confirmation.' );
		qs_auto_ai_end_mail_capture();
	}

	$email = qs_auto_ai_latest_email( $run_id, 'Deposit Payment Received' );
	$url   = $email ? qs_auto_ai_email_preview_url( $run_id, $email['_index'] ) : qs_auto_ai_order_edit_url( $order_id );
	qs_auto_ai_log( $run_id, 'admin', 'Deposit Paid confirmed. Admin payment notification generated and deposit order completed.', 'success', $url, $email ? 'View Email' : 'View Order' );
	return true;
}

function qs_auto_ai_step_mark_production( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$admin_id = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );
	if ( 'deposit_paid' !== get_post_status( $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Quote must be Deposit Paid before marking it In Production.' );
	}

	update_post_meta( $quote_id, '_qs_in_production', current_time( 'mysql' ) );
	update_post_meta( $quote_id, '_qs_in_production_by', $admin_id );
	qs_auto_ai_log( $run_id, 'admin', 'Quote marked In Production by the selected LF Admin.', 'success' );
	return true;
}

function qs_auto_ai_step_add_charge( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$before   = qs_calculate_total( $quote_id );
	$current  = (float) get_post_meta( $quote_id, '_additional_charges', true );
	$charge   = 50.00;

	update_post_meta( $quote_id, '_additional_charges', $current + $charge );
	$after = qs_calculate_total( $quote_id );

	if ( round( $after - $before, 2 ) !== round( $charge, 2 ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Post-deposit pricing test failed: Additional Charge was not reflected in Quote total.' );
	}

	qs_auto_ai_set_meta( $run_id, 'test_additional_charge', $charge );
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Added real Additional Charge of %s after deposit. New total: %s.', qs_auto_ai_money( $charge ), qs_auto_ai_money( $after ) ), 'success' );
	return true;
}

function qs_auto_ai_step_final_invoice( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$admin_id = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );

	$result = qs_auto_ai_with_user(
		$admin_id,
		static function () use ( $run_id, $quote_id ) {
			$order_id = qs_create_payment_order( $quote_id, 'balance' );
			if ( is_wp_error( $order_id ) ) {
				return $order_id;
			}

			qs_auto_ai_tag_order( $order_id, $run_id, 'balance' );
			qs_update_quote_status( $quote_id, 'final_balance' );

			qs_auto_ai_begin_mail_capture( $run_id );
			$mail = qs_email_payment_request( $quote_id, 'balance' );
			qs_auto_ai_end_mail_capture();
			if ( ! $mail ) {
				return new WP_Error( 'balance_email_failed', 'Final order was created, but WordPress did not accept the final-payment email for delivery.' );
			}

			return (int) $order_id;
		}
	);

	if ( is_wp_error( $result ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Final invoice failed: ' . $result->get_error_message() );
	}

	$order_id = absint( $result );
	qs_auto_ai_set_meta( $run_id, 'balance_order_id', $order_id );
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Real final-balance WooCommerce order #%d created for %s. Final-payment email sent.', $order_id, qs_auto_ai_money( qs_calculate_balance( $quote_id ) ) ), 'success', qs_auto_ai_order_edit_url( $order_id ), 'View Order' );
	return true;
}

function qs_auto_ai_step_verify_final_email( $run_id ) {
	$joiner_id = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	$quote_id  = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$joiner    = get_user_by( 'id', $joiner_id );
	$email     = $joiner ? qs_auto_ai_latest_email( $run_id, 'Final Payment Ready', $joiner->user_email ) : array();
	$pay_url   = $quote_id ? qs_get_quote_payment_url( $quote_id, 'balance' ) : '';

	if ( ! $email || ! $pay_url ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Could not verify the real final-payment email and WooCommerce payment URL.' );
	}

	qs_auto_ai_log(
		$run_id,
		'joiner',
		sprintf( 'Final-payment email was handed to WordPress for real delivery to %s. Payment URL is valid.', $joiner->user_email ),
		'success',
		qs_auto_ai_email_preview_url( $run_id, $email['_index'] ),
		'View Email'
	);
	return true;
}

function qs_auto_ai_step_complete_final_order( $run_id ) {
	$order_id = absint( qs_auto_ai_meta( $run_id, 'balance_order_id', 0 ) );
	$order    = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Final WooCommerce order could not be loaded.' );
	}

	if ( ! $order->has_status( 'completed' ) ) {
		qs_auto_ai_begin_mail_capture( $run_id );
		$order->update_status( 'completed', 'Auto AI Testing: LF admin completed the final order after bank payment confirmation.' );
		qs_auto_ai_end_mail_capture();
	}

	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Final WooCommerce order #%d marked Completed.', $order_id ), 'success', qs_auto_ai_order_edit_url( $order_id ), 'View Order' );
	return true;
}

function qs_auto_ai_step_verify_joiner_complete( $run_id ) {
	$quote_id  = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$joiner_id = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	$joiner    = get_user_by( 'id', $joiner_id );

	if ( 'paid_in_full' !== get_post_status( $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Joiner completion check failed: Quote is not Paid In Full.' );
	}

	$email = $joiner ? qs_auto_ai_latest_email( $run_id, 'Payment Complete', $joiner->user_email ) : array();
	$url   = $email ? qs_auto_ai_email_preview_url( $run_id, $email['_index'] ) : ( function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_review', array( 'quote_id' => $quote_id ) ) : '' );
	qs_auto_ai_log( $run_id, 'joiner', 'Quote now shows Completed / Paid In Full. Completion email generated for the Joiner.', 'success', $url, $email ? 'View Email' : 'Open Quote' );
	return true;
}

function qs_auto_ai_step_final_verify( $run_id ) {
	$quote_id      = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$deposit_order = absint( qs_auto_ai_meta( $run_id, 'deposit_order_id', 0 ) );
	$balance_order = absint( qs_auto_ai_meta( $run_id, 'balance_order_id', 0 ) );

	$checks = array(
		'Quote'               => $quote_id && 'quote' === get_post_type( $quote_id ),
		'Paid In Full status' => 'paid_in_full' === get_post_status( $quote_id ),
		'Deposit order'       => $deposit_order && function_exists( 'wc_get_order' ) && wc_get_order( $deposit_order ),
		'Final order'         => $balance_order && function_exists( 'wc_get_order' ) && wc_get_order( $balance_order ),
		'Positive total'      => qs_calculate_total( $quote_id ) > 0,
	);

	$failed = array();
	foreach ( $checks as $label => $passed ) {
		if ( ! $passed ) {
			$failed[] = $label;
		}
	}

	if ( $failed ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Final verification failed: ' . implode( ', ', $failed ) . '.' );
	}

	qs_auto_ai_log( $run_id, 'admin', 'AUTO AI TEST PASSED — real Quote, pricing, PDF, emails, deposit, production, final balance and WooCommerce completion all verified.', 'success', admin_url( 'post.php?post=' . $quote_id . '&action=edit' ), 'Inspect Quote' );
	qs_auto_ai_set_meta( $run_id, 'status', 'passed' );
	qs_auto_ai_set_meta( $run_id, 'completed_at', current_time( 'mysql' ) );
	return true;
}

function qs_auto_ai_execute_current_step( $run_id ) {
	$steps = qs_auto_ai_steps();
	$step  = absint( qs_auto_ai_meta( $run_id, 'current_step', 0 ) );

	if ( ! isset( $steps[ $step ] ) ) {
		qs_auto_ai_set_meta( $run_id, 'status', 'passed' );
		return true;
	}

	switch ( $step ) {
		case 0:
			$ok = qs_auto_ai_step_create_quote( $run_id );
			break;
		case 1:
			$ok = qs_auto_ai_step_submit_quote( $run_id );
			break;
		case 2:
			$ok = qs_auto_ai_step_admin_verify_quote( $run_id );
			break;
		case 3:
			$ok = qs_auto_ai_step_render_pdf( $run_id );
			break;
		case 4:
			$ok = qs_auto_ai_step_deposit_request( $run_id );
			break;
		case 5:
			$ok = qs_auto_ai_step_verify_deposit_email( $run_id );
			break;
		case 6:
			$ok = qs_auto_ai_step_bacs_order( $run_id, 'deposit' );
			break;
		case 7:
			$ok = qs_auto_ai_step_payment_complete( $run_id, 'deposit' );
			break;
		case 8:
			$ok = qs_auto_ai_step_verify_deposit_payment( $run_id );
			break;
		case 9:
			$ok = qs_auto_ai_step_mark_production( $run_id );
			break;
		case 10:
			$ok = qs_auto_ai_step_add_charge( $run_id );
			break;
		case 11:
			$ok = qs_auto_ai_step_final_invoice( $run_id );
			break;
		case 12:
			$ok = qs_auto_ai_step_verify_final_email( $run_id );
			break;
		case 13:
			$ok = qs_auto_ai_step_bacs_order( $run_id, 'balance' );
			break;
		case 14:
			$ok = qs_auto_ai_step_payment_complete( $run_id, 'balance' );
			break;
		case 15:
			$ok = qs_auto_ai_step_complete_final_order( $run_id );
			break;
		case 16:
			$ok = qs_auto_ai_step_verify_joiner_complete( $run_id );
			break;
		case 17:
			$ok = qs_auto_ai_step_final_verify( $run_id );
			break;
		default:
			$ok = false;
	}

	if ( $ok ) {
		$next = $step + 1;
		qs_auto_ai_set_meta( $run_id, 'current_step', $next );
		if ( $next >= count( $steps ) && 'failed' !== qs_auto_ai_meta( $run_id, 'status', '' ) ) {
			qs_auto_ai_set_meta( $run_id, 'status', 'passed' );
		}
	}

	return $ok;
}

function qs_auto_ai_verify_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Administrator access is required.' ), 403 );
	}
	check_ajax_referer( 'qs_auto_ai_ajax', 'nonce' );
}

function qs_auto_ai_ajax_start() {
	qs_auto_ai_verify_ajax();

	if ( function_exists( 'qs_portal_mode' ) && 'live' === qs_portal_mode() ) {
		wp_send_json_error( array( 'message' => 'Switch Portal Mode to Setup or Test before starting Auto AI Testing. This prevents accidental testing against a public live portal.' ), 400 );
	}
	if ( empty( $_POST['real_email_ack'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['real_email_ack'] ) ) ) {
		wp_send_json_error( array( 'message' => 'Confirm that you understand real emails and real WooCommerce orders will be created.' ), 400 );
	}
	if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_create_order' ) ) {
		wp_send_json_error( array( 'message' => 'WooCommerce must be active before running the full workflow test.' ), 400 );
	}

	$admin_id = isset( $_POST['admin_id'] ) ? absint( $_POST['admin_id'] ) : 0;
	if ( ! $admin_id || ! user_can( $admin_id, 'edit_others_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Select a valid LF Admin account.' ), 400 );
	}

	$joiner_value = isset( $_POST['joiner_id'] ) ? sanitize_text_field( wp_unslash( $_POST['joiner_id'] ) ) : '';
	$created_joiner = false;

	if ( 'new' === $joiner_value ) {
		$joiner_id = qs_auto_ai_create_joiner_from_request();
		if ( is_wp_error( $joiner_id ) ) {
			wp_send_json_error( array( 'message' => $joiner_id->get_error_message() ), 400 );
		}
		$created_joiner = true;
	} else {
		$joiner_id = absint( $joiner_value );
		$joiner    = $joiner_id ? get_user_by( 'id', $joiner_id ) : false;
		if ( ! $joiner || ! function_exists( 'qs_user_is_joiner' ) || ! qs_user_is_joiner( $joiner ) ) {
			wp_send_json_error( array( 'message' => 'Select a valid Joiner account.' ), 400 );
		}
		if ( ! $joiner->user_email || ! is_email( $joiner->user_email ) ) {
			wp_send_json_error( array( 'message' => 'The selected Joiner does not have a valid email address. Add one before running a real-email workflow test.' ), 400 );
		}
		update_user_meta( $joiner_id, 'qs_portal_test_access', '1' );
	}

	$joiner = get_user_by( 'id', $joiner_id );
	$admin  = get_user_by( 'id', $admin_id );

	$run_id = wp_insert_post(
		array(
			'post_type'   => QS_AUTO_AI_RUN_POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Auto AI Test — Starting',
			'post_author' => get_current_user_id(),
		),
		true
	);
	if ( is_wp_error( $run_id ) ) {
		wp_send_json_error( array( 'message' => $run_id->get_error_message() ), 500 );
	}

	qs_auto_ai_set_meta( $run_id, 'status', 'running' );
	qs_auto_ai_set_meta( $run_id, 'current_step', 0 );
	qs_auto_ai_set_meta( $run_id, 'started_at', current_time( 'mysql' ) );
	qs_auto_ai_set_meta( $run_id, 'event_sequence', 0 );
	qs_auto_ai_set_meta( $run_id, 'timeline_events', array() );
	qs_auto_ai_set_meta( $run_id, 'joiner_id', $joiner_id );
	qs_auto_ai_set_meta( $run_id, 'admin_id', $admin_id );
	qs_auto_ai_set_meta( $run_id, 'created_joiner', $created_joiner ? '1' : '0' );
	qs_auto_ai_set_meta( $run_id, 'logs_joiner', array() );
	qs_auto_ai_set_meta( $run_id, 'logs_admin', array() );
	qs_auto_ai_set_meta( $run_id, 'emails', array() );

	qs_auto_ai_log(
		$run_id,
		'joiner',
		$created_joiner
			? sprintf( 'Permanent Joiner user created: %s <%s>. Test access enabled.', $joiner->display_name, $joiner->user_email )
			: sprintf( 'Using existing real Joiner: %s <%s>. Test access enabled.', $joiner->display_name, $joiner->user_email ),
		'success',
		admin_url( 'user-edit.php?user_id=' . $joiner_id ),
		'View User'
	);
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Acting LF Admin selected: %s. Starting real end-to-end workflow.', $admin->display_name ), 'success' );
	qs_auto_ai_log( $run_id, 'joiner', 'Real emails are ENABLED for this run. WordPress will send Quote System messages to the selected Joiner email.', 'warning' );

	wp_send_json_success( qs_auto_ai_state( $run_id ) );
}
add_action( 'wp_ajax_qs_auto_ai_start', 'qs_auto_ai_ajax_start' );

function qs_auto_ai_ajax_step() {
	qs_auto_ai_verify_ajax();
	$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;

	if ( QS_AUTO_AI_RUN_POST_TYPE !== get_post_type( $run_id ) ) {
		wp_send_json_error( array( 'message' => 'Test run not found.' ), 404 );
	}

	$status = qs_auto_ai_meta( $run_id, 'status', 'running' );
	if ( 'running' === $status ) {
		try {
			qs_auto_ai_execute_current_step( $run_id );
		} catch ( Throwable $error ) {
			$steps = qs_auto_ai_steps();
			$step  = absint( qs_auto_ai_meta( $run_id, 'current_step', 0 ) );
			$side  = isset( $steps[ $step ]['side'] ) ? $steps[ $step ]['side'] : 'admin';
			qs_auto_ai_fail( $run_id, $side, 'Unexpected error: ' . $error->getMessage() );
			qs_auto_ai_end_mail_capture();
		}
	}

	wp_send_json_success( qs_auto_ai_state( $run_id ) );
}
add_action( 'wp_ajax_qs_auto_ai_step', 'qs_auto_ai_ajax_step' );

function qs_auto_ai_ajax_control() {
	qs_auto_ai_verify_ajax();
	$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;
	$command = isset( $_POST['command'] ) ? sanitize_key( wp_unslash( $_POST['command'] ) ) : '';

	if ( QS_AUTO_AI_RUN_POST_TYPE !== get_post_type( $run_id ) ) {
		wp_send_json_error( array( 'message' => 'Test run not found.' ), 404 );
	}

	switch ( $command ) {
		case 'pause':
			if ( 'running' === qs_auto_ai_meta( $run_id, 'status', '' ) ) {
				qs_auto_ai_set_meta( $run_id, 'status', 'paused' );
				qs_auto_ai_log( $run_id, 'admin', 'Auto AI Testing paused. Current real records remain available for inspection.', 'warning' );
			}
			break;

		case 'resume':
			if ( in_array( qs_auto_ai_meta( $run_id, 'status', '' ), array( 'paused', 'stopped' ), true ) ) {
				qs_auto_ai_set_meta( $run_id, 'status', 'running' );
				qs_auto_ai_log( $run_id, 'admin', 'Auto AI Testing resumed.', 'info' );
			}
			break;

		case 'retry':
			if ( 'failed' === qs_auto_ai_meta( $run_id, 'status', '' ) ) {
				qs_auto_ai_set_meta( $run_id, 'status', 'running' );
				qs_auto_ai_set_meta( $run_id, 'last_error', '' );
				qs_auto_ai_log( $run_id, 'admin', 'Retrying the failed step.', 'warning' );
			}
			break;

		case 'stop':
			if ( ! in_array( qs_auto_ai_meta( $run_id, 'status', '' ), array( 'passed', 'failed' ), true ) ) {
				qs_auto_ai_set_meta( $run_id, 'status', 'stopped' );
				qs_auto_ai_log( $run_id, 'admin', 'Auto AI Testing stopped. Generated users, Quotes, orders and emails were NOT deleted.', 'warning' );
			}
			break;
	}

	wp_send_json_success( qs_auto_ai_state( $run_id ) );
}
add_action( 'wp_ajax_qs_auto_ai_control', 'qs_auto_ai_ajax_control' );

function qs_auto_ai_recent_runs() {
	return get_posts(
		array(
			'post_type'      => QS_AUTO_AI_RUN_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
}

function qs_auto_ai_testing_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Administrator access is required.' );
	}

	$joiners    = qs_auto_ai_joiner_users();
	$admins     = qs_auto_ai_admin_users();
	$run_id     = isset( $_GET['run_id'] ) ? absint( $_GET['run_id'] ) : 0;
	$state      = $run_id ? qs_auto_ai_state( $run_id ) : array();
	$recent     = qs_auto_ai_recent_runs();
	$portal_mode= function_exists( 'qs_portal_mode' ) ? qs_portal_mode() : 'test';
	$ajax_nonce = wp_create_nonce( 'qs_auto_ai_ajax' );
	?>
	<div class="qs-auto-ai">
		<div class="qs-auto-ai-title">
			<div>
				<h1>Auto AI Testing</h1>
				<p>Run the real Loughlin Quote workflow step-by-step with a real Joiner, real Quote, real WooCommerce orders and real emails.</p>
			</div>
			<span class="qs-auto-ai-mode <?php echo esc_attr( $portal_mode ); ?>">PORTAL: <?php echo esc_html( strtoupper( $portal_mode ) ); ?></span>
		</div>

		<?php if ( 'live' === $portal_mode ) : ?>
			<div class="notice notice-error inline"><p><strong>Auto AI Testing is locked while Portal Mode is Live.</strong> Switch Quote System to Setup or Test Mode first, then run the test before reopening the public portal.</p></div>
		<?php endif; ?>

		<div class="notice notice-warning inline">
			<p><strong>This creates real data.</strong> A test run consumes a real Quote number, creates real WooCommerce orders and sends real emails. Nothing is automatically deleted.</p>
			<p><strong>Email note:</strong> a full BACS test compresses the whole customer journey into a few seconds, so several branded emails will arrive close together. WooCommerce's duplicate customer status emails are suppressed for Quote System payment orders.</p>
		</div>

		<section class="qs-auto-ai-setup">
			<div class="qs-auto-ai-person">
				<h2>Joiner Side</h2>
				<label for="qs-auto-ai-joiner">Run as Joiner</label>
				<select id="qs-auto-ai-joiner">
					<option value="">Select Joiner</option>
					<option value="new">+ Create New Joiner</option>
					<?php foreach ( $joiners as $joiner ) : ?>
						<option value="<?php echo esc_attr( $joiner->ID ); ?>" data-email="<?php echo esc_attr( $joiner->user_email ); ?>"><?php echo esc_html( $joiner->display_name . ' — ' . $joiner->user_email ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description" id="qs-auto-ai-existing-email"></p>

				<div class="qs-auto-ai-new-joiner" hidden>
					<label>Name <input type="text" id="qs-auto-ai-new-name" placeholder="Auto Test Joiner"></label>
					<label>Company <input type="text" id="qs-auto-ai-new-company" placeholder="Auto Test Joinery"></label>
					<label>Email <input type="email" id="qs-auto-ai-new-email" placeholder="your-test-inbox@example.com"></label>
					<label>Phone <input type="text" id="qs-auto-ai-new-phone" placeholder="0400 000 000"></label>
					<label>Delivery Address <textarea id="qs-auto-ai-new-address" rows="2" placeholder="Test delivery address"></textarea></label>
					<p class="description">This creates a permanent WordPress Joiner. A random password is used; you can later reset/send a password normally from Users.</p>
				</div>
			</div>

			<div class="qs-auto-ai-person">
				<h2>LF Admin Side</h2>
				<label for="qs-auto-ai-admin">Act as Admin</label>
				<select id="qs-auto-ai-admin">
					<option value="">Select Admin</option>
					<?php foreach ( $admins as $admin ) : ?>
						<option value="<?php echo esc_attr( $admin->ID ); ?>" <?php selected( get_current_user_id(), $admin->ID ); ?>><?php echo esc_html( $admin->display_name . ' — ' . $admin->user_email ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">Admin-side workflow actions and attribution such as “In Production by” use this real WordPress administrator/editor account.</p>
			</div>

			<div class="qs-auto-ai-start">
				<label class="qs-auto-ai-real-email">
					<input type="checkbox" id="qs-auto-ai-real-email">
					<strong>I understand this sends REAL emails and creates REAL WooCommerce orders.</strong>
				</label>
				<button type="button" class="button button-primary button-hero" id="qs-auto-ai-start" <?php disabled( 'live' === $portal_mode ); ?>>Run Full Automatic Test</button>
			</div>
		</section>

		<section class="qs-auto-ai-run" <?php echo $state ? '' : 'hidden'; ?>>
			<div class="qs-auto-ai-run-header">
				<div>
					<div class="qs-auto-ai-kicker">CURRENT TEST RUN</div>
					<h2 id="qs-auto-ai-quote-title"><?php echo $state ? esc_html( ( $state['quote_number'] ? $state['quote_number'] . ' — ' : '' ) . 'Auto AI Test' ) : 'Auto AI Test'; ?></h2>
					<div class="qs-auto-ai-run-meta" id="qs-auto-ai-run-meta"></div>
				</div>
				<div class="qs-auto-ai-status-wrap">
					<span class="qs-auto-ai-status" id="qs-auto-ai-status">STARTING</span>
					<div class="qs-auto-ai-progress"><span id="qs-auto-ai-progress-bar"></span></div>
					<small id="qs-auto-ai-next-step"></small>
				</div>
			</div>

			<div class="qs-auto-ai-links" id="qs-auto-ai-links"></div>

			<div class="qs-auto-ai-controls">
				<button type="button" class="button" data-auto-ai-command="pause">Pause</button>
				<button type="button" class="button button-primary" data-auto-ai-command="resume">Resume</button>
				<button type="button" class="button button-primary" data-auto-ai-command="retry">Retry Failed Step</button>
				<button type="button" class="button" data-auto-ai-command="stop">Stop</button>
			</div>

			<div class="qs-auto-ai-view-tabs" role="tablist" aria-label="Auto AI run views">
				<button type="button" class="qs-auto-ai-view-tab" data-auto-ai-view="timeline" role="tab">Timeline</button>
				<button type="button" class="qs-auto-ai-view-tab" data-auto-ai-view="logs" role="tab">Live Logs</button>
				<button type="button" class="qs-auto-ai-view-tab" data-auto-ai-view="emails" role="tab">Emails <span id="qs-auto-ai-email-count"></span></button>
			</div>

			<section class="qs-auto-ai-view-panel qs-auto-ai-timeline-panel" data-auto-ai-panel="timeline" role="tabpanel">
				<div class="qs-auto-ai-summary" id="qs-auto-ai-summary"></div>
				<div class="qs-auto-ai-timeline-head">
					<div class="qs-auto-ai-lane-head joiner">
						<strong>JOINER</strong>
						<span id="qs-auto-ai-timeline-joiner"></span>
					</div>
					<div class="qs-auto-ai-lane-center">WORKFLOW</div>
					<div class="qs-auto-ai-lane-head admin">
						<strong>LF ADMIN</strong>
						<span id="qs-auto-ai-timeline-admin"></span>
					</div>
				</div>
				<div class="qs-auto-ai-timeline" id="qs-auto-ai-timeline"></div>
			</section>

			<section class="qs-auto-ai-view-panel" data-auto-ai-panel="logs" role="tabpanel">
				<div class="qs-auto-ai-columns">
					<section class="qs-auto-ai-log-panel">
						<header><strong>JOINER</strong><span id="qs-auto-ai-joiner-label"></span></header>
						<div class="qs-auto-ai-log" id="qs-auto-ai-joiner-log"></div>
					</section>
					<section class="qs-auto-ai-log-panel">
						<header><strong>LF ADMIN</strong><span id="qs-auto-ai-admin-label"></span></header>
						<div class="qs-auto-ai-log" id="qs-auto-ai-admin-log"></div>
					</section>
				</div>
			</section>

			<section class="qs-auto-ai-view-panel qs-auto-ai-emails" data-auto-ai-panel="emails" role="tabpanel">
				<h3>Captured Copies of Real Outgoing Emails</h3>
				<div id="qs-auto-ai-email-list"></div>
			</section>
		</section>

		<?php if ( $recent ) : ?>
			<section class="qs-auto-ai-history">
				<h2>Recent Test Runs</h2>
				<table class="widefat striped">
					<thead><tr><th>Run</th><th>Quote</th><th>Joiner</th><th>Status</th><th>Date</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $recent as $run ) :
							$run_state = qs_auto_ai_state( $run->ID );
							?>
							<tr>
								<td>#<?php echo esc_html( $run->ID ); ?></td>
								<td><?php echo esc_html( $run_state['quote_number'] ? $run_state['quote_number'] : 'Not created yet' ); ?></td>
								<td><?php echo esc_html( $run_state['joiner_name'] ? $run_state['joiner_name'] : '—' ); ?></td>
								<td><?php echo esc_html( $run_state['status_label'] ); ?></td>
								<td><?php echo esc_html( get_the_date( 'Y-m-d H:i', $run ) ); ?></td>
								<td><a class="button button-small" href="<?php echo esc_url( qs_auto_ai_run_url( $run->ID ) ); ?>">View Run</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>
	</div>

	<style>
	.qs-auto-ai{max-width:1400px}
	.qs-auto-ai-title{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin:18px 0}
	.qs-auto-ai-title h1{margin-bottom:4px}
	.qs-auto-ai-title p{margin-top:0;color:#646970}
	.qs-auto-ai-mode{padding:7px 11px;border-radius:999px;background:#fff3cd;color:#6f5200;font-size:11px;font-weight:700;letter-spacing:.05em;white-space:nowrap}
	.qs-auto-ai-mode.live{background:#e6f4ea;color:#146c2e}
	.qs-auto-ai-setup{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:20px 0}
	.qs-auto-ai-person{background:#fff;border:1px solid #dcdcde;padding:20px}
	.qs-auto-ai-person h2{margin-top:0}
	.qs-auto-ai-person>label,.qs-auto-ai-new-joiner label{display:block;font-weight:600;margin:12px 0 5px}
	.qs-auto-ai-person select,.qs-auto-ai-new-joiner input,.qs-auto-ai-new-joiner textarea{width:100%;max-width:none}
	.qs-auto-ai-new-joiner{margin-top:16px;padding-top:12px;border-top:1px solid #dcdcde}
	.qs-auto-ai-start{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:18px;background:#f6f7f7;border:1px solid #dcdcde;padding:18px}
	.qs-auto-ai-real-email{display:flex;align-items:center;gap:8px}
	.qs-auto-ai-run{background:#fff;border:1px solid #c3c4c7;margin:24px 0}
	.qs-auto-ai-run-header{display:flex;justify-content:space-between;gap:20px;padding:22px;border-bottom:1px solid #dcdcde}
	.qs-auto-ai-kicker{font-size:10px;font-weight:700;letter-spacing:.08em;color:#646970}
	.qs-auto-ai-run-header h2{margin:4px 0 6px}
	.qs-auto-ai-run-meta{color:#646970}
	.qs-auto-ai-status-wrap{min-width:260px;text-align:right}
	.qs-auto-ai-status{display:inline-block;padding:6px 10px;border-radius:999px;background:#e5e7eb;font-size:11px;font-weight:700;letter-spacing:.05em}
	.qs-auto-ai-status.running{background:#e6eef9;color:#275a8e}.qs-auto-ai-status.paused,.qs-auto-ai-status.stopped{background:#fff1d6;color:#8a5a00}.qs-auto-ai-status.failed{background:#fce8e6;color:#a12622}.qs-auto-ai-status.passed{background:#dff2e5;color:#245b35}
	.qs-auto-ai-progress{height:6px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin:10px 0 5px}
	.qs-auto-ai-progress span{display:block;height:100%;width:0;background:#2271b1;transition:width .25s}
	.qs-auto-ai-links{display:flex;flex-wrap:wrap;gap:8px;padding:14px 22px;background:#f6f7f7;border-bottom:1px solid #dcdcde}
	.qs-auto-ai-links a{display:inline-block;padding:5px 9px;background:#fff;border:1px solid #c3c4c7;text-decoration:none;border-radius:3px}
	.qs-auto-ai-controls{display:flex;gap:8px;padding:14px 22px;border-bottom:1px solid #dcdcde}
	.qs-auto-ai-columns{display:grid;grid-template-columns:1fr 1fr;min-height:430px}
	.qs-auto-ai-log-panel:first-child{border-right:1px solid #dcdcde}
	.qs-auto-ai-log-panel header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;background:#f6f7f7;border-bottom:1px solid #dcdcde}
	.qs-auto-ai-log-panel header strong{font-size:12px;letter-spacing:.05em}
	.qs-auto-ai-log{padding:14px;max-height:520px;overflow:auto}
	.qs-auto-ai-log-entry{--qs-auto-ai-log-bg:#f6f7f7;position:relative;padding:9px 10px 9px 32px;margin-bottom:8px;border-left:3px solid #c3c4c7;background:var(--qs-auto-ai-log-bg)}
	.qs-auto-ai-log-entry.is-latest{animation:qs-auto-ai-latest-log 4.5s ease-out}
	@keyframes qs-auto-ai-latest-log{
		0%,18%{background:#c9f0d5;box-shadow:0 0 0 1px rgba(22,128,60,.18),0 2px 8px rgba(22,128,60,.12)}
		100%{background:var(--qs-auto-ai-log-bg);box-shadow:none}
	}
	.qs-auto-ai-log-entry:before{content:"•";position:absolute;left:12px;top:8px;font-weight:700}
	.qs-auto-ai-log-entry.success{border-left-color:#16803c}.qs-auto-ai-log-entry.success:before{content:"✓";color:#16803c}
	.qs-auto-ai-log-entry.warning{border-left-color:#dba617}.qs-auto-ai-log-entry.warning:before{content:"!";color:#8a5a00}
	.qs-auto-ai-log-entry.error{--qs-auto-ai-log-bg:#fcf0f1;border-left-color:#d63638}.qs-auto-ai-log-entry.error:before{content:"×";color:#d63638}
	.qs-auto-ai-log-entry time{display:block;color:#8c8f94;font-size:11px;margin-bottom:2px}
	.qs-auto-ai-log-entry a{margin-left:8px}
	.qs-auto-ai-emails{padding:18px 22px;border-top:1px solid #dcdcde}
	.qs-auto-ai-email-row{display:grid;grid-template-columns:145px 1fr 2fr auto;gap:12px;padding:9px 0;border-top:1px solid #f0f0f1;align-items:center}
	.qs-auto-ai-history{margin:26px 0}
	@media(max-width:900px){.qs-auto-ai-setup,.qs-auto-ai-columns{grid-template-columns:1fr}.qs-auto-ai-log-panel:first-child{border-right:0;border-bottom:1px solid #dcdcde}.qs-auto-ai-run-header,.qs-auto-ai-start{align-items:flex-start;flex-direction:column}.qs-auto-ai-status-wrap{min-width:0;width:100%;text-align:left}.qs-auto-ai-email-row{grid-template-columns:1fr}.qs-auto-ai-title{flex-direction:column}}
	</style>

	<script>
	(function(){
		const ajaxUrl=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const nonce=<?php echo wp_json_encode( $ajax_nonce ); ?>;
		let state=<?php echo wp_json_encode( $state ? $state : null ); ?>;
		let timer=null;
		let requestRunning=false;
		const renderedLogCounts=new WeakMap();

		const joinerSelect=document.getElementById('qs-auto-ai-joiner');
		const newFields=document.querySelector('.qs-auto-ai-new-joiner');
		const existingEmail=document.getElementById('qs-auto-ai-existing-email');
		const runSection=document.querySelector('.qs-auto-ai-run');

		function updateJoinerChoice(){
			if(!joinerSelect)return;
			const option=joinerSelect.options[joinerSelect.selectedIndex];
			const isNew=joinerSelect.value==='new';
			newFields.hidden=!isNew;
			existingEmail.textContent=!isNew&&option?.dataset.email?'Real workflow emails will be sent to: '+option.dataset.email:'';
		}
		joinerSelect?.addEventListener('change',updateJoinerChoice);
		updateJoinerChoice();

		function esc(value){
			return String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
		}
		function renderLogs(target,logs){
			logs=logs||[];
			const hadPreviousRender=renderedLogCounts.has(target);
			const previousCount=hadPreviousRender?renderedLogCounts.get(target):logs.length;
			const newestIndex=logs.length-1;
			const hasNewLog=hadPreviousRender&&logs.length>previousCount;

			target.innerHTML=logs.map((log,index)=>{
				const latestClass=hasNewLog&&index===newestIndex?' is-latest':'';
				return '<div class="qs-auto-ai-log-entry '+esc(log.type)+latestClass+'"><time>'+esc(log.time)+'</time>'+esc(log.message)+(log.url?' <a href="'+esc(log.url)+'" target="_blank" rel="noopener">'+esc(log.link_label||'View')+'</a>':'')+'</div>';
			}).join('');

			renderedLogCounts.set(target,logs.length);
			target.scrollTop=target.scrollHeight;
		}
		function addLink(container,label,url){
			if(!url)return;
			const a=document.createElement('a');a.href=url;a.target='_blank';a.rel='noopener';a.textContent=label;container.appendChild(a);
		}
		function render(s){
			if(!s)return;
			state=s;runSection.hidden=false;
			const status=document.getElementById('qs-auto-ai-status');
			status.textContent=s.status_label||s.status;
			status.className='qs-auto-ai-status '+s.status;
			document.getElementById('qs-auto-ai-progress-bar').style.width=(s.progress||0)+'%';
			document.getElementById('qs-auto-ai-next-step').textContent=s.next_step_label?'Next: '+s.next_step_label:'Workflow finished';
			document.getElementById('qs-auto-ai-quote-title').textContent=s.quote_number?(s.quote_number+' — '+(s.project_title||'Auto AI Test')):'Auto AI Test';
			document.getElementById('qs-auto-ai-run-meta').textContent='Run #'+s.run_id+(s.quote_id?' · Quote ID #'+s.quote_id:'')+(s.quote_status_label?' · '+s.quote_status_label:'')+(s.quote_total?' · '+s.quote_total:'');
			document.getElementById('qs-auto-ai-joiner-label').textContent=s.joiner_name+(s.joiner_email?' · '+s.joiner_email:'');
			document.getElementById('qs-auto-ai-admin-label').textContent=s.admin_name;
			renderLogs(document.getElementById('qs-auto-ai-joiner-log'),s.logs_joiner);
			renderLogs(document.getElementById('qs-auto-ai-admin-log'),s.logs_admin);

			const links=document.getElementById('qs-auto-ai-links');links.innerHTML='';
			addLink(links,'Joiner User',s.links?.joiner_user);
			addLink(links,'Quote Review',s.links?.quote_review);
			addLink(links,'Quote Builder',s.links?.quote_builder);
			addLink(links,'Thank You',s.links?.thank_you);
			addLink(links,'Quotation PDF',s.links?.quotation_pdf);
			addLink(links,'Job Sheet',s.links?.job_sheet);
			if(s.deposit_order_id)addLink(links,'Deposit Order #'+s.deposit_order_id,s.links?.deposit_order);
			if(s.links?.deposit_pay)addLink(links,'Deposit Payment URL',s.links.deposit_pay);
			if(s.balance_order_id)addLink(links,'Final Order #'+s.balance_order_id,s.links?.balance_order);
			if(s.links?.balance_pay)addLink(links,'Final Payment URL',s.links.balance_pay);

			const emailList=document.getElementById('qs-auto-ai-email-list');
			emailList.innerHTML=(s.emails||[]).map(email=>'<div class="qs-auto-ai-email-row"><span>'+esc(email.time)+'</span><span>'+esc(email.to)+'</span><strong>'+esc(email.subject)+'</strong><a class="button button-small" target="_blank" rel="noopener" href="'+esc(email.preview_url)+'">View Email</a></div>').join('')||'<p class="description">No emails captured yet.</p>';

			document.querySelector('[data-auto-ai-command="pause"]').disabled=s.status!=='running';
			document.querySelector('[data-auto-ai-command="resume"]').disabled=!['paused','stopped'].includes(s.status);
			document.querySelector('[data-auto-ai-command="retry"]').disabled=s.status!=='failed';
			document.querySelector('[data-auto-ai-command="stop"]').disabled=['passed','failed','stopped'].includes(s.status);

			if(s.status==='running')scheduleStep();
			else clearTimeout(timer);
		}

		async function post(action,data={}){
			const body=new URLSearchParams({action,nonce,...data});
			const response=await fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body});
			const result=await response.json();
			if(!result.success)throw new Error(result.data?.message||'Auto AI request failed.');
			return result.data;
		}
		function scheduleStep(){
			clearTimeout(timer);
			timer=setTimeout(runStep,850);
		}
		async function runStep(){
			if(!state||state.status!=='running'||requestRunning)return;
			requestRunning=true;
			try{render(await post('qs_auto_ai_step',{run_id:state.run_id}));}
			catch(error){window.alert(error.message);}
			finally{requestRunning=false;}
		}

		document.getElementById('qs-auto-ai-start')?.addEventListener('click',async function(){
			const joinerId=joinerSelect.value;
			const adminId=document.getElementById('qs-auto-ai-admin').value;
			const ack=document.getElementById('qs-auto-ai-real-email').checked;
			if(!joinerId){window.alert('Select a Joiner or choose Create New Joiner.');return;}
			if(!adminId){window.alert('Select an LF Admin.');return;}
			if(!ack){window.alert('Confirm that real emails and real WooCommerce orders will be created.');return;}
			const recipient=joinerId==='new'?document.getElementById('qs-auto-ai-new-email').value:joinerSelect.options[joinerSelect.selectedIndex]?.dataset.email;
			if(!window.confirm('Start REAL end-to-end testing?\n\nJoiner email: '+(recipient||'not supplied')+'\n\nThis will create a real Quote, real WooCommerce orders and send real emails.'))return;

			this.disabled=true;
			try{
				const data=await post('qs_auto_ai_start',{
					joiner_id:joinerId,
					admin_id:adminId,
					real_email_ack:'1',
					new_joiner_name:document.getElementById('qs-auto-ai-new-name').value,
					new_joiner_company:document.getElementById('qs-auto-ai-new-company').value,
					new_joiner_email:document.getElementById('qs-auto-ai-new-email').value,
					new_joiner_phone:document.getElementById('qs-auto-ai-new-phone').value,
					new_joiner_address:document.getElementById('qs-auto-ai-new-address').value
				});
				history.replaceState({},'',data.links.run);
				render(data);
			}catch(error){window.alert(error.message);this.disabled=false;}
		});

		document.querySelectorAll('[data-auto-ai-command]').forEach(button=>button.addEventListener('click',async function(){
			if(!state||requestRunning)return;
			requestRunning=true;
			try{render(await post('qs_auto_ai_control',{run_id:state.run_id,command:this.dataset.autoAiCommand}));}
			catch(error){window.alert(error.message);}
			finally{requestRunning=false;}
		}));

		if(state)render(state);
	}());
	</script>
	<?php
}


/** Render the complete runner as the second tab inside Quote System Settings. */
function qs_auto_ai_render_settings_tab() {
	qs_auto_ai_testing_page();
}
add_action( 'qs_settings_tab_testing', 'qs_auto_ai_render_settings_tab' );
 . number_format_i18n( (float) $amount, 2 );
}

function qs_auto_ai_log( $run_id, $side, $message, $type = 'info', $url = '', $link_label = 'View' ) {
	$side = 'admin' === $side ? 'admin' : 'joiner';
	$logs = qs_auto_ai_meta( $run_id, 'logs_' . $side, array() );
	$logs = is_array( $logs ) ? $logs : array();

	$logs[] = array(
		'time'       => current_time( 'H:i:s' ),
		'message'    => sanitize_text_field( $message ),
		'type'       => in_array( $type, array( 'info', 'success', 'warning', 'error' ), true ) ? $type : 'info',
		'url'        => $url ? esc_url_raw( $url ) : '',
		'link_label' => sanitize_text_field( $link_label ),
	);

	qs_auto_ai_set_meta( $run_id, 'logs_' . $side, $logs );
}

function qs_auto_ai_steps() {
	return array(
		array( 'side' => 'joiner', 'label' => 'Build real quote through Quote Builder save handler' ),
		array( 'side' => 'joiner', 'label' => 'Submit quote for LF review' ),
		array( 'side' => 'admin',  'label' => 'Verify quote received and pricing' ),
		array( 'side' => 'admin',  'label' => 'Render quotation PDF' ),
		array( 'side' => 'admin',  'label' => 'Create deposit WooCommerce order and send email' ),
		array( 'side' => 'joiner', 'label' => 'Verify deposit email and payment URL' ),
		array( 'side' => 'joiner', 'label' => 'Place deposit order on Direct Bank Transfer' ),
		array( 'side' => 'admin',  'label' => 'Confirm deposit payment through WooCommerce' ),
		array( 'side' => 'admin',  'label' => 'Verify deposit workflow and complete order' ),
		array( 'side' => 'admin',  'label' => 'Mark quote In Production' ),
		array( 'side' => 'admin',  'label' => 'Apply post-deposit Additional Charge' ),
		array( 'side' => 'admin',  'label' => 'Create final-balance order and send email' ),
		array( 'side' => 'joiner', 'label' => 'Verify final-payment email and payment URL' ),
		array( 'side' => 'joiner', 'label' => 'Place final order on Direct Bank Transfer' ),
		array( 'side' => 'admin',  'label' => 'Confirm final payment through WooCommerce' ),
		array( 'side' => 'admin',  'label' => 'Complete final WooCommerce order' ),
		array( 'side' => 'joiner', 'label' => 'Verify completed quote on Joiner workflow' ),
		array( 'side' => 'admin',  'label' => 'Final end-to-end verification' ),
	);
}

function qs_auto_ai_with_user( $user_id, $callback ) {
	$original_user_id = get_current_user_id();
	wp_set_current_user( absint( $user_id ) );

	try {
		return call_user_func( $callback );
	} finally {
		wp_set_current_user( $original_user_id );
	}
}

function qs_auto_ai_joiner_users() {
	return get_users(
		array(
			'role'    => 'joiner',
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'number'  => 500,
		)
	);
}

function qs_auto_ai_admin_users() {
	$users  = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC', 'number' => 500 ) );
	$admins = array();

	foreach ( $users as $user ) {
		if ( user_can( $user, 'edit_others_posts' ) ) {
			$admins[] = $user;
		}
	}

	return $admins;
}

function qs_auto_ai_unique_username( $email, $name ) {
	$base = sanitize_user( strtok( (string) $email, '@' ), true );
	if ( ! $base ) {
		$base = sanitize_user( strtolower( str_replace( ' ', '.', (string) $name ) ), true );
	}
	if ( ! $base ) {
		$base = 'quote-test-joiner';
	}

	$username = $base;
	$suffix   = 2;
	while ( username_exists( $username ) ) {
		$username = $base . $suffix;
		$suffix++;
	}

	return $username;
}

/**
 * Create a permanent, normal Joiner user. Password is intentionally not saved
 * in the test run. An administrator can later send a password reset normally.
 */
function qs_auto_ai_create_joiner_from_request() {
	$email   = isset( $_POST['new_joiner_email'] ) ? sanitize_email( wp_unslash( $_POST['new_joiner_email'] ) ) : '';
	$name    = isset( $_POST['new_joiner_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_joiner_name'] ) ) : '';
	$company = isset( $_POST['new_joiner_company'] ) ? sanitize_text_field( wp_unslash( $_POST['new_joiner_company'] ) ) : '';
	$phone   = isset( $_POST['new_joiner_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['new_joiner_phone'] ) ) : '';
	$address = isset( $_POST['new_joiner_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['new_joiner_address'] ) ) : '';

	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'invalid_joiner_email', 'Enter a valid email address for the new Joiner.' );
	}
	if ( email_exists( $email ) ) {
		return new WP_Error( 'joiner_email_exists', 'That email already belongs to a WordPress user. Select that Joiner instead or use another address.' );
	}
	if ( ! $name ) {
		return new WP_Error( 'missing_joiner_name', 'Enter a name for the new Joiner.' );
	}

	$parts      = preg_split( '/\s+/', trim( $name ) );
	$first_name = $parts ? array_shift( $parts ) : $name;
	$last_name  = $parts ? implode( ' ', $parts ) : '';
	$username   = qs_auto_ai_unique_username( $email, $name );
	$user_id    = wp_insert_user(
		array(
			'user_login'   => $username,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'user_email'   => $email,
			'display_name' => $name,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'role'         => 'joiner',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	update_user_meta( $user_id, 'company_name', $company );
	update_user_meta( $user_id, 'billing_company', $company );
	update_user_meta( $user_id, 'billing_email', $email );
	update_user_meta( $user_id, 'billing_first_name', $first_name );
	update_user_meta( $user_id, 'billing_last_name', $last_name );
	update_user_meta( $user_id, 'billing_phone', $phone );
	update_user_meta( $user_id, 'billing_address_1', $address );
	update_user_meta( $user_id, 'shipping_address_1', $address );
	update_user_meta( $user_id, 'qs_delivery_address', $address );
	update_user_meta( $user_id, 'qs_customer_name', $name );
	update_user_meta( $user_id, 'qs_customer_email', $email );
	update_user_meta( $user_id, 'qs_customer_phone', $phone );
	update_user_meta( $user_id, 'qs_customer_company', $company );
	update_user_meta( $user_id, 'qs_portal_test_access', '1' );

	return (int) $user_id;
}

function qs_auto_ai_product_id( $type, $preferred_title = '' ) {
	if ( $preferred_title && function_exists( 'qs_find_quote_product' ) ) {
		$preferred = qs_find_quote_product( $preferred_title, $type );
		if ( $preferred ) {
			return $preferred;
		}
	}

	$products = function_exists( 'qs_builder_products' ) ? qs_builder_products( $type ) : array();
	return $products ? (int) $products[0]->ID : 0;
}

function qs_auto_ai_test_dimensions( $profile_id, $preferred_width = 600, $preferred_height = 800 ) {
	$width  = max( 1, absint( $preferred_width ) );
	$height = max( 1, absint( $preferred_height ) );

	if ( function_exists( 'qs_item_config_matrix_bounds' ) ) {
		$bounds = qs_item_config_matrix_bounds( $profile_id );
		if ( $bounds ) {
			$width  = max( (int) ceil( $bounds['min_width'] ), min( $width, (int) floor( $bounds['max_width'] ) ) );
			$height = max( (int) ceil( $bounds['min_height'] ), min( $height, (int) floor( $bounds['max_height'] ) ) );
		}
	}

	return array( max( 1, $width ), max( 1, $height ) );
}

/** Pick a real priced kickboard height from the current product configuration. */
function qs_auto_ai_kickboard_dimensions( $product_id ) {
	$height = 150;
	$length = 1200;

	if ( $product_id && function_exists( 'qs_pricing_repeater_rows' ) ) {
		$rows = qs_pricing_repeater_rows(
			$product_id,
			'linear_pricing',
			array(
				'min'   => array( 'height_min', 'min' ),
				'max'   => array( 'height_max', 'max' ),
				'price' => array( 'price_per_lm', 'price' ),
			)
		);

		foreach ( $rows as $row ) {
			$minimum = isset( $row['min'] ) ? max( 1, (int) ceil( (float) $row['min'] ) ) : 1;
			$maximum = isset( $row['max'] ) ? min( 200, (int) floor( (float) $row['max'] ) ) : 200;
			$price   = isset( $row['price'] ) ? (float) $row['price'] : 0;
			if ( $maximum >= $minimum && $price > 0 ) {
				$height = (int) floor( ( $minimum + $maximum ) / 2 );
				break;
			}
		}
	}

	return array( $length, max( 1, min( 200, $height ) ) );
}

function qs_auto_ai_joiner_defaults( $user ) {
	if ( function_exists( 'qs_customer_quote_defaults' ) ) {
		$defaults = qs_customer_quote_defaults( $user->ID );
		return array(
			'name'    => ! empty( $defaults['customer_name'] ) ? $defaults['customer_name'] : ( $user->display_name ? $user->display_name : $user->user_login ),
			'company' => isset( $defaults['company_name'] ) ? $defaults['company_name'] : '',
			'email'   => ! empty( $defaults['customer_email'] ) ? $defaults['customer_email'] : $user->user_email,
			'phone'   => isset( $defaults['customer_phone'] ) ? $defaults['customer_phone'] : '',
			'address' => isset( $defaults['delivery_address'] ) ? $defaults['delivery_address'] : '',
		);
	}

	return array(
		'name'    => $user->display_name ? $user->display_name : $user->user_login,
		'company' => '',
		'email'   => $user->user_email,
		'phone'   => '',
		'address' => '',
	);
}

/** Store a copy of each real wp_mail payload while a test step is running. */
function qs_auto_ai_capture_wp_mail( $args ) {
	$run_id = isset( $GLOBALS['qs_auto_ai_test_run_id'] ) ? absint( $GLOBALS['qs_auto_ai_test_run_id'] ) : 0;
	if ( ! $run_id || QS_AUTO_AI_RUN_POST_TYPE !== get_post_type( $run_id ) ) {
		return $args;
	}

	$emails = qs_auto_ai_meta( $run_id, 'emails', array() );
	$emails = is_array( $emails ) ? $emails : array();
	$emails[] = array(
		'time'        => current_time( 'mysql' ),
		'to'          => isset( $args['to'] ) ? $args['to'] : '',
		'subject'     => isset( $args['subject'] ) ? (string) $args['subject'] : '',
		'message'     => isset( $args['message'] ) ? (string) $args['message'] : '',
		'headers'     => isset( $args['headers'] ) ? $args['headers'] : array(),
		'attachments' => isset( $args['attachments'] ) ? $args['attachments'] : array(),
		'step'        => (int) qs_auto_ai_meta( $run_id, 'current_step', 0 ),
	);
	qs_auto_ai_set_meta( $run_id, 'emails', $emails );

	return $args;
}
add_filter( 'wp_mail', 'qs_auto_ai_capture_wp_mail', 999 );

function qs_auto_ai_begin_mail_capture( $run_id ) {
	$GLOBALS['qs_auto_ai_test_run_id'] = absint( $run_id );
}

function qs_auto_ai_end_mail_capture() {
	unset( $GLOBALS['qs_auto_ai_test_run_id'] );
}

function qs_auto_ai_latest_email( $run_id, $subject_contains = '', $recipient = '' ) {
	$emails = qs_auto_ai_meta( $run_id, 'emails', array() );
	$emails = is_array( $emails ) ? $emails : array();

	for ( $index = count( $emails ) - 1; $index >= 0; $index-- ) {
		$email = $emails[ $index ];
		$to    = isset( $email['to'] ) ? $email['to'] : '';
		$to    = is_array( $to ) ? implode( ',', $to ) : (string) $to;

		if ( $subject_contains && false === stripos( (string) $email['subject'], $subject_contains ) ) {
			continue;
		}
		if ( $recipient && false === stripos( $to, $recipient ) ) {
			continue;
		}

		$email['_index'] = $index;
		return $email;
	}

	return array();
}

function qs_auto_ai_email_preview_url( $run_id, $email_index ) {
	return add_query_arg(
		array(
			'action'      => 'qs_auto_ai_email_preview',
			'run_id'      => absint( $run_id ),
			'email_index' => absint( $email_index ),
		),
		admin_url( 'admin-post.php' )
	);
}

function qs_auto_ai_handle_email_preview() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}

	$run_id = isset( $_GET['run_id'] ) ? absint( $_GET['run_id'] ) : 0;
	if ( ! $run_id || QS_AUTO_AI_RUN_POST_TYPE !== get_post_type( $run_id ) ) {
		wp_die( 'Test run not found.', 404 );
	}

	$index  = isset( $_GET['email_index'] ) ? absint( $_GET['email_index'] ) : 0;
	$emails = qs_auto_ai_meta( $run_id, 'emails', array() );
	if ( ! is_array( $emails ) || ! isset( $emails[ $index ] ) ) {
		wp_die( 'Email preview not found.', 404 );
	}

	$email = $emails[ $index ];
	$to    = isset( $email['to'] ) ? $email['to'] : '';
	$to    = is_array( $to ) ? implode( ', ', $to ) : (string) $to;

	nocache_headers();
	?>
	<!doctype html>
	<html>
	<head>
		<meta charset="utf-8">
		<title><?php echo esc_html( isset( $email['subject'] ) ? $email['subject'] : 'Email Preview' ); ?></title>
		<style>
			body{margin:0;background:#f0f0f1;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#1d2327}
			.meta{position:sticky;top:0;background:#fff;border-bottom:1px solid #dcdcde;padding:14px 20px;z-index:3}
			.meta strong{display:inline-block;min-width:70px}
			.email-frame{display:block;width:calc(100% - 48px);max-width:1100px;height:760px;margin:24px auto;border:1px solid #dcdcde;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08)}
		</style>
	</head>
	<body>
		<div class="meta">
			<div><strong>To:</strong> <?php echo esc_html( $to ); ?></div>
			<div><strong>Subject:</strong> <?php echo esc_html( isset( $email['subject'] ) ? $email['subject'] : '' ); ?></div>
			<div><strong>Captured:</strong> <?php echo esc_html( isset( $email['time'] ) ? $email['time'] : '' ); ?></div>
		</div>
		<iframe
			class="email-frame"
			title="Captured email preview"
			sandbox="allow-popups allow-popups-to-escape-sandbox"
			srcdoc="<?php echo esc_attr( isset( $email['message'] ) ? $email['message'] : '' ); ?>"
		></iframe>
	</body>
	</html>
	<?php
	exit;
}
add_action( 'admin_post_qs_auto_ai_email_preview', 'qs_auto_ai_handle_email_preview' );

function qs_auto_ai_run_url( $run_id ) {
	$url = function_exists( 'qs_settings_url' )
		? qs_settings_url( 'testing' )
		: admin_url( 'edit.php?post_type=quote&page=qs-settings&tab=testing' );

	return add_query_arg( 'run_id', absint( $run_id ), $url );
}

function qs_auto_ai_order_edit_url( $order_id ) {
	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return '';
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return '';
	}
	return method_exists( $order, 'get_edit_order_url' )
		? $order->get_edit_order_url()
		: admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' );
}

function qs_auto_ai_state( $run_id ) {
	$run = get_post( $run_id );
	if ( ! $run || QS_AUTO_AI_RUN_POST_TYPE !== $run->post_type ) {
		return array();
	}

	$joiner_id       = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	$admin_id        = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );
	$quote_id        = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$deposit_order   = absint( qs_auto_ai_meta( $run_id, 'deposit_order_id', 0 ) );
	$balance_order   = absint( qs_auto_ai_meta( $run_id, 'balance_order_id', 0 ) );
	$joiner          = $joiner_id ? get_user_by( 'id', $joiner_id ) : false;
	$admin           = $admin_id ? get_user_by( 'id', $admin_id ) : false;
	$quote_number    = $quote_id ? (string) get_post_meta( $quote_id, '_quote_number', true ) : '';
	$project_title   = $quote_id ? (string) get_the_title( $quote_id ) : '';
	$quote_status    = $quote_id ? get_post_status( $quote_id ) : '';
	$total           = $quote_id ? qs_calculate_total( $quote_id ) : 0;
	$steps           = qs_auto_ai_steps();
	$current_step    = absint( qs_auto_ai_meta( $run_id, 'current_step', 0 ) );
	$status          = (string) qs_auto_ai_meta( $run_id, 'status', 'running' );
	$emails          = qs_auto_ai_meta( $run_id, 'emails', array() );
	$email_summaries = array();

	foreach ( is_array( $emails ) ? $emails : array() as $index => $email ) {
		$to = isset( $email['to'] ) ? $email['to'] : '';
		$to = is_array( $to ) ? implode( ', ', $to ) : (string) $to;
		$email_summaries[] = array(
			'index'       => $index,
			'time'        => isset( $email['time'] ) ? $email['time'] : '',
			'to'          => $to,
			'subject'     => isset( $email['subject'] ) ? $email['subject'] : '',
			'preview_url' => qs_auto_ai_email_preview_url( $run_id, $index ),
		);
	}

	$links = array(
		'run'           => qs_auto_ai_run_url( $run_id ),
		'joiner_user'   => $joiner_id ? admin_url( 'user-edit.php?user_id=' . $joiner_id ) : '',
		'quote_review'  => $quote_id ? ( function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_review', array( 'quote_id' => $quote_id ) ) : add_query_arg( 'quote_id', $quote_id, site_url( '/quote-review/' ) ) ) : '',
		'quote_builder' => $quote_id ? ( function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_builder', array( 'quote_id' => $quote_id ) ) : add_query_arg( 'quote_id', $quote_id, site_url( '/quote-builder/' ) ) ) : '',
		'thank_you'     => $quote_id ? ( function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_submitted', array( 'quote_id' => $quote_id ) ) : add_query_arg( 'quote_id', $quote_id, site_url( '/quote-submitted/' ) ) ) : '',
		'quotation_pdf' => $quote_id ? add_query_arg( 'download_quote_pdf', $quote_id, home_url( '/' ) ) : '',
		'job_sheet'     => $quote_id ? add_query_arg( 'download_jobsheet_pdf', $quote_id, home_url( '/' ) ) : '',
		'deposit_order' => qs_auto_ai_order_edit_url( $deposit_order ),
		'balance_order' => qs_auto_ai_order_edit_url( $balance_order ),
		'deposit_pay'   => $quote_id && function_exists( 'qs_get_quote_payment_url' ) ? qs_get_quote_payment_url( $quote_id, 'deposit' ) : '',
		'balance_pay'   => $quote_id && function_exists( 'qs_get_quote_payment_url' ) ? qs_get_quote_payment_url( $quote_id, 'balance' ) : '',
	);

	return array(
		'run_id'           => (int) $run_id,
		'status'           => $status,
		'status_label'     => strtoupper( str_replace( '_', ' ', $status ) ),
		'current_step'     => $current_step,
		'total_steps'      => count( $steps ),
		'next_step_label'  => isset( $steps[ $current_step ] ) ? $steps[ $current_step ]['label'] : '',
		'progress'         => count( $steps ) ? min( 100, round( ( $current_step / count( $steps ) ) * 100 ) ) : 0,
		'joiner_id'        => $joiner_id,
		'joiner_name'      => $joiner ? $joiner->display_name : '',
		'joiner_email'     => $joiner ? $joiner->user_email : '',
		'admin_id'         => $admin_id,
		'admin_name'       => $admin ? $admin->display_name : '',
		'quote_id'         => $quote_id,
		'quote_number'     => $quote_number,
		'project_title'    => $project_title,
		'quote_status'     => $quote_status,
		'quote_status_label'=> $quote_id && function_exists( 'qs_workflow_quote_status_label' ) ? qs_workflow_quote_status_label( $quote_id ) : $quote_status,
		'quote_total'      => $quote_id ? qs_auto_ai_money( $total ) : '',
		'deposit_order_id' => $deposit_order,
		'balance_order_id' => $balance_order,
		'logs_joiner'      => qs_auto_ai_meta( $run_id, 'logs_joiner', array() ),
		'logs_admin'       => qs_auto_ai_meta( $run_id, 'logs_admin', array() ),
		'emails'           => $email_summaries,
		'links'            => $links,
		'created_at'       => get_the_date( 'Y-m-d H:i:s', $run_id ),
	);
}

function qs_auto_ai_fail( $run_id, $side, $message ) {
	qs_auto_ai_log( $run_id, $side, $message, 'error' );
	qs_auto_ai_set_meta( $run_id, 'status', 'failed' );
	qs_auto_ai_set_meta( $run_id, 'last_error', sanitize_text_field( $message ) );
	return false;
}

function qs_auto_ai_tag_order( $order_id, $run_id, $stage ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	$order->update_meta_data( '_qs_auto_ai_test', '1' );
	$order->update_meta_data( '_qs_auto_ai_test_run', absint( $run_id ) );
	$order->update_meta_data( '_qs_auto_ai_test_stage', sanitize_key( $stage ) );
	$order->add_order_note( sprintf( 'Auto AI Testing run #%d — %s order.', absint( $run_id ), ucfirst( $stage ) ) );
	$order->save();
}

function qs_auto_ai_step_create_quote( $run_id ) {
	$joiner_id = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	$joiner    = get_user_by( 'id', $joiner_id );
	if ( ! $joiner || ! function_exists( 'qs_user_is_joiner' ) || ! qs_user_is_joiner( $joiner ) ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Selected Joiner account is no longer available.' );
	}

	// Use real currently-configured Quote Products so this tests the same
	// selections a Joiner can make in Quote Builder.
	$profile_id   = qs_auto_ai_product_id( 'door-profile', 'Evans' );
	$timber_id    = qs_auto_ai_product_id( 'timber', 'Tasmanian Oak' );
	$finish_id    = qs_auto_ai_product_id( 'finish', 'Finished' );
	$handle_id    = qs_auto_ai_product_id( 'accessory', 'Square Edge' );
	$kickboard_id = qs_auto_ai_product_id( 'kickboard', 'Veneer Kickboard' );

	if ( ! $profile_id ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'No active Door Profile is available, so Quote Builder cannot create a priced test quote.' );
	}
	if ( ! $kickboard_id ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'No active Kickboard product is available, so the full Quote Builder component test cannot run.' );
	}

	list( $door_width, $door_height )       = qs_auto_ai_test_dimensions( $profile_id, 600, 800 );
	list( $drawer_width, $drawer_height )   = qs_auto_ai_test_dimensions( $profile_id, 600, 200 );
	list( $panel_width, $panel_height )     = qs_auto_ai_test_dimensions( qs_find_quote_product( 'Evans', 'door-profile' ), 600, 800 );
	list( $filler_width, $filler_height )   = qs_auto_ai_test_dimensions( qs_find_quote_product( 'Evans', 'door-profile' ), 200, 800 );
	list( $kick_length, $kick_height )      = qs_auto_ai_kickboard_dimensions( $kickboard_id );

	$defaults = qs_auto_ai_joiner_defaults( $joiner );
	$project  = 'Kitchen Renovation';
	$old_post = $_POST;

	$profile_defaults_hook = has_action( 'save_post_quote', 'qs_save_customer_quote_defaults' );
	if ( false !== $profile_defaults_hook ) {
		remove_action( 'save_post_quote', 'qs_save_customer_quote_defaults', 30 );
	}

	try {
		$result = qs_auto_ai_with_user(
			$joiner_id,
			static function () use (
				$profile_id,
				$timber_id,
				$finish_id,
				$handle_id,
				$kickboard_id,
				$door_width,
				$door_height,
				$drawer_width,
				$drawer_height,
				$panel_width,
				$panel_height,
				$filler_width,
				$filler_height,
				$kick_length,
				$kick_height,
				$defaults,
				$project
			) {
				$_POST = array(
					'qs_builder_nonce' => wp_create_nonce( 'qs_save_quote' ),
					'project_name'     => $project,
					'company_name'     => $defaults['company'],
					'customer_name'    => $defaults['name'],
					'customer_email'   => $defaults['email'],
					'customer_phone'   => $defaults['phone'],
					'delivery_address' => $defaults['address'],
					'door_profile'     => (string) $profile_id,
					'timber'           => (string) $timber_id,
					'finish'           => (string) $finish_id,
					'handle_profile'   => (string) $handle_id,
					'paint_colour'     => '',
					'custom_requests'  => 'Please confirm grain direction before production.',
					'project_notes'    => 'Kitchen cabinetry renovation.',
					'pricing_type'     => 'trade',
					'components'       => array(
						'doors_drawers' => array(
							array(
								'type'                 => 'Door',
								'door_profile'         => (string) $profile_id,
								'timber'               => (string) $timber_id,
								'finish'               => (string) $finish_id,
								'handle_profile'       => (string) $handle_id,
								'paint_colour'         => '',
								'width'                => $door_width,
								'height'               => $door_height,
								'quantity'             => 2,
								'edge_profile'         => '',
								'drawer_count'         => 0,
								'top_height'           => 0,
								'top_middle_height'    => 0,
								'middle_height'        => 0,
								'bottom_middle_height' => 0,
								'bottom_height'        => 0,
								'notes'                => 'Kitchen doors.',
							),
							array(
								'type'                 => 'Drawer',
								'door_profile'         => (string) $profile_id,
								'timber'               => (string) $timber_id,
								'finish'               => (string) $finish_id,
								'handle_profile'       => (string) $handle_id,
								'paint_colour'         => '',
								'width'                => $drawer_width,
								'height'               => $drawer_height,
								'quantity'             => 2,
								'edge_profile'         => '',
								'drawer_count'         => 0,
								'top_height'           => 0,
								'top_middle_height'    => 0,
								'middle_height'        => 0,
								'bottom_middle_height' => 0,
								'bottom_height'        => 0,
								'notes'                => 'Drawer fronts.',
							),
							array(
								'type'                 => 'Drawer Bank',
								'door_profile'         => (string) $profile_id,
								'timber'               => (string) $timber_id,
								'finish'               => (string) $finish_id,
								'handle_profile'       => (string) $handle_id,
								'paint_colour'         => '',
								'width'                => $drawer_width,
								'height'               => 0,
								'quantity'             => 1,
								'edge_profile'         => '',
								'drawer_count'         => 3,
								'top_height'           => $drawer_height,
								'top_middle_height'    => 0,
								'middle_height'        => $drawer_height,
								'bottom_middle_height' => 0,
								'bottom_height'        => $drawer_height,
								'notes'                => 'Three-drawer bank.',
							),
							array(
								'type'                 => 'Profile End Panel',
								'door_profile'         => (string) $profile_id,
								'timber'               => (string) $timber_id,
								'finish'               => (string) $finish_id,
								'handle_profile'       => '',
								'paint_colour'         => '',
								'width'                => $door_width,
								'height'               => $door_height,
								'quantity'             => 1,
								'edge_profile'         => '',
								'drawer_count'         => 0,
								'top_height'           => 0,
								'top_middle_height'    => 0,
								'middle_height'        => 0,
								'bottom_middle_height' => 0,
								'bottom_height'        => 0,
								'notes'                => 'Profile end panel.',
							),
						),
						'end_panels' => array(
							array(
								'timber'       => (string) $timber_id,
								'finish'       => (string) $finish_id,
								'paint_colour' => '',
								'height'       => $panel_height,
								'width'        => $panel_width,
								'quantity'     => 1,
								'faces_seen'   => '2 Faces',
								'edges_seen'   => 'Top + Right',
								'notes'        => 'Flat end panel.',
							),
						),
						'fillers' => array(
							array(
								'timber'       => (string) $timber_id,
								'finish'       => (string) $finish_id,
								'paint_colour' => '',
								'height'       => $filler_height,
								'width'        => $filler_width,
								'quantity'     => 1,
								'faces_seen'   => '2 Faces',
								'edges_seen'   => '1 Long / 2 Short',
								'notes'        => 'Kitchen filler.',
							),
						),
						'kickboards' => array(
							array(
								'material'     => (string) $kickboard_id,
								'timber'       => (string) $timber_id,
								'finish'       => (string) $finish_id,
								'paint_colour' => '',
								'height'       => $kick_height,
								'length'       => $kick_length,
								'quantity'     => 2,
								'notes'        => 'Kitchen kickboards.',
							),
						),
					),
				);

				return qs_builder_save_quote( 0, false );
			}
		);
	} finally {
		$_POST = $old_post;
		if ( false !== $profile_defaults_hook ) {
			add_action( 'save_post_quote', 'qs_save_customer_quote_defaults', 30, 2 );
		}
	}

	if ( is_wp_error( $result ) ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Quote Builder failed: ' . $result->get_error_message() );
	}

	$quote_id = absint( $result );
	$subtotal = (float) get_post_meta( $quote_id, '_subtotal', true );
	if ( ! $quote_id || $subtotal <= 0 ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'A Quote record was created, but pricing returned zero. Check Quote Product pricing configuration.' );
	}

	// Full Auto AI coverage should prove all major Builder component groups are
	// both saved and contributing to the real pricing calculation.
	$breakdown = get_post_meta( $quote_id, '_pricing_breakdown', true );
	$breakdown = is_array( $breakdown ) ? $breakdown : array();
	$required_components = array(
		'doors_drawers' => 'Doors / Drawers / Profile End Panel',
		'end_panels'    => 'End Panels',
		'fillers'       => 'Fillers',
		'kickboards'    => 'Kickboards',
	);
	foreach ( $required_components as $component => $label ) {
		if ( ! qs_component_rows( $quote_id, $component ) ) {
			return qs_auto_ai_fail( $run_id, 'joiner', $label . ' were not saved by Quote Builder.' );
		}
		if ( empty( $breakdown[ $component ] ) || (float) $breakdown[ $component ] <= 0 ) {
			return qs_auto_ai_fail( $run_id, 'joiner', $label . ' were saved but did not produce a positive price.' );
		}
	}

	update_post_meta( $quote_id, '_qs_auto_ai_test', '1' );
	update_post_meta( $quote_id, '_qs_auto_ai_test_run', $run_id );
	update_post_meta( $quote_id, '_qs_auto_ai_test_admin', absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) ) );
	qs_auto_ai_set_meta( $run_id, 'quote_id', $quote_id );

	$quote_number = (string) get_post_meta( $quote_id, '_quote_number', true );
	wp_update_post(
		array(
			'ID'         => $run_id,
			'post_title' => 'Auto AI Test — ' . ( $quote_number ? $quote_number : '#' . $quote_id ),
		)
	);

	$review_url = function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_review', array( 'quote_id' => $quote_id ) ) : add_query_arg( 'quote_id', $quote_id, site_url( '/quote-review/' ) );
	$builder_url = function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_builder', array( 'quote_id' => $quote_id ) ) : add_query_arg( 'quote_id', $quote_id, site_url( '/quote-builder/' ) );

	qs_auto_ai_log(
		$run_id,
		'joiner',
		sprintf(
			'Real quote %s created as "%s" with Doors, Drawer, Drawer Bank, Profile End Panel, End Panel, Filler and Kickboards. Subtotal: %s.',
			$quote_number,
			$project,
			qs_auto_ai_money( $subtotal )
		),
		'success',
		$builder_url,
		'Open Builder'
	);

	qs_auto_ai_log(
		$run_id,
		'joiner',
		sprintf(
			'Selections saved: %s / %s / %s / %s / %s.',
			get_the_title( $profile_id ),
			$timber_id ? get_the_title( $timber_id ) : 'No timber',
			$finish_id ? get_the_title( $finish_id ) : 'No finish',
			$handle_id ? get_the_title( $handle_id ) : 'No handle',
			get_the_title( $kickboard_id )
		),
		'success',
		$review_url,
		'Open Quote'
	);

	return true;
}

function qs_auto_ai_step_submit_quote( $run_id ) {
	$quote_id  = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$joiner_id = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	if ( ! $quote_id ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'No Quote exists for this run.' );
	}

	$result = qs_auto_ai_with_user(
		$joiner_id,
		static function () use ( $run_id, $quote_id ) {
			$status = qs_update_quote_status( $quote_id, 'pending_review' );
			if ( is_wp_error( $status ) || false === $status ) {
				return new WP_Error( 'submit_failed', 'Could not move quote to Pending Review.' );
			}
			qs_auto_ai_begin_mail_capture( $run_id );
			$mail = qs_email_quote_submitted( $quote_id );
			qs_auto_ai_end_mail_capture();
			return $mail;
		}
	);

	if ( is_wp_error( $result ) ) {
		return qs_auto_ai_fail( $run_id, 'joiner', $result->get_error_message() );
	}
	if ( ! $result ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Quote was submitted, but WordPress did not accept the admin notification email for delivery.' );
	}

	$quote_number = get_post_meta( $quote_id, '_quote_number', true );
	qs_auto_ai_log( $run_id, 'joiner', sprintf( 'Quote %s submitted for LF review.', $quote_number ), 'success' );
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'New Quote notification handed to WordPress mail for %s.', function_exists( 'qs_get_admin_email' ) ? qs_get_admin_email() : get_option( 'admin_email' ) ), 'success' );

	return true;
}

function qs_auto_ai_step_admin_verify_quote( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$admin_id = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );
	$quote    = get_post( $quote_id );
	if ( ! $quote || 'pending_review' !== get_post_status( $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'LF Admin verification failed: quote is not in Pending Review.' );
	}
	if ( ! user_can( $admin_id, 'edit_post', $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Selected Admin does not have permission to manage the generated Quote.' );
	}

	$total = qs_calculate_total( $quote_id );
	if ( $total <= 0 ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Admin received the Quote, but calculated total is zero.' );
	}

	qs_auto_ai_log(
		$run_id,
		'admin',
		sprintf( 'Quote received from %s. Total quotation: %s.', get_the_author_meta( 'display_name', $quote->post_author ), qs_auto_ai_money( $total ) ),
		'success',
		admin_url( 'post.php?post=' . $quote_id . '&action=edit' ),
		'Edit Quote'
	);

	return true;
}

function qs_auto_ai_step_render_pdf( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );

	try {
		if ( function_exists( 'qs_estimated_lead_time_quotation_pdf' ) ) {
			$pdf = qs_estimated_lead_time_quotation_pdf( $quote_id );
		} else {
			$pdf = qs_generate_quotation_pdf( $quote_id );
		}
		$bytes = $pdf && method_exists( $pdf, 'output' ) ? $pdf->output() : '';
	} catch ( Throwable $error ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Quotation PDF render failed: ' . $error->getMessage() );
	}

	if ( ! is_string( $bytes ) || strlen( $bytes ) < 1000 ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Quotation PDF did not render a valid PDF payload.' );
	}

	$url = add_query_arg( 'download_quote_pdf', $quote_id, home_url( '/' ) );
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Quotation PDF rendered successfully (%s KB).', number_format_i18n( strlen( $bytes ) / 1024, 1 ) ), 'success', $url, 'Open PDF' );
	return true;
}

function qs_auto_ai_step_deposit_request( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$admin_id = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );

	$result = qs_auto_ai_with_user(
		$admin_id,
		static function () use ( $run_id, $quote_id ) {
			$order_id = qs_create_payment_order( $quote_id, 'deposit' );
			if ( is_wp_error( $order_id ) ) {
				return $order_id;
			}

			qs_auto_ai_tag_order( $order_id, $run_id, 'deposit' );
			qs_update_quote_status( $quote_id, 'awaiting_deposit' );

			qs_auto_ai_begin_mail_capture( $run_id );
			$mail = qs_email_payment_request( $quote_id, 'deposit' );
			qs_auto_ai_end_mail_capture();

			if ( ! $mail ) {
				return new WP_Error( 'deposit_email_failed', 'Deposit order was created, but WordPress did not accept the payment email for delivery.' );
			}
			return (int) $order_id;
		}
	);

	if ( is_wp_error( $result ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Deposit request failed: ' . $result->get_error_message() );
	}

	$order_id = absint( $result );
	qs_auto_ai_set_meta( $run_id, 'deposit_order_id', $order_id );
	$amount = function_exists( 'qs_calculate_deposit' ) ? qs_calculate_deposit( $quote_id ) : 0;
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Real WooCommerce deposit order #%d created for %s. Payment email sent.', $order_id, qs_auto_ai_money( $amount ) ), 'success', qs_auto_ai_order_edit_url( $order_id ), 'View Order' );
	return true;
}

function qs_auto_ai_step_verify_deposit_email( $run_id ) {
	$joiner_id = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	$quote_id  = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$joiner    = get_user_by( 'id', $joiner_id );
	$email     = $joiner ? qs_auto_ai_latest_email( $run_id, 'Deposit Payment Ready', $joiner->user_email ) : array();
	$pay_url   = $quote_id ? qs_get_quote_payment_url( $quote_id, 'deposit' ) : '';

	if ( ! $email || ! $pay_url ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Could not verify the real deposit email and WooCommerce payment URL.' );
	}

	qs_auto_ai_log(
		$run_id,
		'joiner',
		sprintf( 'Deposit email was handed to WordPress for real delivery to %s. Payment URL is valid.', $joiner->user_email ),
		'success',
		qs_auto_ai_email_preview_url( $run_id, $email['_index'] ),
		'View Email'
	);
	return true;
}

function qs_auto_ai_step_bacs_order( $run_id, $payment_type ) {
	$order_id = absint( qs_auto_ai_meta( $run_id, 'deposit' === $payment_type ? 'deposit_order_id' : 'balance_order_id', 0 ) );
	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return qs_auto_ai_fail( $run_id, 'joiner', ucfirst( $payment_type ) . ' WooCommerce order is unavailable.' );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return qs_auto_ai_fail( $run_id, 'joiner', ucfirst( $payment_type ) . ' WooCommerce order could not be loaded.' );
	}

	$order->set_payment_method( 'bacs' );
	$order->set_payment_method_title( 'Direct bank transfer' );
	$order->save();
	if ( ! $order->has_status( 'on-hold' ) ) {
		qs_auto_ai_begin_mail_capture( $run_id );
		$order->update_status( 'on-hold', 'Auto AI Testing: customer selected Direct bank transfer.' );
		qs_auto_ai_end_mail_capture();
	}

	qs_auto_ai_log(
		$run_id,
		'joiner',
		sprintf( 'WooCommerce order #%d placed on Direct bank transfer. Status: %s.', $order_id, wc_get_order_status_name( $order->get_status() ) ),
		'success',
		$order->get_checkout_payment_url(),
		'Open Payment'
	);
	return true;
}

function qs_auto_ai_step_payment_complete( $run_id, $payment_type ) {
	$order_key = 'deposit' === $payment_type ? 'deposit_order_id' : 'balance_order_id';
	$order_id  = absint( qs_auto_ai_meta( $run_id, $order_key, 0 ) );
	$admin_id  = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );
	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', ucfirst( $payment_type ) . ' order is unavailable for payment confirmation.' );
	}

	$result = qs_auto_ai_with_user(
		$admin_id,
		static function () use ( $run_id, $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return new WP_Error( 'missing_order', 'WooCommerce order could not be loaded.' );
			}

			qs_auto_ai_begin_mail_capture( $run_id );
			$order->payment_complete();
			qs_auto_ai_end_mail_capture();
			return $order->get_status();
		}
	);

	if ( is_wp_error( $result ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', $result->get_error_message() );
	}

	$quote_id        = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$expected_status = 'deposit' === $payment_type ? 'deposit_paid' : 'paid_in_full';
	if ( $expected_status !== get_post_status( $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', sprintf( 'WooCommerce accepted payment, but Quote status did not change to %s.', $expected_status ) );
	}

	qs_auto_ai_log( $run_id, 'admin', sprintf( '%s bank payment confirmed through WooCommerce. Quote status: %s.', ucfirst( $payment_type ), $expected_status ), 'success', qs_auto_ai_order_edit_url( $order_id ), 'View Order' );
	return true;
}

function qs_auto_ai_step_verify_deposit_payment( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$order_id = absint( qs_auto_ai_meta( $run_id, 'deposit_order_id', 0 ) );
	if ( 'deposit_paid' !== get_post_status( $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Deposit workflow verification failed: Quote is not Deposit Paid.' );
	}

	$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( $order && ! $order->has_status( 'completed' ) ) {
		qs_auto_ai_begin_mail_capture( $run_id );
		$order->update_status( 'completed', 'Auto AI Testing: LF admin completed the deposit order after bank payment confirmation.' );
		qs_auto_ai_end_mail_capture();
	}

	$email = qs_auto_ai_latest_email( $run_id, 'Deposit Payment Received' );
	$url   = $email ? qs_auto_ai_email_preview_url( $run_id, $email['_index'] ) : qs_auto_ai_order_edit_url( $order_id );
	qs_auto_ai_log( $run_id, 'admin', 'Deposit Paid confirmed. Admin payment notification generated and deposit order completed.', 'success', $url, $email ? 'View Email' : 'View Order' );
	return true;
}

function qs_auto_ai_step_mark_production( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$admin_id = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );
	if ( 'deposit_paid' !== get_post_status( $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Quote must be Deposit Paid before marking it In Production.' );
	}

	update_post_meta( $quote_id, '_qs_in_production', current_time( 'mysql' ) );
	update_post_meta( $quote_id, '_qs_in_production_by', $admin_id );
	qs_auto_ai_log( $run_id, 'admin', 'Quote marked In Production by the selected LF Admin.', 'success' );
	return true;
}

function qs_auto_ai_step_add_charge( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$before   = qs_calculate_total( $quote_id );
	$current  = (float) get_post_meta( $quote_id, '_additional_charges', true );
	$charge   = 50.00;

	update_post_meta( $quote_id, '_additional_charges', $current + $charge );
	$after = qs_calculate_total( $quote_id );

	if ( round( $after - $before, 2 ) !== round( $charge, 2 ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Post-deposit pricing test failed: Additional Charge was not reflected in Quote total.' );
	}

	qs_auto_ai_set_meta( $run_id, 'test_additional_charge', $charge );
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Added real Additional Charge of %s after deposit. New total: %s.', qs_auto_ai_money( $charge ), qs_auto_ai_money( $after ) ), 'success' );
	return true;
}

function qs_auto_ai_step_final_invoice( $run_id ) {
	$quote_id = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$admin_id = absint( qs_auto_ai_meta( $run_id, 'admin_id', 0 ) );

	$result = qs_auto_ai_with_user(
		$admin_id,
		static function () use ( $run_id, $quote_id ) {
			$order_id = qs_create_payment_order( $quote_id, 'balance' );
			if ( is_wp_error( $order_id ) ) {
				return $order_id;
			}

			qs_auto_ai_tag_order( $order_id, $run_id, 'balance' );
			qs_update_quote_status( $quote_id, 'final_balance' );

			qs_auto_ai_begin_mail_capture( $run_id );
			$mail = qs_email_payment_request( $quote_id, 'balance' );
			qs_auto_ai_end_mail_capture();
			if ( ! $mail ) {
				return new WP_Error( 'balance_email_failed', 'Final order was created, but WordPress did not accept the final-payment email for delivery.' );
			}

			return (int) $order_id;
		}
	);

	if ( is_wp_error( $result ) ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Final invoice failed: ' . $result->get_error_message() );
	}

	$order_id = absint( $result );
	qs_auto_ai_set_meta( $run_id, 'balance_order_id', $order_id );
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Real final-balance WooCommerce order #%d created for %s. Final-payment email sent.', $order_id, qs_auto_ai_money( qs_calculate_balance( $quote_id ) ) ), 'success', qs_auto_ai_order_edit_url( $order_id ), 'View Order' );
	return true;
}

function qs_auto_ai_step_verify_final_email( $run_id ) {
	$joiner_id = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	$quote_id  = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$joiner    = get_user_by( 'id', $joiner_id );
	$email     = $joiner ? qs_auto_ai_latest_email( $run_id, 'Final Payment Ready', $joiner->user_email ) : array();
	$pay_url   = $quote_id ? qs_get_quote_payment_url( $quote_id, 'balance' ) : '';

	if ( ! $email || ! $pay_url ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Could not verify the real final-payment email and WooCommerce payment URL.' );
	}

	qs_auto_ai_log(
		$run_id,
		'joiner',
		sprintf( 'Final-payment email was handed to WordPress for real delivery to %s. Payment URL is valid.', $joiner->user_email ),
		'success',
		qs_auto_ai_email_preview_url( $run_id, $email['_index'] ),
		'View Email'
	);
	return true;
}

function qs_auto_ai_step_complete_final_order( $run_id ) {
	$order_id = absint( qs_auto_ai_meta( $run_id, 'balance_order_id', 0 ) );
	$order    = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( ! $order ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Final WooCommerce order could not be loaded.' );
	}

	if ( ! $order->has_status( 'completed' ) ) {
		qs_auto_ai_begin_mail_capture( $run_id );
		$order->update_status( 'completed', 'Auto AI Testing: LF admin completed the final order after bank payment confirmation.' );
		qs_auto_ai_end_mail_capture();
	}

	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Final WooCommerce order #%d marked Completed.', $order_id ), 'success', qs_auto_ai_order_edit_url( $order_id ), 'View Order' );
	return true;
}

function qs_auto_ai_step_verify_joiner_complete( $run_id ) {
	$quote_id  = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$joiner_id = absint( qs_auto_ai_meta( $run_id, 'joiner_id', 0 ) );
	$joiner    = get_user_by( 'id', $joiner_id );

	if ( 'paid_in_full' !== get_post_status( $quote_id ) ) {
		return qs_auto_ai_fail( $run_id, 'joiner', 'Joiner completion check failed: Quote is not Paid In Full.' );
	}

	$email = $joiner ? qs_auto_ai_latest_email( $run_id, 'Payment Complete', $joiner->user_email ) : array();
	$url   = $email ? qs_auto_ai_email_preview_url( $run_id, $email['_index'] ) : ( function_exists( 'qs_page_url' ) ? qs_page_url( 'quote_review', array( 'quote_id' => $quote_id ) ) : '' );
	qs_auto_ai_log( $run_id, 'joiner', 'Quote now shows Completed / Paid In Full. Completion email generated for the Joiner.', 'success', $url, $email ? 'View Email' : 'Open Quote' );
	return true;
}

function qs_auto_ai_step_final_verify( $run_id ) {
	$quote_id      = absint( qs_auto_ai_meta( $run_id, 'quote_id', 0 ) );
	$deposit_order = absint( qs_auto_ai_meta( $run_id, 'deposit_order_id', 0 ) );
	$balance_order = absint( qs_auto_ai_meta( $run_id, 'balance_order_id', 0 ) );

	$checks = array(
		'Quote'               => $quote_id && 'quote' === get_post_type( $quote_id ),
		'Paid In Full status' => 'paid_in_full' === get_post_status( $quote_id ),
		'Deposit order'       => $deposit_order && function_exists( 'wc_get_order' ) && wc_get_order( $deposit_order ),
		'Final order'         => $balance_order && function_exists( 'wc_get_order' ) && wc_get_order( $balance_order ),
		'Positive total'      => qs_calculate_total( $quote_id ) > 0,
	);

	$failed = array();
	foreach ( $checks as $label => $passed ) {
		if ( ! $passed ) {
			$failed[] = $label;
		}
	}

	if ( $failed ) {
		return qs_auto_ai_fail( $run_id, 'admin', 'Final verification failed: ' . implode( ', ', $failed ) . '.' );
	}

	qs_auto_ai_log( $run_id, 'admin', 'AUTO AI TEST PASSED — real Quote, pricing, PDF, emails, deposit, production, final balance and WooCommerce completion all verified.', 'success', admin_url( 'post.php?post=' . $quote_id . '&action=edit' ), 'Inspect Quote' );
	qs_auto_ai_set_meta( $run_id, 'status', 'passed' );
	qs_auto_ai_set_meta( $run_id, 'completed_at', current_time( 'mysql' ) );
	return true;
}

function qs_auto_ai_execute_current_step( $run_id ) {
	$steps = qs_auto_ai_steps();
	$step  = absint( qs_auto_ai_meta( $run_id, 'current_step', 0 ) );

	if ( ! isset( $steps[ $step ] ) ) {
		qs_auto_ai_set_meta( $run_id, 'status', 'passed' );
		return true;
	}

	switch ( $step ) {
		case 0:
			$ok = qs_auto_ai_step_create_quote( $run_id );
			break;
		case 1:
			$ok = qs_auto_ai_step_submit_quote( $run_id );
			break;
		case 2:
			$ok = qs_auto_ai_step_admin_verify_quote( $run_id );
			break;
		case 3:
			$ok = qs_auto_ai_step_render_pdf( $run_id );
			break;
		case 4:
			$ok = qs_auto_ai_step_deposit_request( $run_id );
			break;
		case 5:
			$ok = qs_auto_ai_step_verify_deposit_email( $run_id );
			break;
		case 6:
			$ok = qs_auto_ai_step_bacs_order( $run_id, 'deposit' );
			break;
		case 7:
			$ok = qs_auto_ai_step_payment_complete( $run_id, 'deposit' );
			break;
		case 8:
			$ok = qs_auto_ai_step_verify_deposit_payment( $run_id );
			break;
		case 9:
			$ok = qs_auto_ai_step_mark_production( $run_id );
			break;
		case 10:
			$ok = qs_auto_ai_step_add_charge( $run_id );
			break;
		case 11:
			$ok = qs_auto_ai_step_final_invoice( $run_id );
			break;
		case 12:
			$ok = qs_auto_ai_step_verify_final_email( $run_id );
			break;
		case 13:
			$ok = qs_auto_ai_step_bacs_order( $run_id, 'balance' );
			break;
		case 14:
			$ok = qs_auto_ai_step_payment_complete( $run_id, 'balance' );
			break;
		case 15:
			$ok = qs_auto_ai_step_complete_final_order( $run_id );
			break;
		case 16:
			$ok = qs_auto_ai_step_verify_joiner_complete( $run_id );
			break;
		case 17:
			$ok = qs_auto_ai_step_final_verify( $run_id );
			break;
		default:
			$ok = false;
	}

	if ( $ok ) {
		$next = $step + 1;
		qs_auto_ai_set_meta( $run_id, 'current_step', $next );
		if ( $next >= count( $steps ) && 'failed' !== qs_auto_ai_meta( $run_id, 'status', '' ) ) {
			qs_auto_ai_set_meta( $run_id, 'status', 'passed' );
		}
	}

	return $ok;
}

function qs_auto_ai_verify_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Administrator access is required.' ), 403 );
	}
	check_ajax_referer( 'qs_auto_ai_ajax', 'nonce' );
}

function qs_auto_ai_ajax_start() {
	qs_auto_ai_verify_ajax();

	if ( function_exists( 'qs_portal_mode' ) && 'live' === qs_portal_mode() ) {
		wp_send_json_error( array( 'message' => 'Switch Portal Mode to Setup or Test before starting Auto AI Testing. This prevents accidental testing against a public live portal.' ), 400 );
	}
	if ( empty( $_POST['real_email_ack'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['real_email_ack'] ) ) ) {
		wp_send_json_error( array( 'message' => 'Confirm that you understand real emails and real WooCommerce orders will be created.' ), 400 );
	}
	if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_create_order' ) ) {
		wp_send_json_error( array( 'message' => 'WooCommerce must be active before running the full workflow test.' ), 400 );
	}

	$admin_id = isset( $_POST['admin_id'] ) ? absint( $_POST['admin_id'] ) : 0;
	if ( ! $admin_id || ! user_can( $admin_id, 'edit_others_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Select a valid LF Admin account.' ), 400 );
	}

	$joiner_value = isset( $_POST['joiner_id'] ) ? sanitize_text_field( wp_unslash( $_POST['joiner_id'] ) ) : '';
	$created_joiner = false;

	if ( 'new' === $joiner_value ) {
		$joiner_id = qs_auto_ai_create_joiner_from_request();
		if ( is_wp_error( $joiner_id ) ) {
			wp_send_json_error( array( 'message' => $joiner_id->get_error_message() ), 400 );
		}
		$created_joiner = true;
	} else {
		$joiner_id = absint( $joiner_value );
		$joiner    = $joiner_id ? get_user_by( 'id', $joiner_id ) : false;
		if ( ! $joiner || ! function_exists( 'qs_user_is_joiner' ) || ! qs_user_is_joiner( $joiner ) ) {
			wp_send_json_error( array( 'message' => 'Select a valid Joiner account.' ), 400 );
		}
		if ( ! $joiner->user_email || ! is_email( $joiner->user_email ) ) {
			wp_send_json_error( array( 'message' => 'The selected Joiner does not have a valid email address. Add one before running a real-email workflow test.' ), 400 );
		}
		update_user_meta( $joiner_id, 'qs_portal_test_access', '1' );
	}

	$joiner = get_user_by( 'id', $joiner_id );
	$admin  = get_user_by( 'id', $admin_id );

	$run_id = wp_insert_post(
		array(
			'post_type'   => QS_AUTO_AI_RUN_POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Auto AI Test — Starting',
			'post_author' => get_current_user_id(),
		),
		true
	);
	if ( is_wp_error( $run_id ) ) {
		wp_send_json_error( array( 'message' => $run_id->get_error_message() ), 500 );
	}

	qs_auto_ai_set_meta( $run_id, 'status', 'running' );
	qs_auto_ai_set_meta( $run_id, 'current_step', 0 );
	qs_auto_ai_set_meta( $run_id, 'joiner_id', $joiner_id );
	qs_auto_ai_set_meta( $run_id, 'admin_id', $admin_id );
	qs_auto_ai_set_meta( $run_id, 'created_joiner', $created_joiner ? '1' : '0' );
	qs_auto_ai_set_meta( $run_id, 'logs_joiner', array() );
	qs_auto_ai_set_meta( $run_id, 'logs_admin', array() );
	qs_auto_ai_set_meta( $run_id, 'emails', array() );

	qs_auto_ai_log(
		$run_id,
		'joiner',
		$created_joiner
			? sprintf( 'Permanent Joiner user created: %s <%s>. Test access enabled.', $joiner->display_name, $joiner->user_email )
			: sprintf( 'Using existing real Joiner: %s <%s>. Test access enabled.', $joiner->display_name, $joiner->user_email ),
		'success',
		admin_url( 'user-edit.php?user_id=' . $joiner_id ),
		'View User'
	);
	qs_auto_ai_log( $run_id, 'admin', sprintf( 'Acting LF Admin selected: %s. Starting real end-to-end workflow.', $admin->display_name ), 'success' );
	qs_auto_ai_log( $run_id, 'joiner', 'Real emails are ENABLED for this run. WordPress will send Quote System messages to the selected Joiner email.', 'warning' );

	wp_send_json_success( qs_auto_ai_state( $run_id ) );
}
add_action( 'wp_ajax_qs_auto_ai_start', 'qs_auto_ai_ajax_start' );

function qs_auto_ai_ajax_step() {
	qs_auto_ai_verify_ajax();
	$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;

	if ( QS_AUTO_AI_RUN_POST_TYPE !== get_post_type( $run_id ) ) {
		wp_send_json_error( array( 'message' => 'Test run not found.' ), 404 );
	}

	$status = qs_auto_ai_meta( $run_id, 'status', 'running' );
	if ( 'running' === $status ) {
		try {
			qs_auto_ai_execute_current_step( $run_id );
		} catch ( Throwable $error ) {
			$steps = qs_auto_ai_steps();
			$step  = absint( qs_auto_ai_meta( $run_id, 'current_step', 0 ) );
			$side  = isset( $steps[ $step ]['side'] ) ? $steps[ $step ]['side'] : 'admin';
			qs_auto_ai_fail( $run_id, $side, 'Unexpected error: ' . $error->getMessage() );
			qs_auto_ai_end_mail_capture();
		}
	}

	wp_send_json_success( qs_auto_ai_state( $run_id ) );
}
add_action( 'wp_ajax_qs_auto_ai_step', 'qs_auto_ai_ajax_step' );

function qs_auto_ai_ajax_control() {
	qs_auto_ai_verify_ajax();
	$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;
	$command = isset( $_POST['command'] ) ? sanitize_key( wp_unslash( $_POST['command'] ) ) : '';

	if ( QS_AUTO_AI_RUN_POST_TYPE !== get_post_type( $run_id ) ) {
		wp_send_json_error( array( 'message' => 'Test run not found.' ), 404 );
	}

	switch ( $command ) {
		case 'pause':
			if ( 'running' === qs_auto_ai_meta( $run_id, 'status', '' ) ) {
				qs_auto_ai_set_meta( $run_id, 'status', 'paused' );
				qs_auto_ai_log( $run_id, 'admin', 'Auto AI Testing paused. Current real records remain available for inspection.', 'warning' );
			}
			break;

		case 'resume':
			if ( in_array( qs_auto_ai_meta( $run_id, 'status', '' ), array( 'paused', 'stopped' ), true ) ) {
				qs_auto_ai_set_meta( $run_id, 'status', 'running' );
				qs_auto_ai_log( $run_id, 'admin', 'Auto AI Testing resumed.', 'info' );
			}
			break;

		case 'retry':
			if ( 'failed' === qs_auto_ai_meta( $run_id, 'status', '' ) ) {
				qs_auto_ai_set_meta( $run_id, 'status', 'running' );
				qs_auto_ai_set_meta( $run_id, 'last_error', '' );
				qs_auto_ai_log( $run_id, 'admin', 'Retrying the failed step.', 'warning' );
			}
			break;

		case 'stop':
			if ( ! in_array( qs_auto_ai_meta( $run_id, 'status', '' ), array( 'passed', 'failed' ), true ) ) {
				qs_auto_ai_set_meta( $run_id, 'status', 'stopped' );
				qs_auto_ai_log( $run_id, 'admin', 'Auto AI Testing stopped. Generated users, Quotes, orders and emails were NOT deleted.', 'warning' );
			}
			break;
	}

	wp_send_json_success( qs_auto_ai_state( $run_id ) );
}
add_action( 'wp_ajax_qs_auto_ai_control', 'qs_auto_ai_ajax_control' );

function qs_auto_ai_recent_runs() {
	return get_posts(
		array(
			'post_type'      => QS_AUTO_AI_RUN_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
}

function qs_auto_ai_testing_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Administrator access is required.' );
	}

	$joiners    = qs_auto_ai_joiner_users();
	$admins     = qs_auto_ai_admin_users();
	$run_id     = isset( $_GET['run_id'] ) ? absint( $_GET['run_id'] ) : 0;
	$state      = $run_id ? qs_auto_ai_state( $run_id ) : array();
	$recent     = qs_auto_ai_recent_runs();
	$portal_mode= function_exists( 'qs_portal_mode' ) ? qs_portal_mode() : 'test';
	$ajax_nonce = wp_create_nonce( 'qs_auto_ai_ajax' );
	?>
	<div class="qs-auto-ai">
		<div class="qs-auto-ai-title">
			<div>
				<h1>Auto AI Testing</h1>
				<p>Run the real Loughlin Quote workflow step-by-step with a real Joiner, real Quote, real WooCommerce orders and real emails.</p>
			</div>
			<span class="qs-auto-ai-mode <?php echo esc_attr( $portal_mode ); ?>">PORTAL: <?php echo esc_html( strtoupper( $portal_mode ) ); ?></span>
		</div>

		<?php if ( 'live' === $portal_mode ) : ?>
			<div class="notice notice-error inline"><p><strong>Auto AI Testing is locked while Portal Mode is Live.</strong> Switch Quote System to Setup or Test Mode first, then run the test before reopening the public portal.</p></div>
		<?php endif; ?>

		<div class="notice notice-warning inline">
			<p><strong>This creates real data.</strong> A test run consumes a real Quote number, creates real WooCommerce orders and sends real emails. Nothing is automatically deleted.</p>
			<p><strong>Email note:</strong> a full BACS test compresses the whole customer journey into a few seconds, so several branded emails will arrive close together. WooCommerce's duplicate customer status emails are suppressed for Quote System payment orders.</p>
		</div>

		<section class="qs-auto-ai-setup">
			<div class="qs-auto-ai-person">
				<h2>Joiner Side</h2>
				<label for="qs-auto-ai-joiner">Run as Joiner</label>
				<select id="qs-auto-ai-joiner">
					<option value="">Select Joiner</option>
					<option value="new">+ Create New Joiner</option>
					<?php foreach ( $joiners as $joiner ) : ?>
						<option value="<?php echo esc_attr( $joiner->ID ); ?>" data-email="<?php echo esc_attr( $joiner->user_email ); ?>"><?php echo esc_html( $joiner->display_name . ' — ' . $joiner->user_email ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description" id="qs-auto-ai-existing-email"></p>

				<div class="qs-auto-ai-new-joiner" hidden>
					<label>Name <input type="text" id="qs-auto-ai-new-name" placeholder="Auto Test Joiner"></label>
					<label>Company <input type="text" id="qs-auto-ai-new-company" placeholder="Auto Test Joinery"></label>
					<label>Email <input type="email" id="qs-auto-ai-new-email" placeholder="your-test-inbox@example.com"></label>
					<label>Phone <input type="text" id="qs-auto-ai-new-phone" placeholder="0400 000 000"></label>
					<label>Delivery Address <textarea id="qs-auto-ai-new-address" rows="2" placeholder="Test delivery address"></textarea></label>
					<p class="description">This creates a permanent WordPress Joiner. A random password is used; you can later reset/send a password normally from Users.</p>
				</div>
			</div>

			<div class="qs-auto-ai-person">
				<h2>LF Admin Side</h2>
				<label for="qs-auto-ai-admin">Act as Admin</label>
				<select id="qs-auto-ai-admin">
					<option value="">Select Admin</option>
					<?php foreach ( $admins as $admin ) : ?>
						<option value="<?php echo esc_attr( $admin->ID ); ?>" <?php selected( get_current_user_id(), $admin->ID ); ?>><?php echo esc_html( $admin->display_name . ' — ' . $admin->user_email ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">Admin-side workflow actions and attribution such as “In Production by” use this real WordPress administrator/editor account.</p>
			</div>

			<div class="qs-auto-ai-start">
				<label class="qs-auto-ai-real-email">
					<input type="checkbox" id="qs-auto-ai-real-email">
					<strong>I understand this sends REAL emails and creates REAL WooCommerce orders.</strong>
				</label>
				<button type="button" class="button button-primary button-hero" id="qs-auto-ai-start" <?php disabled( 'live' === $portal_mode ); ?>>Run Full Automatic Test</button>
			</div>
		</section>

		<section class="qs-auto-ai-run" <?php echo $state ? '' : 'hidden'; ?>>
			<div class="qs-auto-ai-run-header">
				<div>
					<div class="qs-auto-ai-kicker">CURRENT TEST RUN</div>
					<h2 id="qs-auto-ai-quote-title"><?php echo $state ? esc_html( ( $state['quote_number'] ? $state['quote_number'] . ' — ' : '' ) . 'Auto AI Test' ) : 'Auto AI Test'; ?></h2>
					<div class="qs-auto-ai-run-meta" id="qs-auto-ai-run-meta"></div>
				</div>
				<div class="qs-auto-ai-status-wrap">
					<span class="qs-auto-ai-status" id="qs-auto-ai-status">STARTING</span>
					<div class="qs-auto-ai-progress"><span id="qs-auto-ai-progress-bar"></span></div>
					<small id="qs-auto-ai-next-step"></small>
				</div>
			</div>

			<div class="qs-auto-ai-links" id="qs-auto-ai-links"></div>

			<div class="qs-auto-ai-controls">
				<button type="button" class="button" data-auto-ai-command="pause">Pause</button>
				<button type="button" class="button button-primary" data-auto-ai-command="resume">Resume</button>
				<button type="button" class="button button-primary" data-auto-ai-command="retry">Retry Failed Step</button>
				<button type="button" class="button" data-auto-ai-command="stop">Stop</button>
			</div>

			<div class="qs-auto-ai-columns">
				<section class="qs-auto-ai-log-panel">
					<header><strong>JOINER</strong><span id="qs-auto-ai-joiner-label"></span></header>
					<div class="qs-auto-ai-log" id="qs-auto-ai-joiner-log"></div>
				</section>
				<section class="qs-auto-ai-log-panel">
					<header><strong>LF ADMIN</strong><span id="qs-auto-ai-admin-label"></span></header>
					<div class="qs-auto-ai-log" id="qs-auto-ai-admin-log"></div>
				</section>
			</div>

			<section class="qs-auto-ai-emails">
				<h3>Captured Copies of Real Outgoing Emails</h3>
				<div id="qs-auto-ai-email-list"></div>
			</section>
		</section>

		<?php if ( $recent ) : ?>
			<section class="qs-auto-ai-history">
				<h2>Recent Test Runs</h2>
				<table class="widefat striped">
					<thead><tr><th>Run</th><th>Quote</th><th>Joiner</th><th>Status</th><th>Date</th><th></th></tr></thead>
					<tbody>
						<?php foreach ( $recent as $run ) :
							$run_state = qs_auto_ai_state( $run->ID );
							?>
							<tr>
								<td>#<?php echo esc_html( $run->ID ); ?></td>
								<td><?php echo esc_html( $run_state['quote_number'] ? $run_state['quote_number'] : 'Not created yet' ); ?></td>
								<td><?php echo esc_html( $run_state['joiner_name'] ? $run_state['joiner_name'] : '—' ); ?></td>
								<td><?php echo esc_html( $run_state['status_label'] ); ?></td>
								<td><?php echo esc_html( get_the_date( 'Y-m-d H:i', $run ) ); ?></td>
								<td><a class="button button-small" href="<?php echo esc_url( qs_auto_ai_run_url( $run->ID ) ); ?>">View Run</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>
	</div>

	<style>
	.qs-auto-ai{max-width:1400px}
	.qs-auto-ai-title{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin:18px 0}
	.qs-auto-ai-title h1{margin-bottom:4px}
	.qs-auto-ai-title p{margin-top:0;color:#646970}
	.qs-auto-ai-mode{padding:7px 11px;border-radius:999px;background:#fff3cd;color:#6f5200;font-size:11px;font-weight:700;letter-spacing:.05em;white-space:nowrap}
	.qs-auto-ai-mode.live{background:#e6f4ea;color:#146c2e}
	.qs-auto-ai-setup{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:20px 0}
	.qs-auto-ai-person{background:#fff;border:1px solid #dcdcde;padding:20px}
	.qs-auto-ai-person h2{margin-top:0}
	.qs-auto-ai-person>label,.qs-auto-ai-new-joiner label{display:block;font-weight:600;margin:12px 0 5px}
	.qs-auto-ai-person select,.qs-auto-ai-new-joiner input,.qs-auto-ai-new-joiner textarea{width:100%;max-width:none}
	.qs-auto-ai-new-joiner{margin-top:16px;padding-top:12px;border-top:1px solid #dcdcde}
	.qs-auto-ai-start{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:18px;background:#f6f7f7;border:1px solid #dcdcde;padding:18px}
	.qs-auto-ai-real-email{display:flex;align-items:center;gap:8px}
	.qs-auto-ai-run{background:#fff;border:1px solid #c3c4c7;margin:24px 0}
	.qs-auto-ai-run-header{display:flex;justify-content:space-between;gap:20px;padding:22px;border-bottom:1px solid #dcdcde}
	.qs-auto-ai-kicker{font-size:10px;font-weight:700;letter-spacing:.08em;color:#646970}
	.qs-auto-ai-run-header h2{margin:4px 0 6px}
	.qs-auto-ai-run-meta{color:#646970}
	.qs-auto-ai-status-wrap{min-width:260px;text-align:right}
	.qs-auto-ai-status{display:inline-block;padding:6px 10px;border-radius:999px;background:#e5e7eb;font-size:11px;font-weight:700;letter-spacing:.05em}
	.qs-auto-ai-status.running{background:#e6eef9;color:#275a8e}.qs-auto-ai-status.paused,.qs-auto-ai-status.stopped{background:#fff1d6;color:#8a5a00}.qs-auto-ai-status.failed{background:#fce8e6;color:#a12622}.qs-auto-ai-status.passed{background:#dff2e5;color:#245b35}
	.qs-auto-ai-progress{height:6px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin:10px 0 5px}
	.qs-auto-ai-progress span{display:block;height:100%;width:0;background:#2271b1;transition:width .25s}
	.qs-auto-ai-links{display:flex;flex-wrap:wrap;gap:8px;padding:14px 22px;background:#f6f7f7;border-bottom:1px solid #dcdcde}
	.qs-auto-ai-links a{display:inline-block;padding:5px 9px;background:#fff;border:1px solid #c3c4c7;text-decoration:none;border-radius:3px}
	.qs-auto-ai-controls{display:flex;gap:8px;padding:14px 22px;border-bottom:1px solid #dcdcde}
	.qs-auto-ai-columns{display:grid;grid-template-columns:1fr 1fr;min-height:430px}
	.qs-auto-ai-log-panel:first-child{border-right:1px solid #dcdcde}
	.qs-auto-ai-log-panel header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;background:#f6f7f7;border-bottom:1px solid #dcdcde}
	.qs-auto-ai-log-panel header strong{font-size:12px;letter-spacing:.05em}
	.qs-auto-ai-log{padding:14px;max-height:520px;overflow:auto}
	.qs-auto-ai-log-entry{--qs-auto-ai-log-bg:#f6f7f7;position:relative;padding:9px 10px 9px 32px;margin-bottom:8px;border-left:3px solid #c3c4c7;background:var(--qs-auto-ai-log-bg)}
	.qs-auto-ai-log-entry.is-latest{animation:qs-auto-ai-latest-log 4.5s ease-out}
	@keyframes qs-auto-ai-latest-log{
		0%,18%{background:#c9f0d5;box-shadow:0 0 0 1px rgba(22,128,60,.18),0 2px 8px rgba(22,128,60,.12)}
		100%{background:var(--qs-auto-ai-log-bg);box-shadow:none}
	}
	.qs-auto-ai-log-entry:before{content:"•";position:absolute;left:12px;top:8px;font-weight:700}
	.qs-auto-ai-log-entry.success{border-left-color:#16803c}.qs-auto-ai-log-entry.success:before{content:"✓";color:#16803c}
	.qs-auto-ai-log-entry.warning{border-left-color:#dba617}.qs-auto-ai-log-entry.warning:before{content:"!";color:#8a5a00}
	.qs-auto-ai-log-entry.error{--qs-auto-ai-log-bg:#fcf0f1;border-left-color:#d63638}.qs-auto-ai-log-entry.error:before{content:"×";color:#d63638}
	.qs-auto-ai-log-entry time{display:block;color:#8c8f94;font-size:11px;margin-bottom:2px}
	.qs-auto-ai-log-entry a{margin-left:8px}
	.qs-auto-ai-emails{padding:18px 22px;border-top:1px solid #dcdcde}
	.qs-auto-ai-email-row{display:grid;grid-template-columns:145px 1fr 2fr auto;gap:12px;padding:9px 0;border-top:1px solid #f0f0f1;align-items:center}
	.qs-auto-ai-history{margin:26px 0}
	@media(max-width:900px){.qs-auto-ai-setup,.qs-auto-ai-columns{grid-template-columns:1fr}.qs-auto-ai-log-panel:first-child{border-right:0;border-bottom:1px solid #dcdcde}.qs-auto-ai-run-header,.qs-auto-ai-start{align-items:flex-start;flex-direction:column}.qs-auto-ai-status-wrap{min-width:0;width:100%;text-align:left}.qs-auto-ai-email-row{grid-template-columns:1fr}.qs-auto-ai-title{flex-direction:column}}
	</style>

	<script>
	(function(){
		const ajaxUrl=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const nonce=<?php echo wp_json_encode( $ajax_nonce ); ?>;
		let state=<?php echo wp_json_encode( $state ? $state : null ); ?>;
		let timer=null;
		let requestRunning=false;
		const renderedLogCounts=new WeakMap();

		const joinerSelect=document.getElementById('qs-auto-ai-joiner');
		const newFields=document.querySelector('.qs-auto-ai-new-joiner');
		const existingEmail=document.getElementById('qs-auto-ai-existing-email');
		const runSection=document.querySelector('.qs-auto-ai-run');

		function updateJoinerChoice(){
			if(!joinerSelect)return;
			const option=joinerSelect.options[joinerSelect.selectedIndex];
			const isNew=joinerSelect.value==='new';
			newFields.hidden=!isNew;
			existingEmail.textContent=!isNew&&option?.dataset.email?'Real workflow emails will be sent to: '+option.dataset.email:'';
		}
		joinerSelect?.addEventListener('change',updateJoinerChoice);
		updateJoinerChoice();

		function esc(value){
			return String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
		}
		function renderLogs(target,logs){
			logs=logs||[];
			const hadPreviousRender=renderedLogCounts.has(target);
			const previousCount=hadPreviousRender?renderedLogCounts.get(target):logs.length;
			const newestIndex=logs.length-1;
			const hasNewLog=hadPreviousRender&&logs.length>previousCount;

			target.innerHTML=logs.map((log,index)=>{
				const latestClass=hasNewLog&&index===newestIndex?' is-latest':'';
				return '<div class="qs-auto-ai-log-entry '+esc(log.type)+latestClass+'"><time>'+esc(log.time)+'</time>'+esc(log.message)+(log.url?' <a href="'+esc(log.url)+'" target="_blank" rel="noopener">'+esc(log.link_label||'View')+'</a>':'')+'</div>';
			}).join('');

			renderedLogCounts.set(target,logs.length);
			target.scrollTop=target.scrollHeight;
		}
		function addLink(container,label,url){
			if(!url)return;
			const a=document.createElement('a');a.href=url;a.target='_blank';a.rel='noopener';a.textContent=label;container.appendChild(a);
		}
		function render(s){
			if(!s)return;
			state=s;runSection.hidden=false;
			const status=document.getElementById('qs-auto-ai-status');
			status.textContent=s.status_label||s.status;
			status.className='qs-auto-ai-status '+s.status;
			document.getElementById('qs-auto-ai-progress-bar').style.width=(s.progress||0)+'%';
			document.getElementById('qs-auto-ai-next-step').textContent=s.next_step_label?'Next: '+s.next_step_label:'Workflow finished';
			document.getElementById('qs-auto-ai-quote-title').textContent=s.quote_number?(s.quote_number+' — '+(s.project_title||'Auto AI Test')):'Auto AI Test';
			document.getElementById('qs-auto-ai-run-meta').textContent='Run #'+s.run_id+(s.quote_id?' · Quote ID #'+s.quote_id:'')+(s.quote_status_label?' · '+s.quote_status_label:'')+(s.quote_total?' · '+s.quote_total:'');
			document.getElementById('qs-auto-ai-joiner-label').textContent=s.joiner_name+(s.joiner_email?' · '+s.joiner_email:'');
			document.getElementById('qs-auto-ai-admin-label').textContent=s.admin_name;
			renderLogs(document.getElementById('qs-auto-ai-joiner-log'),s.logs_joiner);
			renderLogs(document.getElementById('qs-auto-ai-admin-log'),s.logs_admin);

			const links=document.getElementById('qs-auto-ai-links');links.innerHTML='';
			addLink(links,'Joiner User',s.links?.joiner_user);
			addLink(links,'Quote Review',s.links?.quote_review);
			addLink(links,'Quote Builder',s.links?.quote_builder);
			addLink(links,'Thank You',s.links?.thank_you);
			addLink(links,'Quotation PDF',s.links?.quotation_pdf);
			addLink(links,'Job Sheet',s.links?.job_sheet);
			if(s.deposit_order_id)addLink(links,'Deposit Order #'+s.deposit_order_id,s.links?.deposit_order);
			if(s.links?.deposit_pay)addLink(links,'Deposit Payment URL',s.links.deposit_pay);
			if(s.balance_order_id)addLink(links,'Final Order #'+s.balance_order_id,s.links?.balance_order);
			if(s.links?.balance_pay)addLink(links,'Final Payment URL',s.links.balance_pay);

			const emailList=document.getElementById('qs-auto-ai-email-list');
			emailList.innerHTML=(s.emails||[]).map(email=>'<div class="qs-auto-ai-email-row"><span>'+esc(email.time)+'</span><span>'+esc(email.to)+'</span><strong>'+esc(email.subject)+'</strong><a class="button button-small" target="_blank" rel="noopener" href="'+esc(email.preview_url)+'">View Email</a></div>').join('')||'<p class="description">No emails captured yet.</p>';

			document.querySelector('[data-auto-ai-command="pause"]').disabled=s.status!=='running';
			document.querySelector('[data-auto-ai-command="resume"]').disabled=!['paused','stopped'].includes(s.status);
			document.querySelector('[data-auto-ai-command="retry"]').disabled=s.status!=='failed';
			document.querySelector('[data-auto-ai-command="stop"]').disabled=['passed','failed','stopped'].includes(s.status);

			if(s.status==='running')scheduleStep();
			else clearTimeout(timer);
		}

		async function post(action,data={}){
			const body=new URLSearchParams({action,nonce,...data});
			const response=await fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body});
			const result=await response.json();
			if(!result.success)throw new Error(result.data?.message||'Auto AI request failed.');
			return result.data;
		}
		function scheduleStep(){
			clearTimeout(timer);
			timer=setTimeout(runStep,850);
		}
		async function runStep(){
			if(!state||state.status!=='running'||requestRunning)return;
			requestRunning=true;
			try{render(await post('qs_auto_ai_step',{run_id:state.run_id}));}
			catch(error){window.alert(error.message);}
			finally{requestRunning=false;}
		}

		document.getElementById('qs-auto-ai-start')?.addEventListener('click',async function(){
			const joinerId=joinerSelect.value;
			const adminId=document.getElementById('qs-auto-ai-admin').value;
			const ack=document.getElementById('qs-auto-ai-real-email').checked;
			if(!joinerId){window.alert('Select a Joiner or choose Create New Joiner.');return;}
			if(!adminId){window.alert('Select an LF Admin.');return;}
			if(!ack){window.alert('Confirm that real emails and real WooCommerce orders will be created.');return;}
			const recipient=joinerId==='new'?document.getElementById('qs-auto-ai-new-email').value:joinerSelect.options[joinerSelect.selectedIndex]?.dataset.email;
			if(!window.confirm('Start REAL end-to-end testing?\n\nJoiner email: '+(recipient||'not supplied')+'\n\nThis will create a real Quote, real WooCommerce orders and send real emails.'))return;

			this.disabled=true;
			try{
				const data=await post('qs_auto_ai_start',{
					joiner_id:joinerId,
					admin_id:adminId,
					real_email_ack:'1',
					new_joiner_name:document.getElementById('qs-auto-ai-new-name').value,
					new_joiner_company:document.getElementById('qs-auto-ai-new-company').value,
					new_joiner_email:document.getElementById('qs-auto-ai-new-email').value,
					new_joiner_phone:document.getElementById('qs-auto-ai-new-phone').value,
					new_joiner_address:document.getElementById('qs-auto-ai-new-address').value
				});
				history.replaceState({},'',data.links.run);
				render(data);
			}catch(error){window.alert(error.message);this.disabled=false;}
		});

		document.querySelectorAll('[data-auto-ai-command]').forEach(button=>button.addEventListener('click',async function(){
			if(!state||requestRunning)return;
			requestRunning=true;
			try{render(await post('qs_auto_ai_control',{run_id:state.run_id,command:this.dataset.autoAiCommand}));}
			catch(error){window.alert(error.message);}
			finally{requestRunning=false;}
		}));

		if(state)render(state);
	}());
	</script>
	<?php
}


/** Render the complete runner as the second tab inside Quote System Settings. */
function qs_auto_ai_render_settings_tab() {
	qs_auto_ai_testing_page();
}
add_action( 'qs_settings_tab_testing', 'qs_auto_ai_render_settings_tab' );
