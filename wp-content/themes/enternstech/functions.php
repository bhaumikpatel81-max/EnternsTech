<?php
/**
 * EnternsTech Theme Functions
 *
 * @package EnternsTech
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ENTERNSTECH_VERSION', '2.0.0' );

// ──────────────────────────────────────────────────────────────────────────────
// Theme Setup
// ──────────────────────────────────────────────────────────────────────────────
function enternstech_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 60,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list',
		'gallery', 'caption', 'style', 'script',
	) );
	register_nav_menus( array(
		'primary' => __( 'Primary Navigation', 'enternstech' ),
		'footer'  => __( 'Footer Navigation',  'enternstech' ),
	) );
	$GLOBALS['content_width'] = 1200;
}
add_action( 'after_setup_theme', 'enternstech_setup' );

// ──────────────────────────────────────────────────────────────────────────────
// Enqueue
// ──────────────────────────────────────────────────────────────────────────────
function enternstech_scripts() {
	$ver = filemtime( get_template_directory() . '/style.css' ) ?: ENTERNSTECH_VERSION;
	wp_enqueue_style(
		'enternstech-style',
		get_stylesheet_uri(),
		array(),
		$ver
	);

	// Hero interactivity — only needed on the front page.
	// Loaded in the footer so it never blocks render.
	if ( is_front_page() ) {
		$hero_path = get_template_directory() . '/assets/js/hero.js';
		wp_enqueue_script(
			'enternstech-hero',
			get_template_directory_uri() . '/assets/js/hero.js',
			array(),
			file_exists( $hero_path ) ? filemtime( $hero_path ) : ENTERNSTECH_VERSION,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'enternstech_scripts' );

// ──────────────────────────────────────────────────────────────────────────────
// Widget Areas
// ──────────────────────────────────────────────────────────────────────────────
function enternstech_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Footer Widget Area', 'enternstech' ),
		'id'            => 'footer-1',
		'description'   => __( 'Add widgets here to appear in the footer.', 'enternstech' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
}
add_action( 'widgets_init', 'enternstech_widgets_init' );

// ──────────────────────────────────────────────────────────────────────────────
// FormSubmit helper – preserves the existing FormSubmit integration
// ──────────────────────────────────────────────────────────────────────────────
function enternstech_formsubmit_email() {
	return apply_filters( 'enternstech_contact_email', 'info@enternstech.com' );
}

// ──────────────────────────────────────────────────────────────────────────────
// Homepage safeguard — forces front-page.php regardless of Settings → Reading
// or any custom Page template, so a stray WP Page can never hijack the homepage.
// Fires at priority 99 (after all other template filters). Does not affect
// REST routes, admin, or any non-front-page request.
// ──────────────────────────────────────────────────────────────────────────────
add_filter( 'template_include', function( $template ) {
	if ( is_front_page() ) {
		$fp = locate_template( 'front-page.php' );
		if ( $fp ) {
			return $fp;
		}
	}
	return $template;
}, 99 );
