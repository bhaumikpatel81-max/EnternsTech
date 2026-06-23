<?php
/**
 * Student portal — Phase 1 skeleton.
 * Full dashboard (mentor cards, sessions, plans) added in Phase 5.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;

$user       = wp_get_current_user();
$student_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}enp_students WHERE user_id = %d LIMIT 1",
		$user->ID
	)
);
$student = $student_id
	? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}enp_students WHERE id = %d", $student_id ) )
	: null;

$display_name = $student ? esc_html( $student->full_name ) : esc_html( $user->display_name );
?>
<div class="enp-wrap">

	<div class="enp-portal-header">
		<div class="enp-portal-header__title">
			<span class="enp-badge enp-badge--student">Student</span>
			<h1><?php printf( esc_html__( 'Welcome, %s', 'enterns-portal' ), $display_name ); ?></h1>
		</div>
		<a class="enp-btn enp-btn--ghost enp-btn--sm"
		   href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
			<?php esc_html_e( 'Log out', 'enterns-portal' ); ?>
		</a>
	</div>

	<?php if ( ! $student ) : ?>
	<div class="enp-notice enp-notice--info">
		<?php esc_html_e( 'Your student profile is being set up. Check back soon.', 'enterns-portal' ); ?>
	</div>
	<?php else : ?>
	<div class="enp-stats-grid">
		<div class="enp-stat-card">
			<span class="enp-stat-card__value"><?php echo esc_html( $student->sessions_used ); ?></span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Sessions Used', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value">
				<?php echo esc_html( max( 0, (int) $student->sessions_total - (int) $student->sessions_used ) ); ?>
			</span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Sessions Remaining', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value">
				<?php echo $student->plan_id ? esc_html( strtoupper( $student->plan_id ) ) : '—'; ?>
			</span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Current Plan', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value">
				<?php
				$cv_map = array(
					'pending'     => __( 'Pending', 'enterns-portal' ),
					'in_progress' => __( 'In Progress', 'enterns-portal' ),
					'done'        => __( 'Done', 'enterns-portal' ),
				);
				echo esc_html( $cv_map[ $student->cv_redesign_status ] ?? ucfirst( esc_html( $student->cv_redesign_status ) ) );
				?>
			</span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'CV Redesign', 'enterns-portal' ); ?></span>
		</div>
	</div>
	<?php endif; ?>

	<div class="enp-notice enp-notice--info" style="margin-top:2rem;">
		<strong><?php esc_html_e( 'Phase 1 complete.', 'enterns-portal' ); ?></strong>
		<?php esc_html_e( 'Mentor selection, session booking, plan upgrades, and skill editing are coming in Phase 5.', 'enterns-portal' ); ?>
	</div>

</div>
