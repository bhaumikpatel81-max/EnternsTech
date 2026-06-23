<?php
/**
 * Role / capability gates.
 * - Redirect mentors + students away from wp-admin.
 * - Point login redirect to correct portal.
 * - Hide admin bar for non-admin roles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Block wp-admin for non-admins (AJAX is allowed through).
add_action( 'admin_init', 'enp_block_admin_for_portal_roles' );
function enp_block_admin_for_portal_roles() {
	if ( wp_doing_ajax() ) {
		return;
	}
	$user = wp_get_current_user();
	if ( ! $user || ! $user->exists() || current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( in_array( 'et_mentor', (array) $user->roles, true ) ) {
		wp_safe_redirect( home_url( '/mentor/' ) );
		exit;
	}
	if ( in_array( 'et_student', (array) $user->roles, true ) ) {
		wp_safe_redirect( home_url( '/student/' ) );
		exit;
	}
}

// Always send portal roles to their own portals after login.
add_filter( 'login_redirect', 'enp_login_redirect', 10, 3 );
function enp_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( is_wp_error( $user ) || ! $user || ! $user->exists() ) {
		return $redirect_to;
	}
	if ( in_array( 'et_mentor', (array) $user->roles, true ) ) {
		return home_url( '/mentor/' );
	}
	if ( in_array( 'et_student', (array) $user->roles, true ) ) {
		return home_url( '/student/' );
	}
	return $redirect_to;
}

// Hide the admin toolbar for portal roles.
add_action( 'after_setup_theme', 'enp_maybe_hide_admin_bar' );
function enp_maybe_hide_admin_bar() {
	$user = wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return;
	}
	if ( in_array( 'et_mentor', (array) $user->roles, true )
		|| in_array( 'et_student', (array) $user->roles, true ) ) {
		show_admin_bar( false );
	}
}
