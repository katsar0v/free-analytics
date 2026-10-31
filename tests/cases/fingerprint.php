<?php
/**
 * Visitor fingerprint cases: the daily salt and the hash.
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Names of the salt options in the sandbox.
 *
 * @return string[]
 */
function fa_test_salt_options() {
	global $wpdb;

	FA_Sandbox::guard();

	return (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name", $wpdb->esc_like( 'free_analytics_salt_' ) . '%' ) ); // phpcs:ignore WordPress.DB
}

FA_Test::add(
	'fingerprint: same ip + ua + host + day gives the same 16-hex visitor; any change gives another',
	function () {
		$f = 'Free_Analytics_Fingerprint';
		$a = $f::visitor( '203.0.113.7', FA_UA::CHROME_WIN, 'example.com', '2026-03-10' );

		FA_Test::eq( 1, preg_match( '/^[0-9a-f]{16}$/', $a ), '16 hex chars: ' . $a );
		FA_Test::eq( $a, $f::visitor( '203.0.113.7', FA_UA::CHROME_WIN, 'example.com', '2026-03-10' ), 'same input, same visitor' );
		FA_Test::ne( $a, $f::visitor( '203.0.113.7', FA_UA::FIREFOX_WIN, 'example.com', '2026-03-10' ), 'other user agent' );
		FA_Test::ne( $a, $f::visitor( '203.0.113.8', FA_UA::CHROME_WIN, 'example.com', '2026-03-10' ), 'other IP' );
		FA_Test::ne( $a, $f::visitor( '203.0.113.7', FA_UA::CHROME_WIN, 'example.org', '2026-03-10' ), 'other host' );
		FA_Test::ne( $a, $f::visitor( '2001:db8::7', FA_UA::CHROME_WIN, 'example.com', '2026-03-10' ), 'IPv6 address' );
	}
);

FA_Test::add(
	'fingerprint: the visitor is a salted HMAC (formula check), and the IP / user agent are not in it',
	function () {
		$f    = 'Free_Analytics_Fingerprint';
		$salt = $f::salt( '2026-03-10' );
		$ip   = '203.0.113.7';
		$want = substr( hash_hmac( 'sha256', $ip . '|' . FA_UA::CHROME_WIN . '|example.com', $salt ), 0, 16 );

		FA_Test::eq( $want, $f::visitor( $ip, FA_UA::CHROME_WIN, 'example.com', '2026-03-10' ), 'HMAC-SHA256(ip|ua|host, salt of the day), first 16 hex chars' );
		FA_Test::ne( substr( hash( 'sha256', $ip . '|' . FA_UA::CHROME_WIN . '|example.com' ), 0, 16 ), $want, 'not an unsalted hash' );
		FA_Test::ne( substr( md5( $ip ), 0, 16 ), $want, 'not a hash of the IP alone' );
	}
);

FA_Test::add(
	'fingerprint: a new day gives another visitor for the same ip + ua',
	function () {
		$f  = 'Free_Analytics_Fingerprint';
		$d1 = $f::visitor( '203.0.113.7', FA_UA::CHROME_WIN, 'example.com', '2026-03-10' );
		$d2 = $f::visitor( '203.0.113.7', FA_UA::CHROME_WIN, 'example.com', '2026-03-11' );

		FA_Test::ne( $d1, $d2, 'day 1 vs day 2' );
		FA_Test::eq( $d2, $f::visitor( '203.0.113.7', FA_UA::CHROME_WIN, 'example.com', '2026-03-11' ), 'day 2 is stable' );
	}
);

FA_Test::add(
	'fingerprint: salt() is 64 hex, stable, stored in a non-autoloaded option',
	function () {
		global $wpdb;
		$f = 'Free_Analytics_Fingerprint';

		$s1 = $f::salt( '2026-03-10' );
		FA_Test::eq( 1, preg_match( '/^[0-9a-f]{64}$/', $s1 ), '64 hex chars: ' . $s1 );
		FA_Test::eq( $s1, $f::salt( '2026-03-10' ), 'stable across calls' );
		wp_cache_flush();
		FA_Test::eq( $s1, $f::salt( '2026-03-10' ), 'stable after the cache is flushed' );
		FA_Test::eq( array( 'free_analytics_salt_2026-03-10' ), fa_test_salt_options(), 'one option, named by the day' );
		FA_Test::eq( $s1, $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'free_analytics_salt_2026-03-10'" ), 'option value is the salt' ); // phpcs:ignore WordPress.DB
		FA_Test::ok( in_array( $wpdb->get_var( "SELECT autoload FROM {$wpdb->options} WHERE option_name = 'free_analytics_salt_2026-03-10'" ), array( 'off', 'no' ), true ), 'autoload is off' ); // phpcs:ignore WordPress.DB

		$other = $f::salt( '2026-03-11' );
		FA_Test::ne( $s1, $other, 'another day, another salt' );
	}
);

FA_Test::add(
	'fingerprint: making the next day\'s salt deletes the previous one',
	function () {
		$f = 'Free_Analytics_Fingerprint';

		$f::salt( '2026-03-10' );
		FA_Test::eq( array( 'free_analytics_salt_2026-03-10' ), fa_test_salt_options(), 'day 1 salt exists' );

		$f::salt( '2026-03-11' );
		FA_Test::eq( array( 'free_analytics_salt_2026-03-11' ), fa_test_salt_options(), 'only day 2 salt is left' );

		// Several stale salts at once; unrelated options stay.
		update_option( 'free_analytics_salt_2020-01-01', str_repeat( 'a', 64 ), false );
		update_option( 'free_analytics_salt_2025-12-31', str_repeat( 'b', 64 ), false );
		update_option( 'free_analytics_unrelated', 'keep', false );
		$f::salt( '2026-03-12' );
		FA_Test::eq( array( 'free_analytics_salt_2026-03-12' ), fa_test_salt_options(), 'every older salt is deleted' );
		wp_cache_flush();
		FA_Test::eq( 'keep', get_option( 'free_analytics_unrelated' ), 'other options are not touched' );
	}
);

FA_Test::add(
	'fingerprint: salt() uses a salt that already exists and delete_old_salts() reports what it removed',
	function () {
		global $wpdb;
		$f = 'Free_Analytics_Fingerprint';

		$known = str_repeat( 'ab', 32 );
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", 'free_analytics_salt_2026-03-10', $known ) ); // phpcs:ignore WordPress.DB
		FA_Test::eq( $known, $f::salt( '2026-03-10' ), 'an existing salt is read, not replaced' );
		FA_Test::eq( array( 'free_analytics_salt_2026-03-10' ), fa_test_salt_options(), 'no second option' );

		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off'), (%s, %s, 'off')", 'free_analytics_salt_2024-01-01', $known, 'free_analytics_salt_2024-01-02', $known ) ); // phpcs:ignore WordPress.DB
		FA_Test::eq( 2, $f::delete_old_salts( '2026-03-10' ), 'two stale salts deleted' );
		FA_Test::eq( array( 'free_analytics_salt_2026-03-10' ), fa_test_salt_options(), 'the kept day stays' );
		FA_Test::eq( 0, $f::delete_old_salts( '2026-03-10' ), 'nothing left to delete' );
	}
);
