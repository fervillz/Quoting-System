<?php
/**
 * Quote System settings hub.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_settings_tabs() {
	return array(
		'settings' => 'Settings',
		'testing'  => 'AI Testing',
		'setup'    => 'Setup',
	);
}

function qs_settings_url( $tab = 'settings' ) {
	return add_query_arg(
		array(
			'post_type' => 'quote',
			'page'      => 'qs-settings',
			'tab'       => sanitize_key( $tab ),
		),
		admin_url( 'edit.php' )
	);
}

function qs_settings_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=quote',
		'Quote System Settings',
		'Settings',
		'manage_options',
		'qs-settings',
		'qs_settings_admin_page'
	);
}
add_action( 'admin_menu', 'qs_settings_admin_menu', 32 );

function qs_settings_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage Quote System settings.', 'quote-system' ) );
	}

	$tabs   = qs_settings_tabs();
	$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
	if ( ! isset( $tabs[ $active ] ) ) {
		$active = 'settings';
	}
	?>
	<div class="wrap qs-settings-wrap">
		<h1>Quote System Settings</h1>
		<p class="description">Manage Quote System settings, run end-to-end testing, and handle installation or deployment setup.</p>

		<nav class="nav-tab-wrapper qs-settings-tabs" aria-label="Quote System settings">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a
					class="nav-tab <?php echo $active === $key ? 'nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( qs_settings_url( $key ) ); ?>"
				><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<div class="qs-settings-content">
			<?php do_action( 'qs_settings_tab_' . $active ); ?>
		</div>
	</div>

	<style>
		.qs-settings-wrap{max-width:1400px}
		.qs-settings-wrap>h1{margin-bottom:4px}
		.qs-settings-wrap>.description{margin-top:0;margin-bottom:20px}
		.qs-settings-tabs{margin-bottom:0}
		.qs-settings-content{padding-top:2px}
		.qs-settings-content .qs-setup-transfer{margin-top:20px;padding:22px;background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1}
		.qs-settings-content .qs-setup-transfer h2{margin-top:0}
		.qs-settings-content .qs-setup-badge{display:inline-block;border-radius:999px;padding:3px 9px;font-size:12px;font-weight:600;background:#f0f0f1;color:#50575e}
		.qs-settings-content .qs-setup-badge.is-ready{background:#e6f4ea;color:#146c2e}
		.qs-settings-content .qs-setup-badge.is-missing{background:#fff3cd;color:#7a5b00}
	</style>
	<?php
}


/**
 * Save Quote System-specific admin notification recipients.
 */
function qs_settings_save_admin_notification_emails() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to change Quote System notification emails.', 'quote-system' ), 403 );
	}

	check_admin_referer( 'qs_save_admin_notification_emails' );

	$raw = isset( $_POST['qs_admin_notification_emails'] )
		? (string) wp_unslash( $_POST['qs_admin_notification_emails'] )
		: '';

	$parts  = preg_split( '/[\\s,;]+/', $raw );
	$emails = array();

	foreach ( (array) $parts as $email ) {
		$email = sanitize_email( $email );
		if ( $email && is_email( $email ) ) {
			$emails[] = $email;
		}
	}

	update_option( 'qs_admin_notification_emails', array_values( array_unique( $emails ) ), false );

	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type'          => 'quote',
				'page'               => 'qs-settings',
				'tab'                => 'settings',
				'notification_saved' => '1',
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}
add_action( 'admin_post_qs_save_admin_notification_emails', 'qs_settings_save_admin_notification_emails' );

/**
 * Render Quote System admin notification recipients on the Settings tab.
 */
function qs_settings_render_admin_notification_emails() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$configured = function_exists( 'qs_get_configured_admin_emails' )
		? qs_get_configured_admin_emails()
		: array();

	$fallback = function_exists( 'qs_is_live_loughlin_site' ) && qs_is_live_loughlin_site()
		? 'info@loughlinfurniture.com.au'
		: sanitize_email( (string) get_option( 'admin_email' ) );

	$saved = isset( $_GET['notification_saved'] )
		&& '1' === sanitize_text_field( wp_unslash( $_GET['notification_saved'] ) );
	?>
	<?php if ( $saved ) : ?>
		<div class="notice notice-success inline"><p>Admin notification emails updated.</p></div>
	<?php endif; ?>

	<section class="qs-setup-transfer">
		<h2>Admin Notification Emails</h2>
		<p>Choose who receives Quote System admin emails such as <strong>New Quote Submitted</strong>, <strong>Deposit Payment Received</strong>, and <strong>Final Payment Received</strong>.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="qs_save_admin_notification_emails">
			<?php wp_nonce_field( 'qs_save_admin_notification_emails' ); ?>

			<textarea
				name="qs_admin_notification_emails"
				rows="5"
				class="large-text"
				placeholder="admin@example.com&#10;developer@example.com"
			><?php echo esc_textarea( implode( "\n", $configured ) ); ?></textarea>

			<p class="description">Enter one email per line. Commas and semicolons also work. Leave this empty to use the normal fallback: <strong><?php echo esc_html( $fallback ); ?></strong>.</p>

			<p><button type="submit" class="button button-primary">Save Notification Emails</button></p>
		</form>
	</section>
	<?php
}
add_action( 'qs_settings_tab_settings', 'qs_settings_render_admin_notification_emails', 20 );


/**
 * Keep bookmarks from the earlier separate Setup / Auto AI screens working
 * while keeping those entries out of the Quote System submenu.
 */
function qs_settings_redirect_legacy_pages() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) || empty( $_GET['post_type'] ) || 'quote' !== sanitize_key( wp_unslash( $_GET['post_type'] ) ) || empty( $_GET['page'] ) ) {
		return;
	}

	$page = sanitize_key( wp_unslash( $_GET['page'] ) );
	if ( 'qs-setup' === $page ) {
		wp_safe_redirect( qs_settings_url( 'setup' ) );
		exit;
	}

	if ( 'qs-auto-ai-testing' === $page ) {
		$args = array();
		if ( isset( $_GET['run_id'] ) ) {
			$args['run_id'] = absint( $_GET['run_id'] );
		}
		$url = qs_settings_url( 'testing' );
		if ( $args ) {
			$url = add_query_arg( $args, $url );
		}
		wp_safe_redirect( $url );
		exit;
	}
}
add_action( 'admin_init', 'qs_settings_redirect_legacy_pages', 50 );
