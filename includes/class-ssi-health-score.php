<?php
/**
 * SSI_Health_Score — calculates a 0–100 health score from check results.
 *
 * Point allocation:
 *   PHP >= 8.0           : 15 pts
 *   Memory >= 256 MB     : 10 pts
 *   Debug OFF (prod)     : 15 pts
 *   HTTPS active         : 15 pts
 *   REST API accessible  : 10 pts
 *   MySQL >= 8.0         : 10 pts
 *   WP up to date        : 10 pts
 *   WP Cron enabled      :  5 pts
 *   XML-RPC disabled     :  5 pts
 *   Debug Log OFF        :  5 pts
 *                      = 100 pts
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSI_Health_Score
 */
class SSI_Health_Score {

	/**
	 * Point weights for each check.
	 *
	 * @var array<string, int>
	 */
	const WEIGHTS = array(
		'php_version'   => 15,
		'memory_limit'  => 10,
		'debug_mode'    => 15,
		'https'         => 15,
		'rest_api'      => 10,
		'mysql_version' => 10,
		'wp_version'    => 10,
		'cron'          =>  5,
		'xmlrpc'        =>  5,
		'debug_log'     =>  5,
	);

	/**
	 * Calculate a score from 0 to 100.
	 *
	 * @param array $checks  Return value of SSI_Health_Check::run().
	 * @return int  Integer score 0–100.
	 */
	public static function calculate( array $checks ) {
		$score = 0;
		$total = array_sum( self::WEIGHTS );

		foreach ( self::WEIGHTS as $key => $weight ) {
			if ( ! isset( $checks[ $key ] ) ) {
				continue; // Missing check treated as failed.
			}
			$status = $checks[ $key ]['status'];
			if ( 'good' === $status ) {
				$score += $weight;
			} elseif ( 'warning' === $status ) {
				// Partial credit (50 %) for warnings.
				$score += (int) round( $weight * 0.5 );
			}
			// Critical / unknown = 0 points.
		}

		return min( 100, max( 0, (int) round( $score / $total * 100 ) ) );
	}

	/**
	 * Map a numeric score to a status label.
	 *
	 * @param int $score  0–100.
	 * @return string  good | warning | critical
	 */
	public static function label( $score ) {
		if ( $score >= 80 ) {
			return 'good';
		}
		if ( $score >= 50 ) {
			return 'warning';
		}
		return 'critical';
	}

	/**
	 * Return a human-readable grade letter.
	 *
	 * @param int $score
	 * @return string  A+, A, B, C, D, F
	 */
	public static function grade( $score ) {
		if ( $score >= 95 ) { return 'A+'; }
		if ( $score >= 80 ) { return 'A';  }
		if ( $score >= 65 ) { return 'B';  }
		if ( $score >= 50 ) { return 'C';  }
		if ( $score >= 35 ) { return 'D';  }
		return 'F';
	}

	/**
	 * Return a translatable description for a given score.
	 *
	 * @param int $score
	 * @return string
	 */
	public static function description( $score ) {
		if ( $score >= 80 ) {
			return __( 'Your site environment is healthy. Keep it up!', 'server-site-insight' );
		}
		if ( $score >= 50 ) {
			return __( 'Some issues need attention. Check the warnings below.', 'server-site-insight' );
		}
		return __( 'Critical issues detected. Immediate action required.', 'server-site-insight' );
	}
}
