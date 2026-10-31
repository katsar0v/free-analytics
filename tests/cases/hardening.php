<?php
/**
 * Hardening cases: fixes from the first review (device of in-app browsers, utm source labels,
 * stricter map links, salts of later days, the per-visitor rate limit).
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

FA_Test::add(
	'hardening: Instagram in-app browser on an Android phone is mobile, an Android tablet stays tablet',
	function () {
		$instagram = 'Mozilla/5.0 (Linux; Android 14; SM-S918B Build/UP1A.231005.007; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/141.0.0.0 Mobile Safari/537.36 Instagram 330.0.0.40.99 Android (34/14; 480dpi; 1080x2340; samsung; SM-S918B; dm3q; qcom; en_US; 601420827)';
		$tablet    = 'Mozilla/5.0 (Linux; Android 14; Lenovo TB-X606F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';
		FA_Test::eq( 'mobile', Free_Analytics_Classify::device( $instagram ), 'Instagram on Android' );
		FA_Test::eq( 'tablet', Free_Analytics_Classify::device( $tablet ), 'Android tablet' );
	}
);

FA_Test::add(
	'hardening: a utm_source that names a known site gets its label, so it groups with the referrer',
	function () {
		$cases = array(
			'facebook'   => 'Facebook',
			'FaceBook'   => 'Facebook',
			'fb'         => 'Facebook',
			'ig'         => 'Instagram',
			'google'     => 'Google',
			'newsletter' => 'newsletter',
		);
		foreach ( $cases as $utm => $expected ) {
			$s = Free_Analytics_Classify::source( '', 'example.com', array( 'source' => $utm ), '', FA_UA::CHROME_WIN );
			FA_Test::eq( $expected, $s['source'], 'utm_source=' . $utm );
		}
	}
);

FA_Test::add(
	'hardening: map links need a real Google host',
	function () {
		FA_Test::ok( Free_Analytics_Classify::is_map_link( 'https://www.google.bg/maps/dir/?api=1' ), 'google.bg/maps' );
		FA_Test::ok( Free_Analytics_Classify::is_map_link( 'https://www.google.co.uk/maps/place/x' ), 'google.co.uk/maps' );
		FA_Test::ok( Free_Analytics_Classify::is_map_link( 'https://maps.app.goo.gl/abc' ), 'maps.app.goo.gl' );
		FA_Test::ok( ! Free_Analytics_Classify::is_map_link( 'https://google.evil.com/maps' ), 'google.evil.com/maps' );
		FA_Test::ok( ! Free_Analytics_Classify::is_map_link( 'https://www.google.example.org/maps' ), 'google.example.org/maps' );
	}
);

FA_Test::add(
	'hardening: making a late salt for yesterday keeps today\'s salt',
	function () {
		$today     = Free_Analytics_Fingerprint::salt( '2026-03-11' );
		$yesterday = Free_Analytics_Fingerprint::salt( '2026-03-10' );
		FA_Test::ne( $today, $yesterday, 'different salts' );
		FA_Test::eq( $today, Free_Analytics_Fingerprint::salt( '2026-03-11' ), 'today\'s salt survived' );

		Free_Analytics_Fingerprint::delete_old_salts( '2026-03-11' );
		FA_Test::eq( array( 'free_analytics_salt_2026-03-11' ), fa_test_salt_options(), 'only older salts are deleted' );
	}
);

FA_Test::add(
	'hardening: one visitor can add at most PER_MINUTE rows per minute',
	function () {
		global $wpdb;

		FA_Sandbox::freeze( '2026-03-10 12:00:00' );
		$env  = array(
			'ip' => '198.51.100.20',
			'ua' => FA_UA::CHROME_WIN,
		);
		$body = wp_json_encode(
			array(
				'e' => 'pageview',
				'p' => '/flood/',
			)
		);

		$statuses = array();
		for ( $i = 0; $i < Free_Analytics_Recorder::PER_MINUTE + 3; $i++ ) {
			$statuses[] = Free_Analytics_Recorder::handle( $body, $env );
		}
		$table = Free_Analytics_Schema::table();
		$rows  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE path = '/flood/'" ); // phpcs:ignore WordPress.DB
		FA_Test::eq( Free_Analytics_Recorder::PER_MINUTE, $rows, 'rows written' );
		FA_Test::eq( 'limited', end( $statuses ), 'status after the limit' );

		$other = Free_Analytics_Recorder::handle( $body, array( 'ip' => '198.51.100.21' ) + $env );
		FA_Test::eq( 'recorded', $other, 'another visitor is not limited' );

		FA_Sandbox::freeze( '2026-03-10 12:01:05' );
		FA_Test::eq( 'recorded', Free_Analytics_Recorder::handle( $body, $env ), 'a minute later it counts again' );
	}
);
