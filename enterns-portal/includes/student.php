<?php
/**
 * Phase 5: Student portal AJAX handlers.
 * Actions: enp_update_skills, enp_pick_mentor, enp_request_mentor_change.
 * All use wp_ajax_ (logged-in only — students are always logged in).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Update skills ─────────────────────────────────────────────────────────────

add_action( 'wp_ajax_enp_update_skills', 'enp_ajax_update_skills' );
function enp_ajax_update_skills() {
	check_ajax_referer( 'enp_portal', 'nonce' );

	$user = wp_get_current_user();
	if ( ! in_array( 'et_student', (array) $user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$student = $wpdb->get_row( $wpdb->prepare(
		"SELECT id FROM {$p}enp_students WHERE user_id = %d LIMIT 1",
		$user->ID
	) );

	if ( ! $student ) {
		wp_send_json_error( 'Student profile not found.' );
	}

	$raw    = sanitize_text_field( wp_unslash( $_POST['tech_stack'] ?? '' ) );
	$skills = array_unique( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	$clean  = implode( ', ', $skills );

	$wpdb->update(
		"{$p}enp_students",
		array( 'tech_stack' => $clean ),
		array( 'id' => (int) $student->id ),
		array( '%s' ),
		array( '%d' )
	);

	wp_send_json_success( array( 'tech_stack' => esc_html( $clean ) ) );
}

// ── Pick mentor ───────────────────────────────────────────────────────────────

add_action( 'wp_ajax_enp_pick_mentor', 'enp_ajax_pick_mentor' );
function enp_ajax_pick_mentor() {
	check_ajax_referer( 'enp_portal', 'nonce' );

	$user = wp_get_current_user();
	if ( ! in_array( 'et_student', (array) $user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$student = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, mentor_id FROM {$p}enp_students WHERE user_id = %d LIMIT 1",
		$user->ID
	) );

	if ( ! $student ) {
		wp_send_json_error( 'Student profile not found.' );
	}
	if ( $student->mentor_id ) {
		wp_send_json_error( 'You already have a mentor. Use "Request Change" to switch mentors.' );
	}

	$mentor_id = (int) ( $_POST['mentor_id'] ?? 0 );
	if ( ! $mentor_id ) {
		wp_send_json_error( 'Invalid mentor selected.' );
	}

	$mentor = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, full_name, available_slots FROM {$p}enp_mentors WHERE id = %d AND status = 'approved' LIMIT 1",
		$mentor_id
	) );

	if ( ! $mentor ) {
		wp_send_json_error( 'Mentor not found or not available.' );
	}
	if ( (int) $mentor->available_slots < 1 ) {
		wp_send_json_error( 'This mentor has no available slots. Please choose another.' );
	}

	$wpdb->update(
		"{$p}enp_students",
		array( 'mentor_id' => $mentor_id ),
		array( 'id' => (int) $student->id ),
		array( '%d' ),
		array( '%d' )
	);

	wp_send_json_success( array(
		'message'     => 'Mentor ' . esc_html( $mentor->full_name ) . ' has been assigned to your profile.',
		'mentor_name' => esc_html( $mentor->full_name ),
	) );
}

// ── Request mentor change ─────────────────────────────────────────────────────

add_action( 'wp_ajax_enp_request_mentor_change', 'enp_ajax_request_mentor_change' );
function enp_ajax_request_mentor_change() {
	check_ajax_referer( 'enp_portal', 'nonce' );

	$user = wp_get_current_user();
	if ( ! in_array( 'et_student', (array) $user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied.' );
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$student = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, mentor_id, full_name FROM {$p}enp_students WHERE user_id = %d LIMIT 1",
		$user->ID
	) );

	if ( ! $student ) {
		wp_send_json_error( 'Student profile not found.' );
	}
	if ( ! $student->mentor_id ) {
		wp_send_json_error( 'You have no current mentor. Please pick one first.' );
	}

	$open = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$p}enp_requests WHERE student_id = %d AND type = 'mentor_change' AND status = 'open' LIMIT 1",
		(int) $student->id
	) );
	if ( $open ) {
		wp_send_json_error( 'You already have a pending mentor change request. Please wait for admin review.' );
	}

	$reason        = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
	$new_mentor_id = (int) ( $_POST['new_mentor_id'] ?? 0 );

	if ( ! $reason ) {
		wp_send_json_error( 'Please enter a reason for the mentor change.' );
	}

	$payload = wp_json_encode( array(
		'current_mentor_id' => (int) $student->mentor_id,
		'reason'            => $reason,
	) );

	if ( $new_mentor_id > 0 ) {
		$wpdb->insert(
			"{$p}enp_requests",
			array(
				'type'       => 'mentor_change',
				'student_id' => (int) $student->id,
				'mentor_id'  => $new_mentor_id,
				'payload'    => $payload,
				'status'     => 'open',
			),
			array( '%s', '%d', '%d', '%s', '%s' )
		);
	} else {
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$p}enp_requests (type, student_id, mentor_id, payload, status) VALUES (%s, %d, NULL, %s, %s)",
			'mentor_change',
			(int) $student->id,
			$payload,
			'open'
		) );
	}

	if ( function_exists( 'enp_send_mail' ) ) {
		$admin_email = enp_config( 'admin_email' );
		$portal_url  = home_url( '/admin-portal/?section=requests' );
		$stname      = $student->full_name ?: $user->display_name;
		$body  = "<h2 style='color:#22D3EE;margin:0 0 16px'>Mentor Change Request</h2>";
		$body .= "<p>Student <strong>" . esc_html( $stname ) . "</strong> has requested a mentor change.</p>";
		$body .= "<p><strong>Reason:</strong><br>" . nl2br( esc_html( $reason ) ) . "</p>";
		if ( $new_mentor_id > 0 ) {
			$nm = $wpdb->get_var( $wpdb->prepare( "SELECT full_name FROM {$p}enp_mentors WHERE id = %d LIMIT 1", $new_mentor_id ) );
			if ( $nm ) {
				$body .= "<p><strong>Preferred new mentor:</strong> " . esc_html( $nm ) . "</p>";
			}
		}
		$body .= "<p style='margin-top:1.5rem'><a href='" . esc_url( $portal_url ) . "'"
			. " style='background:#22D3EE;color:#05080F;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700'>"
			. "Review Request &rarr;</a></p>";
		enp_send_mail( $admin_email, 'Mentor change request — ' . esc_html( $stname ), $body, false );
	}

	wp_send_json_success( 'Your request has been submitted. Admin will review and notify you.' );
}
