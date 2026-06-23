<?php
/**
 * Mentor portal — Phase 1 skeleton.
 * Full dashboard (students, sessions, revenue) added in Phase 3.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;

$user      = wp_get_current_user();
$mentor_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}enp_mentors WHERE user_id = %d LIMIT 1",
		$user->ID
	)
);
$mentor    = $mentor_id
	? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}enp_mentors WHERE id = %d", $mentor_id ) )
	: null;

$display_name = $mentor ? esc_html( $mentor->full_name ) : esc_html( $user->display_name );
?>
<div class="enp-wrap">

	<div class="enp-portal-header">
		<div class="enp-portal-header__title">
			<span class="enp-badge enp-badge--mentor">Mentor</span>
			<h1><?php printf( esc_html__( 'Welcome, %s', 'enterns-portal' ), $display_name ); ?></h1>
		</div>
		<a class="enp-btn enp-btn--ghost enp-btn--sm"
		   href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
			<?php esc_html_e( 'Log out', 'enterns-portal' ); ?>
		</a>
	</div>

	<?php if ( ! $mentor ) : ?>
	<div class="enp-notice enp-notice--info">
		<?php esc_html_e( 'Your mentor profile is being set up by the admin. Check back soon.', 'enterns-portal' ); ?>
	</div>
	<?php else : ?>
	<div class="enp-stats-grid">
		<div class="enp-stat-card">
			<span class="enp-stat-card__value">
				<?php
				echo esc_html(
					(int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}enp_students WHERE mentor_id = %d AND status = 'active'",
							$mentor_id
						)
					)
				);
				?>
			</span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Active Students', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value">
				<?php
				echo esc_html(
					(int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}enp_sessions WHERE mentor_id = %d AND status = 'completed'",
							$mentor_id
						)
					)
				);
				?>
			</span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Sessions Completed', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value">
				<?php
				echo esc_html(
					(int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}enp_sessions WHERE mentor_id = %d AND status = 'planned'",
							$mentor_id
						)
					)
				);
				?>
			</span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Sessions Planned', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value">
				&#8377;<?php
				$rev = (float) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COALESCE(SUM(rate_applied),0) FROM {$wpdb->prefix}enp_sessions WHERE mentor_id = %d AND status = 'completed'",
						$mentor_id
					)
				);
				echo esc_html( number_format( $rev, 0 ) );
				?>
			</span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Revenue Generated', 'enterns-portal' ); ?></span>
		</div>
	</div>
	<?php endif; ?>

	<div class="enp-notice enp-notice--info" style="margin-top:2rem;">
		<strong><?php esc_html_e( 'Phase 1 complete.', 'enterns-portal' ); ?></strong>
		<?php esc_html_e( 'Full student management, session scheduling, and profile editing are coming in Phase 3.', 'enterns-portal' ); ?>
	</div>

</div>
