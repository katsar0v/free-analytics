<?php
/**
 * Clock cases: site time, freezing, date helpers.
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

FA_Test::add(
	'clock: a frozen time is site time (not shifted to UTC), with the summer and winter offsets (sandbox zone Europe/Sofia)',
	function () {
		FA_Sandbox::freeze( '2026-03-10 23:30:00' );
		$now = Free_Analytics_Clock::now();
		FA_Test::eq( 'Europe/Sofia', $now->getTimezone()->getName(), 'timezone' );
		FA_Test::eq( '2026-03-10 23:30:00', $now->format( 'Y-m-d H:i:s' ), 'frozen value kept as local time' );
		FA_Test::eq( '2026-03-10', Free_Analytics_Clock::today(), 'today' );
		FA_Test::eq( '+02:00', $now->format( 'P' ), 'winter offset' );

		FA_Sandbox::freeze( '2026-07-01 12:00:00' );
		FA_Test::eq( '+03:00', Free_Analytics_Clock::now()->format( 'P' ), 'summer offset' );
	}
);

FA_Test::add(
	'clock: the real time is the date in the site timezone',
	function () {
		FA_Sandbox::freeze( null );
		$zone   = wp_timezone();
		$before = ( new DateTimeImmutable( 'now', $zone ) )->format( 'Y-m-d' );
		$today  = Free_Analytics_Clock::today();
		$after  = ( new DateTimeImmutable( 'now', $zone ) )->format( 'Y-m-d' );
		FA_Test::ok( $today === $before || $today === $after, 'today() is the site date: ' . $today );
	}
);

FA_Test::add(
	'clock: is_date() and add_days()',
	function () {
		FA_Test::ok( Free_Analytics_Clock::is_date( '2026-02-28' ), '2026-02-28' );
		FA_Test::ok( Free_Analytics_Clock::is_date( '2028-02-29' ), 'leap day' );
		FA_Test::ok( ! Free_Analytics_Clock::is_date( '2026-02-29' ), 'no leap day in 2026' );
		FA_Test::ok( ! Free_Analytics_Clock::is_date( '2026-02-30' ), '30 February' );
		FA_Test::ok( ! Free_Analytics_Clock::is_date( '2026-2-8' ), 'no zero padding' );
		FA_Test::ok( ! Free_Analytics_Clock::is_date( '10/03/2026' ), 'other format' );
		FA_Test::ok( ! Free_Analytics_Clock::is_date( 20260228 ), 'not a string' );
		FA_Test::ok( ! Free_Analytics_Clock::is_date( '' ), 'empty' );

		FA_Test::eq( '2026-03-29', Free_Analytics_Clock::add_days( '2026-03-28', 1 ), '+1 before the DST change' );
		FA_Test::eq( '2026-03-30', Free_Analytics_Clock::add_days( '2026-03-28', 2 ), '+2 across the DST change (29 March 2026)' );
		FA_Test::eq( '2026-02-28', Free_Analytics_Clock::add_days( '2026-03-01', -1 ), '-1 into February' );
		FA_Test::eq( '2027-01-01', Free_Analytics_Clock::add_days( '2026-12-31', 1 ), 'year end' );
		FA_Test::eq( '2028-02-29', Free_Analytics_Clock::add_days( '2028-02-28', 1 ), 'leap day' );
		FA_Test::eq( '2026-03-10', Free_Analytics_Clock::add_days( '2026-03-10', 0 ), '+0' );
	}
);
