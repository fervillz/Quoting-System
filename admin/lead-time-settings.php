<?php
/**
 * Configurable estimated lead time.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_default_lead_time() {
	$value = trim( (string) get_option( 'qs_default_lead_time', '4–6 Weeks' ) );
	return $value ? $value : '4–6 Weeks';
}

function qs_quote_lead_time( $quote_id ) {
	$value = $quote_id ? trim( (string) get_post_meta( $quote_id, '_estimated_lead_time', true ) ) : '';
	return $value ? $value : qs_default_lead_time();
}

function qs_register_lead_time_setting() {
	register_setting(
		'qs_quote_settings',
		'qs_default_lead_time',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '4–6 Weeks',
		)
	);
}
add_action( 'admin_init', 'qs_register_lead_time_setting' );

function qs_add_quote_settings_page() {
	add_submenu_page(
		'edit.php?post_type=quote',
		__( 'Quote Settings', 'quote-system' ),
		__( 'Quote Settings', 'quote-system' ),
		'manage_options',
		'quote-settings',
		'qs_render_quote_settings_page'
	);
}
add_action( 'admin_menu', 'qs_add_quote_settings_page' );

function qs_render_quote_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Quote Settings', 'quote-system' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'qs_quote_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="qs-default-lead-time"><?php echo esc_html__( 'Default Estimated Lead Time', 'quote-system' ); ?></label></th>
					<td>
						<input id="qs-default-lead-time" class="regular-text" type="text" name="qs_default_lead_time" value="<?php echo esc_attr( qs_default_lead_time() ); ?>">
						<p class="description"><?php echo esc_html__( 'Used for new and existing quotes unless a quote-specific value is saved.', 'quote-system' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

function qs_add_lead_time_meta_box() {
	add_meta_box(
		'qs-estimated-lead-time',
		__( 'Estimated Lead Time', 'quote-system' ),
		'qs_render_lead_time_meta_box',
		'quote',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_quote', 'qs_add_lead_time_meta_box' );

function qs_render_lead_time_meta_box( $post ) {
	wp_nonce_field( 'qs_save_lead_time', 'qs_lead_time_nonce' );
	?>
	<p>
		<label class="screen-reader-text" for="qs-estimated-lead-time-value"><?php echo esc_html__( 'Estimated Lead Time', 'quote-system' ); ?></label>
		<input id="qs-estimated-lead-time-value" class="widefat" type="text" name="estimated_lead_time" value="<?php echo esc_attr( qs_quote_lead_time( $post->ID ) ); ?>" placeholder="4–6 Weeks">
	</p>
	<p class="description"><?php echo esc_html__( 'Shown in the quote summary, review screen and generated documents.', 'quote-system' ); ?></p>
	<?php
}

function qs_save_quote_lead_time( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || 'quote' !== get_post_type( $post_id ) ) {
		return;
	}

	$is_admin_save   = isset( $_POST['qs_lead_time_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qs_lead_time_nonce'] ) ), 'qs_save_lead_time' );
	$is_builder_save = isset( $_POST['estimated_lead_time'] ) && current_user_can( 'edit_post', $post_id );
	if ( ! $is_admin_save && ! $is_builder_save ) {
		return;
	}

	$value = isset( $_POST['estimated_lead_time'] ) ? sanitize_text_field( wp_unslash( $_POST['estimated_lead_time'] ) ) : '';
	if ( '' === trim( $value ) ) {
		delete_post_meta( $post_id, '_estimated_lead_time' );
	} else {
		update_post_meta( $post_id, '_estimated_lead_time', $value );
	}
}
add_action( 'save_post_quote', 'qs_save_quote_lead_time', 30 );
