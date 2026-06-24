<?php
/**
 * Privacy helpers — enforce data-sharing rules between roles.
 *
 * Rules enforced here:
 *   - Mentors NEVER see a student's email or phone number.
 *   - Students NEVER see a mentor's phone or personal email.
 *   - Only admin (manage_options) has unrestricted data access.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return a student record stripped of fields mentors must not see.
 *
 * Removes: email, phone, user_id.
 *
 * @param array|object $student Raw row from wp_enp_students.
 * @return array Safe subset for mentor-facing display.
 */
function enp_student_for_mentor( $student ) {
	if ( is_object( $student ) ) {
		$student = (array) $student;
	}
	$allowed = array(
		'id', 'full_name', 'college', 'tech_stack',
		'cv_url', 'live_project', 'plan_id',
		'sessions_total', 'sessions_used',
		'cv_redesign_status', 'status', 'created_at',
	);
	return array_intersect_key( $student, array_flip( $allowed ) );
}

/**
 * Return a mentor record stripped of fields students must not see.
 *
 * Removes: email, phone, admin_note, user_id.
 *
 * @param array|object $mentor Raw row from wp_enp_mentors.
 * @return array Safe subset for student-facing display.
 */
function enp_mentor_for_student( $mentor ) {
	if ( is_object( $mentor ) ) {
		$mentor = (array) $mentor;
	}
	$allowed = array(
		'id', 'full_name', 'photo_url', 'linkedin',
		'tech_stack', 'available_slots', 'extra_fields',
		'status', 'created_at',
	);
	return array_intersect_key( $mentor, array_flip( $allowed ) );
}
