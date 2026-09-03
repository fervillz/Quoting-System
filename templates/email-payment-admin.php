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
<div style="max-width:600px;margin:auto;font-family:Arial,sans-serif;color:#43586a;">
	<div style="background:#4e1625;color:#fff;padding:30px;">
		<h1 style="margin:0;">Loughlin Furniture</h1>
	</div>
	<div style="padding:30px;border:1px solid #eee;">
		<h2><?php echo esc_html( $is_deposit ? 'Deposit Payment Received' : 'Final Payment Received' ); ?></h2>
		<p>A WooCommerce payment has been confirmed for this quote.</p>

		<p><strong>Quote:</strong> <?php echo esc_html( $quote_number ); ?></p>
		<p><strong>Company:</strong> <?php echo esc_html( $company_name ); ?></p>
		<p><strong>Customer:</strong> <?php echo esc_html( $customer_name ); ?></p>
		<p><strong>WooCommerce Order:</strong> #<?php echo esc_html( absint( $order_id ) ); ?></p>

		<p style="margin-top:28px;">
			<a href="<?php echo esc_url( $review_url ); ?>" style="display:inline-block;padding:12px 22px;background:#4e1625;color:#fff;text-decoration:none;border-radius:3px;">Open Quote</a>
		</p>
	</div>
</div>
<?php include QS_PATH . 'templates/email-footer.php'; ?>
