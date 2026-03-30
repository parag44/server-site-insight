<?php
/**
 * SSI_Tracker — stores daily snapshots of key metrics in wp_options.
 *
 * Uses a circular buffer: maximum SSI_HISTORY_LIMIT entries.
 * Each snapshot is lightweight — only scalar values, no full info dump.
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SSI_Tracker {

	const OPTION_KEY = 'ssi_history';

	/**
	 * Capture a snapshot if none has been stored today.
	 * Called on every dashboard page load (idempotent).
	 */
	public static function maybe_capture() {
		$history = self::get_history();
		$today   = gmdate( 'Y-m-d' );

		// Skip if we already have a record for today.
		if ( ! empty( $history ) && isset( $history[ count( $history ) - 1 ]['date'] ) && $history[ count( $history ) - 1 ]['date'] === $today ) {
			return;
		}

		$info    = SSI_System_Info::get_all();
		$checks  = SSI_Health_Check::run( $info );
		$score   = SSI_Health_Score::calculate( $checks );

		$snapshot = array(
			'date'           => $today,
			'timestamp'      => time(),
			'score'          => $score,
			'overall'        => SSI_Health_Check::overall_status( $checks ),
			'php_version'    => $info['server']['php_version'],
			'wp_version'     => $info['wordpress']['wp_version'],
			'memory_limit'   => $info['server']['memory_limit'],
			'plugin_count'   => $info['wordpress']['plugin_count'],
			'debug_mode'     => $info['environment']['debug_mode'],
			'https'          => $info['environment']['https'],
			'mysql_version'  => $info['server']['mysql_version'],
		);

		// Append and trim to limit.
		$history[] = $snapshot;
		if ( count( $history ) > SSI_HISTORY_LIMIT ) {
			$history = array_slice( $history, -SSI_HISTORY_LIMIT );
		}

		update_option( self::OPTION_KEY, $history, false ); // autoload=false
	}

	/**
	 * Return stored history array (newest last).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_history() {
		$data = get_option( self::OPTION_KEY, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Return the most recent snapshot or null.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_latest() {
		$history = self::get_history();
		return ! empty( $history ) ? end( $history ) : null;
	}

	/**
	 * Detect changes between the two most recent snapshots.
	 *
	 * @return array<string, array{from: mixed, to: mixed}>  Changed keys.
	 */
	public static function get_changes() {
		$history = self::get_history();
		if ( count( $history ) < 2 ) {
			return array();
		}
		$prev    = $history[ count( $history ) - 2 ];
		$current = end( $history );
		$changed = array();

		$tracked_keys = array( 'php_version', 'wp_version', 'memory_limit', 'plugin_count', 'debug_mode', 'https', 'mysql_version', 'score' );
		foreach ( $tracked_keys as $k ) {
			if ( isset( $prev[ $k ], $current[ $k ] ) && $prev[ $k ] !== $current[ $k ] ) {
				$changed[ $k ] = array( 'from' => $prev[ $k ], 'to' => $current[ $k ] );
			}
		}
		return $changed;
	}

	/**
	 * Format a timestamp as a localised "time ago" string.
	 *
	 * @param int $timestamp  Unix timestamp.
	 * @return string
	 */
	public static function time_ago( $timestamp ) {
		$diff = time() - (int) $timestamp;
		if ( $diff < 60 ) {
			return __( 'Just now', 'server-site-insight' );
		}
		if ( $diff < 3600 ) {
			$mins = (int) round( $diff / 60 );
			return sprintf(
				/* translators: %d: number of minutes elapsed */
				_n( '%d minute ago', '%d minutes ago', $mins, 'server-site-insight' ),
				$mins
			);
		}
		if ( $diff < 86400 ) {
			$hours = (int) round( $diff / 3600 );
			return sprintf(
				/* translators: %d: number of hours elapsed */
				_n( '%d hour ago', '%d hours ago', $hours, 'server-site-insight' ),
				$hours
			);
		}
		$days = (int) round( $diff / 86400 );
		return sprintf(
			/* translators: %d: number of days elapsed */
			_n( '%d day ago', '%d days ago', $days, 'server-site-insight' ),
			$days
		);
	}
}
