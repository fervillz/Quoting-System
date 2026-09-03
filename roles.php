<?php
/**
 * Joiner user role and frontend access rules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the dedicated Joiner role used by Loughlin Furniture trade users.
 *
 * Joiners work entirely through the frontend Quote System. upload_files is
 * included so supporting quote documents can be attached from the Builder.
 */
function qs_register_joiner_role() {
	$role = get_role( 'joiner' );

	if ( ! $role ) {
		$role = add_role(
			'joiner',
			__( 'Joiner', 'quote-system' ),
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);
	}

	// Existing installs may already have the role from an earlier version.
	// Ensure the minimum frontend capabilities remain available.
	if ( $role instanceof WP_Role ) {
		$role->add_cap( 'read' );
		$role->add_cap( 'upload_files' );
	}
}
add_action( 'init', 'qs_register_joiner_role', 5 );

/** Return true when a user has the dedicated Joiner role. */
function qs_user_is_joiner( $user = null ) {
	if ( null === $user ) {
		$user = wp_get_current_user();
	} elseif ( is_numeric( $user ) ) {
		$user = get_user_by( 'id', absint( $user ) );
	}

	return $user instanceof WP_User && in_array( 'joiner', (array) $user->roles, true );
}

/** Frontend landing page for Joiners after authentication. */
function qs_joiner_dashboard_url() {
	return site_url( '/my-quotes/' );
}

/**
 * Redirect Joiners who authenticate through WordPress's normal login screen
 * to their frontend Quote System dashboard.
 */
function qs_joiner_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( is_wp_error( $user ) || ! qs_user_is_joiner( $user ) ) {
		return $redirect_to;
	}

	return qs_joiner_dashboard_url();
}
add_filter( 'login_redirect', 'qs_joiner_login_redirect', 20, 3 );

/**
 * Joiners should not use the WordPress dashboard. Keep AJAX available because
 * frontend Quote System calculations rely on admin-ajax.php.
 */
function qs_joiner_block_wp_admin() {
	if ( ! is_user_logged_in() || ! qs_user_is_joiner() ) {
		return;
	}

	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		return;
	}

	wp_safe_redirect( qs_joiner_dashboard_url() );
	exit;
}
add_action( 'admin_init', 'qs_joiner_block_wp_admin', 1 );

/** Hide the WordPress admin toolbar for Joiners on frontend pages. */
function qs_joiner_show_admin_bar( $show ) {
	return qs_user_is_joiner() ? false : $show;
}
add_filter( 'show_admin_bar', 'qs_joiner_show_admin_bar', 20 );
