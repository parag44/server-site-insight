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
		if ( ! $force && get_transient( self::TRANSIENT_KEY ) ) {
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


}
