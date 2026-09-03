<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include QS_PATH . 'templates/email-header.php';

$quote_number  = get_post_meta( $quote_id, '_quote_number', true );
$customer_name = get_post_meta( $quote_id, '_customer_name', true );
$my_quotes_url = site_url( '/my-quotes/' );
?>
<h2 style="margin:0 0 18px;color:#43586a;">Payment Complete</h2>

<p>Hi <strong><?php echo esc_html( $customer_name ); ?></strong>,</p>
<p>Your final payment has been received and quote <strong><?php echo esc_html( $quote_number ); ?></strong> is now paid in full.</p>
<p>The Loughlin Furniture team will continue with the order from here.</p>

<p style="margin:28px 0 0;">
	<a href="<?php echo esc_url( $my_quotes_url ); ?>" style="display:inline-block;padding:12px 22px;background:#4e1625;color:#ffffff;text-decoration:none;border-radius:4px;">View My Quotes</a>
</p>

<?php include QS_PATH . 'templates/email-footer.php'; ?>
