<?php
/**
 * Free Analytics - WP-CLI commands
 *
 * @package Free_Analytics
 * @subpackage CLI
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Visitor statistics.
 */
class Free_Analytics_CLI {

	/**
	 * Prints the totals and the top pages for a period.
	 *
	 * ## OPTIONS
	 *
	 * [--from=<date>]
	 * : First day (Y-m-d, site timezone). Default: 6 days before --to.
	 *
	 * [--to=<date>]
	 * : Last day (Y-m-d, site timezone). Default: today.
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp free-analytics summary
	 *     wp free-analytics summary --from=2026-10-01 --to=2026-10-31 --format=json
	 *
	 * @since 1.0.0
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function summary( $args, $assoc_args ) {
		$to   = isset( $assoc_args['to'] ) ? $assoc_args['to'] : Free_Analytics_Clock::today();
		$from = isset( $assoc_args['from'] ) ? $assoc_args['from'] : Free_Analytics_Clock::add_days( $to, -6 );
		if ( ! Free_Analytics_Clock::is_date( $from ) || ! Free_Analytics_Clock::is_date( $to ) || $from > $to ) {
			WP_CLI::error( 'Use --from and --to as Y-m-d, with --from <= --to.' );
		}

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		if ( ! in_array( $format, array( 'table', 'json' ), true ) ) {
			WP_CLI::error( 'Use --format=table or --format=json.' );
		}

		$stats  = new Free_Analytics_Stats( $from, $to );
		$totals = $stats->totals();
		$pages  = $stats->top_pages( 10 );

		if ( 'json' === $format ) {
			WP_CLI::line(
				wp_json_encode(
					array(
						'from'      => $from,
						'to'        => $to,
						'totals'    => $totals,
						'top_pages' => $pages,
						'sources'   => $stats->sources( 10 ),
						'online'    => Free_Analytics_Stats::online(),
					),
					JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				)
			);
			return;
		}

		WP_CLI::log( "Period {$from} .. {$to}" );
		$rows = array();
		foreach ( array( 'visitors', 'visits', 'pageviews', 'bounce_rate', 'pages_per_visit', 'contacts', 'forms' ) as $key ) {
			$rows[] = array(
				'metric' => $key,
				'value'  => null === $totals[ $key ] ? '-' : $totals[ $key ],
			);
		}
		foreach ( $totals['events'] as $event => $count ) {
			$rows[] = array(
				'metric' => 'event:' . $event,
				'value'  => $count,
			);
		}
		$rows[] = array(
			'metric' => 'online (5 min)',
			'value'  => Free_Analytics_Stats::online(),
		);
		WP_CLI\Utils\format_items( 'table', $rows, array( 'metric', 'value' ) );

		if ( $pages ) {
			WP_CLI::log( 'Top pages' );
			WP_CLI\Utils\format_items( 'table', $pages, array( 'path', 'views', 'visitors' ) );
		}
	}

	/**
	 * Deletes rows older than the retention period (13 months) and old salts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp free-analytics prune
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prune() {
		$deleted = Free_Analytics_Cron::prune();
		WP_CLI::success( sprintf( 'Deleted %d rows older than %s.', $deleted, Free_Analytics_Cron::cutoff() ) );
	}
}
