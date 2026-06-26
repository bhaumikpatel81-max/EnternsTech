<?php
/**
 * Student portal — Phase 5 full dashboard.
 * Loaded via [enp_student] shortcode (ob_start / include pattern).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$p    = $wpdb->prefix;
$user = wp_get_current_user();

// ── Student row ───────────────────────────────────────────────────────────────
$student = $wpdb->get_row( $wpdb->prepare(
	"SELECT * FROM {$p}enp_students WHERE user_id = %d LIMIT 1",
	$user->ID
) );

if ( ! $student ) {
	?>
	<div class="enp-wrap">
	  <div class="enp-portal-header">
	    <div class="enp-portal-header__title">
	      <span class="enp-badge enp-badge--student">Student</span>
	      <h1><?php printf( esc_html__( 'Welcome, %s', 'enterns-portal' ), esc_html( $user->display_name ) ); ?></h1>
	    </div>
	    <a class="enp-btn enp-btn--ghost enp-btn--sm" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Log out</a>
	  </div>
	  <div class="enp-notice enp-notice--info">
	    <?php esc_html_e( 'Your student profile is being set up. Check back soon or contact support.', 'enterns-portal' ); ?>
	  </div>
	</div>
	<?php
	return;
}

$display_name = esc_html( $student->full_name ?: $user->display_name );

// ── Plan info + upgrade options ───────────────────────────────────────────────
$catalog         = function_exists( 'enp_plan_catalog' )    ? enp_plan_catalog()                             : array();
$current_plan    = $catalog[ $student->plan_id ]            ?? null;
$upgrade_options = function_exists( 'enp_upgrade_options' ) ? enp_upgrade_options( $student->plan_id ?? '' ) : array();

// ── Current mentor (privacy-filtered) ─────────────────────────────────────────
$current_mentor     = null;
$current_mentor_raw = null;
if ( $student->mentor_id ) {
	$current_mentor_raw = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$p}enp_mentors WHERE id = %d AND status = 'approved' LIMIT 1",
		(int) $student->mentor_id
	) );
	if ( $current_mentor_raw ) {
		$current_mentor = function_exists( 'enp_mentor_for_student' )
			? enp_mentor_for_student( $current_mentor_raw )
			: (array) $current_mentor_raw;
	}
}

// ── Matched mentors for "Find a Mentor" grid and change-request dropdown ──────
// Excludes current mentor. Filtered through privacy helper.
$student_skills = $student->tech_stack
	? array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', $student->tech_stack ) ) ) )
	: array();

$all_approved_mentors = $wpdb->get_results(
	"SELECT * FROM {$p}enp_mentors WHERE status = 'approved' AND available_slots > 0 ORDER BY full_name"
);

$matched_mentors = array();
foreach ( $all_approved_mentors as $mrow ) {
	if ( (int) $mrow->id === (int) $student->mentor_id ) continue;
	if ( $student_skills ) {
		$mskills = array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', $mrow->tech_stack ?? '' ) ) ) );
		if ( ! array_intersect( $student_skills, $mskills ) ) continue;
	}
	$mf = function_exists( 'enp_mentor_for_student' ) ? enp_mentor_for_student( $mrow ) : (array) $mrow;
	// Fetch average rating.
	$rrow = $wpdb->get_row( $wpdb->prepare(
		"SELECT AVG(rating) AS avg_r, COUNT(id) AS cnt FROM {$p}enp_feedback
		 WHERE about_id = %d AND from_role = 'student' AND rating IS NOT NULL",
		(int) $mrow->id
	) );
	$mf['avg_rating']   = ( $rrow && (int) $rrow->cnt > 0 ) ? round( (float) $rrow->avg_r, 1 ) : null;
	$mf['rating_count'] = $rrow ? (int) $rrow->cnt : 0;
	$matched_mentors[]  = $mf;
}
usort( $matched_mentors, function ( $a, $b ) {
	$ra = $a['avg_rating'] ?? -1;
	$rb = $b['avg_rating'] ?? -1;
	return $rb <=> $ra;
} );

// ── Sessions ──────────────────────────────────────────────────────────────────
$sessions = $wpdb->get_results( $wpdb->prepare(
	"SELECT ss.*, m.full_name AS mentor_name
	 FROM {$p}enp_sessions ss
	 LEFT JOIN {$p}enp_mentors m ON ss.mentor_id = m.id
	 WHERE ss.student_id = %d
	 ORDER BY ss.scheduled_at DESC LIMIT 50",
	(int) $student->id
) );

// ── Open mentor change request ─────────────────────────────────────────────────
$open_change_req = $wpdb->get_var( $wpdb->prepare(
	"SELECT id FROM {$p}enp_requests WHERE student_id = %d AND type = 'mentor_change' AND status = 'open' LIMIT 1",
	(int) $student->id
) );

// ── Helpers ───────────────────────────────────────────────────────────────────
if ( ! function_exists( 'enp_stars_html' ) ) {
	function enp_stars_html( $rating, $count ) {
		if ( null === $rating ) {
			return '<span class="enp-rating enp-rating--none">No ratings yet</span>';
		}
		$full  = (int) floor( $rating );
		$half  = ( $rating - $full ) >= 0.5 ? 1 : 0;
		$empty = 5 - $full - $half;
		$html  = '<span class="enp-rating">';
		for ( $i = 0; $i < $full;  $i++ ) { $html .= '<span class="enp-star enp-star--full">&#9733;</span>'; }
		if ( $half )                        { $html .= '<span class="enp-star enp-star--half">&#9733;</span>'; }
		for ( $i = 0; $i < $empty; $i++ ) { $html .= '<span class="enp-star enp-star--empty">&#9734;</span>'; }
		$html .= ' <span class="enp-rating__val">' . number_format( $rating, 1 ) . '</span>';
		$html .= ' <span class="enp-rating__count">(' . (int) $count . ')</span>';
		$html .= '</span>';
		return $html;
	}
}

function enp_mentor_extra_safe( $extra_json ) {
	$extra = is_string( $extra_json ) ? json_decode( $extra_json, true ) : $extra_json;
	if ( ! is_array( $extra ) ) return array();
	$safe = array();
	foreach ( $extra as $k => $v ) {
		if ( stripos( (string) $k, 'email' ) !== false  ) continue;
		if ( stripos( (string) $k, 'phone' ) !== false  ) continue;
		if ( stripos( (string) $k, 'mobile' ) !== false ) continue;
		$safe[ (string) $k ] = (string) $v;
	}
	return $safe;
}

$cv_map             = array( 'pending' => 'Pending', 'in_progress' => 'In Progress', 'done' => 'Done' );
$sessions_remaining = max( 0, (int) $student->sessions_total - (int) $student->sessions_used );
?>
<div class="enp-wrap">

  <!-- ── Header ──────────────────────────────────────────────────────────────── -->
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

  <!-- ── Stats grid ──────────────────────────────────────────────────────────── -->
  <div class="enp-stats-grid">
    <div class="enp-stat-card">
      <span class="enp-stat-card__value"><?php echo (int) $student->sessions_used; ?></span>
      <span class="enp-stat-card__label"><?php esc_html_e( 'Sessions Used', 'enterns-portal' ); ?></span>
    </div>
    <div class="enp-stat-card">
      <span class="enp-stat-card__value"><?php echo $sessions_remaining; ?></span>
      <span class="enp-stat-card__label"><?php esc_html_e( 'Sessions Remaining', 'enterns-portal' ); ?></span>
    </div>
    <div class="enp-stat-card">
      <span class="enp-stat-card__value">
        <?php echo $current_plan ? esc_html( $current_plan['name'] ) : esc_html( strtoupper( $student->plan_id ?? '—' ) ); ?>
      </span>
      <span class="enp-stat-card__label"><?php esc_html_e( 'Current Plan', 'enterns-portal' ); ?></span>
    </div>
    <div class="enp-stat-card">
      <span class="enp-stat-card__value">
        <?php echo esc_html( $cv_map[ $student->cv_redesign_status ] ?? ucfirst( $student->cv_redesign_status ?? '—' ) ); ?>
      </span>
      <span class="enp-stat-card__label"><?php esc_html_e( 'CV Redesign', 'enterns-portal' ); ?></span>
    </div>
  </div>

  <!-- ── Skills editor ───────────────────────────────────────────────────────── -->
  <section class="enp-section" id="enp-skills-section">
    <h2 class="enp-section-heading"><?php esc_html_e( 'Your Skills', 'enterns-portal' ); ?></h2>
    <p class="enp-section-desc">
      <?php esc_html_e( 'Comma-separated technologies you know. Saving updates your mentor matches below.', 'enterns-portal' ); ?>
    </p>
    <form id="enp-skills-form" class="enp-inline-form">
      <input type="text" id="enp-skills-input" class="enp-input"
             value="<?php echo esc_attr( $student->tech_stack ?? '' ); ?>"
             placeholder="e.g. React, Node.js, Python, AWS">
      <button type="submit" class="enp-btn enp-btn--primary">Save Skills</button>
    </form>
    <div id="enp-skills-msg" class="enp-ajax-msg" aria-live="polite"></div>
  </section>

  <!-- ── Plan upgrade ─────────────────────────────────────────────────────────── -->
  <?php if ( $upgrade_options ) : ?>
  <section class="enp-section" id="enp-upgrade-section">
    <h2 class="enp-section-heading"><?php esc_html_e( 'Upgrade Your Plan', 'enterns-portal' ); ?></h2>
    <p class="enp-section-desc">
      <?php printf(
        esc_html__( 'You are on the %s. Upgrade to unlock more sessions and deeper placement support.', 'enterns-portal' ),
        '<strong>' . esc_html( $current_plan ? $current_plan['name'] : strtoupper( $student->plan_id ?? '' ) ) . '</strong>'
      ); ?>
    </p>
    <?php if ( ! function_exists( 'enp_razorpay_configured' ) || ! enp_razorpay_configured() ) : ?>
      <div class="enp-notice enp-notice--info">
        <?php esc_html_e( 'Online payment is not configured yet. Contact us to upgrade your plan.', 'enterns-portal' ); ?>
      </div>
    <?php else : ?>
    <div class="enp-upgrade-grid">
      <?php foreach ( $upgrade_options as $up_id => $up_info ) : ?>
      <div class="enp-upgrade-card">
        <div class="enp-upgrade-card__name"><?php echo esc_html( $up_info['name'] ); ?></div>
        <div class="enp-upgrade-card__price"><?php echo esc_html( $up_info['price_display'] ); ?></div>
        <div class="enp-upgrade-card__sessions">
          <?php printf( esc_html__( '%d sessions included', 'enterns-portal' ), (int) $up_info['sessions'] ); ?>
        </div>
        <button class="enp-btn enp-btn--primary enp-upgrade-btn"
                data-plan-id="<?php echo esc_attr( $up_id ); ?>"
                data-plan-name="<?php echo esc_attr( $up_info['name'] ); ?>"
                data-plan-price="<?php echo esc_attr( $up_info['price_display'] ); ?>">
          <?php printf( esc_html__( 'Upgrade to %s', 'enterns-portal' ), esc_html( $up_info['name'] ) ); ?>
        </button>
      </div>
      <?php endforeach; ?>
    </div>
    <div id="enp-upgrade-msg" class="enp-ajax-msg" aria-live="polite"></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- ── On-platform notice ───────────────────────────────────────────────────── -->
  <div class="enp-notice enp-notice--info" style="margin-top:2rem;margin-bottom:0">
    <strong><?php esc_html_e( 'Platform reminder:', 'enterns-portal' ); ?></strong>
    <?php esc_html_e( 'All communication with your mentor happens through Enterns Tech. Never share or request personal contact details outside this platform.', 'enterns-portal' ); ?>
  </div>

  <!-- ── Mentor section ───────────────────────────────────────────────────────── -->
  <?php if ( $current_mentor ) : ?>
  <!-- Has a mentor -->
  <section class="enp-section" id="enp-mentor-section">
    <h2 class="enp-section-heading"><?php esc_html_e( 'Your Mentor', 'enterns-portal' ); ?></h2>

    <?php if ( $open_change_req ) : ?>
    <div class="enp-notice enp-notice--info">
      <?php esc_html_e( 'You have a pending mentor change request. Admin will review it and notify you.', 'enterns-portal' ); ?>
    </div>
    <?php endif; ?>

    <?php
    $mrrow  = $wpdb->get_row( $wpdb->prepare(
      "SELECT AVG(rating) AS avg_r, COUNT(id) AS cnt FROM {$p}enp_feedback
       WHERE about_id = %d AND from_role = 'student' AND rating IS NOT NULL",
      (int) $student->mentor_id
    ) );
    $mr_val   = ( $mrrow && (int) $mrrow->cnt > 0 ) ? round( (float) $mrrow->avg_r, 1 ) : null;
    $mr_count = $mrrow ? (int) $mrrow->cnt : 0;
    ?>

    <div class="enp-mentor-card enp-mentor-card--current">
      <?php if ( ! empty( $current_mentor['photo_url'] ) ) : ?>
        <img src="<?php echo esc_url( $current_mentor['photo_url'] ); ?>" alt="" class="enp-mentor-card__photo">
      <?php else : ?>
        <div class="enp-mentor-card__photo enp-mentor-card__photo--placeholder">
          <?php echo esc_html( strtoupper( mb_substr( $current_mentor['full_name'] ?? 'M', 0, 2 ) ) ); ?>
        </div>
      <?php endif; ?>
      <div class="enp-mentor-card__info">
        <div class="enp-mentor-card__name"><?php echo esc_html( $current_mentor['full_name'] ); ?></div>
        <?php if ( ! empty( $current_mentor['tech_stack'] ) ) : ?>
        <div class="enp-mentor-card__skills">
          <?php foreach ( array_slice( explode( ',', $current_mentor['tech_stack'] ), 0, 8 ) as $skill ) : ?>
            <span class="enp-skill-tag"><?php echo esc_html( trim( $skill ) ); ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="enp-mentor-card__meta">
          <?php echo wp_kses_post( enp_stars_html( $mr_val, $mr_count ) ); ?>
          <?php if ( ! empty( $current_mentor['available_slots'] ) ) : ?>
            <span class="enp-mentor-card__slots">
              <?php printf( esc_html__( '%d slots/wk', 'enterns-portal' ), (int) $current_mentor['available_slots'] ); ?>
            </span>
          <?php endif; ?>
        </div>
        <?php if ( ! empty( $current_mentor['linkedin'] ) ) : ?>
          <a href="<?php echo esc_url( $current_mentor['linkedin'] ); ?>" class="enp-link" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e( 'View LinkedIn', 'enterns-portal' ); ?>
          </a>
        <?php endif; ?>
        <?php foreach ( enp_mentor_extra_safe( $current_mentor['extra_fields'] ?? '' ) as $ek => $ev ) : ?>
          <div class="enp-mentor-card__extra"><strong><?php echo esc_html( $ek ); ?>:</strong> <?php echo esc_html( $ev ); ?></div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ( ! $open_change_req ) : ?>
    <div style="margin-top:1.5rem">
      <button class="enp-btn enp-btn--ghost" id="enp-request-change-btn">
        <?php esc_html_e( 'Request Mentor Change', 'enterns-portal' ); ?>
      </button>
    </div>
    <div id="enp-change-form" class="enp-change-form" style="display:none">
      <h3><?php esc_html_e( 'Request Mentor Change', 'enterns-portal' ); ?></h3>
      <div class="enp-form-group">
        <label for="enp-change-reason"><?php esc_html_e( 'Reason for change *', 'enterns-portal' ); ?></label>
        <textarea id="enp-change-reason" class="enp-textarea" rows="4"
          placeholder="<?php esc_attr_e( 'Please describe why you would like a different mentor…', 'enterns-portal' ); ?>"></textarea>
      </div>
      <div class="enp-form-group">
        <label for="enp-change-mentor"><?php esc_html_e( 'Preferred new mentor (optional)', 'enterns-portal' ); ?></label>
        <select id="enp-change-mentor" class="enp-select">
          <option value="0"><?php esc_html_e( '— any available mentor —', 'enterns-portal' ); ?></option>
          <?php
          $pref_pool = ! empty( $matched_mentors ) ? $matched_mentors : array_map( function( $mrow ) {
            return array( 'id' => $mrow->id, 'full_name' => $mrow->full_name );
          }, $all_approved_mentors );
          foreach ( $pref_pool as $pm ) :
            $pm_id   = (int) ( $pm['id'] ?? 0 );
            $pm_name = $pm['full_name'] ?? '';
            if ( $pm_id === (int) $student->mentor_id ) continue;
          ?>
          <option value="<?php echo $pm_id; ?>"><?php echo esc_html( $pm_name ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="enp-form-actions">
        <button class="enp-btn enp-btn--primary" id="enp-change-submit">
          <?php esc_html_e( 'Submit Request', 'enterns-portal' ); ?>
        </button>
        <button class="enp-btn enp-btn--ghost" id="enp-change-cancel">
          <?php esc_html_e( 'Cancel', 'enterns-portal' ); ?>
        </button>
      </div>
      <div id="enp-change-msg" class="enp-ajax-msg" aria-live="polite"></div>
    </div>
    <?php endif; ?>
  </section>

  <?php else : ?>
  <!-- No mentor yet — show matched cards -->
  <section class="enp-section" id="enp-mentor-section">
    <h2 class="enp-section-heading"><?php esc_html_e( 'Find a Mentor', 'enterns-portal' ); ?></h2>

    <?php if ( empty( $student->tech_stack ) ) : ?>
      <div class="enp-notice enp-notice--info">
        <?php esc_html_e( 'Add your skills above and save — matched mentors will appear here.', 'enterns-portal' ); ?>
      </div>
    <?php elseif ( empty( $matched_mentors ) ) : ?>
      <div class="enp-notice enp-notice--info">
        <?php esc_html_e( 'No available mentors match your skill set right now. Check back soon or update your skills.', 'enterns-portal' ); ?>
      </div>
    <?php else : ?>
    <p class="enp-section-desc">
      <?php esc_html_e( 'Mentors below match your skills. Select one to work with.', 'enterns-portal' ); ?>
    </p>
    <div id="enp-mentor-grid" class="enp-mentor-grid">
      <?php foreach ( $matched_mentors as $mm ) : ?>
      <div class="enp-mentor-card" data-rating="<?php echo esc_attr( $mm['avg_rating'] ?? 0 ); ?>">
        <?php if ( ! empty( $mm['photo_url'] ) ) : ?>
          <img src="<?php echo esc_url( $mm['photo_url'] ); ?>" alt="" class="enp-mentor-card__photo">
        <?php else : ?>
          <div class="enp-mentor-card__photo enp-mentor-card__photo--placeholder">
            <?php echo esc_html( strtoupper( mb_substr( $mm['full_name'] ?? 'M', 0, 2 ) ) ); ?>
          </div>
        <?php endif; ?>
        <div class="enp-mentor-card__info">
          <div class="enp-mentor-card__name"><?php echo esc_html( $mm['full_name'] ); ?></div>
          <?php if ( ! empty( $mm['tech_stack'] ) ) : ?>
          <div class="enp-mentor-card__skills">
            <?php foreach ( array_slice( explode( ',', $mm['tech_stack'] ), 0, 6 ) as $skill ) : ?>
              <span class="enp-skill-tag"><?php echo esc_html( trim( $skill ) ); ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="enp-mentor-card__meta">
            <?php echo wp_kses_post( enp_stars_html( $mm['avg_rating'], $mm['rating_count'] ) ); ?>
            <span class="enp-mentor-card__slots">
              <?php printf( esc_html__( '%d slots/wk', 'enterns-portal' ), (int) ( $mm['available_slots'] ?? 0 ) ); ?>
            </span>
          </div>
          <?php if ( ! empty( $mm['linkedin'] ) ) : ?>
            <a href="<?php echo esc_url( $mm['linkedin'] ); ?>" class="enp-link" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e( 'LinkedIn', 'enterns-portal' ); ?>
            </a>
          <?php endif; ?>
          <?php foreach ( enp_mentor_extra_safe( $mm['extra_fields'] ?? '' ) as $ek => $ev ) : ?>
            <div class="enp-mentor-card__extra"><strong><?php echo esc_html( $ek ); ?>:</strong> <?php echo esc_html( $ev ); ?></div>
          <?php endforeach; ?>
        </div>
        <button class="enp-btn enp-btn--primary enp-pick-mentor-btn"
                data-mentor-id="<?php echo (int) $mm['id']; ?>"
                data-mentor-name="<?php echo esc_attr( $mm['full_name'] ); ?>">
          <?php esc_html_e( 'Pick This Mentor', 'enterns-portal' ); ?>
        </button>
      </div>
      <?php endforeach; ?>
    </div>
    <div id="enp-pick-msg" class="enp-ajax-msg" aria-live="polite"></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- ── Sessions ─────────────────────────────────────────────────────────────── -->
  <section class="enp-section">
    <h2 class="enp-section-heading"><?php esc_html_e( 'Your Sessions', 'enterns-portal' ); ?></h2>
    <?php if ( $sessions ) : ?>
    <div class="enp-sessions-table">
      <table>
        <thead>
          <tr>
            <th><?php esc_html_e( 'Date &amp; Time', 'enterns-portal' ); ?></th>
            <th><?php esc_html_e( 'Mentor', 'enterns-portal' ); ?></th>
            <th><?php esc_html_e( 'Duration', 'enterns-portal' ); ?></th>
            <th><?php esc_html_e( 'Status', 'enterns-portal' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $sessions as $sess ) :
            $sc = 'completed' === $sess->status ? 'enp-badge--success' : 'enp-badge--info';
          ?>
          <tr>
            <td style="white-space:nowrap"><?php echo esc_html( date( 'd M Y H:i', strtotime( $sess->scheduled_at ) ) ); ?></td>
            <td><?php echo esc_html( $sess->mentor_name ?? '—' ); ?></td>
            <td><?php echo (int) $sess->duration_min; ?> min</td>
            <td><span class="enp-badge <?php echo esc_attr( $sc ); ?>"><?php echo esc_html( ucfirst( $sess->status ) ); ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else : ?>
    <div class="enp-notice enp-notice--info">
      <?php esc_html_e( 'No sessions booked yet. Your mentor will schedule the first session with you.', 'enterns-portal' ); ?>
    </div>
    <?php endif; 