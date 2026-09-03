<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include QS_PATH . 'templates/email-header.php';

$quote_number   = get_post_meta( $quote_id, '_quote_number', true );
$project_name   = get_post_meta( $quote_id, '_project_name', true );
$company_name   = get_post_meta( $quote_id, '_company_name', true );
$customer_name  = get_post_meta( $quote_id, '_customer_name', true );
$customer_email = get_post_meta( $quote_id, '_customer_email', true );
$quote_review_url = add_query_arg( 'quote_id', $quote_id, site_url( '/quote-review/' ) );
?>
<h2 style="margin:0 0 18px;color:#43586a;">New Quote Submitted</h2>

<p style="margin:0 0 24px;">A new quote has been submitted and is ready for review.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 28px;">
	<tr>
		<td style="padding:10px 0;border-bottom:1px solid #eeeeee;"><strong>Quote Number</strong></td>
		<td style="padding:10px 0;border-bottom:1px solid #eeeeee;text-align:right;"><?php echo esc_html( $quote_number ); ?></td>
	</tr>
	<tr>
		<td style="padding:10px 0;border-bottom:1px solid #eeeeee;"><strong>Project</strong></td>
		<td style="padding:10px 0;border-bottom:1px solid #eeeeee;text-align:right;"><?php echo esc_html( $project_name ); ?></td>
	</tr>
	<tr>
		<td style="padding:10px 0;border-bottom:1px solid #eeeeee;"><strong>Company</strong></td>
		<td style="padding:10px 0;border-bottom:1px solid #eeeeee;text-align:right;"><?php echo esc_html( $company_name ); ?></td>
	</tr>
	<tr>
		<td style="padding:10px 0;border-bottom:1px solid #eeeeee;"><strong>Customer</strong></td>
		<td style="padding:10px 0;border-bottom:1px solid #eeeeee;text-align:right;"><?php echo esc_html( $customer_name ); ?></td>
	</tr>
	<tr>
		<td style="padding:10px 0;border-bottom:1px solid #eeeeee;"><strong>Email</strong></td>
		<td style="padding:10px 0;border-bottom:1px solid #eeeeee;text-align:right;"><?php echo esc_html( $customer_email ); ?></td>
	</tr>
</table>

<p style="margin:0;">
	<a href="<?php echo esc_url( $quote_review_url ); ?>" style="display:inline-block;padding:12px 22px;background:#4e1625;color:#ffffff;text-decoration:none;border-radius:4px;">Review Quote</a>
</p>

<?php include QS_PATH . 'templates/email-footer.php'; ?>
