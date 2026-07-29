<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get admin email address.
 *
 * Future:
 * Could come from plugin settings.
 */
function qs_get_admin_email() {

	return get_option(
		'admin_email'
	);

}

/**
 * Core email sender.
 *
 * All Quote System emails should
 * pass through this function.
 */
function qs_send_email(
	$to,
	$subject,
	$message,
	$headers = array(),
	$attachments = array()
) {

	if ( empty( $headers ) ) {

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

	}

	return wp_mail(
		$to,
		$subject,
		$message,
		$headers,
		$attachments
	);

}

/**
 * Send email to admin.
 */
function qs_send_admin_email(
	$subject,
	$message,
	$attachments = array()
) {

	return qs_send_email(
		qs_get_admin_email(),
		$subject,
		$message,
		array(),
		$attachments
	);

}

/**
 * Send email to customer.
 */
function qs_send_customer_email(
	$quote_id,
	$subject,
	$message,
	$attachments = array()
) {

	$email = get_post_meta(
		$quote_id,
		'_customer_email',
		true
	);

	if ( empty( $email ) ) {
		return false;
	}

	return qs_send_email(
		$email,
		$subject,
		$message,
		array(),
		$attachments
	);

}

/**
 * Render email template.
 */
function qs_render_email_template(
	$template,
	$data = array()
) {

	extract(
		$data
	);

	ob_start();

	include QS_PATH .
		'templates/' .
		$template;

	return ob_get_clean();

}

/**
 * Notify admin when quote submitted.
 */
function qs_email_quote_submitted(
	$quote_id
) {

	$quote_number = get_post_meta(
		$quote_id,
		'_quote_number',
		true
	);

	$subject = sprintf(
		'New Quote Submitted - %s',
		$quote_number
	);

	$message = qs_render_email_template(
		'email-admin.php',
		array(
			'quote_id' => $quote_id,
		)
	);

	return qs_send_admin_email(
		$subject,
		$message
	);

}

/**
 * Notify customer that quote
 * has been approved.
 */
function qs_email_quote_approved(
	$quote_id
) {

	$quote_number = get_post_meta(
		$quote_id,
		'_quote_number',
		true
	);

	$subject = sprintf(
		'Quote Approved - %s',
		$quote_number
	);

	$message = qs_render_email_template(
		'email-customer.php',
		array(
			'quote_id' => $quote_id,
		)
	);

	return qs_send_customer_email(
		$quote_id,
		$subject,
		$message
	);

}
