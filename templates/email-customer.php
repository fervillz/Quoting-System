<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include QS_PATH . 'templates/email-header.php';

$quote_number  = get_post_meta( $quote_id, '_quote_number', true );
$customer_name = get_post_meta( $quote_id, '_customer_name', true );
$payment_url   = qs_get_quote_payment_url( $quote_id, 'deposit' );
?>
<h2 style="margin:0 0 18px;color:#43586a;">Quote Approved</h2>

<p>Hi <strong><?php echo esc_html( $customer_name ); ?></strong>,</p>
<p>Your quotation has been approved.</p>
<p><strong>Quote Number:</strong> <?php echo esc_html( $quote_number ); ?></p>

<?php if ( $payment_url ) : ?>
	<p style="margin:24px 0 0;">
		<a href="<?php echo esc_url( $payment_url ); ?>" style="display:inline-block;padding:12px 22px;background:#4e1625;color:#ffffff;text-decoration:none;border-radius:4px;">Pay Deposit</a>
	</p>
<?php else : ?>
	<p>A deposit invoice will be issued shortly.</p>
<?php endif; ?>

<?php include QS_PATH . 'templates/email-footer.php'; ?>
