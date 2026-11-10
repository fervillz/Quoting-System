<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include QS_PATH . 'templates/email-header.php';

$quote_number  = get_post_meta( $quote_id, '_quote_number', true );
$customer_name = get_post_meta( $quote_id, '_customer_name', true );
$my_quotes_url = site_url( '/my-quotes/' );
$received_date = wp_date( 'd M Y', (int) $received_at );
?>
<h2 style="margin:0 0 18px;color:#43586a;">Deposit Received</h2>

<p>Hi <strong><?php echo esc_html( $customer_name ); ?></strong>,</p>
<p>We’ve received your deposit for quote <strong><?php echo esc_html( $quote_number ); ?></strong>. This email confirms your deposit payment.</p>

<p><strong>Deposit Received:</strong> $<?php echo esc_html( number_format_i18n( (float) $deposit_amount, 2 ) ); ?> AUD</p>
<p><strong>Date Received:</strong> <?php echo esc_html( $received_date ); ?></p>
<p><strong>Quote Total:</strong> $<?php echo esc_html( number_format_i18n( (float) $total_amount, 2 ) ); ?> AUD</p>
<p><strong>Remaining Balance:</strong> $<?php echo esc_html( number_format_i18n( (float) $remaining_balance, 2 ) ); ?> AUD</p>
<?php if ( ! empty( $order_id ) ) : ?>
	<p><strong>Payment Reference:</strong> #<?php echo esc_html( absint( $order_id ) ); ?></p>
<?php endif; ?>

<p>The Loughlin Furniture team will continue with the next stage of your order.</p>

<p style="margin:28px 0 0;">
	<a href="<?php echo esc_url( $my_quotes_url ); ?>" style="display:inline-block;padding:12px 22px;background:#4e1625;color:#ffffff;text-decoration:none;border-radius:4px;">View My Quotes</a>
</p>

<?php include QS_PATH . 'templates/email-footer.php'; ?>
