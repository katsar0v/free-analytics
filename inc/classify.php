<?php
/**
 * Free Analytics - classifiers
 *
 * Pure functions that turn request data into the few labels the table keeps: device,
 * browser and OS names from the user agent, a traffic source from the referrer / UTM tags,
 * the type of a contact link, and a bot check. Nothing here stores the raw input.
 *
 * @package Free_Analytics
 * @subpackage Classify
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Free_Analytics_Classify
 */
class Free_Analytics_Classify {

	/**
	 * Events the endpoint accepts. Every event except pageview is a contact or a form.
	 *
	 * @var string[]
	 */
	const EVENTS = array( 'pageview', 'tel', 'email', 'map', 'viber', 'whatsapp', 'form' );

	/**
	 * User agents that are not people: crawlers, link previews, monitors, HTTP libraries, headless browsers.
	 *
	 * @var string
	 */
	const BOT_PATTERN = '~bot\b|bot/|crawl|spider|slurp|archiver|headless|phantomjs|selenium|puppeteer|playwright|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|curl/|wget|python|httpclient|http-client|okhttp|java/|go-http|axios|node-fetch|undici|libwww|scrapy|guzzle|php/|facebookexternalhit|facebookcatalog|meta-externalagent|preview|embedly|semrush|ahrefs|mj12|dotbot|petalbot|bytespider|yandex(?!browser)|gptbot|claudebot|chatgpt|oai-searchbot|perplexity|ccbot|applebot|dataprovider|google-inspectiontool|mediapartners|adsbot|feedfetcher|validator|check_http|zgrab|masscan~i';

	/**
	 * Known referrer hosts => source label. Matched against the host and its parent domains.
	 *
	 * @var array<string,string>
	 */
	const SOURCES = array(
		'bing.com'                                => 'Bing',
		'duckduckgo.com'                          => 'DuckDuckGo',
		'search.yahoo.com'                        => 'Yahoo',
		'yahoo.com'                               => 'Yahoo',
		'yandex.ru'                               => 'Yandex',
		'yandex.com'                              => 'Yandex',
		'search.brave.com'                        => 'Brave Search',
		'ecosia.org'                              => 'Ecosia',
		'facebook.com'                            => 'Facebook',
		'fb.com'                                  => 'Facebook',
		'fb.me'                                   => 'Facebook',
		'messenger.com'                           => 'Facebook',
		'instagram.com'                           => 'Instagram',
		't.co'                                    => 'X',
		'x.com'                                   => 'X',
		'twitter.com'                             => 'X',
		'tiktok.com'                              => 'TikTok',
		'youtube.com'                             => 'YouTube',
		'youtu.be'                                => 'YouTube',
		'linkedin.com'                            => 'LinkedIn',
		'lnkd.in'                                 => 'LinkedIn',
		'chatgpt.com'                             => 'ChatGPT',
		'chat.openai.com'                         => 'ChatGPT',
		'perplexity.ai'                           => 'Perplexity',
		'claude.ai'                               => 'Claude',
		'gemini.google.com'                       => 'Gemini',
		'com.google.android.googlequicksearchbox' => 'Google',
		'com.google.android.gm'                   => 'Gmail',
		'mail.google.com'                         => 'Gmail',
	);

	/**
	 * Labels for the stored device values.
	 *
	 * @since 1.0.0
	 * @return array<string,string>
	 */
	public static function device_labels() {
		return array(
			'desktop' => __( 'Desktop', 'free-analytics' ),
			'mobile'  => __( 'Mobile', 'free-analytics' ),
			'tablet'  => __( 'Tablet', 'free-analytics' ),
		);
	}

	/**
	 * Labels for the events.
	 *
	 * @since 1.0.0
	 * @return array<string,string>
	 */
	public static function event_labels() {
		return array(
			'pageview' => __( 'Page view', 'free-analytics' ),
			'tel'      => __( 'Phone call', 'free-analytics' ),
			'email'    => __( 'Email', 'free-analytics' ),
			'map'      => __( 'Directions (map)', 'free-analytics' ),
			'viber'    => 'Viber',
			'whatsapp' => 'WhatsApp',
			'form'     => __( 'Form submission', 'free-analytics' ),
		);
	}

	/**
	 * Whether the user agent belongs to a bot (or is missing).
	 *
	 * @since 1.0.0
	 * @param string $ua User agent.
	 * @return bool
	 */
	public static function is_bot( $ua ) {
		$ua = (string) $ua;
		if ( strlen( $ua ) < 20 || false === stripos( $ua, 'mozilla/' ) ) {
			return true; // Every real browser sends "Mozilla/5.0 (...)".
		}

		return (bool) preg_match( self::BOT_PATTERN, $ua );
	}

	/**
	 * Device class.
	 *
	 * iPadOS Safari sends a desktop macOS user agent, so those iPads count as desktop.
	 *
	 * @since 1.0.0
	 * @param string $ua User agent.
	 * @return string desktop, mobile or tablet.
	 */
	public static function device( $ua ) {
		$ua = (string) $ua;
		// Android tablets omit "Mobile" everywhere; phones (including in-app browsers) have it somewhere.
		if ( preg_match( '~iPad|Tablet|PlayBook|Silk/|Kindle|SM-T\d~i', $ua ) || ( false !== stripos( $ua, 'Android' ) && false === stripos( $ua, 'Mobile' ) ) ) {
			return 'tablet';
		}
		if ( preg_match( '~Mobi|iPhone|iPod|Android|Windows Phone|BlackBerry|Opera Mini~i', $ua ) ) {
			return 'mobile';
		}

		return 'desktop';
	}

	/**
	 * Browser name. Order matters: most browsers also say "Chrome" and "Safari".
	 *
	 * @since 1.0.0
	 * @param string $ua User agent.
	 * @return string Browser name, or "other".
	 */
	public static function browser( $ua ) {
		$map = array(
			'~FBAN|FBAV|FB_IAB|FBIOS~'      => 'Facebook',
			'~Instagram~'                   => 'Instagram',
			'~Viber~'                       => 'Viber',
			'~Edg(e|A|iOS)?/~'              => 'Edge',
			'~OPR/|Opera|OPiOS|OPT/~'       => 'Opera',
			'~SamsungBrowser~'              => 'Samsung Internet',
			'~YaBrowser~'                   => 'Yandex',
			'~Firefox/|FxiOS/~'             => 'Firefox',
			'~Chrome/|CriOS/|Chromium/~'    => 'Chrome',
			'~Version/[\d.]+.*Safari/~'     => 'Safari',
			'~AppleWebKit.*Mobile/(?!.*Safari)~' => 'Safari',
		);
		foreach ( $map as $pattern => $name ) {
			if ( preg_match( $pattern, (string) $ua ) ) {
				return $name;
			}
		}

		return 'other';
	}

	/**
	 * Operating system name.
	 *
	 * @since 1.0.0
	 * @param string $ua User agent.
	 * @return string OS name, or "other".
	 */
	public static function os( $ua ) {
		$map = array(
			'~Windows~'                => 'Windows',
			'~Android~'                => 'Android',
			'~iPhone|iPad|iPod|iOS~'   => 'iOS',
			'~Mac OS X|Macintosh~'     => 'macOS',
			'~CrOS~'                   => 'ChromeOS',
			'~Linux|X11~'              => 'Linux',
		);
		foreach ( $map as $pattern => $name ) {
			if ( preg_match( $pattern, (string) $ua ) ) {
				return $name;
			}
		}

		return 'other';
	}

	/**
	 * Lower-case host of a URL without "www." (also for android-app:// referrers).
	 *
	 * @since 1.0.0
	 * @param string $url URL.
	 * @return string Host, or '' when there is none.
	 */
	public static function host( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}
		$host = strtolower( rtrim( $host, '.' ) );
		if ( ! preg_match( '/^[a-z0-9.-]{1,100}$/', $host ) ) {
			return '';
		}

		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * Source label for a referrer host: a known name, "Google" for any google.* host, or the host.
	 *
	 * @since 1.0.0
	 * @param string $host Host from host().
	 * @return string
	 */
	public static function referrer_label( $host ) {
		$parts = explode( '.', $host );
		for ( $i = 0, $n = count( $parts ); $i < $n - 1; $i++ ) {
			$candidate = implode( '.', array_slice( $parts, $i ) );
			if ( isset( self::SOURCES[ $candidate ] ) ) {
				return self::SOURCES[ $candidate ];
			}
		}
		if ( preg_match( '/(^|\.)google\.[a-z]{2,3}(\.[a-z]{2})?$/', $host ) ) {
			return 'Google';
		}

		return $host;
	}

	/**
	 * Traffic source of a hit.
	 *
	 * Priority: utm_source, then a Google Ads click ID, then the referrer, then a Facebook click
	 * ID or a Facebook/Instagram in-app browser. A referrer from this site is not a source.
	 *
	 * @since 1.0.0
	 * @param string $referrer  document.referrer.
	 * @param string $site_host Host of this site (from host()).
	 * @param array  $utm       Keys source, medium, campaign (already sanitised).
	 * @param string $click     'g' for gclid/gbraid/wbraid, 'f' for fbclid, '' otherwise.
	 * @param string $ua        User agent.
	 * @return array{source:string,medium:string,campaign:string,external:bool} external = the hit brings its own source.
	 */
	public static function source( $referrer, $site_host, $utm, $click, $ua ) {
		$out = array(
			'source'   => '',
			'medium'   => isset( $utm['medium'] ) ? (string) $utm['medium'] : '',
			'campaign' => isset( $utm['campaign'] ) ? (string) $utm['campaign'] : '',
			'external' => false,
		);

		$ref_host = self::host( $referrer );
		if ( '' !== $ref_host && ( $ref_host === $site_host || '.' . $site_host === substr( $ref_host, -strlen( $site_host ) - 1 ) ) ) {
			$ref_host = ''; // Internal navigation.
		}

		if ( ! empty( $utm['source'] ) ) {
			$out['source'] = self::utm_label( (string) $utm['source'] );
		} elseif ( 'g' === $click ) {
			$out['source'] = 'Google Ads';
		} elseif ( '' !== $ref_host ) {
			$out['source'] = self::referrer_label( $ref_host );
		} elseif ( 'f' === $click || preg_match( '~FBAN|FBAV|FB_IAB|FBIOS~', (string) $ua ) ) {
			$out['source'] = 'Facebook';
		} elseif ( preg_match( '~Instagram~', (string) $ua ) ) {
			$out['source'] = 'Instagram';
		}

		$out['external'] = '' !== $out['source'] || '' !== $out['medium'] || '' !== $out['campaign'];

		return $out;
	}

	/**
	 * A utm_source that names a known source ("facebook", "GOOGLE") gets that source's label,
	 * so it is grouped with the visits from the same site's referrer.
	 *
	 * @since 1.0.0
	 * @param string $source utm_source.
	 * @return string
	 */
	public static function utm_label( $source ) {
		$key = strtolower( trim( $source ) );
		foreach ( array_merge( array_values( self::SOURCES ), array( 'Google' ) ) as $label ) {
			if ( strtolower( $label ) === $key ) {
				return $label;
			}
		}
		$aliases = array(
			'fb' => 'Facebook',
			'ig' => 'Instagram',
		);

		return isset( $aliases[ $key ] ) ? $aliases[ $key ] : $source;
	}

	/**
	 * Checks that a contact event's link matches its type and returns the value to store.
	 *
	 * @since 1.0.0
	 * @param string $event Event name.
	 * @param string $href  Link (or the Bricks form element ID for "form").
	 * @return string|null The stored target, or null when the link does not match the event.
	 */
	public static function target( $event, $href ) {
		$href = trim( (string) $href );

		switch ( $event ) {
			case 'tel':
				if ( ! preg_match( '/^tel:/i', $href ) ) {
					return null;
				}
				return substr( preg_replace( '/[^0-9+]/', '', rawurldecode( substr( $href, 4 ) ) ), 0, 20 );

			case 'email':
				if ( ! preg_match( '/^mailto:([^?]+)/i', $href, $m ) ) {
					return null;
				}
				return substr( sanitize_email( rawurldecode( $m[1] ) ), 0, 100 );

			case 'map':
				return self::is_map_link( $href ) ? 'Google Maps' : null;

			case 'viber':
				return preg_match( '/^viber:/i', $href ) ? 'Viber' : null;

			case 'whatsapp':
				return preg_match( '~^(whatsapp:|https?://(wa\.me|(api|web)\.whatsapp\.com)/)~i', $href ) ? 'WhatsApp' : null;

			case 'form':
				return substr( preg_replace( '/[^A-Za-z0-9_.:-]/', '', $href ), 0, 40 );
		}

		return null;
	}

	/**
	 * Whether a URL opens Google Maps.
	 *
	 * @since 1.0.0
	 * @param string $href URL.
	 * @return bool
	 */
	public static function is_map_link( $href ) {
		return (bool) preg_match( '~^https?://((www\.)?google\.[a-z]{2,3}(\.[a-z]{2})?/maps|maps\.google\.[a-z]{2,3}(\.[a-z]{2})?/|maps\.app\.goo\.gl/|goo\.gl/maps)~i', (string) $href );
	}
}
