<?php
/**
 * "Partner with Us" — mentor application form.
 * Form HTML is wired up here; backend processing is added in Phase 3.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="enp-wrap enp-partner-wrap">

	<div class="enp-form-card">
		<div class="enp-form-card__header">
			<h2><?php esc_html_e( 'Become a Mentor', 'enterns-portal' ); ?></h2>
			<p><?php esc_html_e( 'Share your expertise and guide the next generation of tech professionals. Fill in the form below — our team will review your application within 2 business days.', 'enterns-portal' ); ?></p>
		</div>

		<div id="enp-partner-msg" class="enp-notice" style="display:none;" aria-live="polite"></div>

		<form id="enp-partner-form" class="enp-form" novalidate>
			<?php wp_nonce_field( 'enp_partner_apply', 'enp_partner_nonce' ); ?>

			<div class="enp-form-row enp-form-row--half">
				<div class="enp-form-group">
					<label for="enp-full-name"><?php esc_html_e( 'Full Name', 'enterns-portal' ); ?> <span class="enp-required">*</span></label>
					<input type="text" id="enp-full-name" name="full_name"
					       placeholder="<?php esc_attr_e( 'Your full name', 'enterns-portal' ); ?>"
					       required maxlength="200" autocomplete="name" />
				</div>
				<div class="enp-form-group">
					<label for="enp-email"><?php esc_html_e( 'Email Address', 'enterns-portal' ); ?> <span class="enp-required">*</span></label>
					<input type="email" id="enp-email" name="email"
					       placeholder="you@example.com"
					       required maxlength="200" autocomplete="email" />
				</div>
			</div>

			<div class="enp-form-row enp-form-row--half">
				<div class="enp-form-group">
					<label for="enp-phone"><?php esc_html_e( 'Phone Number', 'enterns-portal' ); ?> <span class="enp-required">*</span></label>
					<input type="tel" id="enp-phone" name="phone"
					       placeholder="+91 98765 43210"
					       required maxlength="30" autocomplete="tel" />
				</div>
				<div class="enp-form-group">
					<label for="enp-linkedin"><?php esc_html_e( 'LinkedIn Profile URL', 'enterns-portal' ); ?></label>
					<input type="url" id="enp-linkedin" name="linkedin"
					       placeholder="https://linkedin.com/in/yourprofile"
					       maxlength="500" />
				</div>
			</div>

			<div class="enp-form-group">
				<label for="enp-tech-stack"><?php esc_html_e( 'Tech Stack / Expertise', 'enterns-portal' ); ?> <span class="enp-required">*</span></label>
				<input type="text" id="enp-tech-stack" name="tech_stack"
				       placeholder="<?php esc_attr_e( 'e.g. React, Node.js, Python, AWS', 'enterns-portal' ); ?>"
				       required maxlength="500" />
				<span class="enp-form-hint"><?php esc_html_e( 'Comma-separated list of skills.', 'enterns-portal' ); ?></span>
			</div>

			<div class="enp-form-row enp-form-row--half">
				<div class="enp-form-group">
					<label for="enp-slots"><?php esc_html_e( 'Available Slots per Week', 'enterns-portal' ); ?> <span class="enp-required">*</span></label>
					<input type="number" id="enp-slots" name="available_slots"
					       min="1" max="20" value="2" required />
				</div>
				<div class="enp-form-group">
					<label for="enp-photo"><?php esc_html_e( 'Professional Photo', 'enterns-portal' ); ?></label>
					<input type="file" id="enp-photo" name="photo"
					       accept="image/jpeg,image/png,image/webp" />
					<span class="enp-form-hint"><?php esc_html_e( 'JPG/PNG/WebP, max 2 MB.', 'enterns-portal' ); ?></span>
				</div>
			</div>

			<div class="enp-form-actions">
				<button type="submit" class="enp-btn enp-btn--primary enp-btn--lg" id="enp-partner-submit">
					<span class="enp-btn__text"><?php esc_html_e( 'Submit Application', 'enterns-portal' ); 