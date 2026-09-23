<?php
/**
 * Quote System settings hub.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_settings_tabs() {
	return array(
		'portal'  => 'Portal Access',
		'testing' => 'Automatic Testing',
	);
}

function qs_settings_url( $tab = 'portal' ) {
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
	$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'portal';
	if ( ! isset( $tabs[ $active ] ) ) {
		$active = 'portal';
	}
	?>
	<div class="wrap qs-settings-wrap">
		<h1>Quote System Settings</h1>
		<p class="description">Ongoing Quote System controls and deployment verification tools.</p>

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
		.qs-settings-wrap{max-width:1100px}
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
