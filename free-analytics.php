<?php
/**
 * Plugin Name: Free Analytics
 * Plugin URI: https://github.com/katsar0v/free-analytics
 * Description: Simple, cookieless visitor statistics stored in your own database: visitors, page views, top content, traffic sources, contact clicks and form submissions. No IP addresses are stored.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Katsarov Design
 * Author URI: https://github.com/katsar0v
 * Text Domain: free-analytics
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * Update URI: false
 *
 * A small JS beacon (assets/js/tracker.js) reports page views, contact-link clicks and
 * successful form submissions to the REST route free-analytics/v1/hit. The visitor is a
 * daily-salted hash of IP + user agent: no cookies, no browser storage, and the IP is never
 * stored. The dashboard is wp-admin -> Statistics.
 *
 * "Update URI: false" keeps WordPress from offering an update from an unrelated
 * wordpress.org plugin that happens to use the same slug.
 *
 * @package Free_Analytics
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'FREE_ANALYTICS_VERSION', '1.0.0' );
define( 'FREE_ANALYTICS_FILE', __FILE__ );
define( 'FREE_ANALYTICS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Loads the plugin files and registers the hooks.
 *
 * @since 1.0.0
 * @return void
 */
function free_analytics_loader() {
	foreach ( array( 'clock', 'schema', 'classify', 'fingerprint', 'recorder', 'tracker', 'stats', 'cron' ) as $free_analytics_file ) {
		require_once FREE_ANALYTICS_DIR . 'inc/' . $free_analytics_file . '.php';
	}

	Free_Analytics_Schema::init();
	Free_Analytics_Tracker::init();
	Free_Analytics_Cron::init();

	if ( is_admin() ) {
		require_once FREE_ANALYTICS_DIR . 'inc/admin_page.php';
		Free_Analytics_Admin::init();
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once FREE_ANALYTICS_DIR . 'inc/cli.php';
		WP_CLI::add_command( 'free-analytics', 'Free_Analytics_CLI' );
	}
}
add_action( 'plugins_loaded', 'free_analytics_loader' );

/**
 * Loads the translations from the plugin's languages/ folder.
 *
 * @since 1.0.0
 * @return void
 */
function free_analytics_load_textdomain() {
	load_plugin_textdomain( 'free-analytics', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'free_analytics_load_textdomain' );

/**
 * Removes the daily clean-up event. The data stays until the plugin is deleted (uninstall.php).
 *
 * @since 1.0.0
 * @return void
 */
function free_analytics_deactivate() {
	wp_clear_scheduled_hook( 'free_analytics_daily' );
}
register_deactivation_hook( __FILE__, 'free_analytics_deactivate' );
