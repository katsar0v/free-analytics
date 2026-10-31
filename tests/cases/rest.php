<?php
/**
 * REST cases: POST /free-analytics/v1/hit through the real REST server.
 *
 * @package Free_Analytics
 * @subpackage Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatches a request to the hit route and returns the response.
 *
 * @param string $body   Request body.
 * @param string $method HTTP method.
 * @return WP_REST_Response
 */
function fa_test_rest( $body, $method = 'POST' ) {
	global $wp_rest_server;

	// A fresh server, so the route table is built by rest_api_init with the plugin's route in it.
	if ( ! has_action( 'rest_api_init', array( 'Free_Analytics_Tracker', 'register_route' ) ) ) {
		Free_Analytics_Tracker::init();
	}
	$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	rest_get_server();

	$request = new WP_REST_Request( $method, '/free-analytics/v1/hit' );
	$request->set_header( 'content-type', 'text/plain' ); // What sendBeacon( Blob text/plain ) sends.
	$request->set_body( $body );

	return rest_do_request( $request );
}

/**
 * Sets the $_SERVER values the recorder reads.
 *
 * @param string $ua UA.
 * @param string $ip IP.
 * @return void
 */
function fa_test_rest_server( $ua = FA_UA::CHROME_WIN, $ip = '203.0.113.7' ) {
	FA_Sandbox::server(
		array(
			'REMOTE_ADDR'         => $ip,
			'HTTP_USER_AGENT'     => $ua,
			'HTTP_ORIGIN'         => null,
			'HTTP_SEC_FETCH_SITE' => null,
		)
	);
}

FA_Test::add(
	'rest: the hit route is registered for POST only',
	function () {
		fa_test_rest_server();
		fa_test_rest( '' );
		$routes = rest_get_server()->get_routes();
		FA_Test::ok( isset( $routes['/free-analytics/v1/hit'] ), 'route /free-analytics/v1/hit exists' );
		$methods = array();
		foreach ( (array) ( $routes['/free-analytics/v1/hit'] ?? array() ) as $handler ) {
			$methods = array_merge( $methods, array_keys( $handler['methods'] ) );
		}
		FA_Test::eq( array( 'POST' ), array_values( array_unique( $methods ) ), 'POST only' );

		$response = fa_test_rest( FA_Sandbox::body(), 'GET' );
		FA_Test::ok( in_array( $response->get_status(), array( 404, 405 ), true ), 'GET is refused: ' . $response->get_status() );
		FA_Test::eq( 0, FA_Sandbox::count_rows(), 'nothing recorded' );
	}
);

FA_Test::add(
	'rest: a valid body gives 204, no body, no-store, and one row',
	function () {
		FA_Sandbox::freeze( '2026-03-10 12:00:00' );
		fa_test_rest_server();

		$response = fa_test_rest( FA_Sandbox::body( array( 'p' => '/shop/blue-mug/', 'r' => 'https://www.google.bg/' ) ) );
		FA_Test::eq( 204, $response->get_status(), 'status' );
		FA_Test::eq( null, $response->get_data(), 'no body' );
		$headers = array_change_key_case( $response->get_headers(), CASE_LOWER );
		FA_Test::eq( 'no-store', $headers['cache-control'] ?? '', 'Cache-Control: no-store' );

		$rows = FA_Sandbox::rows();
		FA_Test::eq( 1, count( $rows ), 'one row' );
		FA_Test::eq( array( '/shop/blue-mug/', 'Google', 'Chrome', 'Windows', 'desktop' ), array( $rows[0]['path'], $rows[0]['source'], $rows[0]['browser'], $rows[0]['os'], $rows[0]['device'] ), 'row content' );
	}
);

FA_Test::add(
	'rest: an invalid body gives 400 and writes nothing',
	function () {
		fa_test_rest_server();

		foreach ( array( '', '{bad json', '{"e":"purchase","p":"/"}', '{"e":"pageview","p":"//evil"}', '{"e":"tel","p":"/","t":"https://example.com"}', str_repeat( 'x', 5000 ) ) as $body ) {
			$response = fa_test_rest( $body );
			FA_Test::eq( 400, $response->get_status(), 'body: ' . substr( $body, 0, 50 ) );
		}
		FA_Test::eq( 0, FA_Sandbox::count_rows(), 'nothing recorded' );
	}
);

FA_Test::add(
	'rest: a foreign Origin gives 403; bots and staff get 204 and leave no row',
	function () {
		fa_test_rest_server();
		FA_Sandbox::server( array( 'HTTP_ORIGIN' => 'https://evil.example' ) );
		FA_Test::eq( 403, fa_test_rest( FA_Sandbox::body() )->get_status(), 'foreign Origin' );
		FA_Sandbox::server( array( 'HTTP_ORIGIN' => null, 'HTTP_SEC_FETCH_SITE' => 'cross-site' ) );
		FA_Test::eq( 403, fa_test_rest( FA_Sandbox::body() )->get_status(), 'cross-site fetch' );
		FA_Sandbox::server( array( 'HTTP_SEC_FETCH_SITE' => null ) );
		FA_Test::eq( 0, FA_Sandbox::count_rows(), 'foreign requests write nothing' );

		fa_test_rest_server( FA_UA::GOOGLEBOT );
		FA_Test::eq( 204, fa_test_rest( FA_Sandbox::body() )->get_status(), 'bot: 204 (it learns nothing)' );
		fa_test_rest_server( FA_UA::CHROME_WIN );
		FA_Test::eq( 0, FA_Sandbox::count_rows(), 'bot wrote nothing' );

		// Staff: a valid logged_in cookie, even without a REST nonce.
		global $wpdb;
		$user_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} ORDER BY ID LIMIT 1" ); // phpcs:ignore WordPress.DB
		if ( ! $user_id ) {
			return FA_Test::skip( 'no user in the sandbox to make a login cookie for' );
		}
		$expiration = time() + 3600;
		$token      = WP_Session_Tokens::get_instance( $user_id )->create( $expiration );
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );
		FA_Test::ok( false !== wp_validate_auth_cookie( '', 'logged_in' ), 'test setup: the login cookie is valid' );
		FA_Test::eq( 204, fa_test_rest( FA_Sandbox::body() )->get_status(), 'staff: 204' );
		FA_Test::eq( 0, FA_Sandbox::count_rows(), 'staff is not counted' );
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		wp_set_current_user( 0 );

		FA_Test::eq( 204, fa_test_rest( FA_Sandbox::body() )->get_status(), 'the same request without the cookie' );
		FA_Test::eq( 1, FA_Sandbox::count_rows(), 'is counted' );
	}
);

FA_Test::add(
	'rest: the beacon script config: the route URL ends in /free-analytics/v1/hit',
	function () {
		FA_Test::eq( 'free-analytics/v1', Free_Analytics_Tracker::REST_NAMESPACE, 'namespace' );
		FA_Test::ok( (bool) preg_match( '~/free-analytics/v1/hit$~', rest_url( Free_Analytics_Tracker::REST_NAMESPACE . '/hit' ) ), 'rest_url' );
	}
);

FA_Test::add(
	'tracker: should_track() is on for visitors, off for logged-in users and when the filter says so',
	function () {
		FA_Test::eq( true, Free_Analytics_Tracker::should_track(), 'anonymous visitor' );

		add_filter( 'free_analytics_track', '__return_false' );
		FA_Test::eq( false, Free_Analytics_Tracker::should_track(), 'filter returns false' );
		remove_filter( 'free_analytics_track', '__return_false' );

		global $wpdb;
		$user_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} ORDER BY ID LIMIT 1" ); // phpcs:ignore WordPress.DB
		if ( $user_id ) {
			wp_set_current_user( $user_id );
			FA_Test::eq( false, Free_Analytics_Tracker::should_track(), 'logged-in user' );
			wp_set_current_user( 0 );
		}
	}
);

FA_Test::add(
	'tracker: enqueue() adds the deferred beacon with its config; exclude_from_delay() protects it from WP Rocket',
	function () {
		wp_deregister_script( Free_Analytics_Tracker::HANDLE );
		Free_Analytics_Tracker::enqueue();

		FA_Test::ok( wp_script_is( Free_Analytics_Tracker::HANDLE, 'enqueued' ), 'script is enqueued' );
		$scripts = wp_scripts();
		$before  = trim( implode( "\n", (array) $scripts->get_data( Free_Analytics_Tracker::HANDLE, 'before' ) ) );
		FA_Test::ok( 0 === strpos( $before, 'window.freeAnalyticsConfig=' ), 'inline config comes first: ' . $before );
		$config = json_decode( substr( rtrim( $before, ';' ), strlen( 'window.freeAnalyticsConfig=' ) ), true );
		FA_Test::eq( array( 'u', 'id', 'nf' ), array_keys( (array) $config ), 'config keys' );
		FA_Test::ok( (bool) preg_match( '~^https://example\.com/.*free-analytics/v1/hit$~', (string) ( $config['u'] ?? '' ) ), 'config URL: ' . ( $config['u'] ?? '' ) );
		FA_Test::eq( array( 0, 0 ), array( $config['id'] ?? null, $config['nf'] ?? null ), 'no post, not a 404' );
		FA_Test::eq( 'defer', $scripts->registered[ Free_Analytics_Tracker::HANDLE ]->extra['strategy'] ?? null, 'defer strategy' );
		wp_dequeue_script( Free_Analytics_Tracker::HANDLE );
		wp_deregister_script( Free_Analytics_Tracker::HANDLE );

		add_filter( 'free_analytics_track', '__return_false' );
		Free_Analytics_Tracker::enqueue();
		FA_Test::ok( ! wp_script_is( Free_Analytics_Tracker::HANDLE, 'enqueued' ), 'not enqueued when tracking is off' );
		remove_filter( 'free_analytics_track', '__return_false' );

		$ex = Free_Analytics_Tracker::exclude_from_delay( array( 'jquery' ) );
		FA_Test::eq( array( 'jquery', 'free-analytics', 'freeAnalyticsConfig' ), $ex, 'WP Rocket delay exclusions' );
		FA_Test::eq( array( 'free-analytics', 'freeAnalyticsConfig' ), Free_Analytics_Tracker::exclude_from_delay( null ), 'null exclusions' );
	}
);
