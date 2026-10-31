<?php
/**
 * Free Analytics - test framework
 *
 * A tiny assertion runner and the database sandbox. Loaded by tests/run.php.
 *
 * The sandbox switches $wpdb to the table prefix "fatest_" (a copy of the options and users,
 * empty posts, and its own hits table made by Free_Analytics_Schema::install()). The real
 * tables are only read, never written; every destructive statement goes through guard() first.
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Real-looking user agents used by the cases.
 */
class FA_UA {

	const CHROME_WIN   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
	const CHROME_MAC   = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
	const SAFARI_MAC   = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';
	const SAFARI_IOS   = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
	const IPAD         = 'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
	const CHROME_IOS   = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.6478.153 Mobile/15E148 Safari/604.1';
	const FIREFOX_WIN  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0';
	const FIREFOX_LNX  = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0';
	const FIREFOX_AND  = 'Mozilla/5.0 (Android 14; Mobile; rv:127.0) Gecko/127.0 Firefox/127.0';
	const EDGE_WIN     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0';
	const OPERA_WIN    = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/111.0.0.0';
	const SAMSUNG      = 'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0.0.0 Mobile Safari/537.36';
	const CHROME_AND   = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36';
	const TABLET_AND   = 'Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
	const FB_ANDROID   = 'Mozilla/5.0 (Linux; Android 13; SM-A536B Build/TP1A.220624.014; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/125.0.6422.165 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/466.0.0.43.108;]';
	const FB_IOS       = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/465.0.0.38.109;FBBV/600000000;FBDV/iPhone14,5;FBMD/iPhone;FBSN/iOS;FBSV/17.5;FBSS/3;FBID/phone;FBLC/bg_BG;FBOP/5]';
	const INSTAGRAM    = 'Mozilla/5.0 (Linux; Android 13; SM-G991B Build/TP1A.220624.014; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/125.0.6422.165 Mobile Safari/537.36 Instagram 330.0.0.40.99 Android (33/13; 480dpi; 1080x2186; samsung; SM-G991B; o1s; exynos2100; bg_BG; 566049541)';
	const CHROMEOS     = 'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
	const GOOGLEBOT    = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
	const GOOGLEBOT_M  = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
	const BINGBOT      = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';
	const HEADLESS     = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/126.0.0.0 Safari/537.36';
	const LIGHTHOUSE   = 'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Mobile Safari/537.36 Chrome-Lighthouse';
	const FB_CRAWLER   = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
	const FB_CRAWLER_M = 'Mozilla/5.0 (compatible; facebookexternalhit/1.1; +http://www.facebook.com/externalhit_uatext.php)';
	const AHREFS       = 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)';
	const CURL         = 'curl/8.5.0';
	const PYTHON       = 'python-requests/2.31.0';
}

/**
 * Test registry and assertions.
 */
class FA_Test {

	/**
	 * Registered cases: name => callable.
	 *
	 * @var array
	 */
	public static $cases = array();

	/**
	 * Failures of the case that is running.
	 *
	 * @var array
	 */
	public static $failures = array();

	/**
	 * Assertions made in the running case.
	 *
	 * @var int
	 */
	public static $assertions = 0;

	/**
	 * Why the running case skipped itself.
	 *
	 * @var string
	 */
	public static $skip_reason = '';

	/**
	 * Registers a case.
	 *
	 * @param string   $name Case name, "group: description".
	 * @param callable $fn   Test function; may return FA_Test::skip( 'why' ).
	 * @return void
	 */
	public static function add( $name, $fn ) {
		self::$cases[ $name ] = $fn;
	}

	/**
	 * Marks the running case as skipped: `return FA_Test::skip( 'reason' );`.
	 *
	 * @param string $reason Why.
	 * @return string The skip marker.
	 */
	public static function skip( $reason ) {
		self::$skip_reason = $reason;

		return 'skip';
	}

	/**
	 * Records an assertion.
	 *
	 * @param bool   $condition Must be true.
	 * @param string $message   What is checked.
	 * @return bool The condition.
	 */
	public static function ok( $condition, $message = '' ) {
		++self::$assertions;
		if ( ! $condition ) {
			$where = '';
			foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 6 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
				if ( isset( $frame['file'], $frame['line'] ) && __FILE__ !== $frame['file'] ) {
					$where = ' (' . basename( $frame['file'] ) . ':' . $frame['line'] . ')';
					break;
				}
			}
			self::$failures[] = $message . $where;
		}

		return (bool) $condition;
	}

	/**
	 * Asserts equality (strict).
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $message  What is checked.
	 * @return bool Whether equal.
	 */
	public static function eq( $expected, $actual, $message = '' ) {
		$same = $expected === $actual;

		return self::ok( $same, $message . ( $same ? '' : ' - expected ' . self::dump( $expected ) . ', got ' . self::dump( $actual ) ) );
	}

	/**
	 * Asserts inequality.
	 *
	 * @param mixed  $unexpected Value that must not appear.
	 * @param mixed  $actual     Actual value.
	 * @param string $message    What is checked.
	 * @return bool Whether different.
	 */
	public static function ne( $unexpected, $actual, $message = '' ) {
		$differs = $unexpected !== $actual;

		return self::ok( $differs, $message . ( $differs ? '' : ' - expected anything but ' . self::dump( $unexpected ) ) );
	}

	/**
	 * Asserts a number within a tolerance (rates, averages).
	 *
	 * @param float|int|null $expected  Expected value.
	 * @param mixed          $actual    Actual value.
	 * @param float          $tolerance Allowed difference.
	 * @param string         $message   What is checked.
	 * @return bool Whether close enough.
	 */
	public static function near( $expected, $actual, $tolerance, $message = '' ) {
		$close = is_numeric( $actual ) && abs( (float) $expected - (float) $actual ) <= $tolerance;

		return self::ok( $close, $message . ( $close ? '' : ' - expected about ' . self::dump( $expected ) . ', got ' . self::dump( $actual ) ) );
	}

	/**
	 * Short printable form of a value.
	 *
	 * @param mixed $value Value.
	 * @return string Text.
	 */
	public static function dump( $value ) {
		$text = is_scalar( $value ) || null === $value ? var_export( $value, true ) : wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

		return mb_strlen( (string) $text ) > 300 ? mb_substr( (string) $text, 0, 300 ) . '...' : (string) $text;
	}

	/**
	 * Runs the registered cases.
	 *
	 * @param string $filter Substring of the case name ("" = all).
	 * @return array passed, failed, skipped, results.
	 */
	public static function run( $filter = '' ) {
		$passed  = 0;
		$failed  = 0;
		$skipped = 0;

		foreach ( self::$cases as $name => $fn ) {
			if ( '' !== $filter && false === stripos( $name, $filter ) ) {
				continue;
			}

			FA_Sandbox::reset();
			self::$failures    = array();
			self::$assertions  = 0;
			self::$skip_reason = '';
			$started           = microtime( true );
			$state             = 'PASS';

			// PHP warnings and notices raised inside the plugin count as failures.
			set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
				static function ( $severity, $message, $file, $line ) {
					if ( ( error_reporting() & $severity ) && false !== strpos( $file, 'free-analytics/inc/' ) ) {
						FA_Test::$failures[] = 'PHP notice in plugin: ' . $message . ' at ' . basename( $file ) . ':' . $line;
					}

					return false;
				}
			);

			try {
				$outcome = call_user_func( $fn );
				if ( 'skip' === $outcome ) {
					$state = 'SKIP';
				} elseif ( self::$failures ) {
					$state = 'FAIL';
				}
			} catch ( Throwable $e ) {
				self::$failures[] = 'exception: ' . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine();
				$state            = 'FAIL';
			}
			restore_error_handler();

			if ( 'PASS' === $state ) {
				++$passed;
			} elseif ( 'FAIL' === $state ) {
				++$failed;
			} else {
				++$skipped;
			}

			WP_CLI::log( sprintf( '%s  %s  (%d assertions, %.2fs)', $state, $name, self::$assertions, microtime( true ) - $started ) );
			if ( 'SKIP' === $state ) {
				WP_CLI::log( '        ' . self::$skip_reason );
			}
			foreach ( self::$failures as $failure ) {
				WP_CLI::log( '        - ' . $failure );
			}
		}

		return array(
			'passed'  => $passed,
			'failed'  => $failed,
			'skipped' => $skipped,
		);
	}
}

/**
 * Database sandbox: table prefix fatest_, a fixed home URL, a frozen clock, a fake request environment.
 */
class FA_Sandbox {

	const PREFIX = 'fatest_';

	/**
	 * Home URL of the sandboxed site (what the visitor-facing host check sees).
	 */
	const HOME = 'https://example.com';

	/**
	 * The real prefix, while the sandbox is active.
	 *
	 * @var string|null
	 */
	public static $real_prefix = null;

	/**
	 * Home URL currently returned by the home / siteurl options.
	 *
	 * @var string
	 */
	private static $home = self::HOME;

	/**
	 * $_SERVER values changed by the running case: key => previous value (null = was unset).
	 *
	 * @var array
	 */
	private static $server_backup = array();

	/**
	 * Requires the plugin files that are not loaded yet (the plugin need not be active).
	 *
	 * @return string[] Names of the optional files that do not exist yet.
	 */
	public static function load_plugin() {
		$dir     = dirname( __DIR__ ) . '/inc/';
		$missing = array();
		if ( ! defined( 'FREE_ANALYTICS_DIR' ) ) {
			define( 'FREE_ANALYTICS_DIR', dirname( __DIR__ ) . '/' );
			define( 'FREE_ANALYTICS_FILE', dirname( __DIR__ ) . '/free-analytics.php' );
		}
		foreach ( array( 'clock', 'schema', 'classify', 'fingerprint', 'recorder', 'tracker', 'stats', 'cron' ) as $name ) {
			if ( is_readable( $dir . $name . '.php' ) ) {
				require_once $dir . $name . '.php';
			} else {
				$missing[] = $name;
			}
		}

		return $missing;
	}

	/**
	 * Throws unless $wpdb is on the sandbox prefix. Called before every destructive statement.
	 *
	 * @return void
	 * @throws RuntimeException When the sandbox is not active.
	 */
	public static function guard() {
		global $wpdb;

		if ( self::PREFIX !== $wpdb->prefix || self::PREFIX . 'options' !== $wpdb->options || self::PREFIX . 'posts' !== $wpdb->posts || 0 !== strpos( self::table(), self::PREFIX ) ) {
			throw new RuntimeException( 'Sandbox guard: $wpdb is not on the fatest_ prefix; refusing to touch the database.' );
		}
	}

	/**
	 * Name of the sandbox hits table.
	 *
	 * @return string
	 */
	public static function table() {
		return Free_Analytics_Schema::table();
	}

	/**
	 * Enters the sandbox.
	 *
	 * @return void
	 */
	public static function enter() {
		global $wpdb;

		if ( null !== self::$real_prefix ) {
			return;
		}

		$real = $wpdb->prefix;
		$test = self::PREFIX;
		if ( 0 === strpos( $real, $test ) ) {
			WP_CLI::error( 'The database prefix already is ' . $real . '.' );
		}
		self::$real_prefix = $real; // Set first, so the shutdown function can clean up after a failure below.

		self::drop_tables( $real ); // Left over from a crashed run.

		$tables = array_merge( array_values( $wpdb->tables( 'blog' ) ), array_values( $wpdb->tables( 'global' ) ) );
		foreach ( $tables as $table ) {
			$suffix = substr( $table, strlen( $real ) );
			$wpdb->query( "CREATE TABLE {$test}{$suffix} LIKE {$table}" ); // phpcs:ignore WordPress.DB
		}
		foreach ( array( 'options', 'users', 'usermeta' ) as $suffix ) {
			$wpdb->query( "INSERT INTO {$test}{$suffix} SELECT * FROM {$real}{$suffix}" ); // phpcs:ignore WordPress.DB
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$test}options SET option_name = %s WHERE option_name = %s", $test . 'user_roles', $real . 'user_roles' ) ); // phpcs:ignore WordPress.DB
		$wpdb->query( $wpdb->prepare( "UPDATE {$test}usermeta SET meta_key = CONCAT(%s, SUBSTRING(meta_key, %d)) WHERE meta_key LIKE %s", $test, strlen( $real ) + 1, $wpdb->esc_like( $real ) . '%' ) ); // phpcs:ignore WordPress.DB

		$wpdb->set_prefix( $test );
		wp_cache_flush();
		self::guard();

		// Fixed site address and timezone, whatever the local site uses: the date and DST cases
		// expect Europe/Sofia (UTC+2, summer UTC+3).
		add_filter( 'pre_option_timezone_string', array( __CLASS__, 'filter_timezone' ), 99 );
		add_filter( 'pre_option_gmt_offset', '__return_zero', 99 );
		add_filter( 'pre_option_home', array( __CLASS__, 'filter_home' ), 99 );
		add_filter( 'pre_option_siteurl', array( __CLASS__, 'filter_home' ), 99 );

		// The plugin's own table, made by the code under test.
		Free_Analytics_Schema::install();
		if ( self::table() !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::table() ) ) ) ) {
			WP_CLI::error( 'Free_Analytics_Schema::install() did not create ' . self::table() );
		}
		self::reset();
	}

	/**
	 * Leaves the sandbox and drops every fatest_ table. Safe to call twice.
	 *
	 * @return void
	 */
	public static function leave() {
		global $wpdb;

		if ( null === self::$real_prefix ) {
			return;
		}

		$real = self::$real_prefix;
		remove_filter( 'pre_option_timezone_string', array( __CLASS__, 'filter_timezone' ), 99 );
		remove_filter( 'pre_option_gmt_offset', '__return_zero', 99 );
		remove_filter( 'pre_option_home', array( __CLASS__, 'filter_home' ), 99 );
		remove_filter( 'pre_option_siteurl', array( __CLASS__, 'filter_home' ), 99 );
		self::restore_server();
		if ( class_exists( 'Free_Analytics_Clock' ) ) {
			Free_Analytics_Clock::$frozen = null;
		}
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );

		$wpdb->set_prefix( $real );
		wp_cache_flush();
		self::drop_tables( $real );
		self::$real_prefix = null;
	}

	/**
	 * Drops the fatest_ tables (never a table that starts with the real prefix).
	 *
	 * @param string $real Real prefix.
	 * @return void
	 */
	private static function drop_tables( $real ) {
		global $wpdb;

		$like = $wpdb->esc_like( self::PREFIX ) . '%';
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ) as $table ) {
			if ( 0 === strpos( $table, self::PREFIX ) && 0 !== strpos( $table, $real ) ) {
				$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB
			}
		}
	}

	/**
	 * Empties the sandbox between cases and resets the clock, the request environment and the home URL.
	 *
	 * @return void
	 */
	public static function reset() {
		global $wpdb;

		Free_Analytics_Clock::$frozen = null;
		self::restore_server();
		self::$home = self::HOME;
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		wp_set_current_user( 0 );

		self::guard();
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() ); // phpcs:ignore WordPress.DB
		$wpdb->query( "DELETE FROM {$wpdb->posts}" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "DELETE FROM {$wpdb->postmeta}" ); // phpcs:ignore WordPress.DB
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '%' . $wpdb->esc_like( 'free_analytics' ) . '%', '%' . $wpdb->esc_like( 'free-analytics' ) . '%' ) ); // phpcs:ignore WordPress.DB
		wp_cache_flush();
	}

	/**
	 * Timezone of the sandbox.
	 *
	 * @return string
	 */
	public static function filter_timezone() {
		return 'Europe/Sofia';
	}

	/**
	 * Filter for the home and siteurl options.
	 *
	 * @return string
	 */
	public static function filter_home() {
		return self::$home;
	}

	/**
	 * Changes the site address for the running case (reset afterwards).
	 *
	 * @param string $url Home URL, e.g. https://www.example.com.
	 * @return void
	 */
	public static function set_home( $url ) {
		self::$home = $url;
	}

	/**
	 * Sets $_SERVER values for the running case (null removes one); restored by reset().
	 *
	 * @param array $vars Key => value.
	 * @return void
	 */
	public static function server( array $vars ) {
		foreach ( $vars as $key => $value ) {
			if ( ! array_key_exists( $key, self::$server_backup ) ) {
				self::$server_backup[ $key ] = array_key_exists( $key, $_SERVER ) ? $_SERVER[ $key ] : null;
			}
			if ( null === $value ) {
				unset( $_SERVER[ $key ] );
			} else {
				$_SERVER[ $key ] = $value;
			}
		}
	}

	/**
	 * Restores the $_SERVER values changed by server().
	 *
	 * @return void
	 */
	private static function restore_server() {
		foreach ( self::$server_backup as $key => $old ) {
			if ( null === $old ) {
				unset( $_SERVER[ $key ] );
			} else {
				$_SERVER[ $key ] = $old;
			}
		}
		self::$server_backup = array();
	}

	/**
	 * Freezes the plugin clock (site time).
	 *
	 * @param string $datetime Y-m-d H:i:s, or null for the real time.
	 * @return void
	 */
	public static function freeze( $datetime ) {
		Free_Analytics_Clock::$frozen = $datetime;
	}

	/**
	 * A request environment for Recorder::handle().
	 *
	 * @param array $over Overrides for ip, ua, origin, fetch_site.
	 * @return array
	 */
	public static function env( array $over = array() ) {
		return array_merge(
			array(
				'ip'         => '203.0.113.7',
				'ua'         => FA_UA::CHROME_WIN,
				'origin'     => '',
				'fetch_site' => '',
			),
			$over
		);
	}

	/**
	 * JSON body of a beacon (defaults to a page view of "/").
	 *
	 * @param array $over Payload keys.
	 * @return string
	 */
	public static function body( array $over = array() ) {
		return (string) wp_json_encode( array_merge( array( 'e' => 'pageview', 'p' => '/' ), $over ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
	}

	/**
	 * Sends a beacon through Recorder::handle().
	 *
	 * @param array $payload Payload keys (merged over a page view of "/").
	 * @param array $env     Environment overrides, see env().
	 * @return string recorded, bot, invalid or foreign.
	 */
	public static function send( array $payload = array(), array $env = array() ) {
		return Free_Analytics_Recorder::handle( self::body( $payload ), self::env( $env ) );
	}

	/**
	 * All rows of the sandbox hits table, oldest first.
	 *
	 * @return array[]
	 */
	public static function rows() {
		global $wpdb;

		self::guard();

		return (array) $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Number of rows in the sandbox hits table.
	 *
	 * @return int
	 */
	public static function count_rows() {
		global $wpdb;

		self::guard();

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Inserts one row directly (stats and cron fixtures).
	 *
	 * @param array $row Column => value; day defaults to the date part of ts.
	 * @return int Row ID.
	 */
	public static function insert_hit( array $row ) {
		global $wpdb;

		self::guard();
		$row = array_merge(
			array(
				'ts'        => '2026-03-10 12:00:00',
				'visitor'   => 'aaaaaaaaaaaaaaaa',
				'visit'     => 'bbbbbbbbbbbbbbbb',
				'event'     => 'pageview',
				'path'      => '/',
				'post_id'   => 0,
				'post_type' => '',
				'is_404'    => 0,
				'target'    => '',
				'source'    => '',
				'medium'    => '',
				'campaign'  => '',
				'device'    => 'desktop',
				'browser'   => 'Chrome',
				'os'        => 'Windows',
			),
			$row
		);
		if ( empty( $row['day'] ) ) {
			$row['day'] = substr( $row['ts'], 0, 10 );
		}
		$wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB

		return (int) $wpdb->insert_id;
	}

	/**
	 * Inserts a post straight into the sandbox posts table (no hooks run).
	 *
	 * @param string $status Post status.
	 * @param string $type   Post type.
	 * @return int Post ID.
	 */
	public static function make_post( $status = 'publish', $type = 'page' ) {
		global $wpdb;

		self::guard();
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$wpdb->posts,
			array(
				'post_author'           => 1,
				'post_date'             => '2026-01-01 00:00:00',
				'post_date_gmt'         => '2026-01-01 00:00:00',
				'post_content'          => '',
				'post_title'            => 'Test ' . $type . ' ' . $status,
				'post_excerpt'          => '',
				'post_status'           => $status,
				'post_name'             => 'test-' . wp_generate_password( 8, false ),
				'to_ping'               => '',
				'pinged'                => '',
				'post_modified'         => '2026-01-01 00:00:00',
				'post_modified_gmt'     => '2026-01-01 00:00:00',
				'post_content_filtered' => '',
				'post_type'             => $type,
			)
		);
		clean_post_cache( (int) $wpdb->insert_id );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Snapshot of the real data this suite must never change (taken before and after the run).
	 *
	 * The real hits table also receives genuine visits while the suite runs, so only the rows that were
	 * there are counted and the new ones are judged by their day in real_problems().
	 *
	 * @param string $prefix Real table prefix.
	 * @return array
	 */
	public static function real_snapshot( $prefix ) {
		global $wpdb;

		$table = $prefix . 'free_analytics_hits';
		$snap  = array(
			'today'    => ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' ),
			'exists'   => $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ),
			'max_id'   => 0,
			'old_rows' => 0,
		);
		if ( $snap['exists'] ) {
			$snap['max_id']   = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM `{$table}`" ); // phpcs:ignore WordPress.DB
			$snap['old_rows'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE id <= %d", $snap['max_id'] ) ); // phpcs:ignore WordPress.DB
		}
		// Every free_analytics_* option except the salts (a new day legitimately makes a new salt).
		$snap['options'] = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT COUNT(*) AS n, COALESCE(SUM(CRC32(CONCAT(option_name, '=', option_value))), 0) AS crc FROM {$prefix}options WHERE option_name LIKE %s AND option_name NOT LIKE %s",
				$wpdb->esc_like( 'free_analytics' ) . '%',
				$wpdb->esc_like( 'free_analytics_salt_' ) . '%'
			),
			ARRAY_A
		);

		return $snap;
	}

	/**
	 * What changed in the real data since the snapshot, other than genuine visits of today.
	 *
	 * @param string $prefix Real table prefix.
	 * @param array  $before real_snapshot() from before the run.
	 * @return string[] Problems (empty = untouched).
	 */
	public static function real_problems( $prefix, array $before ) {
		global $wpdb;

		$after    = self::real_snapshot( $prefix );
		$problems = array();
		$days     = array_unique( array( $before['today'], $after['today'] ) ); // A visit may come in during the run (or at midnight).

		if ( $after['exists'] ) {
			$table = $prefix . 'free_analytics_hits';
			if ( $before['exists'] ) {
				$old = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE id <= %d", $before['max_id'] ) ); // phpcs:ignore WordPress.DB
				if ( $old !== $before['old_rows'] ) {
					$problems[] = "real hits table: {$before['old_rows']} rows before, $old of them left";
				}
			}
			$strange = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE id > %d AND day NOT IN (" . implode( ',', array_fill( 0, count( $days ), '%s' ) ) . ')', array_merge( array( $before['max_id'] ), $days ) ) ); // phpcs:ignore WordPress.DB
			if ( $strange ) {
				$problems[] = "real hits table: $strange new rows that are not from today (test data leaked?)";
			}
		}
		if ( $before['options'] !== $after['options'] ) {
			$problems[] = 'real free_analytics_* options changed: ' . wp_json_encode( $before['options'] ) . ' -> ' . wp_json_encode( $after['options'] );
		}
		$salts = (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$prefix}options WHERE option_name LIKE %s", $wpdb->esc_like( 'free_analytics_salt_' ) . '%' ) ); // phpcs:ignore WordPress.DB
		foreach ( $salts as $salt ) {
			if ( ! in_array( substr( $salt, strlen( 'free_analytics_salt_' ) ), $days, true ) ) {
				$problems[] = "real option $salt is not a salt of today (test data leaked?)";
			}
		}

		return $problems;
	}
}
