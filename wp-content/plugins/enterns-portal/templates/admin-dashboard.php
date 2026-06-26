<?php
/**
 * Admin portal — Phase 1 skeleton.
 * Full features (applications, students, payments, sessions) added in Phases 3–6.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;

$mentor_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}enp_mentors" );
$student_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}enp_students" );
$pending_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}enp_mentors WHERE status = 'pending'" );
$session_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}enp_sessions" );
?>
<div class="enp-wrap">

	<div class="enp-portal-header">
		<div class="enp-portal-header__title">
			<span class="enp-badge enp-badge--admin">Admin</span>
			<h1><?php esc_html_e( 'Enterns Tech — Admin Portal', 'enterns-portal' ); ?></h1>
		</div>
		<a class="enp-btn enp-btn--ghost enp-btn--sm"
		   href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
			<?php esc_html_e( 'Log out', 'enterns-portal' ); ?>
		</a>
	</div>

	<div class="enp-stats-grid">
		<div class="enp-stat-card">
			<span class="enp-stat-card__value"><?php echo esc_html( $mentor_count ); ?></span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Mentors', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value"><?php echo esc_html( $student_count ); ?></span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Students', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card enp-stat-card--alert">
			<span class="enp-stat-card__value"><?php echo esc_html( $pending_count ); ?></span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Pending Applications', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value"><?php echo esc_html( $session_count ); ?></span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Sessions', 'enterns-portal' ); ?></span>
		</div>
	</div>

	<div class="enp-notice enp-notice--info" style="margin-top:2rem;">
		<strong><?php esc_html_e( 'Phase 1 complete.', 'enterns-portal' ); ?></strong>
		<?php esc_html_e( 'Mentor applications, student management, payments, and session tracking are being built in Phases 3–6.', 'enterns-portal' ); 