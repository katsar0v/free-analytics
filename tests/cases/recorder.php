<?php
/**
 * Recorder cases: payload validation (parse), request checks (handle), what a row may contain, visits.
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses a payload made of the defaults (page view of "/") plus overrides.
 *
 * @param array $over Payload keys.
 * @return array|null
 */
function fa_test_parse( array $over = array() ) {
	return Free_Analytics_Recorder::parse( FA_Sandbox::body( $over ) );
}

FA_Test::add(
	'recorder: parse() accepts a plain page view and returns the clean hit',
	function () {
		$hit = fa_test_parse( array( 'p' => '/shop/mug/' ) );
		FA_Test::eq(
			array(
				'event'     => 'pageview',
				'path'      => '/shop/mug/',
				'post_id'   => 0,
				'post_type' => '',
				'is_404'    => 0,
				'target'    => '',
				'referrer'  => '',
				'utm'       => array(
					'source'   => '',
					'medium'   => '',
					'campaign' => '',
				),
				'click'     => '',
			),
			$hit,
			'clean hit'
		);
	}
);

FA_Test::add(
	'recorder: parse() rejects oversize bodies, bad JSON and unknown events',
	function () {
		$r = 'Free_Analytics_Recorder';

		// MAX_BODY is 2048 bytes: exactly that is fine, one more is not.
		$base = '{"e":"pageview","p":"/","x":""}';
		$fill = str_repeat( 'a', $r::MAX_BODY - strlen( $base ) );
		$ok   = '{"e":"pageview","p":"/","x":"' . $fill . '"}';
		FA_Test::eq( $r::MAX_BODY, strlen( $ok ), 'test body is exactly MAX_BODY' );
		FA_Test::ok( is_array( $r::parse( $ok ) ), 'body of exactly MAX_BODY bytes is accepted' );
		FA_Test::eq( null, $r::parse( '{"e":"pageview","p":"/","x":"' . $fill . 'a"}' ), 'one byte over MAX_BODY is rejected' );
		FA_Test::eq( null, $r::parse( str_repeat( 'x', 100000 ) ), 'huge body' );

		foreach ( array( '', '{not json', 'null', '"pageview"', '123', '[]', 'true', '{"e":' ) as $body ) {
			FA_Test::eq( null, $r::parse( $body ), 'rejected body: ' . $body );
		}
		FA_Test::eq( null, $r::parse( null ), 'null body' );
		FA_Test::eq( null, $r::parse( array( 'e' => 'pageview', 'p' => '/' ) ), 'array instead of a string' );

		foreach ( array( 'purchase', 'PAGEVIEW', 'page view', '', 'tel ' ) as $event ) {
			FA_Test::eq( null, fa_test_parse( array( 'e' => $event ) ), 'unknown event: ' . $event );
		}
		FA_Test::eq( null, fa_test_parse( array( 'e' => array( 'pageview' ) ) ), 'event is an array' );
		FA_Test::eq( null, fa_test_parse( array( 'e' => 1 ) ), 'event is a number' );
		FA_Test::eq( null, $r::parse( '{"p":"/"}' ), 'missing event' );
	}
);

FA_Test::add(
	'recorder: parse() path rules: leading slash, no //, no markup, query and fragment removed, UTF-8 decoded',
	function () {
		$r = 'Free_Analytics_Recorder';

		FA_Test::eq( null, $r::parse( '{"e":"pageview"}' ), 'missing path' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => '' ) ), 'empty path' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => 'shop/' ) ), 'no leading slash' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => 'https://evil.example/' ) ), 'absolute URL' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => '//evil.example/x' ) ), 'protocol-relative //evil' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => '//' ) ), 'double slash' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => 123 ) ), 'path is a number' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => array( '/' ) ) ), 'path is an array' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => '/a<b' ) ), 'path with <' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => '/a>b' ) ), 'path with >' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => '/%3Cscript%3Ealert(1)%3C/script%3E' ) ), 'encoded <script> is checked after decoding' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => '/a"b' ) ), 'path with a double quote' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => '/a\\b' ) ), 'path with a backslash' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => "/a\nb" ) ), 'path with a newline' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => '/a%00b' ) ), 'encoded NUL' );
		FA_Test::eq( null, fa_test_parse( array( 'p' => '/%FF%FE' ) ), 'invalid UTF-8 after decoding' );

		$p = static function ( $path ) {
			$hit = fa_test_parse( array( 'p' => $path ) );

			return $hit ? $hit['path'] : null;
		};
		FA_Test::eq( '/', $p( '/' ), 'root' );
		FA_Test::eq( '/shop/', $p( '/shop/?utm_source=x&secret=1' ), 'query removed' );
		FA_Test::eq( '/shop/', $p( '/shop/#top' ), 'fragment removed' );
		FA_Test::eq( '/shop/', $p( '/shop/#a?b' ), 'fragment with ? removed' );
		FA_Test::eq( '/shop/', $p( '/shop/?a=1#b' ), 'query and fragment removed' );
		FA_Test::eq( '/', $p( '/?x=1' ), 'root with a query' );
		FA_Test::eq( '/автомобили/', $p( '/%D0%B0%D0%B2%D1%82%D0%BE%D0%BC%D0%BE%D0%B1%D0%B8%D0%BB%D0%B8/' ), 'percent-encoded Cyrillic is decoded' );
		FA_Test::eq( '/автомобили/', $p( '/автомобили/' ), 'raw Cyrillic stays' );
		FA_Test::eq( '/shop/my item/', $p( '/shop/my%20item/' ), '%20 decoded' );
		FA_Test::eq( 255, strlen( $p( '/' . str_repeat( 'a', 300 ) ) ), 'cut to 255 characters' );
		FA_Test::eq( 255, mb_strlen( $p( '/' . str_repeat( 'ж', 300 ) ) ), 'cut to 255 characters (multibyte)' );
	}
);

FA_Test::add(
	'recorder: parse() post_id: only published, viewable posts keep their ID',
	function () {
		$published = FA_Sandbox::make_post( 'publish', 'page' );
		$draft     = FA_Sandbox::make_post( 'draft', 'page' );
		$private   = FA_Sandbox::make_post( 'private', 'page' );
		$trashed   = FA_Sandbox::make_post( 'trash', 'page' );
		$menu_item = FA_Sandbox::make_post( 'publish', 'nav_menu_item' );
		$revision  = FA_Sandbox::make_post( 'inherit', 'revision' );

		$hit = fa_test_parse( array( 'id' => $published ) );
		FA_Test::eq( $published, $hit['post_id'], 'published page: ID kept' );
		FA_Test::eq( 'page', $hit['post_type'], 'published page: type page' );

		foreach ( array(
			'draft'         => $draft,
			'private'       => $private,
			'trashed'       => $trashed,
			'nav_menu_item' => $menu_item,
			'revision'      => $revision,
			'nonexistent'   => 999999,
		) as $label => $id ) {
			$hit = fa_test_parse( array( 'id' => $id ) );
			FA_Test::eq( 0, $hit['post_id'], "$label: post_id becomes 0" );
			FA_Test::eq( '', $hit['post_type'], "$label: no post type" );
		}

		foreach ( array( 'abc', array( 1 ), '', null, 0, -0.0 ) as $junk ) {
			$hit = fa_test_parse( array( 'id' => $junk ) );
			FA_Test::eq( 0, $hit['post_id'], 'junk id ' . FA_Test::dump( $junk ) . ': 0' );
		}

		$post = FA_Sandbox::make_post( 'publish', 'post' );
		$hit  = fa_test_parse( array( 'id' => $post ) );
		FA_Test::eq( $post, $hit['post_id'], 'published post: ID kept' );
		FA_Test::eq( 'post', $hit['post_type'], 'published post: type post' );
		$hit = fa_test_parse( array( 'id' => FA_Sandbox::make_post( 'pending', 'post' ) ) );
		FA_Test::eq( 0, $hit['post_id'], 'pending post: 0' );
	}
);

FA_Test::add(
	'recorder: parse() event targets: a tel event needs a tel: link, and so on',
	function () {
		$hit = fa_test_parse( array( 'e' => 'tel', 't' => 'tel:+359 894 222 217' ) );
		FA_Test::eq( 'tel', $hit['event'], 'tel event' );
		FA_Test::eq( '+359894222217', $hit['target'], 'tel target' );

		FA_Test::eq( null, fa_test_parse( array( 'e' => 'tel', 't' => 'mailto:info@example.com' ) ), 'tel event with a mailto link' );
		FA_Test::eq( null, fa_test_parse( array( 'e' => 'tel', 't' => 'https://example.com/' ) ), 'tel event with a web link' );
		FA_Test::eq( null, fa_test_parse( array( 'e' => 'tel' ) ), 'tel event without a link' );
		FA_Test::eq( null, fa_test_parse( array( 'e' => 'tel', 't' => array( 'tel:123' ) ) ), 'tel event with an array link' );
		FA_Test::eq( null, fa_test_parse( array( 'e' => 'email', 't' => 'tel:+359894222217' ) ), 'email event with a tel link' );
		FA_Test::eq( null, fa_test_parse( array( 'e' => 'map', 't' => 'https://example.com/' ) ), 'map event with another site' );
		FA_Test::eq( null, fa_test_parse( array( 'e' => 'viber', 't' => 'tel:1' ) ), 'viber event with a tel link' );
		FA_Test::eq( null, fa_test_parse( array( 'e' => 'whatsapp', 't' => 'tel:1' ) ), 'whatsapp event with a tel link' );

		$hit = fa_test_parse( array( 'e' => 'email', 't' => 'mailto:info@example.com?subject=Hi' ) );
		FA_Test::eq( 'info@example.com', $hit['target'], 'email target' );
		$hit = fa_test_parse( array( 'e' => 'map', 't' => 'https://www.google.com/maps/place/Sofia' ) );
		FA_Test::eq( 'Google Maps', $hit['target'], 'map target' );
		$hit = fa_test_parse( array( 'e' => 'form', 't' => 'brxe-abcdef' ) );
		FA_Test::eq( 'brxe-abcdef', $hit['target'], 'form target' );

		$hit = fa_test_parse( array( 't' => 'tel:+359894222217' ) );
		FA_Test::eq( '', $hit['target'], 'a page view never stores a target' );
	}
);

FA_Test::add(
	'recorder: parse() 404 flag, click kind, UTM tags and referrer are cleaned and cut',
	function () {
		FA_Test::eq( 1, fa_test_parse( array( 'nf' => 1 ) )['is_404'], '404 flag' );
		FA_Test::eq( 0, fa_test_parse( array( 'nf' => 0 ) )['is_404'], 'no 404 flag' );
		FA_Test::eq( 'g', fa_test_parse( array( 'ck' => 'g' ) )['click'], 'click g' );
		FA_Test::eq( 'f', fa_test_parse( array( 'ck' => 'f' ) )['click'], 'click f' );
		FA_Test::eq( '', fa_test_parse( array( 'ck' => 'x' ) )['click'], 'unknown click kind' );
		FA_Test::eq( '', fa_test_parse( array( 'ck' => array( 'g' ) ) )['click'], 'array click kind' );

		$hit = fa_test_parse(
			array(
				'us' => '<b>news</b>letter',
				'um' => str_repeat( 'm', 80 ),
				'uc' => str_repeat( 'c', 150 ),
			)
		);
		FA_Test::eq( 'newsletter', $hit['utm']['source'], 'utm source: tags stripped' );
		FA_Test::eq( 50, strlen( $hit['utm']['medium'] ), 'utm medium cut to 50' );
		FA_Test::eq( 100, strlen( $hit['utm']['campaign'] ), 'utm campaign cut to 100' );
		$hit = fa_test_parse( array( 'us' => array( 'x' ) ) );
		FA_Test::eq( '', $hit['utm']['source'], 'utm source as an array' );

		FA_Test::eq( 1000, strlen( fa_test_parse( array( 'r' => 'https://example.org/' . str_repeat( 'a', 1500 ) ) )['referrer'] ), 'referrer cut to 1000' );
		FA_Test::eq( '', fa_test_parse( array( 'r' => array( 'x' ) ) )['referrer'], 'referrer as an array' );
	}
);

FA_Test::add(
	'recorder: handle() rejects foreign origins and cross-site requests',
	function () {
		$foreign = array(
			'https://evil.example',
			'https://example.com.evil.example',
			'https://evilexample.com',
			'http://localhost:8080',
			'null',
			'garbage',
		);
		foreach ( $foreign as $origin ) {
			FA_Test::eq( 'foreign', FA_Sandbox::send( array(), array( 'origin' => $origin ) ), "Origin $origin" );
		}
		FA_Test::eq( 'foreign', FA_Sandbox::send( array(), array( 'fetch_site' => 'cross-site' ) ), 'Sec-Fetch-Site: cross-site' );
		FA_Test::eq( 'foreign', FA_Sandbox::send( array(), array( 'fetch_site' => 'Cross-Site' ) ), 'Sec-Fetch-Site: Cross-Site (case)' );
		FA_Test::eq( 'foreign', FA_Sandbox::send( array(), array( 'origin' => 'https://example.com', 'fetch_site' => 'cross-site' ) ), 'good Origin but cross-site fetch' );
		FA_Test::eq( 0, FA_Sandbox::count_rows(), 'nothing was written' );
	}
);

FA_Test::add(
	'recorder: handle() accepts the site\'s own origin, with and without www',
	function () {
		FA_Test::eq( 'example.com', Free_Analytics_Recorder::site_host(), 'site host of the sandbox' );
		FA_Test::eq( 'recorded', FA_Sandbox::send( array(), array( 'origin' => 'https://example.com' ) ), 'Origin without www' );
		FA_Test::eq( 'recorded', FA_Sandbox::send( array(), array( 'origin' => 'https://www.example.com' ) ), 'Origin with www' );
		FA_Test::eq( 'recorded', FA_Sandbox::send( array(), array( 'origin' => '' ) ), 'no Origin header (same-origin GET-like beacons)' );
		FA_Test::eq( 'recorded', FA_Sandbox::send( array(), array( 'fetch_site' => 'same-origin' ) ), 'Sec-Fetch-Site: same-origin' );
		FA_Test::eq( 'recorded', FA_Sandbox::send( array(), array( 'fetch_site' => 'none' ) ), 'Sec-Fetch-Site: none' );
		FA_Test::eq( 5, FA_Sandbox::count_rows(), 'five rows so far' );

		// The home URL itself carries www.
		FA_Sandbox::set_home( 'https://www.example.com' );
		FA_Test::eq( 'example.com', Free_Analytics_Recorder::site_host(), 'site host strips www from the home URL' );
		FA_Test::eq( 'recorded', FA_Sandbox::send( array(), array( 'origin' => 'https://example.com' ) ), 'home with www, Origin without' );
		FA_Test::eq( 'recorded', FA_Sandbox::send( array(), array( 'origin' => 'https://www.example.com' ) ), 'home with www, Origin with' );
		FA_Test::eq( 'foreign', FA_Sandbox::send( array(), array( 'origin' => 'https://evil.example' ) ), 'home with www, foreign Origin' );
	}
);

FA_Test::add(
	'recorder: handle() ignores bots and answers invalid for bad payloads',
	function () {
		foreach ( array( FA_UA::GOOGLEBOT, FA_UA::HEADLESS, FA_UA::CURL, FA_UA::LIGHTHOUSE, '' ) as $ua ) {
			FA_Test::eq( 'bot', FA_Sandbox::send( array(), array( 'ua' => $ua ) ), 'bot: ' . ( '' === $ua ? 'empty UA' : substr( $ua, 0, 40 ) ) );
		}
		FA_Test::eq( 'bot', FA_Sandbox::send( array( 'e' => 'nonsense' ), array( 'ua' => FA_UA::GOOGLEBOT ) ), 'a bot with a bad payload is still just a bot' );
		FA_Test::eq( 0, FA_Sandbox::count_rows(), 'bots write nothing' );

		FA_Test::eq( 'invalid', Free_Analytics_Recorder::handle( '{broken', FA_Sandbox::env() ), 'bad JSON' );
		FA_Test::eq( 'invalid', FA_Sandbox::send( array( 'e' => 'nonsense' ) ), 'unknown event' );
		FA_Test::eq( 'invalid', FA_Sandbox::send( array( 'p' => '//evil' ) ), 'bad path' );
		FA_Test::eq( 'invalid', FA_Sandbox::send( array( 'e' => 'tel', 't' => 'https://example.com/' ) ), 'tel event with a web link' );
		FA_Test::eq( 0, FA_Sandbox::count_rows(), 'invalid payloads write nothing' );
	}
);

FA_Test::add(
	'recorder: handle() writes one row with no IP and no user agent anywhere',
	function () {
		global $wpdb;

		FA_Sandbox::freeze( '2026-03-10 12:00:00' );
		$ip = '203.0.113.7';
		$ua = FA_UA::CHROME_WIN . ' ZZUNIQUEUA987';

		$status = FA_Sandbox::send(
			array(
				'p' => '/shop/blue-mug/?token=SECRETQUERY#frag',
				'r' => 'https://www.google.com/search?q=SECRETSEARCH+mug',
			),
			array(
				'ip' => $ip,
				'ua' => $ua,
			)
		);
		FA_Test::eq( 'recorded', $status, 'status' );

		$rows = FA_Sandbox::rows();
		FA_Test::eq( 1, count( $rows ), 'one row' );
		$row = $rows[0];

		FA_Test::eq( '2026-03-10', $row['day'], 'day' );
		FA_Test::eq( '2026-03-10 12:00:00', $row['ts'], 'ts is the (frozen) site time' );
		FA_Test::eq( 'pageview', $row['event'], 'event' );
		FA_Test::eq( '/shop/blue-mug/', $row['path'], 'path without query and fragment' );
		FA_Test::eq( 'Google', $row['source'], 'source from the referrer' );
		FA_Test::eq( 'desktop', $row['device'], 'device' );
		FA_Test::eq( 'Chrome', $row['browser'], 'browser' );
		FA_Test::eq( 'Windows', $row['os'], 'os' );
		FA_Test::eq( 1, preg_match( '/^[0-9a-f]{16}$/', $row['visitor'] ), 'visitor is 16 hex' );
		FA_Test::eq( 1, preg_match( '/^[0-9a-f]{16}$/', $row['visit'] ), 'visit is 16 hex' );
		FA_Test::ne( $row['visitor'], $row['visit'], 'visitor and visit differ' );

		$needles = array( $ip, 'ZZUNIQUEUA987', 'Mozilla', 'AppleWebKit', 'Chrome/', '537.36', 'NT 10', 'SECRETQUERY', 'SECRETSEARCH', 'search?q', 'https://www.google', 'token=' );
		foreach ( $row as $column => $value ) {
			foreach ( $needles as $needle ) {
				FA_Test::ok( false === stripos( (string) $value, $needle ), "column $column does not contain '$needle'" );
			}
		}

		// Nothing but the expected columns, none called ip / user agent.
		$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . FA_Sandbox::table() ); // phpcs:ignore WordPress.DB
		FA_Test::eq(
			array( 'id', 'ts', 'day', 'visitor', 'visit', 'event', 'path', 'post_id', 'post_type', 'is_404', 'target', 'source', 'medium', 'campaign', 'device', 'browser', 'os' ),
			$columns,
			'table columns'
		);

		// IPv6, same checks.
		$status = FA_Sandbox::send( array(), array( 'ip' => '2001:db8:85a3::8a2e:370:7334', 'ua' => $ua ) );
		FA_Test::eq( 'recorded', $status, 'IPv6 status' );
		foreach ( FA_Sandbox::rows() as $r ) {
			foreach ( $r as $column => $value ) {
				FA_Test::ok( false === stripos( (string) $value, '2001:db8' ) && false === stripos( (string) $value, 'ZZUNIQUEUA987' ), "IPv6 row, column $column clean" );
			}
		}
	}
);

FA_Test::add(
	'recorder: handle() stores post, 404 and event data',
	function () {
		FA_Sandbox::freeze( '2026-03-10 12:00:00' );
		$page = FA_Sandbox::make_post( 'publish', 'page' );
		$draft = FA_Sandbox::make_post( 'draft', 'page' );

		FA_Sandbox::send( array( 'p' => '/contacts/', 'id' => $page ) );
		FA_Sandbox::send( array( 'p' => '/nothing-here/', 'nf' => 1 ) );
		FA_Sandbox::send( array( 'e' => 'tel', 't' => 'tel:+1 555 0100', 'p' => '/contacts/', 'id' => $page ) );
		FA_Sandbox::send( array( 'p' => '/secret-draft/', 'id' => $draft ) );

		$rows = FA_Sandbox::rows();
		FA_Test::eq( 4, count( $rows ), 'four rows' );
		FA_Test::eq( array( $page, 'page', 0 ), array( (int) $rows[0]['post_id'], $rows[0]['post_type'], (int) $rows[0]['is_404'] ), 'page view of a page' );
		FA_Test::eq( array( 0, '', 1 ), array( (int) $rows[1]['post_id'], $rows[1]['post_type'], (int) $rows[1]['is_404'] ), '404 page view' );
		FA_Test::eq( array( 'tel', '+15550100', $page ), array( $rows[2]['event'], $rows[2]['target'], (int) $rows[2]['post_id'] ), 'tel event' );
		FA_Test::eq( 0, (int) $rows[3]['post_id'], 'draft post ID is not stored' );
	}
);

/**
 * Sends a page view at a given site time.
 *
 * @param string $time    Y-m-d H:i:s.
 * @param array  $payload Payload overrides.
 * @param array  $env     Environment overrides.
 * @return array The newest row.
 */
function fa_test_hit_at( $time, array $payload = array(), array $env = array() ) {
	FA_Sandbox::freeze( $time );
	FA_Test::eq( 'recorded', FA_Sandbox::send( $payload, $env ), "recorded at $time" );
	$rows = FA_Sandbox::rows();

	return end( $rows );
}

FA_Test::add(
	'recorder: visits: 5 minutes apart is one visit, 31 minutes apart is a new one',
	function () {
		$a = fa_test_hit_at( '2026-03-10 12:00:00', array( 'p' => '/' ) );
		$b = fa_test_hit_at( '2026-03-10 12:05:00', array( 'p' => '/shop/' ) );
		FA_Test::eq( $a['visit'], $b['visit'], '5 minutes apart: same visit' );
		FA_Test::eq( $a['visitor'], $b['visitor'], '5 minutes apart: same visitor' );

		// The gap counts from the LAST hit, not from the first: 12:05 -> 12:34 is 29 minutes.
		$c = fa_test_hit_at( '2026-03-10 12:34:00', array( 'p' => '/shop/mug/' ) );
		FA_Test::eq( $b['visit'], $c['visit'], '29 minutes after the last hit (34 after the first): same visit' );

		$d = fa_test_hit_at( '2026-03-10 13:05:00', array( 'p' => '/contacts/' ) );
		FA_Test::ne( $c['visit'], $d['visit'], '31 minutes after the last hit: new visit' );
		FA_Test::eq( $c['visitor'], $d['visitor'], 'the visitor is the same all day' );
		FA_Test::eq( 2, count( array_unique( array_column( FA_Sandbox::rows(), 'visit' ) ) ), 'two visits in all' );
	}
);

FA_Test::add(
	'recorder: visits: two page views 31 minutes apart are two visits',
	function () {
		$a = fa_test_hit_at( '2026-03-10 12:00:00', array( 'p' => '/' ), array( 'ip' => '198.51.100.9' ) );
		$b = fa_test_hit_at( '2026-03-10 12:31:00', array( 'p' => '/shop/' ), array( 'ip' => '198.51.100.9' ) );
		FA_Test::ne( $a['visit'], $b['visit'], '31 minutes apart: new visit' );
		FA_Test::eq( $a['visitor'], $b['visitor'], 'same visitor' );
	}
);

FA_Test::add(
	'recorder: visits: a new visit forgets the old source',
	function () {
		$a = fa_test_hit_at( '2026-03-10 12:00:00', array( 'r' => 'https://www.google.com/' ) );
		FA_Test::eq( 'Google', $a['source'], 'first visit from Google' );
		$b = fa_test_hit_at( '2026-03-10 12:40:00', array( 'p' => '/shop/' ) );
		FA_Test::ne( $a['visit'], $b['visit'], 'new visit after 40 minutes' );
		FA_Test::eq( '', $b['source'], 'the new direct visit does not inherit Google' );
	}
);

FA_Test::add(
	'recorder: visits: a page view with a new external source starts a new visit',
	function () {
		$a = fa_test_hit_at( '2026-03-10 12:00:00', array( 'p' => '/' ) );
		FA_Test::eq( '', $a['source'], 'first visit is direct' );

		$b = fa_test_hit_at( '2026-03-10 12:02:00', array( 'p' => '/shop/', 'r' => 'https://www.google.com/' ) );
		FA_Test::ne( $a['visit'], $b['visit'], 'Google referrer while the visit is direct: new visit' );
		FA_Test::eq( 'Google', $b['source'], 'the new visit has the source' );

		$c = fa_test_hit_at( '2026-03-10 12:04:00', array( 'p' => '/shop/mug/', 'r' => 'https://www.bing.com/' ) );
		FA_Test::ne( $b['visit'], $c['visit'], 'Bing referrer while the visit is Google: new visit' );
		FA_Test::eq( 'Bing', $c['source'], 'Bing source' );

		$d = fa_test_hit_at( '2026-03-10 12:06:00', array( 'p' => '/', 'us' => 'newsletter', 'um' => 'email', 'uc' => 'spring' ) );
		FA_Test::ne( $c['visit'], $d['visit'], 'utm tags: new visit' );
		FA_Test::eq( array( 'newsletter', 'email', 'spring' ), array( $d['source'], $d['medium'], $d['campaign'] ), 'utm source, medium, campaign stored' );

		$e = fa_test_hit_at( '2026-03-10 12:08:00', array( 'p' => '/', 'us' => 'newsletter', 'um' => 'email', 'uc' => 'summer' ) );
		FA_Test::ne( $d['visit'], $e['visit'], 'same utm source but another campaign: new visit' );

		$f = fa_test_hit_at( '2026-03-10 12:10:00', array( 'p' => '/', 'ck' => 'g' ) );
		FA_Test::ne( $e['visit'], $f['visit'], 'gclid: new visit' );
		FA_Test::eq( 'Google Ads', $f['source'], 'Google Ads source' );
	}
);

FA_Test::add(
	'recorder: visits: a reload, back or internal click does not start a new visit',
	function () {
		$a = fa_test_hit_at( '2026-03-10 12:00:00', array( 'p' => '/shop/mug/', 'r' => 'https://www.google.com/' ) );
		$b = fa_test_hit_at( '2026-03-10 12:01:00', array( 'p' => '/shop/mug/', 'r' => 'https://www.google.com/' ) );
		FA_Test::eq( $a['visit'], $b['visit'], 'reload with the same Google referrer: same visit' );

		$c = fa_test_hit_at( '2026-03-10 12:02:00', array( 'p' => '/shop/', 'r' => 'https://example.com/shop/mug/' ) );
		FA_Test::eq( $a['visit'], $c['visit'], 'click inside the site: same visit' );
		FA_Test::eq( 'Google', $c['source'], 'internal click keeps the visit source' );

		$d = fa_test_hit_at( '2026-03-10 12:03:00', array( 'p' => '/contacts/', 'r' => 'https://www.example.com/shop/' ) );
		FA_Test::eq( $a['visit'], $d['visit'], 'click from the www host: same visit' );

		$e = fa_test_hit_at( '2026-03-10 12:04:00', array( 'p' => '/about/' ) );
		FA_Test::eq( $a['visit'], $e['visit'], 'no referrer (opened in a new tab): same visit' );
		FA_Test::eq( 'Google', $e['source'], 'source kept' );

		$f = fa_test_hit_at( '2026-03-10 12:05:00', array( 'p' => '/', 'us' => 'newsletter' ) );
		$g = fa_test_hit_at( '2026-03-10 12:06:00', array( 'p' => '/', 'us' => 'newsletter' ) );
		FA_Test::eq( $f['visit'], $g['visit'], 'reload with the same utm tags: same visit' );
		FA_Test::eq( 2, count( array_unique( array_column( FA_Sandbox::rows(), 'visit' ) ) ), 'two visits in all: the Google one and the newsletter one' );
	}
);

FA_Test::add(
	'recorder: visits: an event joins the visit and inherits its source',
	function () {
		$a = fa_test_hit_at( '2026-03-10 12:00:00', array( 'p' => '/shop/mug/', 'us' => 'facebook', 'um' => 'cpc', 'uc' => 'spring-sale' ) );
		$b = fa_test_hit_at( '2026-03-10 12:03:00', array( 'e' => 'tel', 't' => 'tel:+15550100', 'p' => '/shop/mug/' ) );
		FA_Test::eq( $a['visit'], $b['visit'], 'tel click: same visit' );
		FA_Test::eq( array( 'Facebook', 'cpc', 'spring-sale' ), array( $b['source'], $b['medium'], $b['campaign'] ), 'tel click inherits source (utm facebook = label Facebook), medium, campaign' );
		FA_Test::eq( 'tel', $b['event'], 'event stored' );
		FA_Test::eq( '+15550100', $b['target'], 'target stored' );

		$c = fa_test_hit_at( '2026-03-10 12:04:00', array( 'e' => 'form', 't' => 'brxe-abcdef', 'p' => '/contacts/' ) );
		FA_Test::eq( $a['visit'], $c['visit'], 'form submission: same visit' );
		FA_Test::eq( 'Facebook', $c['source'], 'form inherits the source' );
	}
);

FA_Test::add(
	'recorder: visits: only a page view can start a visit because of a new source; an event never does',
	function () {
		$a = fa_test_hit_at( '2026-03-10 12:00:00', array( 'p' => '/' ) );
		$b = fa_test_hit_at( '2026-03-10 12:01:00', array( 'e' => 'tel', 't' => 'tel:+15550100', 'r' => 'https://www.google.com/' ) );
		FA_Test::eq( $a['visit'], $b['visit'], 'tel event with a Google referrer: same visit' );
		FA_Test::eq( '', $b['source'], 'the visit stays direct' );
	}
);

FA_Test::add(
	'recorder: visits: another user agent is another visitor and visit; another IP too; other headers do not matter',
	function () {
		$a = fa_test_hit_at( '2026-03-10 12:00:00', array( 'p' => '/' ) );
		$b = fa_test_hit_at( '2026-03-10 12:01:00', array( 'p' => '/' ), array( 'ua' => FA_UA::FIREFOX_WIN ) );
		FA_Test::ne( $a['visitor'], $b['visitor'], 'other user agent: other visitor' );
		FA_Test::ne( $a['visit'], $b['visit'], 'other user agent: other visit' );
		FA_Test::eq( 'Firefox', $b['browser'], 'Firefox recorded' );

		$c = fa_test_hit_at( '2026-03-10 12:02:00', array( 'p' => '/' ), array( 'ip' => '198.51.100.20' ) );
		FA_Test::ne( $a['visitor'], $c['visitor'], 'other IP: other visitor' );
		FA_Test::ne( $a['visit'], $c['visit'], 'other IP: other visit' );

		$d = fa_test_hit_at( '2026-03-10 12:03:00', array( 'p' => '/shop/' ), array( 'origin' => 'https://www.example.com', 'fetch_site' => 'same-origin' ) );
		FA_Test::eq( $a['visitor'], $d['visitor'], 'Origin and Sec-Fetch-Site are not part of the visitor' );
		FA_Test::eq( $a['visit'], $d['visit'], 'and the visit continues' );
	}
);

FA_Test::add(
	'recorder: visits: at midnight (site time) the same browser becomes a new visitor with a new salt',
	function () {
		$a = fa_test_hit_at( '2026-03-10 23:59:00', array( 'p' => '/' ) );
		$b = fa_test_hit_at( '2026-03-11 00:01:00', array( 'p' => '/shop/' ) );

		FA_Test::eq( '2026-03-10', $a['day'], 'day 1' );
		FA_Test::eq( '2026-03-11', $b['day'], 'day 2 starts at midnight' );
		FA_Test::ne( $a['visitor'], $b['visitor'], 'the visitor changes with the day' );
		FA_Test::ne( $a['visit'], $b['visit'], 'a new visit (visits are per visitor)' );

		$salts = fa_test_salt_options();
		FA_Test::eq( array( 'free_analytics_salt_2026-03-11' ), $salts, 'yesterday\'s salt is gone, only today\'s is left' );
	}
);

FA_Test::add(
	'recorder: the stored day is the site day, not the UTC day',
	function () {
		// 00:30 in Sofia (UTC+2) is 22:30 the day before in UTC.
		$a = fa_test_hit_at( '2026-01-15 00:30:00', array( 'p' => '/' ) );
		FA_Test::eq( '2026-01-15', $a['day'], 'day' );
		FA_Test::eq( '2026-01-15 00:30:00', $a['ts'], 'ts' );
		FA_Test::eq( array( 'free_analytics_salt_2026-01-15' ), fa_test_salt_options(), 'salt of the Sofia day' );
	}
);
