<?php
/**
 * Free Analytics - front-end beacon and REST endpoint
 *
 * The beacon is loaded on public pages for visitors who are not logged in. It posts to
 * /wp-json/free-analytics/v1/hit, which needs no nonce, so pages served from a cache
 * still report correctly.
 *
 * @package Free_Analytics
 * @subpackage Tracker
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Free_Analytics_Tracker
 */
class Free_Analytics_Tracker {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'free-analytics/v1';

	/**
	 * Script handle.
	 *
	 * @var string
	 */
	const HANDLE = 'free-analytics';

	/**
	 * Registers the hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_filter( 'rocket_delay_js_exclusions', array( __CLASS__, 'exclude_from_delay' ) );
	}

	/**
	 * Keeps the beacon out of WP Rocket's "Delay JavaScript execution".
	 *
	 * Delayed scripts run only after the first click, scroll or key press, so visitors who leave
	 * without one would not be counted. Patterns match the script URL and the inline config.
	 * Other optimisers with a delay feature (LiteSpeed Cache, Perfmatters, FlyingPress) need
	 * "free-analytics" and "freeAnalyticsConfig" added to their exclusions by hand.
	 *
	 * @since 1.0.0
	 * @param array $exclusions Patterns.
	 * @return array
	 */
	public static function exclude_from_delay( $exclusions ) {
		$exclusions   = is_array( $exclusions ) ? $exclusions : array();
		$exclusions[] = 'free-analytics';
		$exclusions[] = 'freeAnalyticsConfig';

		return $exclusions;
	}

	/**
	 * Whether this page view should load the beacon.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function should_track() {
		if ( is_user_logged_in() || is_preview() || is_customize_preview() || is_admin() || is_feed() || is_robots() ) {
			return false;
		}
		if ( self::in_page_builder() ) {
			return false;
		}

		/**
		 * Filters whether the analytics beacon is loaded on this page.
		 *
		 * @since 1.0.0
		 * @param bool $track True to load it.
		 */
		return (bool) apply_filters( 'free_analytics_track', true );
	}

	/**
	 * Whether the page is rendered inside a page builder's editor or preview.
	 *
	 * Builders usually log the user in anyway; these checks also cover previews opened
	 * with a token. Each check runs only when that builder is installed.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function in_page_builder() {
		// Bricks.
		foreach ( array( 'bricks_is_builder', 'bricks_is_builder_main', 'bricks_is_builder_iframe', 'bricks_is_builder_call' ) as $fn ) {
			if ( function_exists( $fn ) && call_user_func( $fn ) ) {
				return true;
			}
		}
		// Divi visual builder.
		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			return true;
		}
		// Beaver Builder.
		if ( class_exists( 'FLBuilderModel' ) && is_callable( array( 'FLBuilderModel', 'is_builder_active' ) ) && FLBuilderModel::is_builder_active() ) {
			return true;
		}
		// Elementor editor and preview.
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return true;
		}
		// Oxygen and Breakdance editors use a query argument.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only.
		foreach ( array( 'ct_builder', 'breakdance', 'fl_builder', 'et_fb', 'elementor-preview' ) as $arg ) {
			if ( isset( $_GET[ $arg ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueues the beacon with its settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::should_track() ) {
			return;
		}

		$file = FREE_ANALYTICS_DIR . 'assets/js/tracker.js';
		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'assets/js/tracker.js', FREE_ANALYTICS_FILE ),
			array(),
			(string) filemtime( $file ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$config = array(
			'u'  => rest_url( self::REST_NAMESPACE . '/hit' ),
			'id' => is_singular() ? (int) get_queried_object_id() : 0,
			'nf' => is_404() ? 1 : 0,
		);
		wp_add_inline_script( self::HANDLE, 'window.freeAnalyticsConfig=' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Registers POST /hit.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/hit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_hit' ),
				'permission_callback' => '__return_true', // Public by design: anonymous visitors, cached pages, no nonce.
			)
		);
	}

	/**
	 * Handles a beacon. Always answers without a body; 204 also for bots and logged-in users,
	 * so a bot learns nothing from the status.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_hit( $request ) {
		// The REST cookie check drops the user without a nonce, so look at the login cookie itself:
		// staff must not count even when a cached page loaded the beacon for them.
		if ( wp_validate_auth_cookie( '', 'logged_in' ) ) {
			$status = 'staff';
		} else {
			$status = Free_Analytics_Recorder::handle( (string) $request->get_body(), Free_Analytics_Recorder::environment() );
		}

		$codes    = array(
			'invalid' => 400,
			'foreign' => 403,
			'limited' => 429,
		);
		$response = new WP_REST_Response( null, isset( $codes[ $status ] ) ? $codes[ $status ] : 204 );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
