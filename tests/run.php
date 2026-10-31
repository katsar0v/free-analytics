<?php
/**
 * Free Analytics - test suite
 *
 * Run with WP-CLI on a development site (never on production):
 *
 *   wp eval-file wp-content/plugins/free-analytics/tests/run.php [filter]
 *
 * In a Docker setup, run it inside the container as the web server user, e.g.
 *   docker compose exec -T -u www-data wordpress wp --path=/var/www/html eval-file \
 *       wp-content/plugins/free-analytics/tests/run.php
 *
 * The optional filter is a substring of the case name, e.g. "recorder", "stats: top", "visit".
 * Exit status 1 when a case fails. The plugin need not be active: the suite requires
 * the plugin files itself. Cases for files that do not exist yet are skipped.
 *
 * The suite never touches the real data: it switches $wpdb to the prefix "fatest_" (a copy of the
 * options and users, empty posts, its own hits table), freezes the plugin clock
 * (Free_Analytics_Clock::$frozen), fakes the request through $_SERVER and the home URL
 * (https://example.com), and drops all fatest_ tables at the end (also after a fatal error,
 * through a shutdown function). A last check compares the real hits table and the real
 * free_analytics_* options before and after (new rows of today from genuine visitors are ignored).
 *
 * Layout: bootstrap.php (assertions, sandbox), cases/*.php (one file per area),
 * tools/seed.php (dev-only fake data for the real local table, not part of the suite).
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit( "Run with: wp eval-file wp-content/plugins/free-analytics/tests/run.php [filter]\n" );
}

require_once __DIR__ . '/bootstrap.php';

global $wpdb;

$free_analytics_missing = FA_Sandbox::load_plugin();
foreach ( array( 'clock', 'schema', 'classify', 'fingerprint', 'recorder', 'tracker' ) as $free_analytics_core ) {
	if ( in_array( $free_analytics_core, $free_analytics_missing, true ) ) {
		WP_CLI::error( 'inc/' . $free_analytics_core . '.php is missing.' );
	}
}
if ( $free_analytics_missing ) {
	WP_CLI::warning( 'Not written yet, their cases are skipped: inc/' . implode( '.php, inc/', $free_analytics_missing ) . '.php' );
}

$free_analytics_filter = isset( $args[0] ) ? (string) $args[0] : (string) getenv( 'FATEST_FILTER' );

foreach ( glob( __DIR__ . '/cases/*.php' ) as $free_analytics_case_file ) {
	require_once $free_analytics_case_file;
}

register_shutdown_function(
	static function () {
		// Even after a fatal error the real prefix must be back and the test tables gone.
		FA_Sandbox::leave();
	}
);

$free_analytics_real_before = FA_Sandbox::real_snapshot( $wpdb->prefix );

FA_Sandbox::enter();
WP_CLI::log( 'Sandbox: tables ' . FA_Sandbox::PREFIX . '*, home ' . FA_Sandbox::HOME );

$free_analytics_started = microtime( true );
$free_analytics_report  = FA_Test::run( $free_analytics_filter );

FA_Sandbox::leave();

// The real data must be as it was (genuine visits of today, that come in while the suite runs, are fine).
$free_analytics_leaks = FA_Sandbox::real_problems( $wpdb->prefix, $free_analytics_real_before );
if ( ! $free_analytics_leaks ) {
	WP_CLI::log( 'PASS  sandbox: real hits table and free_analytics_* options are untouched' );
	++$free_analytics_report['passed'];
} else {
	WP_CLI::log( 'FAIL  sandbox: real data changed during the run' );
	foreach ( $free_analytics_leaks as $free_analytics_leak ) {
		WP_CLI::log( '        - ' . $free_analytics_leak );
	}
	++$free_analytics_report['failed'];
}

WP_CLI::log( '' );
WP_CLI::log( sprintf( '%d passed, %d failed, %d skipped in %.1fs', $free_analytics_report['passed'], $free_analytics_report['failed'], $free_analytics_report['skipped'], microtime( true ) - $free_analytics_started ) );

if ( $free_analytics_report['failed'] > 0 ) {
	WP_CLI::halt( 1 );
}
