<?php
/**
 * Mentor portal dashboard — Phase 3.
 * Privacy rule enforced: enp_student_for_mentor() strips phone/email/user_id
 * before any student data reaches this template.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;

$user    = wp_get_current_user();
$m_row   = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}enp_mentors WHERE user_id = %d LIMIT 1",
		$user->ID
	)
);
$m_id         = $m_row ? (int) $m_row->id : 0;
$display_name = $m_row ? esc_html( $m_row->full_name ) : esc_html( $user->display_name );

// Stats (only if mentor row exists)
$stat_students = 0;
$stat_done     = 0;
$stat_planned  = 0;
$stat_revenue  = 0.0;
if ( $m_id ) {
	$stat_students = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}enp_students WHERE mentor_id = %d AND status = 'active'",
		$m_id
	) );
	$stat_done = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}enp_sessions WHERE mentor_id = %d AND status = 'completed'",
		$m_id
	) );
	$stat_planned = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}enp_sessions WHERE mentor_id = %d AND status = 'planned'",
		$m_id
	) );
	$stat_revenue = (float) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(rate_applied),0) FROM {$wpdb->prefix}enp_sessions WHERE mentor_id = %d AND status = 'completed'",
		$m_id
	) );
}

// Assigned students — privacy-filtered (no phone, email, user_id)
$students = array();
if ( $m_id ) {
	$raw = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}enp_students WHERE mentor_id = %d ORDER BY full_name",
			$m_id
		),
		ARRAY_A
	);
	foreach ( $raw as $s ) {
		$students[] = enp_student_for_mentor( $s );
	}
}
?>
<div class="enp-wrap">

	<!-- Portal header -->
	<div class="enp-portal-header">
		<div class="enp-portal-header__title">
			<span class="enp-badge enp-badge--mentor"><?php esc_html_e( 'Mentor', 'enterns-portal' ); ?></span>
			<h1><?php printf( esc_html__( 'Welcome, %s', 'enterns-portal' ), $display_name ); ?></h1>
		</div>
		<a class="enp-btn enp-btn--ghost enp-btn--sm"
		   href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
			<?php esc_html_e( 'Log out', 'enterns-portal' ); ?>
		</a>
	</div>

	<?php if ( ! $m_row ) : ?>
	<div class="enp-notice enp-notice--info">
		<?php esc_html_e( 'Your mentor profile is being set up by the admin. Check back soon.', 'enterns-portal' ); ?>
	</div>
	<?php return; endif; ?>

	<!-- Stats -->
	<div class="enp-stats-grid">
		<div class="enp-stat-card">
			<span class="enp-stat-card__value"><?php echo esc_html( (string) $stat_students ); ?></span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Active Students', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value"><?php echo esc_html( (string) $stat_done ); ?></span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Sessions Completed', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value"><?php echo esc_html( (string) $stat_planned ); ?></span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Sessions Planned', 'enterns-portal' ); ?></span>
		</div>
		<div class="enp-stat-card">
			<span class="enp-stat-card__value">&#8377;<?php echo esc_html( number_format( $stat_revenue, 0 ) ); ?></span>
			<span class="enp-stat-card__label"><?php esc_html_e( 'Revenue Generated', 'enterns-portal' ); ?></span>
		</div>
	</div>

	<!-- On-platform communication notice -->
	<div class="enp-notice enp-notice--info" style="margin-bottom:2rem">
		<?php esc_html_e( 'All student communication happens through the Enterns Tech platform. Do not share or request contact details outside the platform.', 'enterns-portal' ); ?>
	</div>

	<!-- Assigned students -->
	<h2 class="enp-section-heading"><?php esc_html_e( 'Your Students', 'enterns-portal' ); ?></h2>

	<?php if ( ! $students ) : ?>
	<div class="enp-notice enp-notice--info">
		<?php esc_html_e( 'You have no assigned students yet. The admin will assign students once they enroll.', 'enterns-portal' ); ?>
	</div>
	<?php else : ?>

	<div class="enp-students-grid">
		<?php foreach ( $students as $s ) :
			$cv_status = $s['cv_redesign_status'] ?? 'pending';
			$cv_cls    = 'done' === $cv_status ? 'enp-badge--success' : 'enp-badge--warn';
		?>
		<div class="enp-student-card">

			<div class="enp-student-card__header">
				<strong class="enp-student-card__name"><?php echo esc_html( $s['full_name'] ?? '—' ); ?></strong>
				<span class="enp-badge <?php echo esc_attr( $cv_cls ); ?>">
					CV: <?php echo esc_html( $cv_status ); ?>
				</span>
			</div>

			<dl class="enp-student-card__details">
				<dt><?php esc_html_e( 'College', 'enterns-portal' ); ?></dt>
				<dd><?php echo esc_html( $s['college'] ?: '—' ); ?></dd>

				<dt><?php esc_html_e( 'Tech Stack', 'enterns-portal' ); ?></dt>
				<dd><?php echo esc_html( $s['tech_stack'] ?: '—' ); ?></dd>

				<dt><?php esc_html_e( 'Sessions', 'enterns-portal' ); ?></dt>
				<dd>
					<?php
					echo esc_html(
						( (int) ( $s['sessions_used'] ?? 0 ) )
						. ' / '
						. ( (int) ( $s['sessions_total'] ?? 0 ) )
					);
					?>
				</dd>

				<dt><?php esc_html_e( 'Plan', 'enterns-portal' ); ?></dt>
				<dd><?php echo esc_html( strtoupper( $s['plan_id'] ?: '—' ) ); ?></dd>

				<?php if ( ! empty( $s['cv_url'] ) ) : ?>
				<dt><?php esc_html_e( 'CV', 'enterns-portal' ); ?></dt>
				<dd>
					<a href="<?php echo esc_url( $s['cv_url'] ); ?>"
					   target="_blank" rel="noopener noreferrer"
					   class="enp-link">
						<?php esc_html_e( 'View CV', 'enterns-portal' ); ?>
					</a>
				</dd>
				<?php endif; ?>

				<?php if ( ! empty( $s['live_project'] ) ) : ?>
				<dt><?php esc_html_e( 'Live Project', 'enterns-portal' ); ?></dt>
				<dd>
					<a href="<?php echo esc_url( $s['live_project'] ); ?>"
					   target="_blank" rel="noopener noreferrer"
					   class="enp-link">
						<?php esc_html_e( 'View Project', 'enterns-portal' ); ?>
					</a>
				</dd>
				<?php endif; ?>
			</dl>

		</div>
		<?php endforeach; ?>
	</div>

	<?php endif; ?>

</div>
