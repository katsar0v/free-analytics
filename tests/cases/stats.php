<?php
/**
 * Stats cases: every read query against a hand-counted fixture.
 *
 * Fixture (site time, clock frozen at 2026-03-12 15:00:00), range 2026-03-10 .. 2026-03-12:
 *
 *   visit visitor day    source (medium/campaign)       device  browser   os       rows
 *   a1    a       03-10  Google                         mobile  Chrome    Android  pv mug, pv lamp, tel lamp
 *   b1    b       03-10  direct                         desktop Firefox   Windows  pv home                         (bounce)
 *   c1    c       03-10  Facebook                       mobile  Facebook  iOS      pv mug, form mug
 *   d1    d       03-10  Google                         desktop Chrome    Windows  pv /shop/discontinued-item/ 404  (bounce)
 *   a2    a2      03-11  direct                         desktop Chrome    macOS    pv mug, pv mug, pv contacts, tel contacts
 *   e1    e       03-11  newsletter (email/spring-sale) mobile  Safari    iOS      pv lamp, email lamp, map contacts, tel lamp
 *   f1    f       03-11  Google                         mobile  Chrome    Android  pv lamp                         (bounce)
 *   h0    h       03-12  Google                         desktop Edge      Windows  pv chair (09:00)                 (bounce)
 *   g1    g       03-12  Google                         mobile  Chrome    Android  pv mug (14:00), tel mug (14:01)
 *   i1    i       03-12  Instagram                      tablet  Safari    iOS      pv chair (14:31)                 (bounce)
 *   h1    h       03-12  direct                         desktop Edge      Windows  pv home (14:57), tel home, pv /shop/ (14:59)
 *   z1    z       03-09  Google                         mobile  Chrome    Android  pv mug   (outside the range)
 *
 * Pages: mug /shop/blue-mug/ (post 101), lamp /shop/desk-lamp/ (102), chair /shop/oak-chair/ (103), all type product;
 * home / (5), contacts /contacts/ (6), products /shop/ (7), all type page.
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Skips a case when the stats class is not written yet.
 *
 * @return string|null Skip marker, or null when the class exists.
 */
function fa_test_need( $class ) {
	return class_exists( $class ) ? null : FA_Test::skip( "$class does not exist yet" );
}

/**
 * Inserts the fixture described above.
 *
 * @return void
 */
function fa_test_stats_fixture() {
	$pages = array(
		'mug'      => array( '/shop/blue-mug/', 101, 'product', 0 ),
		'lamp'     => array( '/shop/desk-lamp/', 102, 'product', 0 ),
		'chair'     => array( '/shop/oak-chair/', 103, 'product', 0 ),
		'home'     => array( '/', 5, 'page', 0 ),
		'contacts' => array( '/contacts/', 6, 'page', 0 ),
		'shop'     => array( '/shop/', 7, 'page', 0 ),
		'gone'     => array( '/shop/discontinued-item/', 0, '', 1 ),
	);
	// visit => visitor key, source, medium, campaign, device, browser, os.
	$visits = array(
		'a1' => array( 'a', 'Google', '', '', 'mobile', 'Chrome', 'Android' ),
		'b1' => array( 'b', '', '', '', 'desktop', 'Firefox', 'Windows' ),
		'c1' => array( 'c', 'Facebook', '', '', 'mobile', 'Facebook', 'iOS' ),
		'd1' => array( 'd', 'Google', '', '', 'desktop', 'Chrome', 'Windows' ),
		'a2' => array( 'a2', '', '', '', 'desktop', 'Chrome', 'macOS' ),
		'e1' => array( 'e', 'newsletter', 'email', 'spring-sale', 'mobile', 'Safari', 'iOS' ),
		'f1' => array( 'f', 'Google', '', '', 'mobile', 'Chrome', 'Android' ),
		'h0' => array( 'h', 'Google', '', '', 'desktop', 'Edge', 'Windows' ),
		'g1' => array( 'g', 'Google', '', '', 'mobile', 'Chrome', 'Android' ),
		'i1' => array( 'i', 'Instagram', '', '', 'tablet', 'Safari', 'iOS' ),
		'h1' => array( 'h', '', '', '', 'desktop', 'Edge', 'Windows' ),
		'z1' => array( 'z', 'Google', '', '', 'mobile', 'Chrome', 'Android' ),
	);
	$rows = array(
		array( '2026-03-10 10:00:00', 'a1', 'pageview', 'mug', '' ),
		array( '2026-03-10 10:03:00', 'a1', 'pageview', 'lamp', '' ),
		array( '2026-03-10 10:04:00', 'a1', 'tel', 'lamp', '15550100' ),
		array( '2026-03-10 11:00:00', 'b1', 'pageview', 'home', '' ),
		array( '2026-03-10 12:00:00', 'c1', 'pageview', 'mug', '' ),
		array( '2026-03-10 12:02:00', 'c1', 'form', 'mug', 'abcdef' ),
		array( '2026-03-10 13:00:00', 'd1', 'pageview', 'gone', '' ),
		array( '2026-03-11 09:00:00', 'a2', 'pageview', 'mug', '' ),
		array( '2026-03-11 09:01:00', 'a2', 'pageview', 'mug', '' ),
		array( '2026-03-11 09:02:00', 'a2', 'pageview', 'contacts', '' ),
		array( '2026-03-11 09:03:00', 'a2', 'tel', 'contacts', '15550100' ),
		array( '2026-03-11 18:00:00', 'e1', 'pageview', 'lamp', '' ),
		array( '2026-03-11 18:01:00', 'e1', 'email', 'lamp', 'info@example.com' ),
		array( '2026-03-11 18:02:00', 'e1', 'map', 'contacts', 'Google Maps' ),
		array( '2026-03-11 18:03:00', 'e1', 'tel', 'lamp', '15550100' ),
		array( '2026-03-11 20:00:00', 'f1', 'pageview', 'lamp', '' ),
		array( '2026-03-12 09:00:00', 'h0', 'pageview', 'chair', '' ),
		array( '2026-03-12 14:00:00', 'g1', 'pageview', 'mug', '' ),
		array( '2026-03-12 14:01:00', 'g1', 'tel', 'mug', '15550100' ),
		array( '2026-03-12 14:31:00', 'i1', 'pageview', 'chair', '' ),
		array( '2026-03-12 14:57:00', 'h1', 'pageview', 'home', '' ),
		array( '2026-03-12 14:58:00', 'h1', 'tel', 'home', '15550100' ),
		array( '2026-03-12 14:59:00', 'h1', 'pageview', 'shop', '' ),
		array( '2026-03-09 10:00:00', 'z1', 'pageview', 'mug', '' ),
	);

	foreach ( $rows as $row ) {
		list( $ts, $visit, $event, $page, $target ) = $row;
		$v = $visits[ $visit ];
		$p = $pages[ $page ];
		FA_Sandbox::insert_hit(
			array(
				'ts'        => $ts,
				'visitor'   => substr( md5( 'visitor-' . $v[0] ), 0, 16 ),
				'visit'     => substr( md5( 'visit-' . $visit ), 0, 16 ),
				'event'     => $event,
				'path'      => $p[0],
				'post_id'   => $p[1],
				'post_type' => $p[2],
				'is_404'    => $p[3],
				'target'    => $target,
				'source'    => $v[1],
				'medium'    => $v[2],
				'campaign'  => $v[3],
				'device'    => $v[4],
				'browser'   => $v[5],
				'os'        => $v[6],
			)
		);
	}
	FA_Sandbox::freeze( '2026-03-12 15:00:00' );
}

/**
 * Stats for the main fixture range.
 *
 * @return Free_Analytics_Stats
 */
function fa_test_stats() {
	return new Free_Analytics_Stats( '2026-03-10', '2026-03-12' );
}

/**
 * Index a list of rows by one of its keys.
 *
 * @param array  $rows Rows.
 * @param string $key  Key.
 * @return array
 */
function fa_test_by( array $rows, $key ) {
	$out = array();
	foreach ( $rows as $row ) {
		$out[ $row[ $key ] ] = $row;
	}

	return $out;
}

FA_Test::add(
	'stats: totals() over three days',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();
		$t = fa_test_stats()->totals();

		FA_Test::eq( 10, $t['visitors'], 'visitors: 4 + 3 + 3 (the visitor with two visits counts once a day)' );
		FA_Test::eq( 11, $t['visits'], 'visits with a page view' );
		FA_Test::eq( 15, $t['pageviews'], 'page views (the 404 counts, the day outside the range does not)' );
		FA_Test::eq( 5, $t['bounces'], 'bounces: b1, d1, f1, h0, i1 (c1 has a page view plus a form: not a bounce)' );
		FA_Test::near( 45.45, $t['bounce_rate'], 0.6, 'bounce rate 5/11' );
		FA_Test::near( 1.36, $t['pages_per_visit'], 0.06, 'pages per visit 15/11' );
		FA_Test::eq( 5, $t['events']['tel'] ?? 0, 'tel events' );
		FA_Test::eq( 1, $t['events']['email'] ?? 0, 'email events' );
		FA_Test::eq( 1, $t['events']['map'] ?? 0, 'map events' );
		FA_Test::eq( 1, $t['events']['form'] ?? 0, 'form events' );
		FA_Test::eq( 0, $t['events']['viber'] ?? 0, 'viber events' );
		FA_Test::ok( ! isset( $t['events']['pageview'] ), 'events do not list page views' );
		FA_Test::eq( 7, $t['contacts'], 'contacts = tel + email + map + viber + whatsapp' );
		FA_Test::eq( 1, $t['forms'], 'forms' );
	}
);

FA_Test::add(
	'stats: totals() of a single day and of an empty range',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();

		$t = ( new Free_Analytics_Stats( '2026-03-11', '2026-03-11' ) )->totals();
		FA_Test::eq( array( 3, 3, 5, 1 ), array( $t['visitors'], $t['visits'], $t['pageviews'], $t['bounces'] ), '11 March: visitors, visits, page views, bounces' );
		FA_Test::near( 33.33, $t['bounce_rate'], 0.5, '11 March bounce rate' );
		FA_Test::near( 1.67, $t['pages_per_visit'], 0.05, '11 March pages per visit' );
		FA_Test::eq( array( 2, 1, 1 ), array( $t['events']['tel'] ?? 0, $t['events']['email'] ?? 0, $t['events']['map'] ?? 0 ), '11 March events' );
		FA_Test::eq( array( 4, 0 ), array( $t['contacts'], $t['forms'] ), '11 March contacts and forms' );

		$t = ( new Free_Analytics_Stats( '2026-02-01', '2026-02-02' ) )->totals();
		FA_Test::eq( array( 0, 0, 0, 0 ), array( $t['visitors'], $t['visits'], $t['pageviews'], $t['bounces'] ), 'empty range: zeros' );
		FA_Test::eq( null, $t['bounce_rate'], 'empty range: bounce rate is null' );
		FA_Test::eq( null, $t['pages_per_visit'], 'empty range: pages per visit is null' );
		FA_Test::eq( array( 0, 0 ), array( $t['contacts'], $t['forms'] ), 'empty range: contacts and forms' );
		FA_Test::ok( array() === array_filter( $t['events'] ), 'empty range: no events' );
	}
);

FA_Test::add(
	'stats: series() by day is zero-filled and counts a visitor once per day',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();
		FA_Sandbox::insert_hit( array( 'ts' => '2026-03-13 10:00:00', 'visitor' => 'ffffffffffffff01', 'visit' => 'eeeeeeeeeeeeee01' ) );

		$s = ( new Free_Analytics_Stats( '2026-03-10', '2026-03-12' ) )->series();
		FA_Test::eq( 'day', $s['unit'], 'three days: unit day' );
		FA_Test::eq(
			array(
				array( 'key' => '2026-03-10', 'visitors' => 4, 'pageviews' => 5 ),
				array( 'key' => '2026-03-11', 'visitors' => 3, 'pageviews' => 5 ),
				array( 'key' => '2026-03-12', 'visitors' => 3, 'pageviews' => 5 ),
			),
			$s['points'],
			'three days'
		);

		$s = ( new Free_Analytics_Stats( '2026-03-08', '2026-03-14' ) )->series();
		FA_Test::eq( 7, count( $s['points'] ), 'seven days, empty days filled' );
		FA_Test::eq(
			array( '2026-03-08' => array( 0, 0 ), '2026-03-09' => array( 1, 1 ), '2026-03-10' => array( 4, 5 ), '2026-03-11' => array( 3, 5 ), '2026-03-12' => array( 3, 5 ), '2026-03-13' => array( 1, 1 ), '2026-03-14' => array( 0, 0 ) ),
			array_combine( array_column( $s['points'], 'key' ), array_map( null, array_column( $s['points'], 'visitors' ), array_column( $s['points'], 'pageviews' ) ) ),
			'visitors and page views per day, including the day before and after the fixture'
		);

		$s = ( new Free_Analytics_Stats( '2026-02-10', '2026-03-11' ) )->series();
		FA_Test::eq( 'day', $s['unit'], '30 days: unit day' );
		FA_Test::eq( 30, count( $s['points'] ), '30 points' );
		FA_Test::eq( '2026-02-10', $s['points'][0]['key'], 'first key' );
		FA_Test::eq( '2026-03-11', $s['points'][29]['key'], 'last key' );
		FA_Test::eq( '2026-02-28', $s['points'][18]['key'], 'February ends on the 19th point' );
		FA_Test::eq( '2026-03-01', $s['points'][19]['key'], 'then March' );
	}
);

FA_Test::add(
	'stats: series() of one day is 24 hours, of a long range is months',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();

		$s = ( new Free_Analytics_Stats( '2026-03-11', '2026-03-11' ) )->series();
		FA_Test::eq( 'hour', $s['unit'], 'one day: unit hour' );
		FA_Test::eq( 24, count( $s['points'] ), '24 points' );
		FA_Test::eq( array_map( static function ( $h ) { return sprintf( '%02d', $h ); }, range( 0, 23 ) ), array_column( $s['points'], 'key' ), 'keys 00..23' );
		$by = fa_test_by( $s['points'], 'key' );
		FA_Test::eq( array( 1, 3 ), array( $by['09']['visitors'], $by['09']['pageviews'] ), '09:00 hour: a2 with three page views' );
		FA_Test::eq( array( 1, 1 ), array( $by['18']['visitors'], $by['18']['pageviews'] ), '18:00 hour: the contact events are not page views' );
		FA_Test::eq( array( 1, 1 ), array( $by['20']['visitors'], $by['20']['pageviews'] ), '20:00 hour' );
		FA_Test::eq( 5, array_sum( array_column( $s['points'], 'pageviews' ) ), 'all page views of the day' );
		FA_Test::eq( 0, $by['00']['pageviews'] + $by['12']['pageviews'] + $by['23']['pageviews'], 'other hours are zero' );

		FA_Sandbox::insert_hit( array( 'ts' => '2026-02-20 10:00:00', 'visitor' => 'ffffffffffffff01', 'visit' => 'eeeeeeeeeeeeee01' ) );
		FA_Sandbox::insert_hit( array( 'ts' => '2026-01-05 10:00:00', 'visitor' => 'ffffffffffffff02', 'visit' => 'eeeeeeeeeeeeee02' ) );
		$s = ( new Free_Analytics_Stats( '2026-01-01', '2026-06-30' ) )->series();
		FA_Test::eq( 'month', $s['unit'], '181 days: unit month' );
		FA_Test::eq( array( '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06' ), array_column( $s['points'], 'key' ), 'month keys' );
		FA_Test::eq( array( 1, 1, 11, 0, 0, 0 ), array_column( $s['points'], 'visitors' ), 'visitors per month (sum of the daily uniques)' );
		FA_Test::eq( array( 1, 1, 16, 0, 0, 0 ), array_column( $s['points'], 'pageviews' ), 'page views per month' );

		$s = ( new Free_Analytics_Stats( '2025-10-15', '2026-02-10' ) )->series();
		FA_Test::eq( array( '2025-10', '2025-11', '2025-12', '2026-01', '2026-02' ), array_column( $s['points'], 'key' ), 'months of a range that starts and ends mid-month' );

		$s = ( new Free_Analytics_Stats( '2026-03-20', '2026-03-22' ) )->series();
		FA_Test::eq( array( 0, 0, 0 ), array_column( $s['points'], 'pageviews' ), 'a range without data is all zeros' );
	}
);

FA_Test::add(
	'stats: top_pages() counts views and visitors, leaves out 404 pages, honours the limit',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();
		$pages = fa_test_stats()->top_pages( 100 );

		FA_Test::eq( 6, count( $pages ), 'six pages (the 404 path is not one of them)' );
		FA_Test::eq( array( 'path' => '/shop/blue-mug/', 'post_id' => 101, 'views' => 5, 'visitors' => 4 ), $pages[0], 'first: mug' );
		FA_Test::eq( array( 'path' => '/shop/desk-lamp/', 'post_id' => 102, 'views' => 3, 'visitors' => 3 ), $pages[1], 'second: lamp' );
		$by = fa_test_by( $pages, 'path' );
		FA_Test::eq( array( 2, 2, 5 ), array( $by['/shop/oak-chair/']['views'], $by['/shop/oak-chair/']['visitors'], $by['/']['post_id'] + 0 ), 'chair views and visitors; home post ID' );
		FA_Test::eq( array( 2, 2 ), array( $by['/']['views'], $by['/']['visitors'] ), 'home views and visitors' );
		FA_Test::eq( array( 1, 1 ), array( $by['/contacts/']['views'], $by['/shop/']['views'] ), 'single views' );
		FA_Test::ok( ! isset( $by['/shop/discontinued-item/'] ), '404 path excluded' );
		FA_Test::eq( array( 'path', 'post_id', 'views', 'visitors' ), array_keys( $pages[0] ), 'row keys' );

		FA_Test::eq( 2, count( fa_test_stats()->top_pages( 2 ) ), 'limit 2' );
		FA_Test::eq( '/shop/blue-mug/', fa_test_stats()->top_pages( 1 )[0]['path'], 'limit 1' );
		FA_Test::eq( array(), ( new Free_Analytics_Stats( '2026-02-01', '2026-02-02' ) )->top_pages( 10 ), 'empty range' );
	}
);

FA_Test::add(
	'stats: top_posts() counts views, visitors, contacts and forms per post',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();
		$products = fa_test_stats()->top_posts( 'product', 10 );

		FA_Test::eq(
			array(
				array( 'post_id' => 101, 'path' => '/shop/blue-mug/', 'views' => 5, 'visitors' => 4, 'contacts' => 1, 'forms' => 1 ),
				array( 'post_id' => 102, 'path' => '/shop/desk-lamp/', 'views' => 3, 'visitors' => 3, 'contacts' => 3, 'forms' => 0 ),
				array( 'post_id' => 103, 'path' => '/shop/oak-chair/', 'views' => 2, 'visitors' => 2, 'contacts' => 0, 'forms' => 0 ),
			),
			$products,
			'three products by views: pages and the 404 path are not products'
		);
		FA_Test::eq( 1, count( fa_test_stats()->top_posts( 'product', 1 ) ), 'limit' );
		FA_Test::eq( array(), ( new Free_Analytics_Stats( '2026-02-01', '2026-02-02' ) )->top_posts( 'product', 10 ), 'empty range' );
	}
);

FA_Test::add(
	'stats: sources() counts visits, contacts and forms per source (direct is the empty source)',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();
		$list = fa_test_stats()->sources( 10 );
		$by   = fa_test_by( $list, 'source' );

		FA_Test::eq( 5, count( $list ), 'five sources' );
		FA_Test::eq( 'Google', $list[0]['source'], 'Google is first' );
		FA_Test::eq( '', $list[1]['source'], 'direct is second' );
		FA_Test::eq( array( 'source' => 'Google', 'visits' => 5, 'contacts' => 2, 'forms' => 0 ), $by['Google'], 'Google' );
		FA_Test::eq( array( 'source' => '', 'visits' => 3, 'contacts' => 2, 'forms' => 0 ), $by[''], 'direct' );
		FA_Test::eq( array( 'source' => 'Facebook', 'visits' => 1, 'contacts' => 0, 'forms' => 1 ), $by['Facebook'], 'Facebook (the form)' );
		FA_Test::eq( array( 'source' => 'newsletter', 'visits' => 1, 'contacts' => 3, 'forms' => 0 ), $by['newsletter'], 'newsletter (email, map, tel)' );
		FA_Test::eq( array( 'source' => 'Instagram', 'visits' => 1, 'contacts' => 0, 'forms' => 0 ), $by['Instagram'], 'Instagram' );
		FA_Test::eq( 11, array_sum( array_column( $list, 'visits' ) ), 'the visits add up to the total' );
		FA_Test::eq( 2, count( fa_test_stats()->sources( 2 ) ), 'limit' );
	}
);

FA_Test::add(
	'stats: campaigns() lists only rows that have a campaign',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();

		FA_Test::eq(
			array( array( 'source' => 'newsletter', 'medium' => 'email', 'campaign' => 'spring-sale', 'visits' => 1, 'contacts' => 3, 'forms' => 0 ) ),
			fa_test_stats()->campaigns( 10 ),
			'one campaign'
		);
		FA_Test::eq( array(), ( new Free_Analytics_Stats( '2026-03-12', '2026-03-12' ) )->campaigns( 10 ), 'a range without campaigns' );
	}
);

FA_Test::add(
	'stats: contacts() counts per event with up to three pages each',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();
		$list = fa_test_stats()->contacts();
		$by   = fa_test_by( $list, 'event' );

		FA_Test::ok( ! isset( $by['pageview'] ), 'no page views in the list' );
		FA_Test::eq( 5, $by['tel']['count'], 'tel count' );
		FA_Test::eq( 3, count( $by['tel']['pages'] ), 'tel: at most three pages (there are four)' );
		FA_Test::eq( array( 'path' => '/shop/desk-lamp/', 'count' => 2 ), $by['tel']['pages'][0], 'tel: top page' );
		FA_Test::eq( array( 1, 1 ), array( $by['tel']['pages'][1]['count'], $by['tel']['pages'][2]['count'] ), 'tel: next pages' );
		FA_Test::eq( array( 'event' => 'email', 'count' => 1, 'pages' => array( array( 'path' => '/shop/desk-lamp/', 'count' => 1 ) ) ), $by['email'], 'email' );
		FA_Test::eq( array( 'event' => 'map', 'count' => 1, 'pages' => array( array( 'path' => '/contacts/', 'count' => 1 ) ) ), $by['map'], 'map' );
		FA_Test::eq( 0, $by['viber']['count'] ?? 0, 'no viber' );
		FA_Test::eq( 'tel', $list[0]['event'], 'sorted by count: tel first' );
		FA_Test::eq( array(), ( new Free_Analytics_Stats( '2026-02-01', '2026-02-02' ) )->contacts(), 'empty range' );
	}
);

FA_Test::add(
	'stats: breakdown() of device, browser and os counts visitors',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();
		$s   = fa_test_stats();
		$map = static function ( $rows ) {
			$out = array_column( $rows, 'visitors', 'key' );
			ksort( $out );

			return $out;
		};

		$device = $s->breakdown( 'device' );
		FA_Test::eq( array( 'desktop' => 4, 'mobile' => 5, 'tablet' => 1 ), $map( $device ), 'device' );
		FA_Test::eq( 'mobile', $device[0]['key'], 'device: biggest first' );
		FA_Test::eq( array( 'key', 'visitors' ), array_keys( $device[0] ), 'row keys' );

		$browser = $s->breakdown( 'browser' );
		FA_Test::eq( array( 'Chrome' => 5, 'Edge' => 1, 'Facebook' => 1, 'Firefox' => 1, 'Safari' => 2 ), $map( $browser ), 'browser' );
		FA_Test::eq( 'Chrome', $browser[0]['key'], 'browser: biggest first' );

		FA_Test::eq( array( 'Android' => 3, 'Windows' => 3, 'iOS' => 3, 'macOS' => 1 ), $map( $s->breakdown( 'os' ) ), 'os' );
		FA_Test::eq( array(), ( new Free_Analytics_Stats( '2026-02-01', '2026-02-02' ) )->breakdown( 'device' ), 'empty range' );
	}
);

FA_Test::add(
	'stats: breakdown() does not take any other column (no SQL injection)',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();
		$before = FA_Sandbox::count_rows();

		foreach ( array( 'visitor', 'path', 'ip', 'device`; DROP TABLE x; --', 'device, visit', '' ) as $column ) {
			$result = array();
			try {
				$result = fa_test_stats()->breakdown( $column );
			} catch ( Throwable $e ) {
				$result = array();
			}
			FA_Test::eq( array(), $result, 'column ' . $column . ' gives nothing' );
		}
		FA_Test::eq( $before, FA_Sandbox::count_rows(), 'the table is intact' );
	}
);

FA_Test::add(
	'stats: not_found() lists 404 paths with their views',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture();

		FA_Test::eq( array( array( 'path' => '/shop/discontinued-item/', 'views' => 1 ) ), fa_test_stats()->not_found( 10 ), 'one 404 path' );

		FA_Sandbox::insert_hit( array( 'ts' => '2026-03-11 10:00:00', 'visitor' => 'ffffffffffffff01', 'visit' => 'eeeeeeeeeeeeee01', 'path' => '/shop/discontinued-item/', 'is_404' => 1 ) );
		FA_Sandbox::insert_hit( array( 'ts' => '2026-03-11 10:05:00', 'visitor' => 'ffffffffffffff02', 'visit' => 'eeeeeeeeeeeeee02', 'path' => '/wp-login.php', 'is_404' => 1 ) );
		$list = fa_test_stats()->not_found( 10 );
		FA_Test::eq( array( '/shop/discontinued-item/' => 2, '/wp-login.php' => 1 ), array_column( $list, 'views', 'path' ), 'views per path' );
		FA_Test::eq( '/shop/discontinued-item/', $list[0]['path'], 'most missed first' );
		FA_Test::eq( 1, count( fa_test_stats()->not_found( 1 ) ), 'limit' );
		FA_Test::eq( array(), ( new Free_Analytics_Stats( '2026-02-01', '2026-02-02' ) )->not_found( 10 ), 'empty range' );
	}
);

FA_Test::add(
	'stats: online() counts distinct visitors seen in the last N minutes',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		fa_test_stats_fixture(); // Clock frozen at 2026-03-12 15:00:00.

		FA_Test::eq( 1, Free_Analytics_Stats::online( 5 ), 'last 5 minutes: h1 only (three rows, one visitor)' );
		FA_Test::eq( 1, Free_Analytics_Stats::online(), 'default is 5 minutes' );
		FA_Test::eq( 2, Free_Analytics_Stats::online( 30 ), 'last 30 minutes: h1 and i1' );
		FA_Test::eq( 3, Free_Analytics_Stats::online( 120 ), 'last 2 hours: g1 joins' );
		FA_Test::eq( 3, Free_Analytics_Stats::online( 360 ), 'last 6 hours: the 09:00 row is the same visitor as h1' );

		FA_Sandbox::freeze( '2026-03-12 15:02:00' );
		FA_Test::eq( 1, Free_Analytics_Stats::online( 5 ), 'two minutes later: 14:59 is still inside' );
		FA_Sandbox::freeze( '2026-03-12 15:06:00' );
		FA_Test::eq( 0, Free_Analytics_Stats::online( 5 ), 'six minutes after 14:59: nobody' );
	}
);

FA_Test::add(
	'stats: online() looks across midnight',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		FA_Sandbox::insert_hit( array( 'ts' => '2026-03-11 23:58:00', 'visitor' => 'ffffffffffffff01', 'visit' => 'eeeeeeeeeeeeee01' ) );
		FA_Sandbox::insert_hit( array( 'ts' => '2026-03-11 22:00:00', 'visitor' => 'ffffffffffffff02', 'visit' => 'eeeeeeeeeeeeee02' ) );
		FA_Sandbox::freeze( '2026-03-12 00:02:00' );
		FA_Test::eq( 1, Free_Analytics_Stats::online( 5 ), 'yesterday 23:58 is inside the window, 22:00 is not' );
		FA_Test::eq( 2, Free_Analytics_Stats::online( 180 ), 'both inside a three hour window' );
	}
);

FA_Test::add(
	'stats: nothing at all gives empty lists and a zero-filled series',
	function () {
		if ( $skip = fa_test_need( 'Free_Analytics_Stats' ) ) {
			return $skip;
		}
		FA_Sandbox::freeze( '2026-03-12 15:00:00' );
		$s = fa_test_stats();

		FA_Test::eq( array( 0, 0, 0 ), array( $s->totals()['visitors'], $s->totals()['visits'], $s->totals()['pageviews'] ), 'totals' );
		FA_Test::eq( array(), $s->top_pages( 10 ), 'top_pages' );
		FA_Test::eq( array(), $s->top_posts( 'product', 10 ), 'top_cars' );
		FA_Test::eq( array(), $s->sources( 10 ), 'sources' );
		FA_Test::eq( array(), $s->campaigns( 10 ), 'campaigns' );
		FA_Test::eq( array(), $s->contacts(), 'contacts' );
		FA_Test::eq( array(), $s->breakdown( 'os' ), 'breakdown' );
		FA_Test::eq( array(), $s->not_found( 10 ), 'not_found' );
		FA_Test::eq( 0, Free_Analytics_Stats::online( 5 ), 'online' );
		FA_Test::eq( array( 0, 0, 0 ), array_column( $s->series()['points'], 'pageviews' ), 'series' );
	}
);
