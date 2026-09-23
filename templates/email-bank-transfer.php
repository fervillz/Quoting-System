<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include QS_PATH . 'templates/email-header.php';

$quote_number  = get_post_meta( $quote_id, '_quote_number', true );
$customer_name = get_post_meta( $quote_id, '_customer_name', true );
$is_deposit    = 'deposit' === $payment_type;
$stage_label   = $is_deposit ? 'Deposit' : 'Final Balance';
?>
<h2 style="margin:0 0 18px;color:#43586a;">Bank Transfer Instructions</h2>

<p>Hi <strong><?php echo esc_html( $customer_name ); ?></strong>,</p>

<p>
	You selected Direct Bank Transfer for your <?php echo esc_html( strtolower( $stage_label ) ); ?> payment.
	Please use the bank details below to complete the transfer.
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:20px 0 26px;">
	<tr>
		<td style="padding:9px 0;border-bottom:1px solid #eeeeee;"><strong>Quote Number</strong></td>
		<td style="padding:9px 0;border-bottom:1px solid #eeeeee;text-align:right;"><?php echo esc_html( $quote_number ); ?></td>
	</tr>
	<tr>
		<td style="padding:9px 0;border-bottom:1px solid #eeeeee;"><strong>Payment Stage</strong></td>
		<td style="padding:9px 0;border-bottom:1px solid #eeeeee;text-align:right;"><?php echo esc_html( $stage_label ); ?></td>
	</tr>
	<tr>
		<td style="padding:9px 0;border-bottom:1px solid #eeeeee;"><strong>WooCommerce Order</strong></td>
		<td style="padding:9px 0;border-bottom:1px solid #eeeeee;text-align:right;">#<?php echo esc_html( absint( $order_id ) ); ?></td>
	</tr>
	<tr>
		<td style="padding:9px 0;border-bottom:1px solid #eeeeee;"><strong>Amount Due</strong></td>
		<td style="padding:9px 0;border-bottom:1px solid #eeeeee;text-align:right;">$<?php echo esc_html( number_format_i18n( (float) $payment_amount, 2 ) ); ?> AUD</td>
	</tr>
</table>

<?php if ( $gateway_instructions ) : ?>
	<div style="margin:0 0 22px;"><?php echo wp_kses_post( wpautop( $gateway_instructions ) ); ?></div>
<?php endif; ?>

<?php if ( $bank_accounts ) : ?>
	<h3 style="margin:0 0 12px;color:#43586a;">Bank Details</h3>

	<?php foreach ( $bank_accounts as $account ) :
		$account_name   = isset( $account['account_name'] ) ? $account['account_name'] : '';
		$account_number = isset( $account['account_number'] ) ? $account['account_number'] : '';
		$bank_name      = isset( $account['bank_name'] ) ? $account['bank_name'] : '';
		$sort_code      = isset( $account['sort_code'] ) ? $account['sort_code'] : '';
		$iban           = isset( $account['iban'] ) ? $account['iban'] : '';
		$bic            = isset( $account['bic'] ) ? $account['bic'] : '';
		?>
		<table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 22px;background:#f7f7f7;">
			<?php if ( $account_name ) : ?>
				<tr><td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;"><strong>Account Name</strong></td><td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;text-align:right;"><?php echo esc_html( $account_name ); ?></td></tr>
			<?php endif; ?>
			<?php if ( $bank_name ) : ?>
				<tr><td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;"><strong>Bank</strong></td><td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;text-align:right;"><?php echo esc_html( $bank_name ); ?></td></tr>
			<?php endif; ?>
			<?php if ( $account_number ) : ?>
				<tr><td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;"><strong>Account Number</strong></td><td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;text-align:right;"><?php echo esc_html( $account_number ); ?></td></tr>
			<?php endif; ?>
			<?php if ( $sort_code ) : ?>
				<tr><td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;"><strong>BSB / Sort Code</strong></td><td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;text-align:right;"><?php echo esc_html( $sort_code ); ?></td></tr>
			<?php endif; ?>
			<?php if ( $iban ) : ?>
				<tr><td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;"><strong>IBAN</strong></td><td style="padding:9px 12px;border-bottom:1px solid #e6e6e6;text-align:right;"><?php echo esc_html( $iban ); ?></td></tr>
			<?php endif; ?>
			<?php if ( $bic ) : ?>
				<tr><td style="padding:9px 12px;"><strong>BIC / SWIFT</strong></td><td style="padding:9px 12px;text-align:right;"><?php echo esc_html( $bic ); ?></td></tr>
			<?php endif; ?>
		</table>
	<?php endforeach; ?>
<?php else : ?>
	<p><strong>Bank details are not currently configured in WooCommerce.</strong> Please contact Loughlin Furniture before making the transfer.</p>
<?php endif; ?>

<p style="margin-bottom:0;">
	<strong>Payment reference:</strong> <?php echo esc_html( $quote_number . ' / Order #' . absint( $order_id ) ); ?>
</p>

<?php include QS_PATH . 'templates/email-footer.php'; ?>
