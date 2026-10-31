<?php
/**
 * Free Analytics - dev-only seed tool
 *
 * Fills the REAL local hits table (wp_free_analytics_hits) with fake, realistic traffic so the
 * dashboard has something to show. Every seeded row has a visit ID that starts with "seed" (real
 * visit IDs are hex, so they never do), which is how `clean` finds them. Local use only: never run it
 * on the live site.
 *
 *   wp eval-file wp-content/plugins/free-analytics/tests/tools/seed.php [days|clean]
 *
 *   (no argument)  seed 60 days, ending today (site time)
 *   45             seed 45 days
 *   clean          delete the seeded rows and nothing else
 *
 * Seeding twice replaces the earlier seed. What it makes: 40-160 visitors a day (fewer on Sundays, a
 * mild upward trend), 1-2 visits each, 1-6 page views per visit (about 45% single-page) on the real
 * published pages and posts of the other public post types (those dominate), a few 404 paths, sources (direct 35%, Google 35%, Facebook
 * 12%, example-portal.com 8%, Instagram 4%, Bing 2%, a few others; a "facebook / cpc / spring-sale" campaign on
 * some days), devices 65/30/5 with matching browsers and systems, and tel / email / map / form events
 * mostly on those post pages. Hours are skewed to the evening. Today only gets the hits up to the current time.
 * Dates are in the site timezone.
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit( "Run with: wp eval-file wp-content/plugins/free-analytics/tests/tools/seed.php [days|clean]\n" );
}

if ( ! class_exists( 'FA_Seed' ) ) {

	/**
	 * Generator of fake traffic.
	 */
	class FA_Seed {

		const MARK = 'seed';

		/**
		 * Hour of the day => relative traffic (skewed to the evening).
		 */
		const HOURS = array( 2, 1, 0.5, 0.3, 0.3, 0.5, 1, 2, 3.5, 5, 6, 6.5, 7, 6.5, 6, 6, 6.5, 7.5, 9, 11, 12, 11, 7, 4 );

		/**
		 * Day of the week (ISO 1 = Monday) => relative traffic.
		 */
		const WEEKDAY = array( 1 => 1.0, 2 => 1.05, 3 => 1.05, 4 => 1.0, 5 => 0.95, 6 => 0.85, 7 => 0.6 );

		/**
		 * Visit sources and their weights.
		 */
		const SOURCES = array(
			''           => 35,
			'Google'     => 35,
			'Facebook'   => 12,
			'example-portal.com' => 8,
			'Instagram'  => 4,
			'Bing'       => 2,
			'Yahoo'      => 2,
			'DuckDuckGo' => 1,
			'ChatGPT'    => 1,
		);

		/**
		 * Paths that do not exist (a few of them).
		 */
		const MISSING = array( '/old-page/', '/2019/05/summer-offer/', '/shop/discontinued-item/', '/wp-login.php', '/category/news/page/9/', '/contact-us.html' );

		/**
		 * Real published pages: list of [path, post_id].
		 *
		 * @var array
		 */
		private $pages = array();

		/**
		 * Real published posts of the featured post types, most popular first: list of [path, post_id, post_type].
		 *
		 * @var array
		 */
		private $products = array();

		/**
		 * Cumulative weights of the products.
		 *
		 * @var float[]
		 */
		private $item_weights = array();

		/**
		 * Counters for the report.
		 *
		 * @var array
		 */
		public $count = array(
			'days'       => 0,
			'visitors'   => 0,
			'visits'     => 0,
			'pageview'   => 0,
			'not_found'  => 0,
			'tel'        => 0,
			'email'      => 0,
			'map'        => 0,
			'form'       => 0,
			'today_rows' => 0,
		);

		/**
		 * Reads the real pages and products.
		 */
		public function __construct() {
			foreach ( get_pages( array( 'post_status' => 'publish' ) ) as $page ) {
				$this->pages[] = array( $this->path( $page ), (int) $page->ID );
			}
			$types = Free_Analytics_Stats::featured_post_types();
			$products  = get_posts(
				array(
					'post_type'   => $types,
					'post_status' => 'publish',
					'numberposts' => -1,
				)
			);
			shuffle( $products );
			$total = 0;
			foreach ( $products as $rank => $product ) {
				$this->products[]        = array( $this->path( $product ), (int) $product->ID, $product->post_type );
				$total              += 1 / pow( $rank + 1, 0.8 );
				$this->item_weights[] = $total;
			}
		}

		/**
		 * Number of real products and pages found.
		 *
		 * @return array
		 */
		public function pool() {
			return array(
				'shop'  => count( $this->products ),
				'pages' => count( $this->pages ),
			);
		}

		/**
		 * Decoded path of a post, as the plugin stores it.
		 *
		 * @param WP_Post $post Post.
		 * @return string
		 */
		private function path( $post ) {
			$path = rawurldecode( (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ) );

			return mb_substr( '' === $path ? '/' : $path, 0, 255 );
		}

		/**
		 * Weighted random key.
		 *
		 * @param array $weights Key => weight.
		 * @return mixed A key.
		 */
		private function pick( array $weights ) {
			$total = array_sum( $weights );
			$r     = mt_rand( 0, 1000000 ) / 1000000 * $total;
			foreach ( $weights as $key => $weight ) {
				$r -= $weight;
				if ( $r <= 0 ) {
					return $key;
				}
			}

			return array_key_last( $weights );
		}

		/**
		 * Chance helper.
		 *
		 * @param float $p Probability 0..1.
		 * @return bool
		 */
		private function chance( $p ) {
			return mt_rand( 0, 1000000 ) / 1000000 < $p;
		}

		/**
		 * A random second of the day, by the hourly weights, not after $limit.
		 *
		 * @param int $limit Last allowed second of the day (86399 for a whole day).
		 * @return int
		 */
		private function start_second( $limit ) {
			$weights = array();
			foreach ( self::HOURS as $hour => $weight ) {
				$from = $hour * 3600;
				if ( $from >= $limit ) {
					break;
				}
				$weights[ $hour ] = $weight * min( 1, ( $limit - $from ) / 3600 );
			}
			$hour = (int) $this->pick( $weights );
			$from = $hour * 3600;

			return mt_rand( $from, max( $from, min( $from + 3599, $limit ) ) );
		}

		/**
		 * One page view target: a product, a page or a missing path.
		 *
		 * @param bool $item_only The first page of a search visit tends to be a product.
		 * @return array path, post_id, post_type, is_404.
		 */
		private function target( $item_only = false ) {
			$kind = $this->pick(
				array(
					'product'  => $this->products ? ( $item_only ? 90 : 72 ) : 0,
					'page' => $this->pages ? ( $item_only ? 8 : 26 ) : 0,
					'gone' => 1.5,
				)
			);
			if ( 'product' === $kind ) {
				$r = mt_rand( 0, 1000000 ) / 1000000 * end( $this->item_weights );
				foreach ( $this->item_weights as $i => $cumulative ) {
					if ( $r <= $cumulative ) {
						return array( $this->products[ $i ][0], $this->products[ $i ][1], $this->products[ $i ][2], 0 );
					}
				}
			}
			if ( 'page' === $kind ) {
				$page = $this->pages[ array_rand( $this->pages ) ];

				return array( $page[0], $page[1], 'page', 0 );
			}

			return array( self::MISSING[ array_rand( self::MISSING ) ], 0, '', 1 );
		}

		/**
		 * Device, browser and OS that fit together.
		 *
		 * @return string[] device, browser, os.
		 */
		private function client() {
			$device = $this->pick(
				array(
					'mobile'  => 65,
					'desktop' => 30,
					'tablet'  => 5,
				)
			);
			if ( 'mobile' === $device ) {
				if ( $this->chance( 0.55 ) ) {
					return array( $device, $this->pick( array( 'Chrome' => 70, 'Samsung Internet' => 15, 'Opera' => 5, 'Firefox' => 4, 'Edge' => 2 ) ), 'Android' );
				}

				return array( $device, $this->pick( array( 'Safari' => 76, 'Chrome' => 15, 'Firefox' => 3, 'Edge' => 2, 'Opera' => 1 ) ), 'iOS' );
			}
			if ( 'tablet' === $device ) {
				if ( $this->chance( 0.6 ) ) {
					return array( $device, $this->pick( array( 'Safari' => 80, 'Chrome' => 20 ) ), 'iOS' );
				}

				return array( $device, $this->pick( array( 'Chrome' => 75, 'Samsung Internet' => 25 ) ), 'Android' );
			}
			$os = $this->pick( array( 'Windows' => 70, 'macOS' => 18, 'Linux' => 8, 'ChromeOS' => 4 ) );
			if ( 'Windows' === $os ) {
				return array( $device, $this->pick( array( 'Chrome' => 62, 'Edge' => 20, 'Firefox' => 10, 'Opera' => 5, 'other' => 3 ) ), $os );
			}
			if ( 'macOS' === $os ) {
				return array( $device, $this->pick( array( 'Safari' => 45, 'Chrome' => 45, 'Firefox' => 6, 'Edge' => 4 ) ), $os );
			}
			if ( 'Linux' === $os ) {
				return array( $device, $this->pick( array( 'Chrome' => 55, 'Firefox' => 45 ) ), $os );
			}

			return array( $device, 'Chrome', $os );
		}

		/**
		 * Source, medium and campaign of a new visit.
		 *
		 * @param bool $campaign_day Whether the Facebook campaign runs today.
		 * @return string[] source, medium, campaign.
		 */
		private function source( $campaign_day ) {
			$weights = self::SOURCES;
			if ( $campaign_day ) {
				$weights['Facebook'] = 30;
			}
			$source = (string) $this->pick( $weights );
			if ( 'Facebook' === $source && $campaign_day && $this->chance( 0.75 ) ) {
				return array( 'facebook', 'cpc', 'spring-sale' );
			}

			return array( $source, '', '' );
		}

		/**
		 * Rows of one visit.
		 *
		 * @param array $ctx Visitor and day context.
		 * @param int   $start Start second of the day.
		 * @return array Rows (column => value), oldest first.
		 */
		private function visit( array $ctx, $start ) {
			$source = $this->source( $ctx['campaign'] );
			$visit  = self::MARK . bin2hex( random_bytes( 6 ) );
			$pages  = $this->chance( 0.45 ) ? 1 : (int) $this->pick( array( 2 => 32, 3 => 28, 4 => 20, 5 => 12, 6 => 8 ) );
			$rows   = array();
			$second = $start;
			$last   = null;
			$views  = array();

			for ( $i = 0; $i < $pages; $i++ ) {
				$target = $this->target( 0 === $i && '' !== $source[0] && 'Facebook' !== $source[0] );
				if ( $last && $target[0] === $last[0] ) {
					$target = $this->target();
				}
				$last = $target;
				$ts   = min( $second, $ctx['limit'] );
				$row  = $this->row( $ctx, $visit, $source, 'pageview', $ts, $target, '' );
				$rows[]  = $row;
				$views[] = $row;
				++$this->count['pageview'];
				if ( $target[3] ) {
					++$this->count['not_found'];
				}
				$second += $this->chance( 0.6 ) ? mt_rand( 15, 60 ) : mt_rand( 60, 240 );
			}

			$mult = array( 'mobile' => 1.25, 'desktop' => 0.5, 'tablet' => 0.8 );
			$m    = $mult[ $ctx['client'][0] ];
			foreach ( array( 'tel' => 0.06, 'email' => 0.02, 'map' => 0.03, 'form' => 0.015 ) as $event => $p ) {
				if ( ! $this->chance( $p * $m ) ) {
					continue;
				}
				$products = array_values( array_filter( $views, static function ( $v ) { return 'page' !== $v['post_type'] && '' !== $v['post_type']; } ) );
				$host = $products && $this->chance( 0.8 ) ? $products[ array_rand( $products ) ] : $views[ array_rand( $views ) ];
				$ts     = min( strtotime( $host['ts'] . ' UTC' ) - strtotime( $ctx['date'] . ' 00:00:00 UTC' ) + mt_rand( 5, 90 ), $ctx['limit'] );
				$target = array( $host['path'], $host['post_id'], $host['post_type'], 0 );
				$forms  = array( 'abcdef', 'qwerty', 'zxcvbn' );
				$value  = array(
					'tel'   => '15550100',
					'email' => 'info@example.com',
					'map'   => 'Google Maps',
					'form'  => $forms[ array_rand( $forms ) ],
				);
				$value  = $value[ $event ];
				$rows[] = $this->row( $ctx, $visit, $source, $event, $ts, $target, $value );
				++$this->count[ $event ];
			}

			usort(
				$rows,
				static function ( $a, $b ) {
					return strcmp( $a['ts'], $b['ts'] );
				}
			);

			return array( $rows, $second );
		}

		/**
		 * One table row.
		 *
		 * @param array  $ctx    Context.
		 * @param string $visit  Visit ID.
		 * @param array  $source source, medium, campaign.
		 * @param string $event  Event.
		 * @param int    $second Second of the day.
		 * @param array  $target path, post_id, post_type, is_404.
		 * @param string $value  Target value.
		 * @return array
		 */
		private function row( array $ctx, $visit, array $source, $event, $second, array $target, $value ) {
			$second = max( 0, min( 86399, $second ) );

			return array(
				'ts'        => sprintf( '%s %02d:%02d:%02d', $ctx['date'], intdiv( $second, 3600 ), intdiv( $second % 3600, 60 ), $second % 60 ),
				'day'       => $ctx['date'],
				'visitor'   => $ctx['visitor'],
				'visit'     => $visit,
				'event'     => $event,
				'path'      => $target[0],
				'post_id'   => $target[1],
				'post_type' => $target[2],
				'is_404'    => $target[3],
				'target'    => $value,
				'source'    => $source[0],
				'medium'    => $source[1],
				'campaign'  => $source[2],
				'device'    => $ctx['client'][0],
				'browser'   => $ctx['client'][1],
				'os'        => $ctx['client'][2],
			);
		}

		/**
		 * Visitors for one day.
		 *
		 * @param int      $index      Day number, 0 = oldest.
		 * @param int      $days       Number of days.
		 * @param DateTimeImmutable $date The day (site time).
		 * @param int      $limit      Last second of the day to use (86399, or "now" for today).
		 * @param bool     $campaign   Whether the campaign runs.
		 * @return array Rows.
		 */
		public function day( $index, $days, DateTimeImmutable $date, $limit, $campaign ) {
			$trend    = 0.82 + 0.36 * ( $days > 1 ? $index / ( $days - 1 ) : 1 );
			$visitors = 105 * self::WEEKDAY[ (int) $date->format( 'N' ) ] * $trend * ( 0.88 + 0.24 * mt_rand( 0, 1000 ) / 1000 );
			$visitors = (int) round( max( 40, min( 160, $visitors ) ) );
			if ( $limit < 86399 ) { // Today: only the share of the day that has passed.
				$all     = array_sum( self::HOURS );
				$passed  = 0;
				foreach ( self::HOURS as $hour => $weight ) {
					$passed += $weight * max( 0, min( 1, ( $limit - $hour * 3600 ) / 3600 ) );
				}
				$visitors = max( 2, (int) round( $visitors * $passed / $all ) );
			}

			$out = array();
			for ( $v = 0; $v < $visitors; $v++ ) {
				$ctx    = array(
					'date'     => $date->format( 'Y-m-d' ),
					'visitor'  => bin2hex( random_bytes( 8 ) ),
					'client'   => $this->client(),
					'campaign' => $campaign,
					'limit'    => $limit,
				);
				list( $rows, $end ) = $this->visit( $ctx, $this->start_second( $limit ) );
				$out                = array_merge( $out, $rows );
				++$this->count['visits'];

				// Some visitors come back later the same day (a new visit after 30+ minutes).
				$again = $end + mt_rand( 35 * 60, 5 * 3600 );
				if ( $this->chance( 0.08 ) && $again < $limit - 60 ) {
					list( $rows ) = $this->visit( $ctx, $again );
					$out          = array_merge( $out, $rows );
					++$this->count['visits'];
				}
				++$this->count['visitors'];
			}

			return $out;
		}
	}
}

global $wpdb;

if ( 0 === strpos( $wpdb->prefix, 'fatest_' ) ) {
	WP_CLI::error( 'Refusing to run on the test sandbox prefix.' );
}

foreach ( array( 'clock', 'schema', 'stats' ) as $free_analytics_file ) {
	require_once dirname( __DIR__, 2 ) . '/inc/' . $free_analytics_file . '.php';
}
$free_analytics_table = Free_Analytics_Schema::table();
if ( $free_analytics_table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $free_analytics_table ) ) ) ) {
	Free_Analytics_Schema::maybe_migrate(); // Same as activating the plugin would do.
}
if ( $free_analytics_table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $free_analytics_table ) ) ) ) {
	WP_CLI::error( 'The table ' . $free_analytics_table . ' does not exist and could not be created.' );
}

$free_analytics_arg = isset( $args[0] ) ? strtolower( (string) $args[0] ) : '';
if ( '' !== $free_analytics_arg && 'clean' !== $free_analytics_arg && ! ctype_digit( $free_analytics_arg ) ) {
	WP_CLI::error( 'Usage: seed.php [days|clean]' );
}

// Remove an earlier seed first (also what "clean" does).
$free_analytics_removed = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$free_analytics_table} WHERE visit LIKE %s", $wpdb->esc_like( FA_Seed::MARK ) . '%' ) ); // phpcs:ignore WordPress.DB
if ( 'clean' === $free_analytics_arg ) {
	WP_CLI::success( sprintf( 'Removed %d seeded rows. Rows left in %s: %d.', $free_analytics_removed, $free_analytics_table, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$free_analytics_table}" ) ) ); // phpcs:ignore WordPress.DB
	return;
}
if ( $free_analytics_removed ) {
	WP_CLI::log( sprintf( 'Replaced the earlier seed (%d rows removed).', $free_analytics_removed ) );
}

$free_analytics_days = '' === $free_analytics_arg ? 60 : max( 1, min( 400, (int) $free_analytics_arg ) );
$free_analytics_zone = wp_timezone();
$free_analytics_now  = new DateTimeImmutable( 'now', $free_analytics_zone );
$free_analytics_today = $free_analytics_now->setTime( 0, 0, 0 );
$free_analytics_nowsec = (int) $free_analytics_now->format( 'G' ) * 3600 + (int) $free_analytics_now->format( 'i' ) * 60 + (int) $free_analytics_now->format( 's' );

$free_analytics_seed = new FA_Seed();
$free_analytics_pool = $free_analytics_seed->pool();
if ( ! $free_analytics_pool['shop'] ) {
	WP_CLI::warning( 'No published posts found: seeding pages only.' );
}
WP_CLI::log( sprintf( 'Seeding %d days to %s (site time, now %s) from %d posts and %d pages.', $free_analytics_days, $free_analytics_table, $free_analytics_now->format( 'Y-m-d H:i' ), $free_analytics_pool['shop'], $free_analytics_pool['pages'] ) );

// The Facebook campaign runs for about a fifth of the period, from a third of the way in, with a day off every fifth day.
$free_analytics_c_start = (int) floor( $free_analytics_days * 0.35 );
$free_analytics_c_end   = $free_analytics_c_start + max( 5, (int) round( $free_analytics_days * 0.22 ) );

$free_analytics_formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
$free_analytics_total   = 0;
$free_analytics_failed  = 0;

for ( $free_analytics_i = 0; $free_analytics_i < $free_analytics_days; $free_analytics_i++ ) {
	$free_analytics_date     = $free_analytics_today->modify( '-' . ( $free_analytics_days - 1 - $free_analytics_i ) . ' days' );
	$free_analytics_is_today = $free_analytics_date->format( 'Y-m-d' ) === $free_analytics_today->format( 'Y-m-d' );
	$free_analytics_limit    = $free_analytics_is_today ? $free_analytics_nowsec - 30 : 86399;
	if ( $free_analytics_limit < 120 ) {
		continue; // Just after midnight: nothing to show for today yet.
	}
	$free_analytics_campaign = $free_analytics_i >= $free_analytics_c_start && $free_analytics_i < $free_analytics_c_end && 4 !== $free_analytics_i % 5;
	$free_analytics_rows     = $free_analytics_seed->day( $free_analytics_i, $free_analytics_days, $free_analytics_date, $free_analytics_limit, $free_analytics_campaign );

	$wpdb->query( 'START TRANSACTION' );
	foreach ( $free_analytics_rows as $free_analytics_row ) {
		if ( false === $wpdb->insert( $free_analytics_table, $free_analytics_row, $free_analytics_formats ) ) { // phpcs:ignore WordPress.DB
			++$free_analytics_failed;
			if ( $free_analytics_failed < 4 ) {
				WP_CLI::warning( 'Insert failed: ' . $wpdb->last_error );
			}
		}
	}
	$wpdb->query( 'COMMIT' );

	++$free_analytics_seed->count['days'];
	$free_analytics_total += count( $free_analytics_rows );
	if ( $free_analytics_is_today ) {
		$free_analytics_seed->count['today_rows'] = count( $free_analytics_rows );
	}
}

if ( $free_analytics_failed ) {
	WP_CLI::error( sprintf( '%d of %d inserts failed.', $free_analytics_failed, $free_analytics_total ) );
}

$free_analytics_c = $free_analytics_seed->count;
WP_CLI::log( sprintf( 'Days: %d (%s .. %s)', $free_analytics_c['days'], $free_analytics_today->modify( '-' . ( $free_analytics_days - 1 ) . ' days' )->format( 'Y-m-d' ), $free_analytics_today->format( 'Y-m-d' ) ) );
WP_CLI::log( sprintf( 'Visitor-days: %d, visits: %d', $free_analytics_c['visitors'], $free_analytics_c['visits'] ) );
WP_CLI::log( sprintf( 'Page views: %d (of which 404: %d)', $free_analytics_c['pageview'], $free_analytics_c['not_found'] ) );
WP_CLI::log( sprintf( 'Events: tel %d, email %d, map %d, form %d', $free_analytics_c['tel'], $free_analytics_c['email'], $free_analytics_c['map'], $free_analytics_c['form'] ) );
WP_CLI::log( sprintf( 'Today (until %s): %d rows', $free_analytics_now->format( 'H:i' ), $free_analytics_c['today_rows'] ) );
WP_CLI::success( sprintf( 'Inserted %d rows into %s. Remove them with: seed.php clean', $free_analytics_total, $free_analytics_table ) );
