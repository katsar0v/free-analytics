<?php
/**
 * Free Analytics - daily clean-up
 *
 * Deletes rows older than the retention period (13 months by default, filter free_analytics_retention_months) and
 * any salt left over from earlier days. Runs once a day at about 03:30 site time.
 *
 * @package Free_Analytics
 * @subpackage Cron
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Free_Analytics_Cron
 */
class Free_Analytics_Cron {

	/**
	 * Cron hook.
	 *
	 * @var string
	 */
	const HOOK = 'free_analytics_daily';

	/**
	 * Months of data kept.
	 *
	 * @var int
	 */
	const RETENTION_MONTHS = 13;

	/**
	 * Registers the hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure' ), 20 );
	}

	/**
	 * Schedules the daily event when it is missing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ensure() {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$next = new DateTimeImmutable( 'tomorrow 03:30', Free_Analytics_Clock::zone() );
		wp_schedule_event( $next->getTimestamp(), 'daily', self::HOOK );
	}

	/**
	 * Cron callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function run() {
		self::prune();
	}

	/**
	 * First day that is kept.
	 *
	 * @since 1.0.0
	 * @return string Y-m-d.
	 */
	public static function cutoff() {
		/**
		 * Filters how many months of statistics are kept.
		 *
		 * @since 1.0.0
		 * @param int $months Months (default 13).
		 */
		$months = max( 1, (int) apply_filters( 'free_analytics_retention_months', self::RETENTION_MONTHS ) );

		return Free_Analytics_Clock::now()->modify( '-' . $months . ' months' )->format( 'Y-m-d' );
	}

	/**
	 * Deletes old rows in batches, and the salts of earlier days.
	 *
	 * @since 1.0.0
	 * @return int Rows deleted.
	 */
	public static function prune() {
		global $wpdb;

		$table   = Free_Analytics_Schema::table();
		$cutoff  = self::cutoff();
		$deleted = 0;

		for ( $i = 0; $i < 200; $i++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$n = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE day < %s LIMIT 5000", $cutoff ) );
			$deleted += $n;
			if ( $n < 5000 ) {
				break;
			}
		}

		Free_Analytics_Fingerprint::delete_old_salts( Free_Analytics_Clock::today() );

		return $deleted;
	}
}
