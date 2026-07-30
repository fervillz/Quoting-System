<?php
/**
 * Payment verification helpers for the frontend quote-management dashboard.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the WooCommerce order attached to a quote payment stage.
 */
function qs_payment_priority_get_order( $quote_id, $payment_type ) {
	if ( ! function_exists( 'wc_get_order' ) || ! in_array( $payment_type, array( 'deposit', 'balance' ), true ) ) {
		return false;
	}

	$order_id = absint( get_post_meta( $quote_id, '_qs_' . $payment_type . '_order_id', true ) );
	return $order_id ? wc_get_order( $order_id ) : false;
}

/**
 * Builds the secondary payment badge and dashboard priority for a quote.
 */
function qs_payment_priority_state( $quote_id ) {
	static $cache = array();

	$quote_id = absint( $quote_id );
	if ( isset( $cache[ $quote_id ] ) ) {
		return $cache[ $quote_id ];
	}

	$status = get_post_status( $quote_id );
	$state  = array(
		'label'              => '',
		'class'              => '',
		'priority'           => 60,
		'needs_verification' => false,
		'payment_type'       => '',
		'confirm_action'     => '',
		'confirm_label'      => '',
		'confirm_message'    => '',
		'nonce'              => '',
	);

	$priorities = array(
		'pending'          => 10,
		'pending_review'   => 10,
		'awaiting_deposit' => 20,
		'deposit_paid'     => 30,
		'final_balance'    => 30,
		'paid_in_full'     => 40,
		'draft'            => 50,
	);
	if ( isset( $priorities[ $status ] ) ) {
		$state['priority'] = $priorities[ $status ];
	}

	if ( 'deposit_paid' === $status ) {
		$state['label']        = 'Deposit Received';
		$state['class']        = 'received';
		$state['payment_type'] = 'deposit';
	} elseif ( 'paid_in_full' === $status ) {
		$state['label']        = 'Payment Received';
		$state['class']        = 'received';
		$state['payment_type'] = 'balance';
	} elseif ( in_array( $status, array( 'awaiting_deposit', 'final_balance' ), true ) ) {
		$payment_type = 'awaiting_deposit' === $status ? 'deposit' : 'balance';
		$order        = qs_payment_priority_get_order( $quote_id, $payment_type );

		$state['payment_type'] = $payment_type;
		if ( $order ) {
			$order_status = $order->get_status();
			$method       = $order->get_payment_method();
			$is_paid      = method_exists( $order, 'is_paid' ) && $order->is_paid();

			if ( 'bacs' === $method && 'on-hold' === $order_status ) {
				$state['label']              = 'Payment to Verify';
				$state['class']              = 'verify';
				$state['priority']           = 0;
				$state['needs_verification'] = true;
				$state['confirm_action']     = 'deposit' === $payment_type ? 'mark_deposit_paid' : 'mark_balance_paid';
				$state['confirm_label']      = 'deposit' === $payment_type ? 'Confirm Deposit Received' : 'Confirm Final Payment Received';
				$state['confirm_message']    = 'Confirm that the bank transfer has been received and complete the WooCommerce order?';
				$state['nonce']              = wp_create_nonce( 'qs_admin_quote_action_' . $quote_id );
			} elseif ( $is_paid || in_array( $order_status, array( 'processing', 'completed' ), true ) ) {
				$state['label'] = 'deposit' === $payment_type ? 'Deposit Received' : 'Payment Received';
				$state['class'] = 'received';
			} elseif ( in_array( $order_status, array( 'failed', 'cancelled', 'refunded' ), true ) ) {
				$state['label'] = 'Payment Failed';
				$state['class'] = 'failed';
			} else {
				$state['label'] = 'deposit' === $payment_type ? 'Awaiting Deposit' : 'Awaiting Final Payment';
				$state['class'] = 'awaiting';
			}
		} elseif ( 'final_balance' === $status ) {
			$state['label'] = 'Deposit Received';
			$state['class'] = 'received';
		}
	}

	$cache[ $quote_id ] = $state;
	return $state;
}

/**
 * Completes a quote payment order and synchronises the quote workflow.
 */
function qs_payment_priority_confirm_order( $quote_id, $payment_type ) {
	$order = qs_payment_priority_get_order( $quote_id, $payment_type );
	if ( ! $order ) {
		return new WP_Error( 'payment_order_missing', 'The related WooCommerce order could not be found.' );
	}

	$user_id      = get_current_user_id();
	$confirmed_at = current_time( 'mysql' );
	$note         = sprintf(
		'%s manually confirmed from Quote Management by user #%d.',
		'deposit' === $payment_type ? 'Deposit payment' : 'Final payment',
		$user_id
	);

	$order->update_meta_data( '_qs_manually_confirmed_by', $user_id );
	$order->update_meta_data( '_qs_manually_confirmed_at', $confirmed_at );
	$order->save();

	if ( ! method_exists( $order, 'is_paid' ) || ! $order->is_paid() ) {
		$order->payment_complete();
	}

	$order = wc_get_order( $order->get_id() );
	if ( $order && ! $order->has_status( 'completed' ) ) {
		$order->update_status( 'completed', $note );
	} elseif ( $order ) {
		$order->add_order_note( $note );
	}

	$quote_status = 'deposit' === $payment_type ? 'deposit_paid' : 'paid_in_full';
	qs_update_quote_status( $quote_id, $quote_status );
	update_post_meta( $quote_id, '_qs_' . $payment_type . '_manually_paid_by', $user_id );
	update_post_meta( $quote_id, '_qs_' . $payment_type . '_manually_paid_at', $confirmed_at );

	return $order ? $order->get_id() : 0;
}

/**
 * Intercepts the dashboard confirmation action so the WooCommerce order is
 * completed before the quote is advanced.
 */
function qs_payment_priority_handle_confirmation() {
	if ( empty( $_POST['qs_dashboard_action'] ) || empty( $_POST['quote_id'] ) ) {
		return;
	}

	$action_map = array(
		'mark_deposit_paid' => 'deposit',
		'mark_balance_paid' => 'balance',
	);
	$action     = sanitize_key( wp_unslash( $_POST['qs_dashboard_action'] ) );
	if ( ! isset( $action_map[ $action ] ) ) {
		return;
	}

	$quote_id = absint( $_POST['quote_id'] );
	$nonce    = isset( $_POST['qs_dashboard_action_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_dashboard_action_nonce'] ) ) : '';
	$quote    = get_post( $quote_id );
	if ( ! $quote || 'quote' !== $quote->post_type || ! current_user_can( 'edit_post', $quote_id ) || ! wp_verify_nonce( $nonce, 'qs_admin_quote_action_' . $quote_id ) ) {
		return;
	}

	$payment_type = $action_map[ $action ];
	$order        = qs_payment_priority_get_order( $quote_id, $payment_type );
	if ( ! $order && 'deposit' === $payment_type ) {
		// Preserve the legacy manual quote-only action when no order exists.
		return;
	}

	$result   = qs_payment_priority_confirm_order( $quote_id, $payment_type );
	$notice   = is_wp_error( $result ) ? 'payment_error' : ( 'deposit' === $payment_type ? 'deposit_received' : 'balance_received' );
	$redirect = wp_get_referer() ? wp_get_referer() : site_url( '/quote-admin-dashboard/' );
	$redirect = remove_query_arg( 'qs_payment_notice', $redirect );
	$redirect = add_query_arg( 'qs_payment_notice', $notice, $redirect );

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'template_redirect', 'qs_payment_priority_handle_confirmation', 1 );

/**
 * Detects the frontend administrator dashboard page.
 */
function qs_payment_priority_is_dashboard() {
	if ( ! is_singular() ) {
		return false;
	}

	$post = get_queried_object();
	return $post instanceof WP_Post && (
		has_shortcode( $post->post_content, 'quote_admin_dashboard' ) ||
		has_shortcode( $post->post_content, 'admin_dashboard' )
	);
}

/**
 * Supplies payment badges, priority counts and confirmation data to the UI.
 */
function qs_payment_priority_enqueue_assets() {
	if ( ! qs_payment_priority_is_dashboard() ) {
		return;
	}

	$statuses = array( 'draft', 'pending', 'pending_review', 'awaiting_deposit', 'deposit_paid', 'final_balance', 'paid_in_full' );
	$args     = array(
		'post_type'      => 'quote',
		'post_status'    => $statuses,
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);
	if ( function_exists( 'qs_admin_dashboard_visible_meta_query' ) ) {
		$args['meta_query'] = qs_admin_dashboard_visible_meta_query();
	}

	$states       = array();
	$verify_count = 0;
	foreach ( get_posts( $args ) as $quote_id ) {
		$state               = qs_payment_priority_state( $quote_id );
		$states[ $quote_id ]  = $state;
		if ( ! empty( $state['needs_verification'] ) ) {
			$verify_count++;
		}
	}

	$css_path = QS_PATH . 'assets/css/payment-priority.css';
	$js_path  = QS_PATH . 'assets/js/payment-priority.js';
	wp_enqueue_style(
		'qs-payment-priority',
		QS_URL . 'assets/css/payment-priority.css',
		array( 'qs-admin-dashboard' ),
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : QS_VERSION
	);
	wp_enqueue_script(
		'qs-payment-priority',
		QS_URL . 'assets/js/payment-priority.js',
		array(),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : QS_VERSION,
		true
	);
	wp_localize_script(
		'qs-payment-priority',
		'QSPaymentPriority',
		array(
			'states'       => $states,
			'verifyCount'  => $verify_count,
			'activeFilter' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'filterUrl'    => add_query_arg( 'status', 'payment_verify', site_url( '/quote-admin-dashboard/' ) ),
			'clearUrl'     => remove_query_arg( 'status', site_url( '/quote-admin-dashboard/' ) ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'qs_payment_priority_enqueue_assets', 10000 );

/**
 * Shows confirmation feedback on the dashboard or quote review page.
 */
function qs_payment_priority_confirmation_notice() {
	$notice = isset( $_GET['qs_payment_notice'] ) ? sanitize_key( wp_unslash( $_GET['qs_payment_notice'] ) ) : '';
	$labels = array(
		'deposit_received' => 'Deposit received. The WooCommerce order is completed and the quote is now approved.',
		'balance_received' => 'Final payment received. The WooCommerce order and quote are now completed.',
		'payment_error'    => 'The payment could not be confirmed because the related WooCommerce order was not available.',
	);
	if ( ! isset( $labels[ $notice ] ) ) {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded',function(){
		var root=document.querySelector('.qs-admin-dashboard, .qs-review-page');
		if(!root)return;
		var notice=document.createElement('div');
		notice.className='qs-admin-notice qs-payment-confirmation-notice';
		notice.textContent=<?php echo wp_json_encode( $labels[ $notice ] ); ?>;
		var header=root.querySelector('.qs-dashboard-page-header, .qs-review-page-header');
		if(header&&header.nextSibling){header.parentNode.insertBefore(notice,header.nextSibling);}else{root.insertBefore(notice,root.firstChild);}
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'qs_payment_priority_confirmation_notice', 99 );
