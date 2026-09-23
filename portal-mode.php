<?php
/**
 * Quote System portal launch/access modes.
 *
 * This controls who can reach the Quote System frontend. It does NOT change
 * WooCommerce/Stripe gateway modes and does NOT reroute email.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_portal_modes() {
	return array(
		'setup' => array(
			'label'       => 'Setup Mode',
			'description' => 'Administrators only. Use while installing/importing/configuring the live site.',
		),
		'test' => array(
			'label'       => 'Test Mode',
			'description' => 'Administrators and Joiners explicitly approved for Quote System test access.',
		),
		'live' => array(
			'label'       => 'Live Mode',
			'description' => 'The normal Joiner portal is available to approved Joiner accounts.',
		),
	);
}

/**
 * Safe default for sites upgraded before the option existed:
 * - real LF production hostname starts closed in Setup Mode;
 * - staging/dev starts in Test Mode.
 */
function qs_portal_default_mode() {
	return function_exists( 'qs_is_live_loughlin_site' ) && qs_is_live_loughlin_site()
		? 'setup'
		: 'test';
}

function qs_portal_mode() {
	$mode  = (string) get_option( 'qs_portal_mode', '' );
	$modes = qs_portal_modes();

	return isset( $modes[ $mode ] ) ? $mode : qs_portal_default_mode();
}

function qs_portal_mode_label( $mode = '' ) {
	$mode  = $mode ? $mode : qs_portal_mode();
	$modes = qs_portal_modes();

	return isset( $modes[ $mode ] ) ? $modes[ $mode ]['label'] : ucfirst( $mode );
}

/** Return whether one Joiner is explicitly approved for live-domain testing. */
function qs_portal_user_has_test_access( $user = null ) {
	if ( null === $user ) {
		$user = wp_get_current_user();
	} elseif ( is_numeric( $user ) ) {
		$user = get_user_by( 'id', absint( $user ) );
	}

	return $user instanceof WP_User
		&& function_exists( 'qs_user_is_joiner' )
		&& qs_user_is_joiner( $user )
		&& '1' === (string) get_user_meta( $user->ID, 'qs_portal_test_access', true );
}

/**
 * Portal permission used by frontend pages and protected Quote documents.
 * Administrators always retain access so Setup Mode cannot lock out LF staff.
 */
function qs_portal_user_can_access( $user = null ) {
	if ( null === $user ) {
		$user = wp_get_current_user();
	} elseif ( is_numeric( $user ) ) {
		$user = get_user_by( 'id', absint( $user ) );
	}

	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return false;
	}

	if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_others_posts' ) ) {
		return true;
	}

	$mode = qs_portal_mode();
	if ( 'live' === $mode ) {
		return function_exists( 'qs_user_is_joiner' ) && qs_user_is_joiner( $user );
	}

	if ( 'test' === $mode ) {
		return qs_portal_user_has_test_access( $user );
	}

	return false;
}

function qs_portal_is_login_page() {
	if ( ! is_singular() ) {
		return false;
	}

	$page_id = get_queried_object_id();
	if ( function_exists( 'qs_setup_get_page_id' ) && $page_id === (int) qs_setup_get_page_id( 'login' ) ) {
		return true;
	}

	$post = get_post( $page_id );
	return $post instanceof WP_Post
		&& ( has_shortcode( (string) $post->post_content, 'quote_login' ) || has_shortcode( (string) $post->post_content, 'joiner_login' ) );
}

/**
 * Gate all normal Quote System frontend pages.
 *
 * In Test Mode the login screen remains reachable to signed-out testers.
 * Every other Quote System page requires an administrator or test-approved
 * Joiner. Setup Mode is administrator-only.
 */
function qs_portal_gate_frontend_pages() {
	if ( is_admin() || ! function_exists( 'qs_is_frontend_ui_page' ) || ! qs_is_frontend_ui_page() ) {
		return;
	}

	$mode = qs_portal_mode();

	if ( 'live' === $mode || qs_portal_user_can_access() ) {
		return;
	}

	if ( 'test' === $mode && qs_portal_is_login_page() && ! is_user_logged_in() ) {
		return;
	}

	if ( 'test' === $mode && ! is_user_logged_in() ) {
		$login_url = function_exists( 'qs_page_url' ) ? qs_page_url( 'login' ) : site_url( '/quote-login/' );
		wp_safe_redirect( $login_url );
		exit;
	}

	// Keep pre-launch pages out of public view rather than exposing a half-live portal.
	wp_safe_redirect( home_url( '/' ) );
	exit;
}
add_action( 'template_redirect', 'qs_portal_gate_frontend_pages', 1 );

/** Prevent non-live portal pages being indexed while Setup/Test Mode is active. */
function qs_portal_robots( $robots ) {
	if ( 'live' !== qs_portal_mode() && function_exists( 'qs_is_frontend_ui_page' ) && qs_is_frontend_ui_page() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}

	return $robots;
}
add_filter( 'wp_robots', 'qs_portal_robots', 20 );

/** Keep blocked Joiners from being redirected straight back into My Quotes after wp-login.php. */
function qs_portal_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if (
		! is_wp_error( $user ) &&
		$user instanceof WP_User &&
		function_exists( 'qs_user_is_joiner' ) &&
		qs_user_is_joiner( $user ) &&
		! qs_portal_user_can_access( $user )
	) {
		return home_url( '/' );
	}

	return $redirect_to;
}
add_filter( 'login_redirect', 'qs_portal_login_redirect', 100, 3 );

/**
 * Preserve the existing dedicated test account on upgrades. This runs once
 * and only grants access to the exact `testjoiner` helper account.
 */
function qs_portal_migrate_legacy_test_joiner() {
	if ( get_option( 'qs_portal_test_joiner_migrated' ) ) {
		return;
	}

	$user = get_user_by( 'login', 'testjoiner' );
	if ( $user instanceof WP_User && function_exists( 'qs_user_is_joiner' ) && qs_user_is_joiner( $user ) ) {
		update_user_meta( $user->ID, 'qs_portal_test_access', '1' );
	}

	update_option( 'qs_portal_test_joiner_migrated', '1', false );
}
add_action( 'init', 'qs_portal_migrate_legacy_test_joiner', 30 );

/** Save Portal Mode from Quote System → Setup. */
function qs_portal_handle_mode_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to change Quote System Portal Mode.', 'quote-system' ), 403 );
	}

	check_admin_referer( 'qs_save_portal_mode' );

	$mode  = isset( $_POST['qs_portal_mode'] ) ? sanitize_key( wp_unslash( $_POST['qs_portal_mode'] ) ) : '';
	$modes = qs_portal_modes();
	if ( ! isset( $modes[ $mode ] ) ) {
		$mode = qs_portal_default_mode();
	}

	update_option( 'qs_portal_mode', $mode, false );

	$redirect = add_query_arg(
		array(
			'post_type'   => 'quote',
			'page'        => 'qs-setup',
			'portal_mode' => $mode,
			'portal_saved'=> '1',
		),
		admin_url( 'edit.php' )
	);
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_qs_save_portal_mode', 'qs_portal_handle_mode_save' );

/** Render Portal Mode before the staging/live transfer panel. */
function qs_portal_render_setup_panel() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$current = qs_portal_mode();
	$modes   = qs_portal_modes();
	$saved   = isset( $_GET['portal_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['portal_saved'] ) );
	?>
	<?php if ( $saved ) : ?>
		<div class="notice notice-success inline"><p>Portal Mode updated to <strong><?php echo esc_html( qs_portal_mode_label( $current ) ); ?></strong>.</p></div>
	<?php endif; ?>
	<section class="qs-setup-transfer qs-portal-mode-card">
		<h2>Portal Mode <span class="qs-setup-badge <?php echo 'live' === $current ? 'is-ready' : 'is-missing'; ?>"><?php echo esc_html( strtoupper( qs_portal_mode_label( $current ) ) ); ?></span></h2>
		<p>Controls who can access the Quote System frontend on this site. This lets the plugin stay active while the live portal is still being prepared or privately tested.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="qs_save_portal_mode">
			<?php wp_nonce_field( 'qs_save_portal_mode' ); ?>

			<div class="qs-portal-mode-options">
				<?php foreach ( $modes as $key => $mode ) : ?>
					<label class="qs-portal-mode-option">
						<input type="radio" name="qs_portal_mode" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>>
						<span><strong><?php echo esc_html( $mode['label'] ); ?></strong><small><?php echo esc_html( $mode['description'] ); ?></small></span>
					</label>
				<?php endforeach; ?>
			</div>

			<p>
				<button type="submit" class="button button-primary" onclick="return document.querySelector('input[name=qs_portal_mode]:checked')?.value!=='live' || confirm('Switch Quote System to LIVE MODE? All approved Joiner accounts will be able to access the portal.');">Save Portal Mode</button>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'users.php?role=joiner' ) ); ?>">Manage Joiners</a>
			</p>
		</form>

		<p class="description"><strong>Important:</strong> Portal Mode controls Quote System access only. TEST MODE does not switch WooCommerce/Stripe into sandbox mode and does not change email recipients.</p>
		<p class="description">On a fresh live installation the safe default is <strong>Setup Mode</strong>. This environment-specific setting is intentionally not included in configuration Export / Import.</p>
	</section>
	<style>
		.qs-portal-mode-card{border-left-color:#8c8f94}
		.qs-portal-mode-card h2{display:flex;align-items:center;justify-content:space-between;gap:12px}
		.qs-portal-mode-options{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:18px 0}
		.qs-portal-mode-option{display:flex;gap:10px;padding:16px;border:1px solid #dcdcde;background:#f6f7f7;cursor:pointer}
		.qs-portal-mode-option:has(input:checked){border-color:#2271b1;background:#f0f6fc;box-shadow:inset 0 0 0 1px #2271b1}
		.qs-portal-mode-option input{margin-top:2px}
		.qs-portal-mode-option strong,.qs-portal-mode-option small{display:block}
		.qs-portal-mode-option small{margin-top:5px;color:#646970;line-height:1.4}
		@media(max-width:782px){.qs-portal-mode-options{grid-template-columns:1fr}}
	</style>
	<?php
}
add_action( 'qs_settings_tab_portal', 'qs_portal_render_setup_panel' );

/** Persistent admin warning until the portal is deliberately switched Live. */
function qs_portal_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) || 'live' === qs_portal_mode() ) {
		return;
	}

	$mode = qs_portal_mode();
	$url  = function_exists( 'qs_settings_url' ) ? qs_settings_url( 'portal' ) : admin_url( 'edit.php?post_type=quote&page=qs-settings&tab=portal' );
	?>
	<div class="notice notice-warning">
		<p><strong>Quote System: <?php echo esc_html( qs_portal_mode_label( $mode ) ); ?></strong> — <?php echo 'setup' === $mode ? 'only administrators can access the Quote System frontend.' : 'only administrators and test-approved Joiners can access the Quote System frontend.'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="<?php echo esc_url( $url ); ?>">Change Portal Mode</a></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'qs_portal_admin_notice', 20 );

/**
 * Admin-only frontend badge so staff never mistake Setup/Test Mode for the
 * public production portal.
 */
function qs_portal_frontend_mode_badge() {
	if (
		'live' === qs_portal_mode() ||
		! is_user_logged_in() ||
		! current_user_can( 'edit_others_posts' ) ||
		! function_exists( 'qs_is_frontend_ui_page' ) ||
		! qs_is_frontend_ui_page()
	) {
		return;
	}

	$label = strtoupper( qs_portal_mode_label() );
	?>
	<style>
	.qs-portal-mode-badge{display:inline-flex;align-items:center;align-self:center;padding:7px 11px;border-radius:999px;background:#fff3cd;color:#6f5200;font-size:11px;font-weight:700;letter-spacing:.05em;line-height:1;white-space:nowrap}
	.qs-portal-mode-badge:before{content:"";width:7px;height:7px;margin-right:7px;border-radius:50%;background:currentColor}
	</style>
	<script>
	(function(){
		var nav=document.querySelector('.qs-container header nav');
		if(!nav||nav.querySelector('.qs-portal-mode-badge'))return;
		var badge=document.createElement('span');
		badge.className='qs-portal-mode-badge';
		badge.textContent=<?php echo wp_json_encode( $label ); ?>;
		nav.insertBefore(badge,nav.firstChild);
	}());
	</script>
	<?php
}
add_action( 'wp_footer', 'qs_portal_frontend_mode_badge', 120 );

/** Administrator-managed Joiner test-access checkbox. */
function qs_portal_test_access_profile_field( $user ) {
	if ( ! current_user_can( 'manage_options' ) || ! $user instanceof WP_User || ! function_exists( 'qs_user_is_joiner' ) || ! qs_user_is_joiner( $user ) ) {
		return;
	}
	?>
	<h2>Quote System</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="qs-portal-test-access">Portal Test Access</label></th>
			<td>
				<label>
					<input type="checkbox" id="qs-portal-test-access" name="qs_portal_test_access" value="1" <?php checked( '1', get_user_meta( $user->ID, 'qs_portal_test_access', true ) ); ?>>
					Allow this Joiner to use Quote System while Portal Mode is <strong>Test Mode</strong>.
				</label>
				<p class="description">This setting has no effect in Live Mode. Joiners cannot grant this permission to themselves.</p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'qs_portal_test_access_profile_field' );
add_action( 'edit_user_profile', 'qs_portal_test_access_profile_field' );

function qs_portal_save_test_access_profile_field( $user_id ) {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	$user = get_user_by( 'id', absint( $user_id ) );
	if ( ! $user instanceof WP_User || ! function_exists( 'qs_user_is_joiner' ) || ! qs_user_is_joiner( $user ) ) {
		delete_user_meta( $user_id, 'qs_portal_test_access' );
		return;
	}

	if ( isset( $_POST['qs_portal_test_access'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['qs_portal_test_access'] ) ) ) {
		update_user_meta( $user_id, 'qs_portal_test_access', '1' );
	} else {
		delete_user_meta( $user_id, 'qs_portal_test_access' );
	}
}
add_action( 'personal_options_update', 'qs_portal_save_test_access_profile_field' );
add_action( 'edit_user_profile_update', 'qs_portal_save_test_access_profile_field' );

/** Make test access visible at a glance in Users → All Users. */
function qs_portal_users_columns( $columns ) {
	$columns['qs_portal_test_access'] = 'Quote Test Access';
	return $columns;
}
add_filter( 'manage_users_columns', 'qs_portal_users_columns' );

function qs_portal_users_column_content( $value, $column_name, $user_id ) {
	if ( 'qs_portal_test_access' !== $column_name ) {
		return $value;
	}

	$user = get_user_by( 'id', absint( $user_id ) );
	if ( ! $user instanceof WP_User || ! function_exists( 'qs_user_is_joiner' ) || ! qs_user_is_joiner( $user ) ) {
		return '—';
	}

	return qs_portal_user_has_test_access( $user )
		? '<span style="color:#146c2e;font-weight:600">Allowed</span>'
		: '<span style="color:#646970">No</span>';
}
add_filter( 'manage_users_custom_column', 'qs_portal_users_column_content', 10, 3 );
