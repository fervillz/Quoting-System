<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include QS_PATH . 'templates/email-header.php';

$quote_number  = get_post_meta( $quote_id, '_quote_number', true );
$customer_name = get_post_meta( $quote_id, '_customer_name', true );
$is_deposit    = 'deposit' === $payment_type;
$label         = $is_deposit ? 'Deposit Payment' : 'Final Balance Payment';
?>
<h2 style="margin:0 0 18px;color:#43586a;"><?php echo esc_html( $label . ' Ready' ); ?></h2>

<p>Hi <strong><?php echo esc_html( $customer_name ); ?></strong>,</p>

<p>
	<?php echo esc_html(
		$is_deposit
			? 'Your quote has been reviewed and your 30% deposit is ready for payment.'
			: 'Your final balance is now ready for payment.'
	); ?>
</p>

<p><strong>Quote Number:</strong> <?php echo esc_html( $quote_number ); ?></p>
<p><strong>Amount Due:</strong> $<?php echo esc_html( number_format_i18n( (float) $payment_amount, 2 ) ); ?> AUD</p>

<p style="margin:28px 0 20px;">
	<a href="<?php echo esc_url( $payment_url ); ?>" style="display:inline-block;padding:13px 22px;background:#4e1625;color:#ffffff;text-decoration:none;border-radius:4px;">
		<?php echo esc_html( $is_deposit ? 'Pay Deposit' : 'Pay Final Balance' ); ?>
	</a>
</p>

<p style="margin-bottom:0;">You will complete payment securely through the Loughlin Furniture checkout.</p>

<?php include QS_PATH . 'templates/email-footer.php'; ?>
