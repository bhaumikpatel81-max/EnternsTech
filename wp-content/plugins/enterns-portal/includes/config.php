<?php
/**
 * Centralized plugin configuration.
 * Defaults live here; admins can override via Settings (Phase 7).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return a config value (or the whole array).
 *
 * @param string $key Option key, or '' for the full array.
 * @return mixed
 */
function enp_config( $key = '' ) {
	static $cache = null;
	if ( null === $cache ) {
		$defaults = array(
			'admin_email'  => 'admin@enternstech.com',
			'mentor_email' => 'mentor@enternstech.com',
			'from_email'   => 'admin@enternstech.com',
			'from_name'    => 'Enterns Tech',
			'mentor_rate'  => 500,
			'sessions_min' => 4,
			'sessions_max' => 8,
			'brand_cyan'   => '#22D3EE',
			'brand_blue'   => '#3BA4FF',
			'brand_bg'     => '#05080F',
			'brand_surf'   => '#0C1426',
			'brand_text'   => '#ECF2FF',
		);
		$saved  = get_option( 'enp_config', array() );
		$cache  = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	if ( '' === $key ) {
		return $cache;
	}
	return isset( $cache[ $key ] ) ? $cache[ $key ] : null;
}
