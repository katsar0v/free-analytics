<?php
/**
 * Free Analytics - visitor fingerprint
 *
 * visitor = first 16 hex chars of HMAC-SHA256( IP | user agent | site host, salt of the day ).
 *
 * The salt is 32 random bytes per day (site timezone), kept in the option
 * "free_analytics_salt_<Y-m-d>" and deleted when the next day's salt is made. Once it is
 * gone, nobody (including us) can tell whether two days' visitors were the same person. The IP
 * and the user agent themselves are never stored.
 *
 * The IP is REMOTE_ADDR only: X-Forwarded-For is set by the client and could be spoofed. The web
 * server (behind a proxy: mod_remoteip or real_ip) must pass the real client address; the dashboard
 * warns when it does not.
 *
 * @package Free_Analytics
 * @subpackage Fingerprint
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Free_Analytics_Fingerprint
 */
class Free_Analytics_Fingerprint {

	/**
	 * Option name prefix of the daily salts.
	 *
	 * @var string
	 */
	const SALT_PREFIX = 'free_analytics_salt_';

	/**
	 * The salt of a day, created on first use.
	 *
	 * Created with INSERT IGNORE and read back, so two requests at midnight agree on one salt.
	 * Read and written directly (not through get_option) so no cache can hold a stale value.
	 *
	 * @since 1.0.0
	 * @param string $day Y-m-d (site timezone).
	 * @return string 64 hex chars.
	 */
	public static function salt( $day ) {
		global $wpdb;

		$name = self::SALT_PREFIX . $day;
		$sql  = $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name );
		$salt = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( is_string( $salt ) && 64 === strlen( $salt ) ) {
			return $salt;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$created = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
				$name,
				bin2hex( random_bytes( 32 ) )
			)
		);
		if ( 1 === $created ) {
			self::delete_old_salts( $day );
		}

		return (string) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Deletes the salts of days before the given day.
	 *
	 * Only older days: a request from just before midnight that arrives late recreates
	 * yesterday's salt, and that must not delete today's.
	 *
	 * @since 1.0.0
	 * @param string $day Y-m-d; its salt and any later one stay.
	 * @return int Number of salts deleted.
	 */
	public static function delete_old_salts( $day ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s",
				$wpdb->esc_like( self::SALT_PREFIX ) . '%',
				self::SALT_PREFIX . $day
			)
		);
	}

	/**
	 * The visitor code.
	 *
	 * @since 1.0.0
	 * @param string $ip   Client IP (REMOTE_ADDR).
	 * @param string $ua   User agent.
	 * @param string $host Site host.
	 * @param string $day  Y-m-d (site timezone).
	 * @return string 16 hex chars.
	 */
	public static function visitor( $ip, $ua, $host, $day ) {
		return substr( hash_hmac( 'sha256', $ip . '|' . $ua . '|' . $host, self::salt( $day ) ), 0, 16 );
	}
}
