<?php
/**
 * Free Analytics - database schema
 *
 * One table, one row per page view or event. The version is stored in the option
 * "free_analytics_db_version"; the migration runs on plugin load when it is behind
 * (a schema version option plus dbDelta). Table names use the current $wpdb->prefix.
 *
 * @package Free_Analytics
 * @subpackage Schema
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Free_Analytics_Schema
 */
class Free_Analytics_Schema {

	/**
	 * Schema version. Bump it when the table definition changes.
	 *
	 * @var int
	 */
	const VERSION = 1;

	/**
	 * Name of the option that stores the installed schema version.
	 *
	 * @var string
	 */
	const OPTION = 'free_analytics_db_version';

	/**
	 * Full name of the hits table.
	 *
	 * @since 1.0.0
	 * @return string Table name with the current prefix.
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'free_analytics_hits';
	}

	/**
	 * Runs the migration on plugin load.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		self::maybe_migrate();
	}

	/**
	 * Runs the migration when the stored version is behind.
	 *
	 * Costs one cached option read when up to date. A database lock keeps two simultaneous
	 * requests from migrating at once.
	 *
	 * @since 1.0.0
	 * @return bool True when the schema is current afterwards.
	 */
	public static function maybe_migrate() {
		global $wpdb;

		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return true;
		}

		$lock_name = 'free_analytics_migrate_' . $wpdb->prefix;
		$got_lock  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 20)', $lock_name ) );
		if ( 1 !== $got_lock ) {
			return false;
		}

		try {
			// Another process may have finished while we waited.
			wp_cache_delete( self::OPTION, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			if ( (int) get_option( self::OPTION, 0 ) < self::VERSION ) {
				self::install();
				update_option( self::OPTION, self::VERSION, false );
			}
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}

		return true;
	}

	/**
	 * Creates or updates the table (dbDelta).
	 *
	 * Columns:
	 * - ts, day: local time in the site timezone.
	 * - visitor: first 16 hex chars of a daily-salted HMAC of IP + user agent (see fingerprint.php).
	 * - visit: random per visit (30 minutes of inactivity, or a new external source, starts a new one).
	 * - event: pageview, tel, email, map, viber, whatsapp or form.
	 * - source, medium, campaign: copied from the visit's first hit, so events can be grouped by source.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ts datetime NOT NULL,
  day date NOT NULL,
  visitor char(16) NOT NULL,
  visit char(16) NOT NULL,
  event varchar(16) NOT NULL DEFAULT 'pageview',
  path varchar(255) NOT NULL DEFAULT '',
  post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  post_type varchar(20) NOT NULL DEFAULT '',
  is_404 tinyint(1) unsigned NOT NULL DEFAULT 0,
  target varchar(100) NOT NULL DEFAULT '',
  source varchar(100) NOT NULL DEFAULT '',
  medium varchar(50) NOT NULL DEFAULT '',
  campaign varchar(100) NOT NULL DEFAULT '',
  device varchar(10) NOT NULL DEFAULT '',
  browser varchar(20) NOT NULL DEFAULT '',
  os varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY day_event (day,event),
  KEY visitor_ts (visitor,ts),
  KEY visit (visit),
  KEY post_day (post_id,day)
) {$charset};"
		);
	}
}
