Exit code: 0
Wall time: 0.5 seconds
Output:
<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Adds the office-only price list under the Quote System menu. */
function qs_add_pricing_settings_page() {
	add_submenu_page( 'edit.php?post_type=quote', 'Quote Pricing', 'Quote Pricing', 'manage_options', 'quote-pricing', 'qs_render_pricing_settings_page' );
}
add_action( 'admin_menu', 'qs_add_pricing_settings_page' );

/** Receives only numeric rates and explains every field in plain English. */
function qs_save_pricing_settings() {
	if ( ! isset( $_POST['qs_save_pricing'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['qs_pricing_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qs_pricing_nonce'] ) ), 'qs_save_pricing' ) ) {
		wp_die( esc_html__( 'You are not allowed to change quote prices.', 'quote-system' ) );
	}
	$fields = array( 'door_rate', 'drawer_rate', 'drawer_bank_rate', 'end_panel_rate', 'filler_rate', 'kickboard_rate', 'profile_surcharge', 'timber_surcharge', 'finish_surcharge', 'handle_surcharge' );
	$prices = array();
	foreach ( $fields as $field ) {
		$prices[ $field ] = isset( $_POST[ $field ] ) ? max( 0, (float) wp_unslash( $_POST[ $field ] ) ) : 0;
	}
	update_option( 'qs_pricing_settings', $prices );
	wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'edit.php?post_type=quote&page=quote-pricing' ) ) );
	exit;
}
add_action( 'admin_init', 'qs_save_pricing_settings' );

/** Renders the editable pricing table used by every new calculation. */
function qs_render_pricing_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$prices = qs_get_pricing_settings();
	$fields = array(
		'door_rate' => array( 'Door rate', 'Trade price per mÂ² for a door.' ),
		'drawer_rate' => array( 'Drawer front rate', 'Trade price per mÂ² for a drawer front.' ),
		'drawer_bank_rate' => array( 'Drawer bank rate', 'Trade price per mÂ² for a complete drawer bank.' ),
		'end_panel_rate' => array( 'End panel rate', 'Trade price per mÂ² for end panels.' ),
		'filler_rate' => array( 'Filler rate', 'Trade price per mÂ² for fillers.' ),
		'kickboard_rate' => array( 'Kickboard rate', 'Trade price per linear metre of kickboard.' ),
		'profile_surcharge' => array( 'Profile surcharge', 'Fixed amount added when a profile is selected.' ),
		'timber_surcharge' => array( 'Timber surcharge', 'Fixed amount added when a timber is selected.' ),
		'finish_surcharge' => array( 'Finish surcharge', 'Fixed amount added when a finish is selected.' ),
		'handle_surcharge' => array( 'Handle surcharge', 'Fixed amount added when a handle profile is selected.' ),
	);
	?>
	<div class="wrap"><h1>Quote Pricing</h1>
	<p>Enter your <strong>trade</strong> prices here. Retail quotes are calculated automatically at trade price + 22.22%. Saving these prices does not rewrite older quotes; open and save a draft to refresh its calculation.</p>
	<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Quote prices saved.</p></div><?php endif; ?>
	<form method="post"><input type="hidden" name="qs_save_pricing" value="1"><?php wp_nonce_field( 'qs_save_pricing', 'qs_pricing_nonce' ); ?>
	<table class="form-table" role="presentation"><tbody>
	<?php foreach ( $fields as $key => $field ) : ?><tr><th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th><td><span>$ </span><input type="number" min="0" step="0.01" class="regular-text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $prices[ $key ] ); ?>"><p class="description"><?php echo esc_html( $field[1] ); ?></p></td></tr><?php endforeach; ?>
	</tbody></table><?php submit_button( 'Save quote prices' ); ?></form></div>
	<?php
}

