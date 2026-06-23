<?php
/**
 * Mentor application AJAX handler.
 * Receives the partner form submission, validates, uploads photo,
 * inserts pending row, and emails the team.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_enp_partner_apply',        'enp_handle_partner_apply' );
add_action( 'wp_ajax_nopriv_enp_partner_apply', 'enp_handle_partner_apply' );

function enp_handle_partner_apply() {
	check_ajax_referer( 'enp_partner_apply', 'enp_partner_nonce' );

	// ── Sanitize inputs ───────────────────────────────────────────────────────
	$full_name       = sanitize_text_field( wp_unslash( $_POST['full_name']       ?? '' ) );
	$email           = sanitize_email(      wp_unslash( $_POST['email']           ?? '' ) );
	$phone           = sanitize_text_field( wp_unslash( $_POST['phone']           ?? '' ) );
	$linkedin        = esc_url_raw(         wp_unslash( $_POST['linkedin']        ?? '' ) );
	$tech_stack      = sanitize_text_field( wp_unslash( $_POST['tech_stack']      ?? '' ) );
	$available_slots = max( 1, min( 20, (int) ( $_POST['available_slots'] ?? 1 ) ) );

	// ── Required field validation ─────────────────────────────────────────────
	if ( ! $full_name || ! $email || ! $phone || ! $tech_stack ) {
		wp_send_json_error( __( 'Please fill in all required fields.', 'enterns-portal' ) );
	}
	if ( ! is_email( $email ) ) {
		wp_send_json_error( __( 'Please enter a valid email address.', 'enterns-portal' ) );
	}

	// ── Duplicate check ───────────────────────────────────────────────────────
	global $wpdb;
	$table  = $wpdb->prefix . 'enp_mentors';
	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s LIMIT 1", $email ) );
	if ( $exists ) {
		wp_send_json_error( __( 'An application with this email address already exists. Contact us if you need help.', 'enterns-portal' ) );
	}

	// ── Photo upload (optional) ───────────────────────────────────────────────
	$photo_url = '';
	if ( ! empty( $_FILES['photo']['name'] ) && UPLOAD_ERR_OK === $_FILES['photo']['error'] ) {
		if ( $_FILES['photo']['size'] > 2 * 1024 * 1024 ) {
			wp_send_json_error( __( 'Photo must be smaller than 2 MB.', 'enterns-portal' ) );
		}
		// Validate actual MIME type from file bytes, not user-supplied type
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = finfo_file( $finfo, $_FILES['photo']['tmp_name'] );
			finfo_close( $finfo );
			if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
				wp_send_json_error( __( 'Photo must be a JPG, PNG, or WebP image.', 'enterns-portal' ) );
			}
		}
		// Restrict upload mimes to images only, then upload
		$mime_filter = function() {
			return array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'webp'         => 'image/webp',
			);
		};
		add_filter( 'upload_mimes', $mime_filter, 99 );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload = wp_handle_upload( $_FILES['photo'], array( 'test_form' => false ) );
		remove_filter( 'upload_mimes', $mime_filter, 99 );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error(
				sprintf( __( 'Photo upload failed: %s', 'enterns-portal' ), $upload['error'] )
			);
		}
		$photo_url = $upload['url'];
	}

	// ── Insert mentor application row ─────────────────────────────────────────
	$rate     = (float) enp_config( 'mentor_rate' );
	$inserted = $wpdb->insert(
		$table,
		array(
			'full_name'        => $full_name,
			'email'            => $email,
			'phone'            => $phone,
			'linkedin'         => $linkedin,
			'photo_url'        => $photo_url,
			'tech_stack'       => $tech_stack,
			'available_slots'  => $available_slots,
			'rate_per_session' => $rate,
			'extra_fields'     => '{}',
			'status'           => 'pending',
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s' )
	);

	if ( ! $inserted ) {
		wp_send_json_error( __( 'Could not save your application. Please try again.', 'enterns-portal' ) );
	}

	// ── Notification email to mentor@enternstech.com (+ BCC admin@) ──────────
	$mentor_email = enp_config( 'mentor_email' );
	$subject      = sprintf( 'New mentor application from %s', $full_name );

	$rows = array(
		'Name'         => esc_html( $full_name ),
		'Email'        => esc_html( $email ),
		'Phone'        => esc_html( $phone ),
		'Tech Stack'   => esc_html( $tech_stack ),
		'LinkedIn'     => $linkedin
			? '<a href="' . esc_url( $linkedin ) . '" style="color:#22D3EE">' . esc_html( $linkedin ) . '</a>'
			: '—',
		'Slots / Week' => esc_html( (string) $available_slots ),
	);
	$table_html = '<table style="width:100%;border-collapse:collapse">';
	foreach ( $rows as $label => $value ) {
		$table_html .= '<tr>'
			. '<td style="padding:6px 16px 6px 0;color:#94a3b8;white-space:nowrap;vertical-align:top"><strong>' . esc_html( $label ) . '</strong></td>'
			. '<td style="padding:6px 0">' . $value . '</td>'
			. '</tr>';
	}
	$table_html .= '</table>';

	$body  = '<h2 style="color:#22D3EE;margin:0 0 16px">New Mentor Application</h2>';
	$body .= $table_html;
	$body .= '<p style="margin-top:1.5rem">'
		. '<a href="' . esc_url( home_url( '/admin-portal/?section=applications' ) ) . '"'
		. ' style="background:#22D3EE;color:#05080F;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700">'
		. 'Review in Admin Portal'
		. '</a></p>';

	enp_send_mail( $mentor_email, $subject, $body, true );

	wp_send_json_success(
		__( 'Application submitted! Our team will review it and get back to you within 2 business days.', 'enterns-portal' )
	);
}
