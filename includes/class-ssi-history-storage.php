<?php
/**
 * History Storage — manages the data options for the Site Activity Timeline.
 *
 * Stores both chronological events (timeline) and daily snapshots (for the chart).
 * Uses structured arrays saved to wp_options.
 *
 * @package Server_Site_Insight
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SSI_History_Storage {

	const OPTION_TIMELINE = 'ssi_activity_timeline';
	const OPTION_HISTORY  = 'ssi_history'; // Legacy / Chart support
	const MAX_EVENTS      = 500;

	/**
	 * Get the full activity timeline.
	 *
	 * @return array List of event arrays.
	 */
	public static function get_timeline() {
		$timeline = get_option( self::OPTION_TIMELINE, array() );
		return is_array( $timeline ) ? $timeline : array();
	}

	/**
	 * Add a new event to the timeline.
	 *
	 * @param string $type      Event category: 'content', 'user', 'system', 'plugin', 'theme'.
	 * @param string $severity  Severity: 'info', 'warning', 'critical', 'good'.
	 * @param string $message   Human-readable log message.
	 * @param array  $context   Array of supplementary details.
	 */
	public static function add_event( $type, $severity, $message, $context = array() ) {
		$timeline = self::get_timeline();
		$user     = wp_get_current_user();
		
		// Capture IP Address
		$ip = '0.0.0.0';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		$event = array(
			'id'        => wp_generate_uuid4(),
			'timestamp' => microtime( true ), // Use microtime for milliseconds
			'type'      => $type,
			'severity'  => $severity,
			'message'   => $message,
			'user_id'   => $user ? $user->ID : 0,
			'user_name' => ( $user && $user->display_name ) ? $user->display_name : 'System',
			'user_role' => ( $user && ! empty( $user->roles ) ) ? reset( $user->roles ) : 'none',
			'user_ip'   => sanitize_text_field( $ip ),
			'context'   => $context,
		);

		// Prepend so newest is first.
		array_unshift( $timeline, $event );

		// Cap the size.
		if ( count( $timeline ) > self::MAX_EVENTS ) {
			$timeline = array_slice( $timeline, 0, self::MAX_EVENTS );
		}

		update_option( self::OPTION_TIMELINE, $timeline, false );
	}

	/**
	 * Retrieve chart history (daily snapshots).
	 *
	 * @return array
	 */
	public static function get_history() {
		$data = get_option( self::OPTION_HISTORY, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Return the most recent snapshot or null.
	 *
	 * @return array|null
	 */
	public static function get_latest() {
		$history = self::get_history();
		return ! empty( $history ) ? end( $history ) : null;
	}

	/**
	 * Detect changes between the two most recent snapshots.
	 *
	 * @return array
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
	 * Update the daily history snapshot used by the chart.
	 *
	 * @param array $snapshot Current state array.
	 */
	public static function update_daily_history( $snapshot ) {
		$history = self::get_history();
		$today   = gmdate( 'Y-m-d' );

		// Update or append today's record
		$found = false;
		foreach ( $history as &$record ) {
			if ( $record['date'] === $today ) {
				$record = $snapshot; // Update today's entry with latest stats
				$found  = true;
				break;
			}
		}

		if ( ! $found ) {
			$history[] = $snapshot;
			// Keep max 14 days for the chart.
			if ( count( $history ) > 14 ) {
				$history = array_slice( $history, -14 );
			}
		}

		update_option( self::OPTION_HISTORY, $history, false );
	}

	/**
	 * Clear all history (Tools panel).
	 */
	public static function clear_all() {
		delete_option( self::OPTION_TIMELINE );
		delete_option( self::OPTION_HISTORY );
		self::add_event( 'system', 'warning', __( 'Audit log was cleared by Administrator.', 'server-site-insight' ) );
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
