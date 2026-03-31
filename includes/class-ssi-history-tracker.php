<?php
/**
 * History Tracker — detects state changes and generates timeline events.
 *
 * Scans system information periodically (throttled by transients) and compares
 * against the last known state. Meaningful diffs are logged to the Timeline.
 *
 * @package Server_Site_Insight
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SSI_History_Tracker {

	const TRANSIENT_KEY   = 'ssi_last_snapshot';
	const STATE_KEY       = 'ssi_last_state';
	const THROTTLE_SECS   = 1800; // 30 minutes

	/**
	 * Main entry point. Captures a snapshot and compares it to history.
	 *
	 * @param bool $force Bypass the 30-minute throttle (useful after settings changes).
	 */
	public static function maybe_capture( $force = false ) {
		// Prevent flooding the database, but allow capture if the timeline is empty
		// so the user isn't stuck with an empty state on first load.
		$timeline = SSI_History_Storage::get_timeline();
		if ( ! $force && get_transient( self::TRANSIENT_KEY ) && ! empty( $timeline ) ) {
			return;
		}

		// Gather current data.
		$info   = SSI_System_Info::get_all();
		$checks = SSI_Health_Check::run( $info );
		$score  = SSI_Health_Score::calculate( $checks );

		// Extract critical state variables.
		$issues = array();
		foreach ( $checks as $k => $c ) {
			if ( 'good' !== $c['status'] && 'info' !== $c['status'] ) {
				$issues[ $k ] = $c; // Store full check data for context
			}
		}

		$current_state = array(
			'score'       => $score,
			'issues'      => $issues,
			'env'         => array(
				'php_version'   => $info['server']['php_version'],
				'memory_limit'  => $info['server']['memory_limit'],
				'plugin_count'  => $info['wordpress']['plugin_count'],
				'debug_mode'    => $info['environment']['debug_mode'],
				'https'         => $info['environment']['https'],
			)
		);

		$last_state = get_option( self::STATE_KEY );

		// If we have a previous state, detect changes.
		if ( is_array( $last_state ) && ! empty( $last_state ) ) {
			self::detect_changes( $last_state, $current_state, $checks );
		} else {
			// First run of the tracker: log an initial event so the timeline isn't empty.
			SSI_History_Storage::add_event( 'info', 'info', __( 'System monitoring started. Baseline health and environment metrics established.', 'server-site-insight' ) );
		}

		// Save the new state baseline.
		update_option( self::STATE_KEY, $current_state, false );

		// Also update the daily chart data structure.
		$snapshot = array(
			'date'         => gmdate( 'Y-m-d' ),
			'timestamp'    => time(),
			'score'        => $score,
			'overall'      => SSI_Health_Check::overall_status( $checks ),
			// Include historical data needed for legacy UI compatibility
			'php_version'  => $current_state['env']['php_version'],
			'wp_version'   => $info['wordpress']['wp_version'],
			'memory_limit' => $current_state['env']['memory_limit'],
			'plugin_count' => $current_state['env']['plugin_count'],
			'debug_mode'   => $current_state['env']['debug_mode'],
			'https'        => $current_state['env']['https'],
		);
		SSI_History_Storage::update_daily_history( $snapshot );

		// Set the throttle lock.
		set_transient( self::TRANSIENT_KEY, time(), self::THROTTLE_SECS );
	}

	/**
	 * Compare last state vs current state and log events to storage.
	 *
	 * @param array $last    Previous state array.
	 * @param array $current Current state array.
	 * @param array $checks  All current health checks.
	 */
	private static function detect_changes( $last, $current, $checks ) {
		// ── 1. Detect Environment Changes ──────────────────────────────
		$old_env = isset( $last['env'] ) ? $last['env'] : array();
		$new_env = $current['env'];

		if ( isset( $old_env['plugin_count'] ) && $old_env['plugin_count'] !== $new_env['plugin_count'] ) {
			$diff = $new_env['plugin_count'] - (int) $old_env['plugin_count'];
			$msg  = $diff > 0 
				? sprintf( __( '%d new plugin(s) activated or installed.', 'server-site-insight' ), $diff )
				: sprintf( __( '%d plugin(s) deactivated or deleted.', 'server-site-insight' ), abs( $diff ) );
			SSI_History_Storage::add_event( 'change', 'info', $msg, array(
				'from' => $old_env['plugin_count'],
				'to'   => $new_env['plugin_count']
			) );
		}

		if ( isset( $old_env['php_version'] ) && $old_env['php_version'] !== $new_env['php_version'] ) {
			SSI_History_Storage::add_event( 'change', 'info', sprintf( __( 'PHP Version updated to %s', 'server-site-insight' ), $new_env['php_version'] ), array(
				'from' => $old_env['php_version'],
				'to'   => $new_env['php_version']
			) );
		}

		if ( isset( $old_env['memory_limit'] ) && $old_env['memory_limit'] !== $new_env['memory_limit'] ) {
			SSI_History_Storage::add_event( 'change', 'info', sprintf( __( 'Memory Limit changed to %s', 'server-site-insight' ), $new_env['memory_limit'] ), array(
				'from' => $old_env['memory_limit'],
				'to'   => $new_env['memory_limit']
			) );
		}

		if ( isset( $old_env['debug_mode'] ) && $old_env['debug_mode'] !== $new_env['debug_mode'] ) {
			$mode_str = $new_env['debug_mode'] ? __( 'Enabled', 'server-site-insight' ) : __( 'Disabled', 'server-site-insight' );
			$sev      = $new_env['debug_mode'] ? 'warning' : 'good';
			SSI_History_Storage::add_event( 'change', $sev, sprintf( __( 'Debug Mode was %s', 'server-site-insight' ), $mode_str ) );
		}
	}
}
