<?php
/**
 * Free Analytics - read queries
 *
 * Every number on the dashboard, the dashboard widget and the CLI summary comes from here.
 * A range is two site-timezone dates, both inclusive. "Visitors" over several days is the sum of each
 * day's unique visitors: the salt changes daily, so a person seen on two days counts twice.
 *
 * @package Free_Analytics
 * @subpackage Stats
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, values prepared.

/**
 * Class Free_Analytics_Stats
 */
class Free_Analytics_Stats {

	/**
	 * Contact events (everything except pageview and form).
	 *
	 * @var string[]
	 */
	const CONTACTS = array( 'tel', 'email', 'map', 'viber', 'whatsapp' );

	/**
	 * First day, Y-m-d.
	 *
	 * @var string
	 */
	public $from;

	/**
	 * Last day, Y-m-d.
	 *
	 * @var string
	 */
	public $to;

	/**
	 * Post types that get their own "Most viewed" table: public types except pages, media and
	 * page-builder templates.
	 *
	 * @since 1.0.0
	 * @return string[]
	 */
	public static function featured_post_types() {
		$skip  = array( 'page', 'attachment', 'bricks_template', 'elementor_library', 'et_pb_layout', 'fl-builder-template', 'ct_template', 'oxy_user_library', 'breakdance_template', 'breakdance_header', 'breakdance_footer', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' );
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), $skip ) );

		/**
		 * Filters the post types that get their own "Most viewed" table on the dashboard.
		 *
		 * @since 1.0.0
		 * @param string[] $types Post type names.
		 */
		return (array) apply_filters( 'free_analytics_featured_post_types', $types );
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $from First day (Y-m-d, inclusive).
	 * @param string $to   Last day (Y-m-d, inclusive).
	 */
	public function __construct( $from, $to ) {
		$this->from = $from;
		$this->to   = $to;
	}

	/**
	 * Table name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function t() {
		return Free_Analytics_Schema::table();
	}

	/**
	 * "day BETWEEN from AND to", prepared.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function range() {
		global $wpdb;

		return $wpdb->prepare( 'day BETWEEN %s AND %s', $this->from, $this->to );
	}

	/**
	 * SQL list of the contact events.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private static function contact_list() {
		return "'" . implode( "','", self::CONTACTS ) . "'";
	}

	/**
	 * Totals for the KPI tiles.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function totals() {
		global $wpdb;

		$t     = $this->t();
		$range = $this->range();

		$row = $wpdb->get_row(
			"SELECT COUNT(*) AS pageviews,
				COUNT(DISTINCT day, visitor) AS visitors,
				COUNT(DISTINCT visit) AS visits
			FROM {$t} WHERE {$range} AND event = 'pageview'",
			ARRAY_A
		);

		$bounces = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM (
				SELECT visit FROM {$t} WHERE {$range}
				GROUP BY visit HAVING COUNT(*) = 1 AND SUM(event = 'pageview') = 1
			) AS b"
		);

		$events = array();
		foreach ( $wpdb->get_results( "SELECT event, COUNT(*) AS n FROM {$t} WHERE {$range} AND event <> 'pageview' GROUP BY event", ARRAY_A ) as $e ) {
			$events[ $e['event'] ] = (int) $e['n'];
		}

		$visits    = (int) $row['visits'];
		$pageviews = (int) $row['pageviews'];
		$contacts  = 0;
		foreach ( self::CONTACTS as $event ) {
			$contacts += isset( $events[ $event ] ) ? $events[ $event ] : 0;
		}

		return array(
			'visitors'        => (int) $row['visitors'],
			'visits'          => $visits,
			'pageviews'       => $pageviews,
			'bounces'         => $bounces,
			'bounce_rate'     => $visits ? round( 100 * $bounces / $visits, 1 ) : null,
			'pages_per_visit' => $visits ? round( $pageviews / $visits, 2 ) : null,
			'events'          => $events,
			'contacts'        => $contacts,
			'forms'           => isset( $events['form'] ) ? $events['form'] : 0,
		);
	}

	/**
	 * Time series for the chart, zero-filled: hours for one day, days up to 92 days, months beyond.
	 *
	 * @since 1.0.0
	 * @return array{unit:string,points:array}
	 */
	public function series() {
		global $wpdb;

		$t     = $this->t();
		$range = $this->range();
		$zone  = Free_Analytics_Clock::zone();
		$first = new DateTimeImmutable( $this->from, $zone );
		$last  = new DateTimeImmutable( $this->to, $zone );
		$days  = (int) $first->diff( $last )->days + 1;

		if ( 1 === $days ) {
			$unit = 'hour';
			$key  = "DATE_FORMAT(ts, '%H')";
		} elseif ( $days <= 92 ) {
			$unit = 'day';
			$key  = "DATE_FORMAT(day, '%Y-%m-%d')";
		} else {
			$unit = 'month';
			$key  = "DATE_FORMAT(day, '%Y-%m')";
		}

		$rows = $wpdb->get_results(
			"SELECT {$key} AS k, COUNT(DISTINCT day, visitor) AS visitors, COUNT(*) AS pageviews
			FROM {$t} WHERE {$range} AND event = 'pageview' GROUP BY k",
			OBJECT_K
		);

		$keys = array();
		if ( 'hour' === $unit ) {
			for ( $h = 0; $h < 24; $h++ ) {
				$keys[] = sprintf( '%02d', $h );
			}
		} elseif ( 'day' === $unit ) {
			for ( $d = $first; $d <= $last; $d = $d->modify( '+1 day' ) ) {
				$keys[] = $d->format( 'Y-m-d' );
			}
		} else {
			for ( $m = $first->modify( 'first day of this month' ); $m <= $last; $m = $m->modify( 'first day of next month' ) ) {
				$keys[] = $m->format( 'Y-m' );
			}
		}

		$points = array();
		foreach ( $keys as $k ) {
			$points[] = array(
				'key'       => $k,
				'visitors'  => isset( $rows[ $k ] ) ? (int) $rows[ $k ]->visitors : 0,
				'pageviews' => isset( $rows[ $k ] ) ? (int) $rows[ $k ]->pageviews : 0,
			);
		}

		return array(
			'unit'   => $unit,
			'points' => $points,
		);
	}

	/**
	 * Most viewed pages (404 pages excluded).
	 *
	 * @since 1.0.0
	 * @param int $limit Rows.
	 * @return array
	 */
	public function top_pages( $limit = 10 ) {
		global $wpdb;

		$t     = $this->t();
		$range = $this->range();

		return $this->ints(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT path, MAX(post_id) AS post_id, COUNT(*) AS views, COUNT(DISTINCT day, visitor) AS visitors
					FROM {$t} WHERE {$range} AND event = 'pageview' AND is_404 = 0
					GROUP BY path ORDER BY views DESC, path ASC LIMIT %d",
					$limit
				),
				ARRAY_A
			),
			array( 'post_id', 'views', 'visitors' )
		);
	}

	/**
	 * Most viewed posts of one post type, with the contacts and forms sent from their pages.
	 *
	 * @since 1.0.0
	 * @param string $post_type Post type.
	 * @param int    $limit     Rows.
	 * @return array
	 */
	public function top_posts( $post_type, $limit = 10 ) {
		global $wpdb;

		$t        = $this->t();
		$range    = $this->range();
		$contacts = self::contact_list();

		return $this->ints(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, MAX(path) AS path,
						SUM(event = 'pageview') AS views,
						COUNT(DISTINCT CASE WHEN event = 'pageview' THEN CONCAT(day, visitor) END) AS visitors,
						SUM(event IN ({$contacts})) AS contacts,
						SUM(event = 'form') AS forms
					FROM {$t} WHERE {$range} AND post_type = %s AND post_id > 0
					GROUP BY post_id HAVING views > 0 ORDER BY views DESC, post_id DESC LIMIT %d",
					$post_type,
					$limit
				),
				ARRAY_A
			),
			array( 'post_id', 'views', 'visitors', 'contacts', 'forms' )
		);
	}

	/**
	 * Visits, contacts and forms per traffic source ('' = direct).
	 *
	 * @since 1.0.0
	 * @param int $limit Rows.
	 * @return array
	 */
	public function sources( $limit = 10 ) {
		global $wpdb;

		$t        = $this->t();
		$range    = $this->range();
		$contacts = self::contact_list();

		return $this->ints(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT source,
						COUNT(DISTINCT CASE WHEN event = 'pageview' THEN visit END) AS visits,
						SUM(event IN ({$contacts})) AS contacts,
						SUM(event = 'form') AS forms
					FROM {$t} WHERE {$range}
					GROUP BY source ORDER BY visits DESC, source ASC LIMIT %d",
					$limit
				),
				ARRAY_A
			),
			array( 'visits', 'contacts', 'forms' )
		);
	}

	/**
	 * UTM campaigns.
	 *
	 * @since 1.0.0
	 * @param int $limit Rows.
	 * @return array
	 */
	public function campaigns( $limit = 10 ) {
		global $wpdb;

		$t        = $this->t();
		$range    = $this->range();
		$contacts = self::contact_list();

		return $this->ints(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT source, medium, campaign,
						COUNT(DISTINCT CASE WHEN event = 'pageview' THEN visit END) AS visits,
						SUM(event IN ({$contacts})) AS contacts,
						SUM(event = 'form') AS forms
					FROM {$t} WHERE {$range} AND campaign <> ''
					GROUP BY source, medium, campaign ORDER BY visits DESC, campaign ASC LIMIT %d",
					$limit
				),
				ARRAY_A
			),
			array( 'visits', 'contacts', 'forms' )
		);
	}

	/**
	 * Contacts and forms per type, each with its top three pages.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function contacts() {
		global $wpdb;

		$t     = $this->t();
		$range = $this->range();
		$out   = array();

		foreach ( $wpdb->get_results( "SELECT event, path, COUNT(*) AS n FROM {$t} WHERE {$range} AND event <> 'pageview' GROUP BY event, path ORDER BY n DESC, path ASC", ARRAY_A ) as $row ) {
			$event = $row['event'];
			if ( ! isset( $out[ $event ] ) ) {
				$out[ $event ] = array(
					'event' => $event,
					'count' => 0,
					'pages' => array(),
				);
			}
			$out[ $event ]['count'] += (int) $row['n'];
			if ( count( $out[ $event ]['pages'] ) < 3 ) {
				$out[ $event ]['pages'][] = array(
					'path'  => $row['path'],
					'count' => (int) $row['n'],
				);
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		return $out;
	}

	/**
	 * Visitors by device, browser or OS.
	 *
	 * @since 1.0.0
	 * @param string $column device, browser or os.
	 * @return array
	 */
	public function breakdown( $column ) {
		global $wpdb;

		if ( ! in_array( $column, array( 'device', 'browser', 'os' ), true ) ) {
			return array();
		}
		$t     = $this->t();
		$range = $this->range();

		return $this->ints(
			$wpdb->get_results(
				"SELECT {$column} AS `key`, COUNT(DISTINCT day, visitor) AS visitors
				FROM {$t} WHERE {$range} AND event = 'pageview'
				GROUP BY `key` ORDER BY visitors DESC, `key` ASC",
				ARRAY_A
			),
			array( 'visitors' )
		);
	}

	/**
	 * Requested pages that did not exist.
	 *
	 * @since 1.0.0
	 * @param int $limit Rows.
	 * @return array
	 */
	public function not_found( $limit = 10 ) {
		global $wpdb;

		$t     = $this->t();
		$range = $this->range();

		return $this->ints(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT path, COUNT(*) AS views FROM {$t} WHERE {$range} AND event = 'pageview' AND is_404 = 1
					GROUP BY path ORDER BY views DESC, path ASC LIMIT %d",
					$limit
				),
				ARRAY_A
			),
			array( 'views' )
		);
	}

	/**
	 * Visitors seen in the last few minutes.
	 *
	 * @since 1.0.0
	 * @param int $minutes Window.
	 * @return int
	 */
	public static function online( $minutes = 5 ) {
		global $wpdb;

		$t     = Free_Analytics_Schema::table();
		$now   = Free_Analytics_Clock::now();
		$since = $now->modify( '-' . (int) $minutes . ' minutes' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT visitor) FROM {$t} WHERE day BETWEEN %s AND %s AND ts >= %s",
				$since->format( 'Y-m-d' ),
				$now->format( 'Y-m-d' ),
				$since->format( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * Casts the given keys of every row to int.
	 *
	 * @since 1.0.0
	 * @param array|null $rows Rows (ARRAY_A).
	 * @param string[]   $keys Keys to cast.
	 * @return array
	 */
	private function ints( $rows, $keys ) {
		$rows = is_array( $rows ) ? $rows : array();
		foreach ( $rows as &$row ) {
			foreach ( $keys as $key ) {
				$row[ $key ] = (int) $row[ $key ];
			}
		}
		unset( $row );

		return $rows;
	}
}
