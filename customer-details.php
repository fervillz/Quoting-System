<?php
/**
 * Quote customer defaults and ownership protection.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the first non-empty user-meta value from a list of compatible keys.
 */
function qs_customer_user_meta_first( $user_id, $keys ) {
	foreach ( (array) $keys as $key ) {
		$value = trim( (string) get_user_meta( $user_id, $key, true ) );
		if ( '' !== $value ) {
			return $value;
		}
	}

	return '';
}

/**
 * Build a delivery-address fallback from WooCommerce/WordPress user meta.
 */
function qs_customer_default_address( $user_id ) {
	$saved = qs_customer_user_meta_first( $user_id, 'qs_delivery_address' );
	if ( '' !== $saved ) {
		return $saved;
	}

	foreach ( array( 'shipping', 'billing' ) as $prefix ) {
		$parts = array_filter(
			array(
				trim( (string) get_user_meta( $user_id, $prefix . '_address_1', true ) ),
				trim( (string) get_user_meta( $user_id, $prefix . '_address_2', true ) ),
				trim( (string) get_user_meta( $user_id, $prefix . '_city', true ) ),
				trim( (string) get_user_meta( $user_id, $prefix . '_state', true ) ),
				trim( (string) get_user_meta( $user_id, $prefix . '_postcode', true ) ),
			)
		);

		if ( $parts ) {
			return implode( "\n", $parts );
		}
	}

	return '';
}

/**
 * Customer details used to prefill a new quote.
 *
 * Quote-specific defaults saved by this plugin take priority, then standard
 * WooCommerce user fields, then the core WordPress account name/email.
 */
function qs_customer_quote_defaults( $user_id = 0 ) {
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
	$user    = $user_id ? get_user_by( 'id', $user_id ) : false;

	if ( ! $user instanceof WP_User ) {
		return array(
			'company_name'     => '',
			'customer_name'    => '',
			'customer_email'   => '',
			'customer_phone'   => '',
			'delivery_address' => '',
		);
	}

	$first_name = qs_customer_user_meta_first( $user_id, array( 'billing_first_name', 'first_name' ) );
	$last_name  = qs_customer_user_meta_first( $user_id, array( 'billing_last_name', 'last_name' ) );
	$full_name  = trim( $first_name . ' ' . $last_name );

	return array(
		'company_name' => qs_customer_user_meta_first(
			$user_id,
			array( 'company_name', 'billing_company' )
		),
		'customer_name' => qs_customer_user_meta_first( $user_id, 'qs_customer_name' )
			?: ( $full_name ?: $user->display_name ),
		'customer_email' => qs_customer_user_meta_first(
			$user_id,
			array( 'qs_customer_email', 'billing_email' )
		) ?: $user->user_email,
		'customer_phone' => qs_customer_user_meta_first(
			$user_id,
			array( 'qs_customer_phone', 'billing_phone' )
		),
		'delivery_address' => qs_customer_default_address( $user_id ),
	);
}

/**
 * The Builder historically sent post_author on every wp_update_post() call.
 * Preserve the original trade owner whenever an existing quote is saved by
 * the frontend Builder, including its live AJAX recalculations.
 */
function qs_preserve_builder_quote_author( $data, $postarr ) {
	if (
		! isset( $data['post_type'] ) ||
		'quote' !== $data['post_type'] ||
		empty( $postarr['ID'] ) ||
		empty( $_POST['qs_builder_nonce'] )
	) {
		return $data;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['qs_builder_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'qs_save_quote' ) ) {
		return $data;
	}

	$existing = get_post( absint( $postarr['ID'] ) );
	if ( $existing && 'quote' === $existing->post_type ) {
		$data['post_author'] = (int) $existing->post_author;
	}

	return $data;
}
add_filter( 'wp_insert_post_data', 'qs_preserve_builder_quote_author', 20, 2 );

/**
 * Remember a Joiner's editable contact details for their next new quote.
 * This runs from the Builder request itself because save_post fires before the
 * Builder writes the Quote CPT meta values.
 */
function qs_save_customer_quote_defaults( $post_id, $post ) {
	if (
		! $post instanceof WP_Post ||
		'quote' !== $post->post_type ||
		empty( $_POST['qs_builder_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qs_builder_nonce'] ) ), 'qs_save_quote' )
	) {
		return;
	}

	$user_id = (int) $post->post_author;
	if ( ! $user_id || get_current_user_id() !== $user_id || ! qs_user_is_joiner( $user_id ) ) {
		return;
	}

	$fields = array(
		'company_name'     => array( 'meta_key' => 'company_name', 'sanitiser' => 'sanitize_text_field' ),
		'customer_name'    => array( 'meta_key' => 'qs_customer_name', 'sanitiser' => 'sanitize_text_field' ),
		'customer_email'   => array( 'meta_key' => 'qs_customer_email', 'sanitiser' => 'sanitize_email' ),
		'customer_phone'   => array( 'meta_key' => 'qs_customer_phone', 'sanitiser' => 'sanitize_text_field' ),
		'delivery_address' => array( 'meta_key' => 'qs_delivery_address', 'sanitiser' => 'sanitize_textarea_field' ),
	);

	foreach ( $fields as $field => $settings ) {
		if ( ! isset( $_POST[ $field ] ) ) {
			continue;
		}

		$value = call_user_func( $settings['sanitiser'], wp_unslash( $_POST[ $field ] ) );
		update_user_meta( $user_id, $settings['meta_key'], $value );
	}
}
add_action( 'save_post_quote', 'qs_save_customer_quote_defaults', 30, 2 );

/**
 * Fill an empty Builder input in the rendered shortcode HTML.
 */
function qs_builder_prefill_input_html( $html, $field, $value ) {
	if ( '' === trim( (string) $value ) ) {
		return $html;
	}

	$pattern = '/(<input\b[^>]*\bname="' . preg_quote( $field, '/' ) . '"[^>]*\bvalue=")([^"]*)("[^>]*>)/i';

	return preg_replace_callback(
		$pattern,
		static function ( $matches ) use ( $value ) {
			if ( '' !== html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' ) ) {
				return $matches[0];
			}

			return $matches[1] . esc_attr( $value ) . $matches[3];
		},
		$html,
		1
	);
}

/**
 * Add the Company field back to the Joiner Builder and prefill new quotes.
 * Existing quote values always win and remain editable per quote.
 */
function qs_builder_apply_customer_defaults( $output, $tag ) {
	if ( 'quote_builder' !== $tag || ! is_user_logged_in() || ! qs_user_is_joiner() ) {
		return $output;
	}

	$defaults = qs_customer_quote_defaults();

	$output = preg_replace_callback(
		'/<input type="hidden" name="company_name" value="([^"]*)">/i',
		static function ( $matches ) use ( $defaults ) {
			$value = '' !== $matches[1] ? $matches[1] : esc_attr( $defaults['company_name'] );

			return '<div class="qs-field"><label for="company_name">Company *</label>' .
				'<input id="company_name" name="company_name" type="text" value="' . $value . '" required>' .
				'<small>This is your business name.</small></div>';
		},
		$output,
		1
	);

	$quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
	if ( $quote_id ) {
		return $output;
	}

	foreach ( array( 'customer_name', 'customer_email', 'customer_phone' ) as $field ) {
		$output = qs_builder_prefill_input_html( $output, $field, $defaults[ $field ] );
	}

	if ( '' !== trim( (string) $defaults['delivery_address'] ) ) {
		$output = preg_replace_callback(
			'/<textarea id="delivery_address" name="delivery_address">(.*?)<\/textarea>/is',
			static function ( $matches ) use ( $defaults ) {
				if ( '' !== trim( html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) ) ) {
					return $matches[0];
				}

				return '<textarea id="delivery_address" name="delivery_address">' . esc_textarea( $defaults['delivery_address'] ) . '</textarea>';
			},
			$output,
			1
		);
	}

	return $output;
}
add_filter( 'do_shortcode_tag', 'qs_builder_apply_customer_defaults', 20, 2 );
