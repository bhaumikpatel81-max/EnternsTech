<?php
/**
 * SMTP configuration and email helpers.
 *
 * Add these constants to wp-config.php (never commit them):
 *
 *   define( 'ENP_SMTP_HOST',      'mail.enternstech.com' ); // Bluehost mail server
 *   define( 'ENP_SMTP_PORT',      465 );                    // 465 = SSL, 587 = TLS
 *   define( 'ENP_SMTP_ENCRYPT',   'ssl' );                  // 'ssl' or 'tls'
 *   define( 'ENP_SMTP_USER',      'admin@enternstech.com' );
 *   define( 'ENP_SMTP_PASS',      'your-mailbox-password' );
 *   define( 'ENP_SMTP_FROM',      'admin@enternstech.com' );
 *   define( 'ENP_SMTP_FROM_NAME', 'Enterns Tech' );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── SMTP via PHPMailer ────────────────────────────────────────────────────────

add_action( 'phpmailer_init', 'enp_configure_smtp' );
function enp_configure_smtp( $phpmailer ) {
	if ( ! defined( 'ENP_SMTP_HOST' ) || ! defined( 'ENP_SMTP_USER' ) || ! defined( 'ENP_SMTP_PASS' ) ) {
		return;
	}
	$phpmailer->isSMTP();
	$phpmailer->Host       = ENP_SMTP_HOST;
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Port       = defined( 'ENP_SMTP_PORT' )    ? (int) ENP_SMTP_PORT    : 465;
	$phpmailer->SMTPSecure = defined( 'ENP_SMTP_ENCRYPT' ) ? ENP_SMTP_ENCRYPT       : 'ssl';
	$phpmailer->Username   = ENP_SMTP_USER;
	$phpmailer->Password   = ENP_SMTP_PASS;
	$phpmailer->From       = defined( 'ENP_SMTP_FROM' )      ? ENP_SMTP_FROM      : ENP_SMTP_USER;
	$phpmailer->FromName   = defined( 'ENP_SMTP_FROM_NAME' ) ? ENP_SMTP_FROM_NAME : 'Enterns Tech';
}

// ── Always send FROM admin@enternstech.com ────────────────────────────────────

add_filter( 'wp_mail_from', 'enp_mail_from' );
function enp_mail_from( $original ) {
	return defined( 'ENP_SMTP_FROM' ) ? ENP_SMTP_FROM : $original;
}

add_filter( 'wp_mail_from_name', 'enp_mail_from_name' );
function enp_mail_from_name( $original ) {
	return defined( 'ENP_SMTP_FROM_NAME' ) ? ENP_SMTP_FROM_NAME : $original;
}

// ── Central mail wrapper ──────────────────────────────────────────────────────
// All plugin-generated emails go through enp_send_mail() so BCC is consistent.

/**
 * Send a plugin email, always BCCing admin@.
 *
 * @param string|array $to      Recipient(s).
 * @param string       $subject Subject line.
 * @param string       $message HTML message body (auto-wrapped in brand template).
 * @param bool         $bcc_admin Whether to BCC admin@enternstech.com (default true).
 * @return bool
 */
function enp_send_mail( $to, $subject, $message, $bcc_admin = true ) {
	$admin_email = enp_config( 'admin_email' );
	$from_name   = enp_config( 'from_name' );

	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
	);
	if ( $bcc_admin && $admin_email ) {
		$headers[] = 'Bcc: ' . $admin_email;
	}

	$body = enp_mail_wrap( $subject, $message );

	return wp_mail( $to, $subject, $body, $headers );
}

/**
 * Wrap message body in a minimal brand-consistent HTML email template.
 *
 * @param string $title   Used as the email heading.
 * @param string $content HTML content body.
 * @return string
 */
function enp_mail_wrap( $title, $content ) {
	$cyan  = '#22D3EE';
	$bg    = '#05080F';
	$surf  = '#0C1426';
	$text  = '#ECF2FF';
	$muted = '#6B7280';
	$site  = esc_url( home_url() );

	ob_start();
	?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
</head>
<body style="margin:0;padding:0;background:<?php echo $bg; ?>;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:<?php echo $bg; ?>;padding:32px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:<?php echo $surf; ?>;border-radius:16px;border:1px solid rgba(34,211,238,0.12);overflow:hidden;">

        <!-- Header -->
        <tr>
          <td style="padding:28px 32px;border-bottom:1px solid rgba(34,211,238,0.12);">
            <a href="<?php echo $site; ?>" style="text-decoration:none;font-size:1.1rem;font-weight:700;color:<?php echo $cyan; ?>;">
              Enterns Tech
            </a>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px;color:<?php echo $text; ?>;font-size:15px;line-height:1.7;">
            <?php echo $content; // Already sanitized by caller. ?>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:20px 32px;border-top:1px solid rgba(34,211,238,0.08);font-size:12px;color:<?php echo $muted; ?>;">
            You received this email from Enterns Tech. If you did not expect it, you can safely ignore it.
            &mdash; <a href="<?php echo $site; ?>" style="color:<?php echo $cyan; ?>;">enternstech.com</a>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
	<?php
	return ob_get_clean();
}

// ── SMTP status helper ────────────────────────────────────────────────────────

function enp_smtp_configured() {
	return defined( 'ENP_SMTP_HOST' )
		&& defined( 'ENP_SMTP_USER' )
		&& defined( 'ENP_SMTP_PASS' );
}

// ── Test-email AJAX (admin only) ──────────────────────────────────────────────

add_action( 'wp_ajax_enp_test_email', 'enp_ajax_test_email' );
function enp_ajax_test_email() {
	check_ajax_referer( 'enp_test_email', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Permission denied.', 'enterns-portal' ) );
	}

	if ( ! enp_smtp_configured() ) {
		wp_send_json_error( __( 'SMTP is not configured. Add ENP_SMTP_* constants to wp-config.php first.', 'enterns-portal' ) );
	}

	$recipient = sanitize_email( wp_unslash( $_POST['recipient'] ?? '' ) );
	if ( ! is_email( $recipient ) ) {
		wp_send_json_error( __( 'Please enter a valid email address.', 'enterns-portal' ) );
	}

	$subject = __( 'Enterns Tech — SMTP test email', 'enterns-portal' );
	$content = '<h2 style="color:#22D3EE;margin:0 0 16px">SMTP is working!</h2>'
		. '<p>This test email was sent via SMTP from your Enterns Tech WordPress installation.</p>'
		. '<p style="color:#6B7280;font-size:13px;">Sent at: ' . esc_html( current_time( 'mysql' ) ) . '</p>';

	$sent = enp_send_mail( $recipient, $subject, $content, false );

	if ( $sent ) {
		wp_send_json_success( sprintf(
			/* translators: %s: email address */
			__( 'Test email sent to %s. Check your inbox (and spam folder).', 'enterns-portal' ),
			$recipient
		) );
	} else {
		global $phpmailer;
		$err = '';
		if ( isset( $phpmailer ) && method_exists( $phpmailer, 'ErrorInfo' ) ) {
			$err = $phpmailer->ErrorInfo;
		}
		wp_send_json_error(
			__( 'wp_mail() returned false.', 'enterns-portal' )
			. ( $err ? ' PHPMailer: ' . esc_html( $err ) : '' )
		);
	}
}
