<?php
/**
 * Free Analytics - uninstall
 *
 * Runs only when the plugin is deleted in wp-admin (not on deactivation): drops the table
 * and removes the plugin's options, including the daily salt.
 *
 * @package Free_Analytics
 * @since 1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}free_analytics_hits" );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'free_analytics_' ) . '%' ) );
// phpcs:enable

wp_clear_scheduled_hook( 'free_analytics_daily' );
