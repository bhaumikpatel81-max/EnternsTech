<?php
/**
 * WP Admin settings page: Enterns Portal → Settings.
 * Shows SMTP status, test-email form, and DNS record documentation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Register admin menu ───────────────────────────────────────────────────────

add_action( 'admin_menu', 'enp_admin_menu' );
function enp_admin_menu() {
	add_menu_page(
		__( 'Enterns Portal', 'enterns-portal' ),
		__( 'Enterns Portal', 'enterns-portal' ),
		'manage_options',
		'enp-settings',
		'enp_render_settings_page',
		'dashicons-groups',
		30
	);
}

// ── Enqueue inline JS for the settings page ───────────────────────────────────

add_action( 'admin_print_scripts', 'enp_settings_inline_js' );
function enp_settings_inline_js() {
	$screen = get_current_screen();
	if ( ! $screen || 'toplevel_page_enp-settings' !== $screen->id ) {
		return;
	}
	?>
	<script>
	jQuery(function($){
		$('#enp-test-email-btn').on('click', function(){
			var $btn = $(this);
			var $msg = $('#enp-test-email-msg');
			var email = $('#enp-test-email-addr').val();
			$btn.prop('disabled', true).text('Sending…');
			$msg.hide().removeClass('notice-success notice-error');
			$.post(ajaxurl, {
				action:    'enp_test_email',
				nonce:     $('#enp_test_email_nonce').val(),
				recipient: email
			}, function(res){
				$btn.prop('disabled', false).text('Send test email');
				$msg.show().addClass(res.success ? 'notice-success' : 'notice-error')
				    .find('p').text(res.data);
			}).fail(function(){
				$btn.prop('disabled', false).text('Send test email');
				$msg.show().addClass('notice-error').find('p').text('Request failed.');
			});
		});
	});
	</script>
	<?php
}

// ── Settings page renderer ────────────────────────────────────────────────────

function enp_render_settings_page() {
	$smtp_ok   = enp_smtp_configured();
	$host      = defined( 'ENP_SMTP_HOST' )      ? ENP_SMTP_HOST      : '—';
	$port      = defined( 'ENP_SMTP_PORT' )      ? ENP_SMTP_PORT      : '—';
	$encrypt   = defined( 'ENP_SMTP_ENCRYPT' )   ? ENP_SMTP_ENCRYPT   : '—';
	$user      = defined( 'ENP_SMTP_USER' )      ? ENP_SMTP_USER      : '—';
	$from      = defined( 'ENP_SMTP_FROM' )      ? ENP_SMTP_FROM      : '—';
	$from_name = defined( 'ENP_SMTP_FROM_NAME' ) ? ENP_SMTP_FROM_NAME : '—';
	$admin_email = enp_config( 'admin_email' );
	?>
	<div class="wrap">
		<h1 style="display:flex;align-items:center;gap:10px;">
			Enterns Portal
			<span style="font-size:12px;background:#0c1426;color:#22d3ee;border:1px solid rgba(34,211,238,.3);border-radius:4px;padding:2px 8px;font-weight:400;">
				v<?php echo esc_html( ENP_VERSION ); ?>
			</span>
		</h1>

		<!-- ── SMTP Status ── -->
		<h2 style="margin-top:1.5rem;"><?php esc_html_e( 'SMTP Configuration', 'enterns-portal' ); ?></h2>

		<?php if ( $smtp_ok ) : ?>
		<div class="notice notice-success inline"><p>
			<strong><?php esc_html_e( 'SMTP is configured.', 'enterns-portal' ); ?></strong>
			<?php esc_html_e( 'WordPress will send mail through your Bluehost SMTP server.', 'enterns-portal' ); ?>
		</p></div>
		<?php else : ?>
		<div class="notice notice-warning inline"><p>
			<strong><?php esc_html_e( 'SMTP not yet configured.', 'enterns-portal' ); ?></strong>
			<?php esc_html_e( 'Add the ENP_SMTP_* constants to wp-config.php (see snippet below) — then reload this page.', 'enterns-portal' ); ?>
		</p></div>
		<?php endif; ?>

		<table class="widefat" style="max-width:600px;margin-top:1rem;">
			<tbody>
				<?php
				$rows = array(
					array( 'ENP_SMTP_HOST',      $host,      'Bluehost SMTP server hostname' ),
					array( 'ENP_SMTP_PORT',      $port,      '465 (SSL) or 587 (TLS)' ),
					array( 'ENP_SMTP_ENCRYPT',   $encrypt,   '"ssl" or "tls"' ),
					array( 'ENP_SMTP_USER',      $user,      'Full mailbox address' ),
					array( 'ENP_SMTP_PASS',      $smtp_ok ? '••••••••' : '—', 'Mailbox password (hidden)' ),
					array( 'ENP_SMTP_FROM',      $from,      'From address on all emails' ),
					array( 'ENP_SMTP_FROM_NAME', $from_name, 'From name on all emails' ),
				);
				foreach ( $rows as $row ) :
				?>
				<tr>
					<td style="font-family:monospace;font-weight:600;width:220px;"><?php echo esc_html( $row[0] ); ?></td>
					<td style="font-family:monospace;"><?php echo esc_html( $row[1] ); ?></td>
					<td style="color:#6b7280;font-size:12px;"><?php echo esc_html( $row[2] ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- ── wp-config.php snippet ── -->
		<h3 style="margin-top:1.5rem;"><?php esc_html_e( 'Add to wp-config.php', 'enterns-portal' ); ?></h3>
		<p style="color:#6b7280;font-size:13px;"><?php esc_html_e( 'Paste this block above the "/* That\'s all, stop editing!" line. Never commit this file to GitHub.', 'enterns-portal' ); ?></p>
		<textarea readonly rows="11" style="width:100%;max-width:680px;font-family:monospace;font-size:12px;background:#1e1e1e;color:#d4d4d4;border:1px solid #3c3c3c;border-radius:4px;padding:12px;resize:vertical;"
		><?php echo esc_textarea( "// ── Enterns Portal — SMTP ──────────────────────────────────────────
// Get the exact hostname from: Bluehost hPanel → Hosting → Manage → Email → Configure Client
define( 'ENP_SMTP_HOST',      'mail.enternstech.com' );  // or your Bluehost server hostname
define( 'ENP_SMTP_PORT',      465 );                     // 465=SSL  |  587=TLS/STARTTLS
define( 'ENP_SMTP_ENCRYPT',   'ssl' );                   // 'ssl'    |  'tls'
define( 'ENP_SMTP_USER',      'admin@enternstech.com' ); // full mailbox address
define( 'ENP_SMTP_PASS',      'REPLACE_WITH_MAILBOX_PASSWORD' );
define( 'ENP_SMTP_FROM',      'admin@enternstech.com' );
define( 'ENP_SMTP_FROM_NAME', 'Enterns Tech' );" ); ?></textarea>

		<!-- ── Test email ── -->
		<h3 style="margin-top:1.5rem;"><?php esc_html_e( 'Send a test email', 'enterns-portal' ); ?></h3>
		<?php if ( ! $smtp_ok ) : ?>
		<p style="color:#9ca3af;"><?php esc_html_e( 'Configure SMTP first, then use this to verify delivery.', 'enterns-portal' ); ?></p>
		<?php else : ?>
		<div id="enp-test-email-msg" class="notice inline" style="display:none;max-width:480px;"><p></p></div>
		<p style="display:flex;gap:8px;flex-wrap:wrap;max-width:480px;align-items:center;">
			<?php wp_nonce_field( 'enp_test_email', 'enp_test_email_nonce' ); ?>
			<input type="email" id="enp-test-email-addr"
				   value="<?php echo esc_attr( $admin_email ); ?>"
				   style="flex:1;min-width:240px;"
				   placeholder="you@example.com" />
			<button id="enp-test-email-btn" type="button" class="button button-primary">
				<?php esc_html_e( 'Send test email', 'enterns-portal' ); ?>
			</button>
		</p>
		<?php endif; ?>

		<!-- ── DNS Records ── -->
		<h2 style="margin-top:2rem;"><?php esc_html_e( 'DNS Records for Deliverability', 'enterns-portal' ); ?></h2>
		<p><?php esc_html_e( 'Add these three DNS records in Bluehost hPanel → Domains → DNS Zone Editor to prevent email being classified as spam.', 'enterns-portal' ); ?></p>

		<table class="widefat" style="max-width:900px;margin-top:1rem;">
			<thead>
				<tr>
					<th style="width:80px;"><?php esc_html_e( 'Type', 'enterns-portal' ); ?></th>
					<th style="width:200px;"><?php esc_html_e( 'Name / Host', 'enterns-portal' ); ?></th>
					<th><?php esc_html_e( 'Value', 'enterns-portal' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Where to get it', 'enterns-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong>TXT</strong></td>
					<td style="font-family:monospace;">@</td>
					<td style="font-family:monospace;font-size:12px;">v=spf1 include:bluehost.com ~all</td>
					<td style="font-size:12px;color:#6b7280;"><?php esc_html_e( 'Bluehost hPanel → Email → Email Deliverability → Fix (copy SPF value shown there)', 'enterns-portal' ); ?></td>
				</tr>
				<tr style="background:#f9f9f9;">
					<td><strong>TXT</strong></td>
					<td style="font-family:monospace;font-size:12px;">default._domainkey</td>
					<td style="font-family:monospace;font-size:11px;word-break:break-all;">v=DKIM1; k=rsa; p=<em>&lt;generated by Bluehost&gt;</em></td>
					<td style="font-size:12px;color:#6b7280;"><?php esc_html_e( 'Bluehost hPanel → Email → Email Deliverability → DKIM record (copy the full value shown)', 'enterns-portal' ); ?></td>
				</tr>
				<tr>
					<td><strong>TXT</strong></td>
					<td style="font-family:monospace;">_dmarc</td>
					<td style="font-family:monospace;font-size:12px;">v=DMARC1; p=quarantine; rua=mailto:<?php echo esc_html( $admin_email ); ?>; aspf=r; adkim=r;</td>
					<td style="font-size:12px;color:#6b7280;"><?php esc_html_e( 'Add this manually — no generation needed.', 'enterns-portal' ); ?></td>
				</tr>
			</tbody>
		</table>

		<div class="notice notice-info inline" style="max-width:900px;margin-top:1rem;">
			<p>
				<strong><?php esc_html_e( 'Step-by-step in Bluehost:', 'enterns-portal' ); ?></strong><br>
				<?php esc_html_e( '1. hPanel → Email → Email Accounts → Create mailbox admin@enternstech.com (if not already done).', 'enterns-portal' ); ?><br>
				<?php esc_html_e( '2. hPanel → Email → Email Deliverability → find enternstech.com → click "Fix" for both SPF and DKIM. Copy the exact record values shown.', 'enterns-portal' ); ?><br>
				<?php esc_html_e( '3. hPanel → Domains → DNS Zone Editor → Add the three TXT records above.', 'enterns-portal' ); ?><br>
				<?php esc_html_e( '4. DNS propagation can take 10–60 minutes.', 'enterns-portal' ); ?><br>
				<?php esc_html_e( '5. Add SMTP constants to wp-config.php on the server (not locally), reload this page, then use the test form above.', 'enterns-portal' ); ?>
			</p>
		</div>

		<!-- ── How to verify ── -->
		<h2 style="margin-top:2rem;"><?php esc_html_e( 'Verification checklist', 'enterns-portal' ); ?></h2>
		<ul style="list-style:disc;padding-left:1.5rem;color:#374151;line-height:2;">
			<li><?php esc_html_e( 'SMTP constants set in wp-config.php on the server.', 'enterns-portal' ); ?></li>
			<li><?php esc_html_e( 'Test email arrives in inbox (not spam) within 1 minute.', 'enterns-portal' ); ?></li>
			<li><?php esc_html_e( 'Email From shows "Enterns Tech <admin@enternstech.com>".', 'enterns-portal' ); ?></li>
			<li><?php esc_html_e( 'SPF, DKIM, and DMARC records added in DNS.', 'enterns-portal' ); ?></li>
			<li><?php esc_html_e( 'Create a test user → Forgot Password → email arrives → link opens WP password screen.', 'enterns-portal' ); ?></li>
			<li><?php esc_html_e( 'After setting password, login lands on correct portal (/mentor/ or /student/).', 'enterns-portal' ); ?>