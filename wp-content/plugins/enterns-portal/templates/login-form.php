<?php
/**
 * Shown on any portal page when the visitor is not logged in.
 * $login_url is set by the calling function enp_login_prompt().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( empty( $login_url ) ) {
	$login_url = wp_login_url();
}
?>
<div class="enp-wrap">
	<div class="enp-login-card">
		<div class="enp-login-card__logo">
			<svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
				<circle cx="20" cy="20" r="20" fill="rgba(34,211,238,0.12)"/>
				<path d="M12 20h16M20 12v16" stroke="#22D3EE" stroke-width="2.5" stroke-linecap="round"/>
			</svg>
			<span>Enterns Tech</span>
		</div>
		<h1 class="enp-login-card__title">
			<?php esc_html_e( 'Sign in to continue', 'enterns-portal' ); ?>
		</h1>
		<p class="enp-login-card__sub">
			<?php esc_html_e( 'This area is restricted to enrolled students and verified mentors.', 'enterns-portal' ); ?>
		</p>
		<a class="enp-btn enp-btn--primary" href="<?php echo esc_url( $login_url ); ?>">
			<?php esc_html_e( 'Log in', 'enterns-portal' ); ?>
		</a>
		<p class="enp-login-card__foot">
			<?php esc_html_e( 'Not enrolled yet?', 'enterns-portal' ); ?>
			<a href="<?php echo esc_url( home_url( '/partner-with-us/' ) ); ?>">
				<?php esc_html_e( 'Apply to mentor', 'enterns-portal' ); ?>
			</a>
		</p>
	</div>
</div>
