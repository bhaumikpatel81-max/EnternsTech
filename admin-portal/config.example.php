<?php
/**
 * Admin Portal Configuration — EXAMPLE FILE
 *
 * Copy this file to config.php and fill in your real values.
 * NEVER commit config.php to GitHub — it is listed in .gitignore.
 *
 * HOW TO FIND YOUR VALUES:
 *   ADMIN_PASSWORD  — choose any strong password you like
 *   DB_HOST         — almost always 'localhost' on Bluehost
 *   DB_NAME         — Bluehost cPanel → MySQL Databases → Database Name
 *   DB_USER         — Bluehost cPanel → MySQL Databases → Database User
 *   DB_PASS         — the password you set for that DB user
 *   DB_PREFIX       — check your wp-config.php, usually 'wp_'
 */

define('ADMIN_PASSWORD', 'CHANGE_ME_TO_A_STRONG_PASSWORD');

define('DB_HOST',   'localhost');
define('DB_NAME',   'your_wordpress_database_name');
define('DB_USER',   'your_database_username');
define('DB_PASS',   'your_database_password');
define('DB_PREFIX', 'wp_');

// Optional override. Use this when admin-portal is outside the WordPress folder.
// Example Bluehost path: /home/ACCOUNT/public_html/enternstech/wp-load.php
// define('ENP_WP_LOAD_PATH', '/home/ACCOUNT/public_html/enternstech/wp-load.php');
