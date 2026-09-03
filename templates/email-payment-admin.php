<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include QS_PATH . 'templates/email-header.php';

$quote_number  = get_post_meta( $quote_id, '_quote_number', true );
$company_name  = get_post_meta( $quote_id, '_company_name', true );
$customer_name = get_post_meta( $quote_id, '_customer_name', true );
$review_url    = add_query_arg( 'quote_id', $quote_id, site_url( '/quote-review/' ) );
$is_deposit    = 'deposit' === $payment_type;
?>
<h2 style="margin:0 0 18px;color:#43586a;"><?php echo esc_html( $is_deposit ? 'Deposit Payment Received' : 'Final Payment Received' ); ?></h2>

<p>A WooCommerce payment has been confirmed for this quote.</p>
<p><strong>Quote:</strong> <?php echo esc_html( $quote_number ); ?></p>
<p><strong>Company:</strong> <?php echo esc_html( $company_name ); ?></p>
<p><strong>Customer:</strong> <?php echo esc_html( $customer_name ); ?></p>
<p><strong>WooCommerce Order:</strong> #<?php echo esc_html( absint( $order_id ) ); ?></p>

<p style="margin:28px 0 0;">
	<a href="<?php echo esc_url( $review_url ); ?>" style="display:inline-block;padding:12px 22px;background:#4e1625;color:#ffffff;text-decoration:none;border-radius:4px;">Open Quote</a>
</p>

<?php include QS_PATH . 'templates/email-footer.php'; ?>
