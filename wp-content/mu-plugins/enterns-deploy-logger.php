<?php
/**
 * Enterns Deployment Logger (mu-plugin)
 * 
 * This file is automatically loaded by WordPress.
 * Logs plugin and theme status to help diagnose deployment issues.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Log deployment status on every page load (safely)
add_action( 'plugins_loaded', function() {
    $log_file = WP_CONTENT_DIR . '/enterns-deploy-log.txt';
    
    // Only log once per hour to avoid spam
    if ( file_exists( $log_file ) ) {
        if ( time() - filemtime( $log_file ) < 3600 ) {
            return;
        }
    }
    
    $plugin_dir = WP_PLUGIN_DIR . '/enterns-portal';
    $plugin_file = $plugin_dir . '/enterns-portal.php';
    $theme_dir = WP_CONTENT_DIR . '/themes/enternstech';
    $theme_file = $theme_dir . '/style.css';
    
    $log = array(
        'timestamp' => date( 'Y-m-d H:i:s' ),
        'wp_version' => get_bloginfo( 'version' ),
        'wp_content_dir' => WP_CONTENT_DIR,
        'plugin_dir_exists' => is_dir( $plugin_dir ),
        'plugin_file_exists' => file_exists( $plugin_file ),
        'plugin_file_readable' => is_readable( $plugin_file ),
        'plugin_file_perms' => file_exists( $plugin_file ) ? substr( sprintf( '%o', fileperms( $plugin_file ) ), -4 ) : 'N/A',
        'theme_dir_exists' => is_dir( $theme_dir ),
        'theme_file_exists' => file_exists( $theme_file ),
        'theme_file_readable' => is_readable( $theme_file ),
        'theme_file_perms' => file_exists( $theme_file ) ? substr( sprintf( '%o', fileperms( $theme_file ) ), -4 ) : 'N/A',
    );
    
    // Verify headers
    if ( file_exists( $plugin_file ) && is_readable( $plugin_file ) ) {
        $plugin_header = file_get_contents( $plugin_file, false, null, 0, 500 );
        $log['plugin_has_header'] = strpos( $plugin_header, 'Plugin Name:' ) !== false;
    }
    
    if ( file_exists( $theme_file ) && is_readable( $theme_file ) ) {
        $theme_header = file_get_contents( $theme_file, false, null, 0, 500 );
        $log['theme_has_header'] = strpos( $theme_header, 'Theme Name:' ) !== false;
    }
    
    file_put_contents( $log_file, json_encode( $log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n", FILE_APPEND );
}, 1 );

// Show admin notice if plugin or theme is missing
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $plugin_file = WP_PLUGIN_DIR . '/enterns-portal/enterns-portal.php';
    $theme_dir = WP_CONTENT_DIR . '/themes/enternstech';
    
    if ( ! file_exists( $plugin_file ) ) {
        echo '<div class="notice notice-error"><p><strong>Enterns Deployment:</strong> Plugin file not found at ' . esc_html( $plugin_file ) . '. Check FTP or deployment logs.</p></div>';
    }
    
    if ( ! is_dir( $theme_dir ) ) {
        echo '<div class="notice notice-error"><p><strong>Enterns Deployment:</strong> Theme folder not found at ' . esc_html( $theme_dir ) . '. Check FTP or deployment logs.</p></div>';
    }
} );
