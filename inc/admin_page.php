<?php
/**
 * Free Analytics - dashboard
 *
 * wp-admin -> Statistics: period presets and a custom range, KPI tiles with the change against
 * the previous period, a column chart of visitors, and tables for top content, pages, sources,
 * campaigns, contacts, devices, browsers, systems and missing pages. Plus a small widget on the
 * wp-admin home screen. Server-rendered; admin.js only adds the chart tooltip.
 *
 * Strings are English and translated through the "free-analytics" text domain (languages/).
 * Dates use wp_date() and numbers number_format_i18n(), so they follow the admin language.
 *
 * @package Free_Analytics
 * @subpackage Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Free_Analytics_Admin
 */
class Free_Analytics_Admin {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const SLUG = 'free-analytics';

	/**
	 * Hook suffix of the page.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Registers the hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_widget' ) );
	}

	/**
	 * Capability needed to see the statistics.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability needed to see the statistics.
		 *
		 * @since 1.0.0
		 * @param string $capability Default manage_options.
		 */
		return (string) apply_filters( 'free_analytics_capability', 'manage_options' );
	}

	/**
	 * Adds the top-level menu "Statistics".
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function menu() {
		self::$hook = (string) add_menu_page(
			__( 'Visitor statistics', 'free-analytics' ),
			__( 'Statistics', 'free-analytics' ),
			self::capability(),
			self::SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-chart-bar',
			3
		);
	}

	/**
	 * Loads the styles on the page and the wp-admin home (widget), the script on the page only.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function assets( $hook_suffix ) {
		$on_page = '' !== self::$hook && self::$hook === $hook_suffix;
		if ( ! $on_page && 'index.php' !== $hook_suffix ) {
			return;
		}

		$css = FREE_ANALYTICS_DIR . 'assets/css/admin.css';
		wp_enqueue_style( 'free-analytics-admin', plugins_url( 'assets/css/admin.css', FREE_ANALYTICS_FILE ), array(), (string) filemtime( $css ) );

		if ( $on_page ) {
			$js = FREE_ANALYTICS_DIR . 'assets/js/admin.js';
			wp_enqueue_script( 'free-analytics-admin', plugins_url( 'assets/js/admin.js', FREE_ANALYTICS_FILE ), array(), (string) filemtime( $js ), true );
		}
	}

	/* --------------------------------------------------------------------- period */

	/**
	 * Preset periods: key => label.
	 *
	 * @since 1.0.0
	 * @return array<string,string>
	 */
	public static function presets() {
		return array(
			'today'     => __( 'Today', 'free-analytics' ),
			'yesterday' => __( 'Yesterday', 'free-analytics' ),
			'7d'        => __( '7 days', 'free-analytics' ),
			'30d'       => __( '30 days', 'free-analytics' ),
			'month'     => __( 'This month', 'free-analytics' ),
			'lastmonth' => __( 'Last month', 'free-analytics' ),
			'12m'       => __( '12 months', 'free-analytics' ),
		);
	}

	/**
	 * The selected period and the previous period of the same length.
	 *
	 * A custom range (from/to) wins over a preset; it is clamped to today and to at most 3 years.
	 *
	 * @since 1.0.0
	 * @param array $query Query arguments (period, from, to).
	 * @return array{key:string,from:string,to:string,days:int,prev_from:string,prev_to:string}
	 */
	public static function period( $query ) {
		$today = Free_Analytics_Clock::today();
		$key   = isset( $query['period'] ) ? sanitize_key( (string) $query['period'] ) : '';
		$from  = isset( $query['from'] ) ? (string) $query['from'] : '';
		$to    = isset( $query['to'] ) ? (string) $query['to'] : '';

		if ( ! isset( self::presets()[ $key ] ) && Free_Analytics_Clock::is_date( $from ) && Free_Analytics_Clock::is_date( $to ) ) {
			$key = 'custom';
			if ( $from > $to ) {
				list( $from, $to ) = array( $to, $from );
			}
			$to   = min( $to, $today );
			$from = min( $from, $to );
			$from = max( $from, Free_Analytics_Clock::add_days( $to, -1095 ) );
		} else {
			$key  = isset( self::presets()[ $key ] ) ? $key : '30d';
			$now  = Free_Analytics_Clock::now();
			$to   = $today;
			$from = $today;
			switch ( $key ) {
				case 'yesterday':
					$from = Free_Analytics_Clock::add_days( $today, -1 );
					$to   = $from;
					break;
				case '7d':
					$from = Free_Analytics_Clock::add_days( $today, -6 );
					break;
				case '30d':
					$from = Free_Analytics_Clock::add_days( $today, -29 );
					break;
				case 'month':
					$from = $now->format( 'Y-m-01' );
					break;
				case 'lastmonth':
					$from = $now->modify( 'first day of last month' )->format( 'Y-m-d' );
					$to   = $now->modify( 'last day of last month' )->format( 'Y-m-d' );
					break;
				case '12m':
					$from = Free_Analytics_Clock::add_days( $now->modify( '-1 year' )->format( 'Y-m-d' ), 1 );
					break;
			}
		}

		$days    = (int) ( new DateTimeImmutable( $from ) )->diff( new DateTimeImmutable( $to ) )->days + 1;
		$prev_to = Free_Analytics_Clock::add_days( $from, -1 );

		return array(
			'key'       => $key,
			'from'      => $from,
			'to'        => $to,
			'days'      => $days,
			'prev_from' => Free_Analytics_Clock::add_days( $prev_to, 1 - $days ),
			'prev_to'   => $prev_to,
		);
	}

	/* --------------------------------------------------------------------- formatting */

	/**
	 * Number in the admin language's format.
	 *
	 * @since 1.0.0
	 * @param int|float $value    Number.
	 * @param int       $decimals Decimals.
	 * @return string
	 */
	public static function num( $value, $decimals = 0 ) {
		return number_format_i18n( (float) $value, $decimals );
	}

	/**
	 * A date in the admin language.
	 *
	 * @since 1.0.0
	 * @param string $day    Y-m-d.
	 * @param string $format PHP date format.
	 * @return string
	 */
	public static function date( $day, $format ) {
		return wp_date( $format, Free_Analytics_Clock::noon( $day ), Free_Analytics_Clock::zone() );
	}

	/**
	 * Label of a period: one day with its weekday, or "from – to".
	 *
	 * @since 1.0.0
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return string
	 */
	public static function range_label( $from, $to ) {
		/* translators: date format of a single day, see https://www.php.net/manual/datetime.format.php */
		$day_format = __( 'l, F j, Y', 'free-analytics' );
		if ( $from === $to ) {
			return self::date( $from, $day_format );
		}
		/* translators: date format inside a period, see https://www.php.net/manual/datetime.format.php */
		$format = __( 'F j, Y', 'free-analytics' );

		/* translators: 1: first day, 2: last day */
		return sprintf( __( '%1$s – %2$s', 'free-analytics' ), self::date( $from, $format ), self::date( $to, $format ) );
	}

	/**
	 * Display name of a path: page title when known, "Home page" for "/".
	 *
	 * @since 1.0.0
	 * @param string $path    Path.
	 * @param int    $post_id Post ID or 0.
	 * @return string
	 */
	public static function page_title( $path, $post_id = 0 ) {
		if ( '/' === $path ) {
			return __( 'Home page', 'free-analytics' );
		}
		if ( $post_id ) {
			$title = get_the_title( $post_id );
			if ( '' !== $title ) {
				return wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) );
			}
		}

		return $path;
	}

	/**
	 * Front-end URL of a stored (decoded) path.
	 *
	 * @since 1.0.0
	 * @param string $path Path.
	 * @return string
	 */
	public static function path_url( $path ) {
		return home_url( implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) );
	}

	/**
	 * Change against the previous period.
	 *
	 * @since 1.0.0
	 * @param int|float|null $current   Current value.
	 * @param int|float|null $previous  Previous value.
	 * @param bool           $up_good   Whether a rise is good.
	 * @param bool           $as_points Show the difference in percentage points (for rates).
	 * @return array{text:string,class:string}
	 */
	public static function delta( $current, $previous, $up_good = true, $as_points = false ) {
		if ( null === $current || null === $previous ) {
			return array(
				'text'  => '—',
				'class' => 'is-flat',
			);
		}
		if ( $as_points ) {
			$diff = round( $current - $previous, 1 );
			/* translators: %s: difference in percentage points */
			$text = sprintf( __( '%s pp', 'free-analytics' ), self::num( abs( $diff ), 1 ) );
		} else {
			if ( 0 == $previous ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- int or float zero.
				return array(
					'text'  => $current > 0 ? __( 'new', 'free-analytics' ) : '—',
					'class' => $current > 0 ? ( $up_good ? 'is-good' : 'is-bad' ) : 'is-flat',
				);
			}
			$diff = round( 100 * ( $current - $previous ) / $previous );
			$text = self::num( abs( $diff ) ) . '%';
		}
		if ( 0 == $diff ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
			return array(
				'text'  => '0%',
				'class' => 'is-flat',
			);
		}
		$up = $diff > 0;

		return array(
			'text'  => ( $up ? '▲ +' : '▼ −' ) . $text,
			'class' => $up === $up_good ? 'is-good' : 'is-bad',
		);
	}

	/* --------------------------------------------------------------------- page */

	/**
	 * Renders the dashboard page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'free-analytics' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter in the URL.
		$period = self::period( wp_unslash( $_GET ) );
		$stats  = new Free_Analytics_Stats( $period['from'], $period['to'] );
		$prev   = new Free_Analytics_Stats( $period['prev_from'], $period['prev_to'] );
		$cur_t  = $stats->totals();
		$prev_t = $prev->totals();
		$online = Free_Analytics_Stats::online();

		echo '<div class="wrap fran">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Visitor statistics', 'free-analytics' ) . '</h1>';
		printf(
			'<span class="fran-online%1$s"><span class="fran-online__dot" aria-hidden="true"></span>%2$s</span>',
			$online ? ' is-live' : '',
			/* translators: %s: number of visitors in the last 5 minutes */
			esc_html( sprintf( __( 'Online now: %s', 'free-analytics' ), self::num( $online ) ) )
		);
		echo '<hr class="wp-header-end">';

		self::render_diagnostics();
		self::render_filters( $period );

		printf(
			'<p class="fran-period">%1$s <span>%2$s</span></p>',
			esc_html( self::range_label( $period['from'], $period['to'] ) ),
			/* translators: %s: the previous period */
			esc_html( sprintf( __( 'Change compared with %s', 'free-analytics' ), self::range_label( $period['prev_from'], $period['prev_to'] ) ) )
		);

		self::render_kpis( $cur_t, $prev_t, $period );
		self::render_chart( $stats->series() );

		echo '<div class="fran-grid">';
		foreach ( Free_Analytics_Stats::featured_post_types() as $post_type ) {
			$rows = $stats->top_posts( $post_type, 10 );
			if ( $rows ) {
				self::render_top_posts( $post_type, $rows );
			}
		}
		self::render_top_pages( $stats->top_pages( 10 ) );
		self::render_sources( $stats->sources( 10 ) );
		self::render_contacts( $stats->contacts() );
		$campaigns = $stats->campaigns( 10 );
		if ( $campaigns ) {
			self::render_campaigns( $campaigns );
		}
		$missing = $stats->not_found( 10 );
		if ( $missing ) {
			self::render_not_found( $missing );
		}
		echo '</div>';

		echo '<div class="fran-grid fran-grid--3">';
		self::render_breakdown( __( 'Devices', 'free-analytics' ), $stats->breakdown( 'device' ), Free_Analytics_Classify::device_labels() );
		self::render_breakdown( __( 'Browsers', 'free-analytics' ), $stats->breakdown( 'browser' ) );
		self::render_breakdown( __( 'Operating systems', 'free-analytics' ), $stats->breakdown( 'os' ) );
		echo '</div>';

		$months = max( 1, (int) apply_filters( 'free_analytics_retention_months', Free_Analytics_Cron::RETENTION_MONTHS ) );
		echo '<p class="fran-footnote">' . esc_html(
			sprintf(
				/* translators: %d: months the data is kept */
				_n(
					'These statistics use no cookies and store no IP addresses. A visitor is recognised only within one day, by an anonymous code that changes every night, so for a period of several days "Visitors" is the sum of each day\'s unique visitors. Data is kept for %d month. Logged-in users are not counted.',
					'These statistics use no cookies and store no IP addresses. A visitor is recognised only within one day, by an anonymous code that changes every night, so for a period of several days "Visitors" is the sum of each day\'s unique visitors. Data is kept for %d months. Logged-in users are not counted.',
					$months,
					'free-analytics'
				),
				$months
			)
		) . '</p>';
		echo '</div>';
	}

	/**
	 * Warns when PHP sees a proxy address instead of the visitor's, which would merge all visitors.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function render_diagnostics() {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- only inspected, never printed.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$xff = ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		// phpcs:enable
		$public = (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		if ( $public || ! $xff ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'The web server passes the address of a proxy to WordPress instead of the visitor\'s (REMOTE_ADDR is an internal address and the request has X-Forwarded-For). All visitors would be counted as one. Configure the server to pass the real client IP (for example mod_remoteip or the nginx real_ip module).', 'free-analytics' ) . '</p></div>';
	}

	/**
	 * Preset links and the custom range form.
	 *
	 * @since 1.0.0
	 * @param array $period Period from period().
	 * @return void
	 */
	private static function render_filters( $period ) {
		$base  = admin_url( 'admin.php?page=' . self::SLUG );
		$today = Free_Analytics_Clock::today();

		echo '<div class="fran-filters">';
		echo '<nav class="fran-presets" aria-label="' . esc_attr__( 'Period', 'free-analytics' ) . '">';
		foreach ( self::presets() as $key => $label ) {
			$active = $key === $period['key'];
			printf(
				'<a href="%1$s" class="fran-preset%2$s"%3$s>%4$s</a>',
				esc_url( add_query_arg( 'period', $key, $base ) ),
				$active ? ' is-active' : '',
				$active ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		echo '<form class="fran-range" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
		printf(
			'<label><span>%1$s</span><input type="date" name="from" value="%2$s" max="%3$s" required></label>',
			esc_html_x( 'from', 'date range', 'free-analytics' ),
			esc_attr( $period['from'] ),
			esc_attr( $today )
		);
		printf(
			'<label><span>%1$s</span><input type="date" name="to" value="%2$s" max="%3$s" required></label>',
			esc_html_x( 'to', 'date range', 'free-analytics' ),
			esc_attr( $period['to'] ),
			esc_attr( $today )
		);
		printf(
			'<button type="submit" class="button%1$s">%2$s</button>',
			'custom' === $period['key'] ? ' button-primary' : '',
			esc_html__( 'Show', 'free-analytics' )
		);
		echo '</form>';
		echo '</div>';
	}

	/**
	 * KPI tiles.
	 *
	 * @since 1.0.0
	 * @param array $cur    Current totals.
	 * @param array $prev   Previous totals.
	 * @param array $period Period.
	 * @return void
	 */
	private static function render_kpis( $cur, $prev, $period ) {
		$tel   = isset( $cur['events']['tel'] ) ? $cur['events']['tel'] : 0;
		$tel_p = isset( $prev['events']['tel'] ) ? $prev['events']['tel'] : 0;

		$tiles = array(
			array( __( 'Visitors', 'free-analytics' ), $cur['visitors'], $prev['visitors'], 0, true, false, $period['days'] > 1 ? __( 'sum of each day\'s unique visitors', 'free-analytics' ) : __( 'unique for the day', 'free-analytics' ) ),
			array( __( 'Visits', 'free-analytics' ), $cur['visits'], $prev['visits'], 0, true, false, __( 'a new one after 30 minutes of inactivity', 'free-analytics' ) ),
			array( __( 'Page views', 'free-analytics' ), $cur['pageviews'], $prev['pageviews'], 0, true, false, '' ),
			array( __( 'Pages per visit', 'free-analytics' ), $cur['pages_per_visit'], $prev['pages_per_visit'], 1, true, false, '' ),
			array( __( 'Bounce rate', 'free-analytics' ), $cur['bounce_rate'], $prev['bounce_rate'], 1, false, true, __( 'visits with a single page', 'free-analytics' ) ),
			array( __( 'Phone calls', 'free-analytics' ), $tel, $tel_p, 0, true, false, __( 'clicks on phone numbers', 'free-analytics' ) ),
			array( __( 'Form submissions', 'free-analytics' ), $cur['forms'], $prev['forms'], 0, true, false, __( 'successfully sent forms', 'free-analytics' ) ),
			array( __( 'All contacts', 'free-analytics' ), $cur['contacts'], $prev['contacts'], 0, true, false, __( 'phone, email, map, Viber, WhatsApp', 'free-analytics' ) ),
		);

		echo '<div class="fran-kpis">';
		foreach ( $tiles as $tile ) {
			list( $label, $value, $before, $decimals, $up_good, $points, $note ) = $tile;
			$delta = self::delta( $value, $before, $up_good, $points );
			$shown = null === $value ? '—' : self::num( $value, $decimals ) . ( $points ? '%' : '' );

			echo '<div class="fran-kpi">';
			echo '<div class="fran-kpi__label">' . esc_html( $label ) . '</div>';
			echo '<div class="fran-kpi__value">' . esc_html( $shown ) . '</div>';
			echo '<div class="fran-kpi__delta ' . esc_attr( $delta['class'] ) . '">' . esc_html( $delta['text'] ) . '</div>';
			if ( '' !== $note ) {
				echo '<div class="fran-kpi__note">' . esc_html( $note ) . '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Axis label and tooltip title of a series point.
	 *
	 * @since 1.0.0
	 * @param string $unit hour, day or month.
	 * @param string $key  Point key.
	 * @return array{0:string,1:string} Short label, long label.
	 */
	private static function point_labels( $unit, $key ) {
		if ( 'hour' === $unit ) {
			$h = (int) $key;
			return array( sprintf( '%02d', $h ), sprintf( '%02d:00 – %02d:00', $h, ( $h + 1 ) % 24 ) );
		}
		if ( 'month' === $unit ) {
			$day = $key . '-15';
			/* translators: short month on the chart axis, see https://www.php.net/manual/datetime.format.php */
			$short = self::date( $day, '01' === substr( $key, 5, 2 ) ? __( 'M Y', 'free-analytics' ) : __( 'M', 'free-analytics' ) );
			/* translators: month in the chart tooltip, see https://www.php.net/manual/datetime.format.php */
			return array( $short, self::date( $day, __( 'F Y', 'free-analytics' ) ) );
		}

		/* translators: day on the chart axis, see https://www.php.net/manual/datetime.format.php */
		return array( self::date( $key, __( 'n/j', 'free-analytics' ) ), self::date( $key, __( 'l, F j, Y', 'free-analytics' ) ) );
	}

	/**
	 * A round axis maximum and step (1, 2 or 5 x 10^n) for about four gridlines.
	 *
	 * @since 1.0.0
	 * @param int $max Largest value.
	 * @return array{0:int,1:int} Axis maximum, step.
	 */
	private static function nice_axis( $max ) {
		if ( $max <= 4 ) {
			return array( 4, 1 );
		}
		$raw  = $max / 4;
		$pow  = 10 ** floor( log10( $raw ) );
		$step = (int) $pow;
		foreach ( array( 1, 2, 5, 10 ) as $m ) {
			if ( $m * $pow >= $raw ) {
				$step = (int) ( $m * $pow );
				break;
			}
		}

		return array( (int) ( ceil( $max / $step ) * $step ), $step );
	}

	/**
	 * Column chart of visitors, with page views in the tooltip and a table view.
	 *
	 * @since 1.0.0
	 * @param array $series Series from Free_Analytics_Stats::series().
	 * @return void
	 */
	private static function render_chart( $series ) {
		$titles = array(
			'hour'  => __( 'Visitors by hour', 'free-analytics' ),
			'day'   => __( 'Visitors by day', 'free-analytics' ),
			'month' => __( 'Visitors by month', 'free-analytics' ),
		);
		$points = $series['points'];
		$count  = count( $points );
		$max    = 0;
		foreach ( $points as $p ) {
			$max = max( $max, $p['visitors'] );
		}
		list( $axis_max, $step ) = self::nice_axis( $max );
		$every                   = max( 1, (int) ceil( $count / 8 ) );
		$every_m                 = max( 1, (int) ceil( $count / 4 ) );

		echo '<section class="fran-card fran-card--chart">';
		echo '<h2>' . esc_html( $titles[ $series['unit'] ] ) . '</h2>';
		echo '<div class="fran-chart" data-fran-chart>';

		for ( $v = 0; $v <= $axis_max; $v += $step ) {
			printf(
				'<div class="fran-chart__y" style="bottom:%1$s%%"><span>%2$s</span></div>',
				esc_attr( (string) round( 100 * $v / $axis_max, 3 ) ),
				esc_html( self::num( $v ) )
			);
		}

		echo '<div class="fran-chart__bars">';
		foreach ( $points as $p ) {
			list( , $long ) = self::point_labels( $series['unit'], $p['key'] );
			$height         = $axis_max ? round( 100 * $p['visitors'] / $axis_max, 3 ) : 0;
			$visitors       = _n( 'visitor', 'visitors', $p['visitors'], 'free-analytics' );
			$views          = _n( 'page view', 'page views', $p['pageviews'], 'free-analytics' );
			printf(
				'<div class="fran-chart__slot" tabindex="0" role="img" aria-label="%1$s" data-title="%2$s" data-visitors="%3$s" data-visitors-label="%4$s" data-pageviews="%5$s" data-pageviews-label="%6$s"><i style="height:%7$s%%"></i></div>',
				esc_attr( $long . ': ' . self::num( $p['visitors'] ) . ' ' . $visitors . ', ' . self::num( $p['pageviews'] ) . ' ' . $views ),
				esc_attr( $long ),
				esc_attr( self::num( $p['visitors'] ) ),
				esc_attr( $visitors ),
				esc_attr( self::num( $p['pageviews'] ) ),
				esc_attr( $views ),
				esc_attr( (string) $height )
			);
		}
		echo '</div>';
		echo '<div class="fran-chart__tip" hidden></div>';
		echo '</div>';

		echo '<div class="fran-chart__x" aria-hidden="true">';
		foreach ( $points as $i => $p ) {
			list( $short ) = self::point_labels( $series['unit'], $p['key'] );
			$classes       = array();
			if ( 0 === $i % $every ) {
				$classes[] = 'is-shown';
			}
			if ( 0 === $i % $every_m ) {
				$classes[] = 'is-shown-m';
			}
			echo '<span class="' . esc_attr( implode( ' ', $classes ) ) . '">' . esc_html( $short ) . '</span>';
		}
		echo '</div>';

		echo '<details class="fran-tableview"><summary>' . esc_html__( 'Show as a table', 'free-analytics' ) . '</summary>';
		echo '<table class="widefat striped fran-table"><thead><tr><th>' . esc_html__( 'Period', 'free-analytics' ) . '</th><th class="num">' . esc_html__( 'Visitors', 'free-analytics' ) . '</th><th class="num">' . esc_html__( 'Page views', 'free-analytics' ) . '</th></tr></thead><tbody>';
		foreach ( $points as $p ) {
			list( , $long ) = self::point_labels( $series['unit'], $p['key'] );
			echo '<tr><td>' . esc_html( $long ) . '</td><td class="num">' . esc_html( self::num( $p['visitors'] ) ) . '</td><td class="num">' . esc_html( self::num( $p['pageviews'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></details>';
		echo '</section>';
	}

	/**
	 * Opens a card with a title.
	 *
	 * @since 1.0.0
	 * @param string $title Title.
	 * @param string $hint  Optional muted explanation.
	 * @return void
	 */
	private static function card_open( $title, $hint = '' ) {
		echo '<section class="fran-card"><h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $hint ) {
			echo '<p class="fran-hint">' . esc_html( $hint ) . '</p>';
		}
	}

	/**
	 * The "no data" line.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function empty_note() {
		echo '<p class="fran-empty">' . esc_html__( 'No data for the selected period.', 'free-analytics' ) . '</p>';
	}

	/**
	 * Table header row.
	 *
	 * @since 1.0.0
	 * @param array  $columns Label => is numeric.
	 * @param string $class   Extra table class.
	 * @return void
	 */
	private static function thead( $columns, $class = '' ) {
		echo '<table class="widefat striped fran-table' . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '"><thead><tr>';
		foreach ( $columns as $label => $numeric ) {
			echo '<th' . ( $numeric ? ' class="num"' : '' ) . '>' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
	}

	/**
	 * Link to a front-end path, with the path as a muted second line when a title is shown.
	 *
	 * @since 1.0.0
	 * @param string $path    Path.
	 * @param int    $post_id Post ID.
	 * @return string HTML.
	 */
	private static function page_cell( $path, $post_id = 0 ) {
		$title = self::page_title( $path, $post_id );
		$html  = '<a href="' . esc_url( self::path_url( $path ) ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a>';
		if ( $title !== $path ) {
			$html .= '<span class="fran-sub">' . esc_html( $path ) . '</span>';
		}

		return $html;
	}

	/**
	 * Most viewed posts of one post type, with thumbnail and contacts.
	 *
	 * @since 1.0.0
	 * @param string $post_type Post type.
	 * @param array  $rows      Rows from top_posts().
	 * @return void
	 */
	private static function render_top_posts( $post_type, $rows ) {
		$object = get_post_type_object( $post_type );
		$label  = $object ? $object->labels->name : $post_type;
		$single = $object ? $object->labels->singular_name : $post_type;

		self::card_open(
			/* translators: %s: post type name, plural (e.g. Posts, Products) */
			sprintf( __( 'Most viewed: %s', 'free-analytics' ), $label ),
			__( 'Contacts: phone, email, map and form submissions from the page.', 'free-analytics' )
		);
		_prime_post_caches( wp_list_pluck( $rows, 'post_id' ) );
		self::thead(
			array(
				$single                                => false,
				__( 'Views', 'free-analytics' )        => true,
				__( 'Visitors', 'free-analytics' )     => true,
				__( 'Contacts', 'free-analytics' )     => true,
			),
			'fran-table--posts'
		);
		foreach ( $rows as $row ) {
			$post = get_post( $row['post_id'] );
			echo '<tr><td><div class="fran-item">';
			if ( $post && $post->post_type === $post_type ) {
				$thumb = get_the_post_thumbnail(
					$post,
					'thumbnail',
					array(
						'class'   => 'fran-item__thumb',
						'loading' => 'lazy',
						'alt'     => '',
					)
				);
				echo $thumb ? $thumb : '<span class="fran-item__thumb is-empty"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated img tag.

				/**
				 * Filters the muted second line under a post in the "Most viewed" tables (e.g. a price).
				 *
				 * @since 1.0.0
				 * @param string  $subtitle Default ''.
				 * @param WP_Post $post     The post.
				 */
				$subtitle = (string) apply_filters( 'free_analytics_item_subtitle', '', $post );
				if ( 'publish' !== $post->post_status ) {
					$subtitle .= ( '' !== $subtitle ? ' · ' : '' ) . __( 'not published', 'free-analytics' );
				}
				echo '<div><a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">' . esc_html( self::page_title( $row['path'], $post->ID ) ) . '</a>';
				if ( '' !== $subtitle ) {
					echo '<span class="fran-sub">' . esc_html( wp_strip_all_tags( $subtitle ) ) . '</span>';
				}
				echo '</div>';
			} else {
				echo '<span class="fran-item__thumb is-empty"></span><div>' . esc_html( $row['path'] ) . '<span class="fran-sub">' . esc_html__( 'deleted', 'free-analytics' ) . '</span></div>';
			}
			echo '</div></td>';
			echo '<td class="num">' . esc_html( self::num( $row['views'] ) ) . '</td>';
			echo '<td class="num">' . esc_html( self::num( $row['visitors'] ) ) . '</td>';
			echo '<td class="num">' . esc_html( self::num( $row['contacts'] + $row['forms'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	/**
	 * Most viewed pages.
	 *
	 * @since 1.0.0
	 * @param array $rows Rows from top_pages().
	 * @return void
	 */
	private static function render_top_pages( $rows ) {
		self::card_open( __( 'Most viewed pages', 'free-analytics' ) );
		if ( ! $rows ) {
			self::empty_note();
			echo '</section>';
			return;
		}
		_prime_post_caches( array_filter( wp_list_pluck( $rows, 'post_id' ) ) );
		self::thead(
			array(
				__( 'Page', 'free-analytics' )     => false,
				__( 'Views', 'free-analytics' )    => true,
				__( 'Visitors', 'free-analytics' ) => true,
			)
		);
		foreach ( $rows as $row ) {
			echo '<tr><td>' . self::page_cell( $row['path'], $row['post_id'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in page_cell().
			echo '<td class="num">' . esc_html( self::num( $row['views'] ) ) . '</td>';
			echo '<td class="num">' . esc_html( self::num( $row['visitors'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	/**
	 * Display name of a source ('' = direct).
	 *
	 * @since 1.0.0
	 * @param string $source Stored source.
	 * @return string
	 */
	private static function source_label( $source ) {
		return '' === $source ? __( 'Direct', 'free-analytics' ) : $source;
	}

	/**
	 * Traffic sources.
	 *
	 * @since 1.0.0
	 * @param array $rows Rows from sources().
	 * @return void
	 */
	private static function render_sources( $rows ) {
		self::card_open( __( 'Traffic sources', 'free-analytics' ), __( '"Direct": typed address, bookmark, app, or a site that hides the referrer.', 'free-analytics' ) );
		if ( ! $rows ) {
			self::empty_note();
			echo '</section>';
			return;
		}
		self::thead(
			array(
				__( 'Source', 'free-analytics' )   => false,
				__( 'Visits', 'free-analytics' )   => true,
				__( 'Contacts', 'free-analytics' ) => true,
				__( 'Forms', 'free-analytics' )    => true,
			)
		);
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( self::source_label( $row['source'] ) ) . '</td>';
			echo '<td class="num">' . esc_html( self::num( $row['visits'] ) ) . '</td>';
			echo '<td class="num">' . esc_html( self::num( $row['contacts'] ) ) . '</td>';
			echo '<td class="num">' . esc_html( self::num( $row['forms'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	/**
	 * UTM campaigns.
	 *
	 * @since 1.0.0
	 * @param array $rows Rows from campaigns().
	 * @return void
	 */
	private static function render_campaigns( $rows ) {
		self::card_open( __( 'Campaigns (UTM)', 'free-analytics' ) );
		self::thead(
			array(
				__( 'Campaign', 'free-analytics' ) => false,
				__( 'Visits', 'free-analytics' )   => true,
				__( 'Contacts', 'free-analytics' ) => true,
				__( 'Forms', 'free-analytics' )    => true,
			)
		);
		foreach ( $rows as $row ) {
			$sub = self::source_label( $row['source'] ) . ( '' !== $row['medium'] ? ' / ' . $row['medium'] : '' );
			echo '<tr><td>' . esc_html( $row['campaign'] ) . '<span class="fran-sub">' . esc_html( $sub ) . '</span></td>';
			echo '<td class="num">' . esc_html( self::num( $row['visits'] ) ) . '</td>';
			echo '<td class="num">' . esc_html( self::num( $row['contacts'] ) ) . '</td>';
			echo '<td class="num">' . esc_html( self::num( $row['forms'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	/**
	 * Contacts and forms by type, with their top pages.
	 *
	 * @since 1.0.0
	 * @param array $rows Rows from contacts().
	 * @return void
	 */
	private static function render_contacts( $rows ) {
		self::card_open( __( 'Contacts', 'free-analytics' ), __( 'Clicks on contact links and sent forms, with the pages where they happen most.', 'free-analytics' ) );
		if ( ! $rows ) {
			self::empty_note();
			echo '</section>';
			return;
		}
		$labels = Free_Analytics_Classify::event_labels();
		self::thead(
			array(
				__( 'Type', 'free-analytics' )  => false,
				__( 'Count', 'free-analytics' ) => true,
			)
		);
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( isset( $labels[ $row['event'] ] ) ? $labels[ $row['event'] ] : $row['event'] );
			echo '<ul class="fran-pages">';
			foreach ( $row['pages'] as $page ) {
				echo '<li><a href="' . esc_url( self::path_url( $page['path'] ) ) . '" target="_blank" rel="noopener">' . esc_html( $page['path'] ) . '</a> <span>' . esc_html( self::num( $page['count'] ) ) . '</span></li>';
			}
			echo '</ul></td><td class="num">' . esc_html( self::num( $row['count'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	/**
	 * Missing pages (404).
	 *
	 * @since 1.0.0
	 * @param array $rows Rows from not_found().
	 * @return void
	 */
	private static function render_not_found( $rows ) {
		self::card_open( __( 'Missing pages (404)', 'free-analytics' ), __( 'Addresses that visitors open but that do not exist, for example removed content or broken links.', 'free-analytics' ) );
		self::thead(
			array(
				__( 'Address', 'free-analytics' ) => false,
				__( 'Views', 'free-analytics' )   => true,
			)
		);
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( $row['path'] ) . '</td><td class="num">' . esc_html( self::num( $row['views'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	/**
	 * Share of visitors by device, browser or OS as horizontal bars.
	 *
	 * @since 1.0.0
	 * @param string $title  Card title.
	 * @param array  $rows   Rows from breakdown().
	 * @param array  $labels Optional key => label.
	 * @return void
	 */
	private static function render_breakdown( $title, $rows, $labels = array() ) {
		self::card_open( $title );
		if ( ! $rows ) {
			self::empty_note();
			echo '</section>';
			return;
		}
		$total = array_sum( wp_list_pluck( $rows, 'visitors' ) );
		$shown = array_slice( $rows, 0, 6 );
		$rest  = array_sum( wp_list_pluck( array_slice( $rows, 6 ), 'visitors' ) );
		if ( $rest ) {
			$shown[] = array(
				'key'      => 'other',
				'visitors' => $rest,
			);
		}

		echo '<ul class="fran-bars">';
		foreach ( $shown as $row ) {
			$key   = (string) $row['key'];
			$name  = isset( $labels[ $key ] ) ? $labels[ $key ] : ( 'other' === $key || '' === $key ? __( 'Other', 'free-analytics' ) : $key );
			$share = $total ? 100 * $row['visitors'] / $total : 0;
			printf(
				'<li><span class="fran-bars__name">%1$s</span><span class="fran-bars__val">%2$s%% <small>(%3$s)</small></span><span class="fran-bars__track"><span style="width:%4$s%%"></span></span></li>',
				esc_html( $name ),
				esc_html( self::num( $share, $share < 10 ? 1 : 0 ) ),
				esc_html( self::num( $row['visitors'] ) ),
				esc_attr( (string) round( $share, 2 ) )
			);
		}
		echo '</ul></section>';
	}

	/* --------------------------------------------------------------------- widget */

	/**
	 * Registers the wp-admin home widget.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function dashboard_widget() {
		if ( current_user_can( self::capability() ) ) {
			wp_add_dashboard_widget( 'free_analytics', __( 'Visitor statistics', 'free-analytics' ), array( __CLASS__, 'render_widget' ) );
		}
	}

	/**
	 * Widget: today, last 7 days, contacts, online now.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_widget() {
		$today = Free_Analytics_Clock::today();
		$day   = ( new Free_Analytics_Stats( $today, $today ) )->totals();
		$week  = ( new Free_Analytics_Stats( Free_Analytics_Clock::add_days( $today, -6 ), $today ) )->totals();

		$views = static function ( $n ) {
			/* translators: %s: number of page views */
			return sprintf( _n( '%s page view', '%s page views', $n, 'free-analytics' ), self::num( $n ) );
		};

		$tiles = array(
			array( __( 'Visitors today', 'free-analytics' ), $day['visitors'], $views( $day['pageviews'] ) ),
			array( __( 'Visitors, 7 days', 'free-analytics' ), $week['visitors'], $views( $week['pageviews'] ) ),
			/* translators: %s: number of form submissions */
			array( __( 'Contacts, 7 days', 'free-analytics' ), $week['contacts'] + $week['forms'], sprintf( _n( '%s form submission', '%s form submissions', $week['forms'], 'free-analytics' ), self::num( $week['forms'] ) ) ),
		);

		echo '<div class="fran fran-widget"><div class="fran-widget__tiles">';
		foreach ( $tiles as $tile ) {
			echo '<div><span class="fran-kpi__label">' . esc_html( $tile[0] ) . '</span><strong>' . esc_html( self::num( $tile[1] ) ) . '</strong><span class="fran-kpi__note">' . esc_html( $tile[2] ) . '</span></div>';
		}
		echo '</div>';
		printf(
			'<p class="fran-widget__foot"><span>%1$s</span><a href="%2$s">%3$s</a></p>',
			/* translators: %s: number of visitors in the last 5 minutes */
			esc_html( sprintf( __( 'Online now: %s', 'free-analytics' ), self::num( Free_Analytics_Stats::online() ) ) ),
			esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
			esc_html__( 'View statistics →', 'free-analytics' )
		);
		echo '</div>';
	}
}
