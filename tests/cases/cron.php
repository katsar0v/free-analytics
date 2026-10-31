<?php
/**
 * Cron cases: the daily clean-up (13 months of rows, stale salts).
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

FA_Test::add(
	'cron: prune() deletes rows older than 13 months and keeps the rest',
	function () {
		if ( ! class_exists( 'Free_Analytics_Cron' ) ) {
			return FA_Test::skip( 'Free_Analytics_Cron does not exist yet' );
		}
		// Today is 2026-06-15, so the cut-off is 2025-05-15.
		FA_Sandbox::freeze( '2026-06-15 04:00:00' );
		$old = array( '2024-01-10', '2025-04-01', '2025-04-01', '2025-05-10' );
		$new = array( '2025-05-20', '2025-06-20', '2026-06-14', '2026-06-15' );
		foreach ( array_merge( $old, $new ) as $i => $day ) {
			FA_Sandbox::insert_hit( array( 'ts' => $day . ' 12:00:00', 'visitor' => sprintf( '%016x', $i + 1 ), 'visit' => sprintf( '%016x', $i + 1 ) ) );
		}
		FA_Test::eq( 8, FA_Sandbox::count_rows(), 'eight rows before' );

		$deleted = Free_Analytics_Cron::prune();
		FA_Test::eq( 4, $deleted, 'returns the number of deleted rows' );
		FA_Test::eq( $new, array_column( FA_Sandbox::rows(), 'day' ), 'the newer rows are all still there' );

		FA_Test::eq( 0, Free_Analytics_Cron::prune(), 'a second run deletes nothing and returns 0' );
		FA_Test::eq( 4, FA_Sandbox::count_rows(), 'four rows after the second run' );
	}
);

FA_Test::add(
	'cron: prune() on an empty table and with only fresh rows',
	function () {
		if ( ! class_exists( 'Free_Analytics_Cron' ) ) {
			return FA_Test::skip( 'Free_Analytics_Cron does not exist yet' );
		}
		FA_Sandbox::freeze( '2026-06-15 04:00:00' );
		FA_Test::eq( 0, Free_Analytics_Cron::prune(), 'empty table' );

		FA_Sandbox::insert_hit( array( 'ts' => '2026-06-15 03:00:00' ) );
		FA_Sandbox::insert_hit( array( 'ts' => '2026-01-01 03:00:00' ) );
		FA_Test::eq( 0, Free_Analytics_Cron::prune(), 'nothing is old' );
		FA_Test::eq( 2, FA_Sandbox::count_rows(), 'both rows stay' );
	}
);

FA_Test::add(
	'cron: prune() deletes stale salts and keeps today\'s',
	function () {
		if ( ! class_exists( 'Free_Analytics_Cron' ) ) {
			return FA_Test::skip( 'Free_Analytics_Cron does not exist yet' );
		}
		global $wpdb;

		FA_Sandbox::freeze( '2026-06-15 04:00:00' );
		$today = Free_Analytics_Fingerprint::salt( '2026-06-15' );
		foreach ( array( '2020-01-01', '2026-05-01' ) as $day ) {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", 'free_analytics_salt_' . $day, str_repeat( 'cd', 32 ) ) ); // phpcs:ignore WordPress.DB
		}
		FA_Test::eq( 3, count( fa_test_salt_options() ), 'three salts before' );

		Free_Analytics_Cron::prune();

		FA_Test::eq( array( 'free_analytics_salt_2026-06-15' ), fa_test_salt_options(), 'only today\'s salt is left' );
		FA_Test::eq( $today, Free_Analytics_Fingerprint::salt( '2026-06-15' ), 'and it is unchanged' );
	}
);

FA_Test::add(
	'cron: the daily event has a handler and a schedule',
	function () {
		if ( ! class_exists( 'Free_Analytics_Cron' ) ) {
			return FA_Test::skip( 'Free_Analytics_Cron does not exist yet' );
		}
		if ( ! has_action( Free_Analytics_Cron::HOOK ) ) {
			Free_Analytics_Cron::init(); // The plugin may not be active in this process.
		}
		FA_Test::ok( (bool) has_action( Free_Analytics_Cron::HOOK ), 'the hook ' . Free_Analytics_Cron::HOOK . ' has a callback' );

		wp_clear_scheduled_hook( Free_Analytics_Cron::HOOK );
		Free_Analytics_Cron::ensure();
		$next = wp_next_scheduled( Free_Analytics_Cron::HOOK );
		FA_Test::ok( (bool) $next, 'ensure() schedules the event' );
		FA_Test::eq( '03:30', ( new DateTimeImmutable( '@' . $next ) )->setTimezone( Free_Analytics_Clock::zone() )->format( 'H:i' ), 'at 03:30 site time' );
		wp_clear_scheduled_hook( Free_Analytics_Cron::HOOK );
	}
);
