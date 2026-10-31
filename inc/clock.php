<?php
/**
 * Free Analytics - clock
 *
 * All stored dates are local time in the site's timezone (Settings -> General). Tests can
 * freeze the time with Free_Analytics_Clock::$frozen.
 *
 * @package Free_Analytics
 * @subpackage Clock
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Free_Analytics_Clock
 */
class Free_Analytics_Clock {

	/**
	 * Fixed "now" for tests ('Y-m-d H:i:s', site time), or null for the real time.
	 *
	 * @var string|null
	 */
	public static $frozen = null;

	/**
	 * The site's timezone.
	 *
	 * @since 1.0.0
	 * @return DateTimeZone
	 */
	public static function zone() {
		return wp_timezone();
	}

	/**
	 * Current time in the site's timezone.
	 *
	 * @since 1.0.0
	 * @return DateTimeImmutable
	 */
	public static function now() {
		return new DateTimeImmutable( null === self::$frozen ? 'now' : self::$frozen, self::zone() );
	}

	/**
	 * Today's date in the site's timezone.
	 *
	 * @since 1.0.0
	 * @return string Y-m-d.
	 */
	public static function today() {
		return self::now()->format( 'Y-m-d' );
	}

	/**
	 * Checks a Y-m-d string.
	 *
	 * @since 1.0.0
	 * @param mixed $value Candidate date.
	 * @return bool True for a real calendar date.
	 */
	public static function is_date( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return false;
		}

		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Adds days to a Y-m-d date.
	 *
	 * @since 1.0.0
	 * @param string $day  Y-m-d.
	 * @param int    $days Days to add (negative to subtract).
	 * @return string Y-m-d.
	 */
	public static function add_days( $day, $days ) {
		return ( new DateTimeImmutable( $day, self::zone() ) )->modify( sprintf( '%+d days', $days ) )->format( 'Y-m-d' );
	}

	/**
	 * Unix timestamp of noon on a Y-m-d day (safe input for wp_date() around DST changes).
	 *
	 * @since 1.0.0
	 * @param string $day Y-m-d.
	 * @return int
	 */
	public static function noon( $day ) {
		return ( new DateTimeImmutable( $day . ' 12:00:00', self::zone() ) )->getTimestamp();
	}
}
