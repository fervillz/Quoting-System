<?php

include QS_PATH .
	'templates/email-header.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quote_number = get_post_meta(
	$quote_id,
	'_quote_number',
	true
);

$customer_name = get_post_meta(
	$quote_id,
	'_customer_name',
	true
);

?>

<div style="
	max-width:600px;
	margin:auto;
	font-family:Arial,sans-serif;
	color:#43586a;
">

	<div style="
		background:#4e1625;
		color:#fff;
		padding:30px;
	">

		<h1 style="margin:0;">
			Loughlin Furniture
		</h1>

	</div>

	<div style="
		padding:30px;
		border:1px solid #eee;
	">

		<h2>
			Quote Approved
		</h2>

		<p>

			Hi
			<strong>
				<?php echo esc_html(
					$customer_name
				); ?>
			</strong>,

		</p>

		<p>

			Your quotation has been approved.

		</p>

		<p>

			<strong>
				Quote Number:
			</strong>

			<?php echo esc_html(
				$quote_number
			); ?>

		</p>

		<p>

			<?php $payment_url = qs_get_quote_payment_url( $quote_id, 'deposit' ); ?>
			<?php if ( $payment_url ) : ?>
				Your deposit is ready. <a href="<?php echo esc_url( $payment_url ); ?>">Pay the deposit securely</a>.
			<?php else : ?>
				A deposit invoice will be issued shortly.
			<?php endif; ?>

		</p>

	</div>

</div>

<?php

include QS_PATH .
	'templates/email-footer.php';
