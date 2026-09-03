<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include QS_PATH . 'templates/email-header.php';

$quote_number  = get_post_meta( $quote_id, '_quote_number', true );
$customer_name = get_post_meta( $quote_id, '_customer_name', true );
$my_quotes_url = site_url( '/my-quotes/' );
?>
<div style="max-width:600px;margin:auto;font-family:Arial,sans-serif;color:#43586a;">
	<div style="background:#4e1625;color:#fff;padding:30px;">
		<h1 style="margin:0;">Loughlin Furniture</h1>
	</div>
	<div style="padding:30px;border:1px solid #eee;">
		<h2>Payment Complete</h2>
		<p>Hi <strong><?php echo esc_html( $customer_name ); ?></strong>,</p>
		<p>Your final payment has been received and quote <strong><?php echo esc_html( $quote_number ); ?></strong> is now paid in full.</p>
		<p>The Loughlin Furniture team will continue with the order from here.</p>

		<p style="margin-top:28px;">
			<a href="<?php echo esc_url( $my_quotes_url ); ?>" style="display:inline-block;padding:12px 22px;background:#4e1625;color:#fff;text-decoration:none;border-radius:3px;">View My Quotes</a>
		</p>
	</div>
</div>
<?php include QS_PATH . 'templates/email-footer.php'; ?>
