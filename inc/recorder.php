<?php
/**
 * Free Analytics - recorder
 *
 * Validates one beacon payload and writes one row. Kept apart from the REST layer so the
 * tests can call it directly with a payload and a fake request environment.
 *
 * @package Free_Analytics
 * @subpackage Recorder
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Free_Analytics_Recorder
 */
class Free_Analytics_Recorder {

	/**
	 * Largest accepted payload, in bytes.
	 *
	 * @var int
	 */
	const MAX_BODY = 2048;

	/**
	 * Inactivity after which the next hit starts a new visit, in seconds.
	 *
	 * @var int
	 */
	const VISIT_GAP = 1800;

	/**
	 * Most rows one visitor may add per minute; more is a script, not a person.
	 *
	 * @var int
	 */
	const PER_MINUTE = 30;

	/**
	 * Set by record() when it refused a hit because of the rate limit.
	 *
	 * @var bool
	 */
	public static $limited = false;

	/**
	 * Host of this site, without "www.".
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function site_host() {
		return Free_Analytics_Classify::host( home_url( '/' ) );
	}

	/**
	 * The request environment from $_SERVER.
	 *
	 * @since 1.0.0
	 * @return array{ip:string,ua:string,origin:string,fetch_site:string}
	 */
	public static function environment() {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- hashed or compared only, never stored or printed.
		return array(
			'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '',
			'ua'         => isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '',
			'origin'     => isset( $_SERVER['HTTP_ORIGIN'] ) ? (string) wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '',
			'fetch_site' => isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ? (string) wp_unslash( $_SERVER['HTTP_SEC_FETCH_SITE'] ) : '',
		);
		// phpcs:enable
	}

	/**
	 * Validates a raw beacon body and records it.
	 *
	 * @since 1.0.0
	 * @param string $body JSON body of the beacon.
	 * @param array  $env  Request environment, see environment().
	 * @return string recorded, bot, invalid, foreign or limited.
	 */
	public static function handle( $body, $env ) {
		$env = wp_parse_args(
			$env,
			array(
				'ip'         => '',
				'ua'         => '',
				'origin'     => '',
				'fetch_site' => '',
			)
		);

		if ( '' !== $env['origin'] && Free_Analytics_Classify::host( $env['origin'] ) !== self::site_host() ) {
			return 'foreign';
		}
		if ( 'cross-site' === strtolower( $env['fetch_site'] ) ) {
			return 'foreign';
		}
		if ( Free_Analytics_Classify::is_bot( $env['ua'] ) ) {
			return 'bot';
		}

		$hit = self::parse( $body );
		if ( null === $hit ) {
			return 'invalid';
		}

		if ( self::record( $hit, $env ) ) {
			return 'recorded';
		}

		return self::$limited ? 'limited' : 'invalid';
	}

	/**
	 * Parses and sanitises a beacon body.
	 *
	 * Keys: e event, p path, id post ID, nf 404 flag, r referrer, us/um/uc utm source/medium/campaign,
	 * ck click-ID kind (g or f), t link or form element ID.
	 *
	 * @since 1.0.0
	 * @param string $body JSON.
	 * @return array|null Clean hit, or null when it is not acceptable.
	 */
	public static function parse( $body ) {
		if ( ! is_string( $body ) || '' === $body || strlen( $body ) > self::MAX_BODY ) {
			return null;
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$event = isset( $data['e'] ) && is_string( $data['e'] ) ? $data['e'] : '';
		if ( ! in_array( $event, Free_Analytics_Classify::EVENTS, true ) ) {
			return null;
		}

		$path = self::clean_path( isset( $data['p'] ) ? $data['p'] : '' );
		if ( null === $path ) {
			return null;
		}

		$target = '';
		if ( 'pageview' !== $event ) {
			$target = Free_Analytics_Classify::target( $event, isset( $data['t'] ) && is_scalar( $data['t'] ) ? (string) $data['t'] : '' );
			if ( null === $target ) {
				return null;
			}
		}

		$post_id   = isset( $data['id'] ) && is_numeric( $data['id'] ) ? absint( $data['id'] ) : 0;
		$post_type = '';
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post && 'publish' === $post->post_status && is_post_type_viewable( $post->post_type ) ) {
				$post_type = $post->post_type;
			} else {
				$post_id = 0;
			}
		}

		$utm = array();
		foreach ( array(
			'us' => 'source',
			'um' => 'medium',
			'uc' => 'campaign',
		) as $key => $name ) {
			$value        = isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? sanitize_text_field( (string) $data[ $key ] ) : '';
			$utm[ $name ] = mb_substr( $value, 0, 'medium' === $name ? 50 : 100 );
		}

		return array(
			'event'     => $event,
			'path'      => $path,
			'post_id'   => $post_id,
			'post_type' => $post_type,
			'is_404'    => ! empty( $data['nf'] ) ? 1 : 0,
			'target'    => $target,
			'referrer'  => isset( $data['r'] ) && is_string( $data['r'] ) ? substr( $data['r'], 0, 1000 ) : '',
			'utm'       => $utm,
			'click'     => isset( $data['ck'] ) && in_array( $data['ck'], array( 'g', 'f' ), true ) ? $data['ck'] : '',
		);
	}

	/**
	 * Cleans a location.pathname: decoded, no query or fragment, at most 255 characters.
	 *
	 * @since 1.0.0
	 * @param mixed $path Raw path.
	 * @return string|null Path, or null when it is not a path.
	 */
	public static function clean_path( $path ) {
		if ( ! is_string( $path ) || '' === $path || '/' !== $path[0] || 0 === strpos( $path, '//' ) ) {
			return null;
		}
		$path = preg_replace( '/[?#].*$/s', '', $path );
		$path = rawurldecode( $path );
		if ( ! wp_check_invalid_utf8( $path ) || preg_match( '/[\x00-\x1F\x7F<>"\\\\]/', $path ) ) {
			return null;
		}

		return mb_substr( $path, 0, 255 );
	}

	/**
	 * Writes one row: computes the visitor code, finds or starts the visit, classifies the browser.
	 *
	 * @since 1.0.0
	 * @param array $hit Clean hit from parse().
	 * @param array $env Request environment (ip, ua).
	 * @return bool True when the row was written.
	 */
	public static function record( $hit, $env ) {
		global $wpdb;

		$table   = Free_Analytics_Schema::table();
		$now     = Free_Analytics_Clock::now();
		$day     = $now->format( 'Y-m-d' );
		$ts      = $now->format( 'Y-m-d H:i:s' );
		$host    = self::site_host();
		$visitor = Free_Analytics_Fingerprint::visitor( (string) $env['ip'], (string) $env['ua'], $host, $day );
		$source  = Free_Analytics_Classify::source( $hit['referrer'], $host, $hit['utm'], $hit['click'], (string) $env['ua'] );

		self::$limited = false;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$recent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE visitor = %s AND ts >= %s", $visitor, $now->modify( '-60 seconds' )->format( 'Y-m-d H:i:s' ) ) );
		if ( $recent >= self::PER_MINUTE ) {
			self::$limited = true;
			return false;
		}

		$since = $now->modify( '-' . self::VISIT_GAP . ' seconds' )->format( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$last = $wpdb->get_row( $wpdb->prepare( "SELECT visit, source, medium, campaign FROM {$table} WHERE visitor = %s AND ts >= %s ORDER BY ts DESC, id DESC LIMIT 1", $visitor, $since ) );

		// A hit that brings a different source (ad, search, social, campaign) starts a new visit,
		// but a reload or "back" to a landing page keeps its old referrer and must not.
		$new_visit = ! $last
			|| ( 'pageview' === $hit['event'] && $source['external']
				&& ( $source['source'] !== $last->source || $source['medium'] !== $last->medium || $source['campaign'] !== $last->campaign ) );

		if ( $new_visit ) {
			$visit = bin2hex( random_bytes( 8 ) );
		} else {
			$visit  = $last->visit;
			$source = array(
				'source'   => $last->source,
				'medium'   => $last->medium,
				'campaign' => $last->campaign,
			);
		}

		$ua = (string) $env['ua'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->insert(
			$table,
			array(
				'ts'        => $ts,
				'day'       => $day,
				'visitor'   => $visitor,
				'visit'     => $visit,
				'event'     => $hit['event'],
				'path'      => $hit['path'],
				'post_id'   => $hit['post_id'],
				'post_type' => $hit['post_type'],
				'is_404'    => $hit['is_404'],
				'target'    => $hit['target'],
				'source'    => mb_substr( $source['source'], 0, 100 ),
				'medium'    => mb_substr( $source['medium'], 0, 50 ),
				'campaign'  => mb_substr( $source['campaign'], 0, 100 ),
				'device'    => Free_Analytics_Classify::device( $ua ),
				'browser'   => Free_Analytics_Classify::browser( $ua ),
				'os'        => Free_Analytics_Classify::os( $ua ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
