<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include QS_PATH .
	'templates/email-header.php';

$quote_number = get_post_meta(
	$quote_id,
	'_quote_number',
	true
);

$project_name = get_post_meta(
	$quote_id,
	'_project_name',
	true
);

$company_name = get_post_meta(
	$quote_id,
	'_company_name',
	true
);

$customer_name = get_post_meta(
	$quote_id,
	'_customer_name',
	true
);

$customer_email = get_post_meta(
	$quote_id,
	'_customer_email',
	true
);

$quote_review_url = add_query_arg(
	'quote_id',
	$quote_id,
	site_url(
		'/quote-review/'
	)
);

?>

<div style="
	max-width:600px;
	margin:0 auto;
	font-family:Arial, sans-serif;
	color:#43586a;
">

	<div style="
		background:#4e1625;
		padding:30px;
		color:#ffffff;
	">

		<h1 style="
			margin:0;
			font-size:24px;
		">
			Loughlin Furniture
		</h1>

	</div>

	<div style="
		padding:30px;
		background:#ffffff;
		border:1px solid #eeeeee;
	">

		<h2 style="
			margin-top:0;
			color:#43586a;
		">
			New Quote Submitted
		</h2>

		<p>
			A new quote has been submitted and is ready for review.
		</p>

		<table style="
			width:100%;
			border-collapse:collapse;
			margin:25px 0;
		">

			<tr>
				<td style="
					padding:10px 0;
					border-bottom:1px solid #eeeeee;
				">
					<strong>Quote Number</strong>
				</td>

				<td style="
					padding:10px 0;
					border-bottom:1px solid #eeeeee;
					text-align:right;
				">
					<?php echo esc_html( $quote_number ); ?>
				</td>
			</tr>

			<tr>
				<td style="
					padding:10px 0;
					border-bottom:1px solid #eeeeee;
				">
					<strong>Project</strong>
				</td>

				<td style="
					padding:10px 0;
					border-bottom:1px solid #eeeeee;
					text-align:right;
				">
					<?php echo esc_html( $project_name ); ?>
				</td>
			</tr>

			<tr>
				<td style="
					padding:10px 0;
					border-bottom:1px solid #eeeeee;
				">
					<strong>Company</strong>
				</td>

				<td style="
					padding:10px 0;
					border-bottom:1px solid #eeeeee;
					text-align:right;
				">
					<?php echo esc_html( $company_name ); ?>
				</td>
			</tr>

			<tr>
				<td style="
					padding:10px 0;
					border-bottom:1px solid #eeeeee;
				">
					<strong>Customer</strong>
				</td>

				<td style="
					padding:10px 0;
					border-bottom:1px solid #eeeeee;
					text-align:right;
				">
					<?php echo esc_html( $customer_name ); ?>
				</td>
			</tr>

			<tr>
				<td style="
					padding:10px 0;
					border-bottom:1px solid #eeeeee;
				">
					<strong>Email</strong>
				</td>

				<td style="
					padding:10px 0;
					border-bottom:1px solid #eeeeee;
					text-align:right;
				">
					<?php echo esc_html( $customer_email ); ?>
				</td>
			</tr>

		</table>

		<p style="margin-top:30px;">

			<a
				href="<?php echo esc_url( $quote_review_url ); ?>"
				style="
					display:inline-block;
					padding:12px 22px;
					background:#4e1625;
					color:#ffffff;
					text-decoration:none;
					border-radius:4px;
				"
			>
				Review Quote
			</a>

		</p>

	</div>

</div>

<?php

include QS_PATH .
	'templates/email-footer.php';