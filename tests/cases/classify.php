<?php
/**
 * Classifier cases: bots, device, browser, OS, hosts, traffic sources, contact targets.
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

FA_Test::add(
	'classify: is_bot() lets real browsers through',
	function () {
		$people = array(
			'Chrome Windows' => FA_UA::CHROME_WIN,
			'Chrome macOS'   => FA_UA::CHROME_MAC,
			'Safari macOS'   => FA_UA::SAFARI_MAC,
			'Safari iPhone'  => FA_UA::SAFARI_IOS,
			'Safari iPad'    => FA_UA::IPAD,
			'Chrome iOS'     => FA_UA::CHROME_IOS,
			'Firefox'        => FA_UA::FIREFOX_WIN,
			'Firefox Android' => FA_UA::FIREFOX_AND,
			'Edge'           => FA_UA::EDGE_WIN,
			'Opera'          => FA_UA::OPERA_WIN,
			'Samsung'        => FA_UA::SAMSUNG,
			'Chrome Android' => FA_UA::CHROME_AND,
			'Facebook app (Android)' => FA_UA::FB_ANDROID,
			'Facebook app (iOS)'     => FA_UA::FB_IOS,
			'Instagram app'  => FA_UA::INSTAGRAM,
			'ChromeOS'       => FA_UA::CHROMEOS,
		);
		foreach ( $people as $label => $ua ) {
			FA_Test::ok( ! Free_Analytics_Classify::is_bot( $ua ), "$label is not a bot" );
		}
	}
);

FA_Test::add(
	'classify: is_bot() catches crawlers, tools and empty user agents',
	function () {
		$bots = array(
			'Googlebot'            => FA_UA::GOOGLEBOT,
			'Googlebot smartphone' => FA_UA::GOOGLEBOT_M,
			'bingbot'              => FA_UA::BINGBOT,
			'HeadlessChrome'       => FA_UA::HEADLESS,
			'Lighthouse'           => FA_UA::LIGHTHOUSE,
			'curl'                 => FA_UA::CURL,
			'python-requests'      => FA_UA::PYTHON,
			'facebookexternalhit'  => FA_UA::FB_CRAWLER,
			'facebookexternalhit (Mozilla)' => FA_UA::FB_CRAWLER_M,
			'AhrefsBot'            => FA_UA::AHREFS,
			'GPTBot'               => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.0; +https://openai.com/gptbot)',
			'YandexBot'            => 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
			'Slackbot'             => 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
			'Mozilla + wget'       => 'Mozilla/5.0 (compatible; Wget/1.21)',
			'empty'                => '',
			'too short'            => 'Mozilla/5.0',
			'no Mozilla'           => 'Opera/9.80 (X11; Linux i686) Presto/2.12.388 Version/12.16',
		);
		foreach ( $bots as $label => $ua ) {
			FA_Test::ok( Free_Analytics_Classify::is_bot( $ua ), "$label is a bot" );
		}
		FA_Test::ok( Free_Analytics_Classify::is_bot( null ), 'null is a bot' );
	}
);

FA_Test::add(
	'classify: device()',
	function () {
		$map = array(
			'mobile'  => array( FA_UA::SAFARI_IOS, FA_UA::CHROME_AND, FA_UA::SAMSUNG, FA_UA::FIREFOX_AND, FA_UA::CHROME_IOS, FA_UA::FB_ANDROID, FA_UA::INSTAGRAM ),
			'tablet'  => array( FA_UA::TABLET_AND, FA_UA::IPAD ),
			'desktop' => array( FA_UA::CHROME_WIN, FA_UA::CHROME_MAC, FA_UA::SAFARI_MAC, FA_UA::FIREFOX_LNX, FA_UA::EDGE_WIN, FA_UA::CHROMEOS ),
		);
		foreach ( $map as $device => $uas ) {
			foreach ( $uas as $ua ) {
				FA_Test::eq( $device, Free_Analytics_Classify::device( $ua ), substr( $ua, 0, 70 ) );
			}
		}
	}
);

FA_Test::add(
	'classify: browser()',
	function () {
		$map = array(
			'Edge'             => FA_UA::EDGE_WIN,
			'Opera'            => FA_UA::OPERA_WIN,
			'Samsung Internet' => FA_UA::SAMSUNG,
			'Firefox'          => FA_UA::FIREFOX_WIN,
			'Chrome'           => FA_UA::CHROME_WIN,
			'Safari'           => FA_UA::SAFARI_IOS,
			'Facebook'         => FA_UA::FB_ANDROID,
			'Instagram'        => FA_UA::INSTAGRAM,
		);
		foreach ( $map as $name => $ua ) {
			FA_Test::eq( $name, Free_Analytics_Classify::browser( $ua ), $name );
		}
		FA_Test::eq( 'Safari', Free_Analytics_Classify::browser( FA_UA::SAFARI_MAC ), 'Safari macOS' );
		FA_Test::eq( 'Chrome', Free_Analytics_Classify::browser( FA_UA::CHROME_IOS ), 'Chrome on iOS (CriOS)' );
		FA_Test::eq( 'Facebook', Free_Analytics_Classify::browser( FA_UA::FB_IOS ), 'Facebook app on iOS' );
		FA_Test::eq( 'Firefox', Free_Analytics_Classify::browser( FA_UA::FIREFOX_AND ), 'Firefox Android' );
		FA_Test::eq( 'Chrome', Free_Analytics_Classify::browser( FA_UA::CHROME_AND ), 'Chrome Android' );
		FA_Test::eq( 'Chrome', Free_Analytics_Classify::browser( FA_UA::CHROMEOS ), 'Chrome ChromeOS' );
		FA_Test::eq( 'other', Free_Analytics_Classify::browser( 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)' ), 'unknown browser' );
		FA_Test::eq( 'other', Free_Analytics_Classify::browser( '' ), 'empty' );
	}
);

FA_Test::add(
	'classify: os()',
	function () {
		$map = array(
			'Windows'  => array( FA_UA::CHROME_WIN, FA_UA::FIREFOX_WIN, FA_UA::EDGE_WIN ),
			'Android'  => array( FA_UA::CHROME_AND, FA_UA::SAMSUNG, FA_UA::TABLET_AND, FA_UA::FB_ANDROID, FA_UA::FIREFOX_AND ),
			'iOS'      => array( FA_UA::SAFARI_IOS, FA_UA::IPAD, FA_UA::CHROME_IOS, FA_UA::FB_IOS ),
			'macOS'    => array( FA_UA::CHROME_MAC, FA_UA::SAFARI_MAC ),
			'Linux'    => array( FA_UA::FIREFOX_LNX ),
			'ChromeOS' => array( FA_UA::CHROMEOS ),
			'other'    => array( '', 'Mozilla/5.0 (PlayStation; PlayStation 5/2.26) AppleWebKit/605.1.15' ),
		);
		foreach ( $map as $name => $uas ) {
			foreach ( $uas as $ua ) {
				FA_Test::eq( $name, Free_Analytics_Classify::os( $ua ), substr( $ua, 0, 70 ) );
			}
		}
	}
);

FA_Test::add(
	'classify: host() strips www, lower-cases, understands android-app:// referrers',
	function () {
		$map = array(
			'https://www.google.bg/'                                  => 'google.bg',
			'https://www.google.com.tr/search?q=x'                    => 'google.com.tr',
			'HTTPS://WWW.Example.COM:8443/Path?a=1#b'                 => 'example.com',
			'https://l.facebook.com/l.php?u=https%3A%2F%2Fexample.com' => 'l.facebook.com',
			'android-app://com.google.android.googlequicksearchbox'   => 'com.google.android.googlequicksearchbox',
			'android-app://com.google.android.googlequicksearchbox/'  => 'com.google.android.googlequicksearchbox',
			'http://example.com./x'                                 => 'example.com',
			'https://www.www.example.com/'                            => 'www.example.com',
			''                                                        => '',
			'not a url'                                               => '',
			'/relative/path'                                          => '',
			'javascript:alert(1)'                                     => '',
			'https://exa mple.com/'                                   => '',
		);
		foreach ( $map as $url => $host ) {
			FA_Test::eq( $host, Free_Analytics_Classify::host( $url ), $url );
		}
	}
);

FA_Test::add(
	'classify: referrer_label()',
	function () {
		$map = array(
			'google.bg'                               => 'Google',
			'google.com'                              => 'Google',
			'google.com.tr'                           => 'Google',
			'google.co.uk'                            => 'Google',
			'www.google.com.tr'                       => 'Google',
			'maps.google.com'                         => 'Google',
			'l.facebook.com'                          => 'Facebook',
			'm.facebook.com'                          => 'Facebook',
			'facebook.com'                            => 'Facebook',
			'l.instagram.com'                         => 'Instagram',
			'example.org'                             => 'example.org',
			'news.example.org'                        => 'news.example.org',
			'bing.com'                                => 'Bing',
			'duckduckgo.com'                          => 'DuckDuckGo',
			't.co'                                    => 'X',
			'out.t.co'                                => 'X',
			'mail.google.com'                         => 'Gmail',
			'gemini.google.com'                       => 'Gemini',
			'com.google.android.googlequicksearchbox' => 'Google',
			'unknown-site.example'                    => 'unknown-site.example',
			'evilfacebook.com'                        => 'evilfacebook.com',
			'notgoogle.com'                           => 'notgoogle.com',
			'google.evil.com'                         => 'google.evil.com',
			'facebook.com.evil.example'               => 'facebook.com.evil.example',
		);
		foreach ( $map as $host => $label ) {
			FA_Test::eq( $label, Free_Analytics_Classify::referrer_label( $host ), $host );
		}
	}
);

FA_Test::add(
	'classify: source() priority is utm > gclid > referrer > fbclid / in-app browser',
	function () {
		$site = 'example.com';
		$src  = function ( $referrer, $utm = array(), $click = '', $ua = FA_UA::CHROME_WIN ) use ( $site ) {
			return Free_Analytics_Classify::source( $referrer, $site, $utm, $click, $ua );
		};

		$r = $src( 'https://www.google.com/', array( 'source' => 'newsletter', 'medium' => 'email', 'campaign' => 'spring' ), 'g', FA_UA::FB_ANDROID );
		FA_Test::eq( 'newsletter', $r['source'], 'utm beats gclid, referrer and in-app' );
		FA_Test::eq( 'email', $r['medium'], 'utm medium' );
		FA_Test::eq( 'spring', $r['campaign'], 'utm campaign' );
		FA_Test::eq( true, $r['external'], 'utm is external' );

		$r = $src( 'https://l.facebook.com/', array(), 'g', FA_UA::FB_ANDROID );
		FA_Test::eq( 'Google Ads', $r['source'], 'gclid beats the referrer and the in-app browser' );
		FA_Test::eq( true, $r['external'], 'gclid is external' );

		$r = $src( 'https://www.google.bg/search?q=mug', array(), '', FA_UA::FB_ANDROID );
		FA_Test::eq( 'Google', $r['source'], 'referrer beats the in-app browser' );
		FA_Test::eq( true, $r['external'], 'referrer is external' );

		$r = $src( 'https://l.instagram.com/?u=x', array(), 'f' );
		FA_Test::eq( 'Instagram', $r['source'], 'referrer beats fbclid' );

		$r = $src( '', array(), 'f' );
		FA_Test::eq( 'Facebook', $r['source'], 'fbclid without a referrer' );
		FA_Test::eq( true, $r['external'], 'fbclid is external' );

		$r = $src( '', array(), '', FA_UA::FB_ANDROID );
		FA_Test::eq( 'Facebook', $r['source'], 'Facebook in-app browser' );
		$r = $src( '', array(), '', FA_UA::FB_IOS );
		FA_Test::eq( 'Facebook', $r['source'], 'Facebook in-app browser (iOS)' );
		$r = $src( '', array(), '', FA_UA::INSTAGRAM );
		FA_Test::eq( 'Instagram', $r['source'], 'Instagram in-app browser' );

		$r = $src( 'android-app://com.google.android.googlequicksearchbox' );
		FA_Test::eq( 'Google', $r['source'], 'Google app referrer' );

		$r = $src( 'https://www.example.org/page' );
		FA_Test::eq( 'example.org', $r['source'], 'unknown host is its own source' );

		$r = $src( '', array( 'medium' => 'cpc' ) );
		FA_Test::eq( '', $r['source'], 'medium alone: no source' );
		FA_Test::eq( 'cpc', $r['medium'], 'medium alone: kept' );
		FA_Test::eq( true, $r['external'], 'medium alone is still external' );

		$r = $src( '', array( 'campaign' => 'x' ) );
		FA_Test::eq( true, $r['external'], 'campaign alone is external' );

		$r = $src( '' );
		FA_Test::eq( array( 'source' => '', 'medium' => '', 'campaign' => '', 'external' => false ), $r, 'direct visit' );
	}
);

FA_Test::add(
	'classify: source() ignores a referrer from this site (www, subdomain), not a look-alike',
	function () {
		$site = 'example.com';
		foreach ( array( 'https://example.com/shop/', 'https://www.example.com/shop/', 'https://shop.example.com/', 'http://EXAMPLE.COM/' ) as $ref ) {
			$r = Free_Analytics_Classify::source( $ref, $site, array(), '', FA_UA::CHROME_WIN );
			FA_Test::eq( '', $r['source'], "own referrer $ref: no source" );
			FA_Test::eq( false, $r['external'], "own referrer $ref: not external" );
		}

		$r = Free_Analytics_Classify::source( 'https://www.example.com/', $site, array(), '', FA_UA::FB_ANDROID );
		FA_Test::eq( 'Facebook', $r['source'], 'own referrer, Facebook in-app browser: falls through to the in-app source' );
		$r = Free_Analytics_Classify::source( 'https://example.com/', $site, array( 'source' => 'newsletter' ), '', FA_UA::CHROME_WIN );
		FA_Test::eq( 'newsletter', $r['source'], 'own referrer with utm: utm wins' );
		$r = Free_Analytics_Classify::source( 'https://example.com/', $site, array(), 'g', FA_UA::CHROME_WIN );
		FA_Test::eq( 'Google Ads', $r['source'], 'own referrer with gclid: gclid wins' );

		foreach ( array( 'https://evilexample.com/', 'https://example.com.evil.example/', 'https://example.net/' ) as $ref ) {
			$r = Free_Analytics_Classify::source( $ref, $site, array(), '', FA_UA::CHROME_WIN );
			FA_Test::eq( Free_Analytics_Classify::host( $ref ), $r['source'], "look-alike $ref is an external source" );
			FA_Test::eq( true, $r['external'], "look-alike $ref is external" );
		}
	}
);

FA_Test::add(
	'classify: target() accepts matching links and returns null for mismatches',
	function () {
		$t = array( 'Free_Analytics_Classify', 'target' );

		FA_Test::eq( '+359894222217', $t( 'tel', 'tel:+359894222217' ), 'tel' );
		FA_Test::eq( '0894222217', $t( 'tel', 'tel:0894 222 217' ), 'tel: spaces removed' );
		FA_Test::eq( '+359894222217', $t( 'tel', 'tel:%2B359894222217' ), 'tel: decoded' );
		FA_Test::eq( '0894222217', $t( 'tel', 'TEL:0894-222-217' ), 'tel: upper case scheme, dashes removed' );
		FA_Test::eq( null, $t( 'tel', 'https://example.com/tel:123' ), 'tel: not a tel link' );
		FA_Test::eq( null, $t( 'tel', 'mailto:info@example.com' ), 'tel event with a mailto link' );
		FA_Test::eq( null, $t( 'tel', '' ), 'tel: empty' );

		FA_Test::eq( 'info@example.com', $t( 'email', 'mailto:info@example.com' ), 'email' );
		FA_Test::eq( 'info@example.com', $t( 'email', 'mailto:info@example.com?subject=Hello%20there' ), 'email: query removed' );
		FA_Test::eq( 'info@example.com', $t( 'email', 'mailto:%69nfo@example.com' ), 'email: decoded' );
		FA_Test::eq( null, $t( 'email', 'tel:+359894222217' ), 'email event with a tel link' );
		FA_Test::eq( null, $t( 'email', 'info@example.com' ), 'email: no scheme' );

		FA_Test::eq( 'Google Maps', $t( 'map', 'https://www.google.com/maps/place/Sofia' ), 'map' );
		FA_Test::eq( 'Google Maps', $t( 'map', 'https://maps.google.com/?q=Sofia' ), 'map: maps.google.com' );
		FA_Test::eq( 'Google Maps', $t( 'map', 'https://maps.app.goo.gl/abc123' ), 'map: short link' );
		FA_Test::eq( 'Google Maps', $t( 'map', 'https://goo.gl/maps/abc123' ), 'map: old short link' );
		FA_Test::eq( null, $t( 'map', 'https://example.com/maps' ), 'map: other site' );
		FA_Test::eq( null, $t( 'map', 'tel:+359894222217' ), 'map event with a tel link' );

		FA_Test::eq( 'Viber', $t( 'viber', 'viber://chat?number=%2B359894222217' ), 'viber' );
		FA_Test::eq( null, $t( 'viber', 'https://www.viber.com/' ), 'viber: web link' );

		FA_Test::eq( 'WhatsApp', $t( 'whatsapp', 'https://wa.me/359894222217' ), 'whatsapp: wa.me' );
		FA_Test::eq( 'WhatsApp', $t( 'whatsapp', 'whatsapp://send?phone=359894222217' ), 'whatsapp: scheme' );
		FA_Test::eq( 'WhatsApp', $t( 'whatsapp', 'https://api.whatsapp.com/send?phone=359894222217' ), 'whatsapp: api' );
		FA_Test::eq( 'WhatsApp', $t( 'whatsapp', 'https://web.whatsapp.com/' ), 'whatsapp: web' );
		FA_Test::eq( null, $t( 'whatsapp', 'https://evil.example/wa.me/1' ), 'whatsapp: look-alike' );
		FA_Test::eq( null, $t( 'whatsapp', 'tel:+359894222217' ), 'whatsapp event with a tel link' );

		FA_Test::eq( 'brxe-abcdef', $t( 'form', 'brxe-abcdef' ), 'form: element ID' );
		FA_Test::eq( 'abcdef', $t( 'form', 'abc def!<>' ), 'form: only safe characters' );
		FA_Test::eq( '', $t( 'form', '' ), 'form: empty ID is allowed' );
		FA_Test::eq( 40, strlen( $t( 'form', str_repeat( 'a', 80 ) ) ), 'form: at most 40 characters' );

		FA_Test::eq( null, $t( 'pageview', 'tel:123' ), 'pageview has no target' );
		FA_Test::eq( null, $t( 'bogus', 'tel:123' ), 'unknown event' );
		FA_Test::eq( null, $t( '', 'tel:123' ), 'empty event' );
	}
);

FA_Test::add(
	'classify: EVENTS lists the seven events',
	function () {
		FA_Test::eq( array( 'pageview', 'tel', 'email', 'map', 'viber', 'whatsapp', 'form' ), Free_Analytics_Classify::EVENTS, 'events' );
		FA_Test::eq( Free_Analytics_Classify::EVENTS, array_keys( Free_Analytics_Classify::event_labels() ), 'every event has a label' );
		FA_Test::eq( array( 'desktop', 'mobile', 'tablet' ), array_keys( Free_Analytics_Classify::device_labels() ), 'device labels' );
	}
);
