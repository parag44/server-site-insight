<?php
/**
 * SSI_Health_Check — evaluates metrics with status, explain, recommendation.
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SSI_Health_Check {

	const STATUS_GOOD     = 'good';
	const STATUS_WARNING  = 'warning';
	const STATUS_CRITICAL = 'critical';

	public static function run( array $info ) {
		$srv = $info['server'];
		$env = $info['environment'];
		$wp  = $info['wordpress'];
		$sec = isset( $info['security'] ) ? $info['security'] : array();

		return array(
			'php_version'   => self::check_php( $srv['php_version'] ),
			'memory_limit'  => self::check_memory( $srv['memory_limit_mb'] ),
			'debug_mode'    => self::check_debug( $env['debug_mode'], $env['environment'] ),
			'https'         => self::check_https( $env['https'] ),
			'rest_api'      => self::check_rest( $env['rest_api'] ),
			'mysql_version' => self::check_mysql( $srv['mysql_version'] ),
			'wp_version'    => self::check_wp_version( $wp['wp_version'] ),
			'cron'          => self::check_cron( $env['cron'] ),
			'xmlrpc'        => self::check_xmlrpc( isset( $sec['xmlrpc_enabled'] ) ? $sec['xmlrpc_enabled'] : true ),
			'debug_log'     => self::check_debug_log( $env['debug_log'] ),
			'file_editor'   => self::check_file_editor( isset( $sec['file_editor_disabled'] ) ? $sec['file_editor_disabled'] : false ),
		);
	}

	private static function check_php( $v ) {
		// Map of major.minor PHP version to End-of-Life date (Y-m-d).
		// TODO: Update EOL map when PHP 8.5+ is released (https://www.php.net/supported-versions.php)
		$eol_map = array(
			'7.4' => '2022-11-28',
			'8.0' => '2023-11-26',
			'8.1' => '2024-12-31',
			'8.2' => '2026-12-31',
			'8.3' => '2027-12-31',
			'8.4' => '2028-12-31',
		);

		// Extract major.minor
		$parts = explode( '.', $v );
		$major_minor = isset( $parts[0] ) && isset( $parts[1] ) ? $parts[0] . '.' . $parts[1] : '';

		$now       = current_time( 'timestamp' );
		$half_year = 180 * DAY_IN_SECONDS;

		if ( isset( $eol_map[ $major_minor ] ) ) {
			$eol_time = strtotime( $eol_map[ $major_minor ] );
			if ( $now > $eol_time ) {
				// Past EOL
				return self::make( self::STATUS_CRITICAL, __( 'PHP Version', 'server-site-insight' ), $v, 
					sprintf( __( 'PHP %s reached End-of-Life on %s.', 'server-site-insight' ), $major_minor, $eol_map[ $major_minor ] ), 
					__( 'End-of-life PHP versions receive no security patches. Your site is exposed to known exploits.', 'server-site-insight' ), 
					__( 'Upgrade PHP immediately via your hosting control panel.', 'server-site-insight' ) );
			} elseif ( $eol_time - $now < $half_year ) {
				// Approaching EOL
				return self::make( self::STATUS_WARNING, __( 'PHP Version', 'server-site-insight' ), $v, 
					sprintf( __( 'PHP %s will reach End-of-Life on %s.', 'server-site-insight' ), $major_minor, $eol_map[ $major_minor ] ), 
					__( 'This PHP version will soon stop receiving security patches.', 'server-site-insight' ), 
					__( 'Plan to upgrade PHP via your hosting control panel soon.', 'server-site-insight' ) );
			} else {
				// Good
				return self::make( self::STATUS_GOOD, __( 'PHP Version', 'server-site-insight' ), $v, 
					__( 'PHP is current and actively supported.', 'server-site-insight' ), 
					__( 'WordPress and plugins run faster and more securely on current PHP versions.', 'server-site-insight' ), '' );
			}
		}

		// Fallback for very old versions or unmapped ones.
		if ( version_compare( $v, '7.4', '<' ) ) {
			return self::make( self::STATUS_CRITICAL, __( 'PHP Version', 'server-site-insight' ), $v, 
				__( 'PHP is critically outdated.', 'server-site-insight' ), 
				__( 'Outdated PHP exposes your site to known exploits.', 'server-site-insight' ), 
				__( 'Upgrade PHP immediately. Contact your host.', 'server-site-insight' ) );
		}

		// Fallback for newer versions not yet in the map.
		return self::make( self::STATUS_GOOD, __( 'PHP Version', 'server-site-insight' ), $v, 
			__( 'PHP is current.', 'server-site-insight' ), 
			__( 'WordPress requires PHP 7.4+; 8.0+ is recommended for performance and security.', 'server-site-insight' ), '' );
	}

	private static function check_memory( $mb ) {
		if ( -1 === $mb ) {
			return self::make( self::STATUS_GOOD, __( 'Memory Limit', 'server-site-insight' ), __( 'Unlimited', 'server-site-insight' ), __( 'Memory is unlimited.', 'server-site-insight' ), __( 'PHP memory_limit controls maximum script memory.', 'server-site-insight' ), '' );
		}
		if ( $mb >= 256 ) { $s = self::STATUS_GOOD;     $m = __( 'Memory limit is sufficient.',  'server-site-insight' ); $r = ''; }
		elseif ( $mb >= 128 ) { $s = self::STATUS_WARNING;  $m = __( 'Memory below 256 MB.',           'server-site-insight' ); $r = __( "Add define('WP_MEMORY_LIMIT','256M'); to wp-config.php.", 'server-site-insight' ); }
		else { $s = self::STATUS_CRITICAL; $m = __( 'Memory critically low.', 'server-site-insight' ); $r = __( 'Increase PHP memory_limit to at least 128 MB.', 'server-site-insight' ); }
		return self::make(
			$s,
			__( 'Memory Limit', 'server-site-insight' ),
			sprintf(
				/* translators: %d: memory limit value in megabytes */
				__( '%d MB', 'server-site-insight' ),
				$mb
			),
			$m,
			__( 'Low memory causes PHP fatal errors, plugin failures, and white screens.', 'server-site-insight' ),
			$r
		);
	}

	private static function check_debug( $on, $env ) {
		$internal_on = get_option( 'ssi_debug_enabled', false );
		if ( ! $on && ! $internal_on ) {
			return self::make( self::STATUS_GOOD, __( 'Debug Mode', 'server-site-insight' ), __( 'Disabled', 'server-site-insight' ), __( 'Debug mode is off.', 'server-site-insight' ), __( 'Debug mode outputs PHP errors publicly, exposing code paths to visitors.', 'server-site-insight' ), '' );
		}
		$prod = ( 'production' === $env );
		$val  = ( $on ? 'WP_DEBUG' : 'Internal Debug' ) . ' ' . __( 'Enabled', 'server-site-insight' );
		return self::make( $prod ? self::STATUS_CRITICAL : self::STATUS_WARNING, __( 'Debug Mode', 'server-site-insight' ), $val,
			$prod ? __( 'Debug ON in production — security risk!', 'server-site-insight' ) : __( 'Debug mode enabled. Disable before going live.', 'server-site-insight' ),
			__( 'Debug mode leaks file paths and code logic. Never enable on live sites.', 'server-site-insight' ),
			__( 'Disable debug mode in the Tools tab.', 'server-site-insight' ) );
	}

	private static function check_https( $ssl ) {
		return self::make( $ssl ? self::STATUS_GOOD : self::STATUS_WARNING, __( 'HTTPS', 'server-site-insight' ), $ssl ? __( 'Enabled', 'server-site-insight' ) : __( 'Disabled', 'server-site-insight' ),
			$ssl ? __( 'Site served over HTTPS.', 'server-site-insight' ) : __( 'HTTPS not active.', 'server-site-insight' ),
			__( 'HTTPS encrypts data and is required by modern browsers for secure features.', 'server-site-insight' ),
			$ssl ? '' : __( 'Install a free SSL certificate (e.g. Let\'s Encrypt) and update settings.', 'server-site-insight' ) );
	}

	private static function check_rest( $ok ) {
		return self::make( $ok ? self::STATUS_GOOD : self::STATUS_CRITICAL, __( 'REST API', 'server-site-insight' ), $ok ? __( 'Accessible', 'server-site-insight' ) : __( 'Blocked', 'server-site-insight' ),
			$ok ? __( 'REST API is accessible.', 'server-site-insight' ) : __( 'REST API is blocked.', 'server-site-insight' ),
			__( 'The REST API powers Gutenberg, mobile apps, and many plugins. Blocking it breaks core features.', 'server-site-insight' ),
			$ok ? '' : __( 'Check security plugin settings or .htaccess for rules blocking /wp-json/.', 'server-site-insight' ) );
	}

	private static function check_mysql( $version ) {
		$v = (string) preg_replace( '/[^0-9.].*/', '', $version );
		if ( version_compare( $v, '8.0', '>=' ) )     { $s = self::STATUS_GOOD;     $m = __( 'MySQL/MariaDB is current.', 'server-site-insight' );    $r = ''; }
		elseif ( version_compare( $v, '5.7', '>=' ) ) { $s = self::STATUS_WARNING;  $m = __( 'MySQL 5.7 nearing EOL.', 'server-site-insight' );        $r = __( 'Upgrade to MySQL 8.0.', 'server-site-insight' ); }
		else                                           { $s = self::STATUS_CRITICAL; $m = __( 'MySQL/MariaDB outdated.', 'server-site-insight' );        $r = __( 'Upgrade your database server immediately.', 'server-site-insight' ); }
		return self::make( $s, __( 'MySQL Version', 'server-site-insight' ), $version, $m, __( 'Outdated database software lacks security patches and performance improvements.', 'server-site-insight' ), $r );
	}

	private static function check_wp_version( $installed ) {
		$data   = get_site_transient( 'update_core' );
		$latest = isset( $data->updates[0]->version ) ? $data->updates[0]->version : $installed;
		$ok     = version_compare( $installed, $latest, '>=' );
		return self::make(
			$ok ? self::STATUS_GOOD : self::STATUS_WARNING,
			__( 'WordPress Version', 'server-site-insight' ),
			$installed,
			$ok
				? __( 'WordPress is up to date.', 'server-site-insight' )
				: sprintf(
					/* translators: %s: latest available WordPress version number, e.g. "6.5" */
					__( 'WordPress %s is available.', 'server-site-insight' ),
					$latest
				),
			__( 'Outdated WordPress contains known vulnerabilities. Always update promptly.', 'server-site-insight' ),
			$ok ? '' : __( 'Go to Dashboard → Updates.', 'server-site-insight' )
		);
	}

	private static function check_cron( $on ) {
		return self::make( $on ? self::STATUS_GOOD : self::STATUS_WARNING, __( 'WP Cron', 'server-site-insight' ), $on ? __( 'Enabled', 'server-site-insight' ) : __( 'Disabled', 'server-site-insight' ),
			$on ? __( 'WP-Cron running normally.', 'server-site-insight' ) : __( 'WP-Cron disabled.', 'server-site-insight' ),
			__( 'WP-Cron handles scheduled tasks. Disabling without a real server cron stops all automation.', 'server-site-insight' ),
			$on ? '' : __( 'Set up a real server cron job to call wp-cron.php.', 'server-site-insight' ) );
	}

	private static function check_xmlrpc( $enabled ) {
		return self::make( $enabled ? self::STATUS_WARNING : self::STATUS_GOOD, __( 'XML-RPC', 'server-site-insight' ), $enabled ? __( 'Enabled', 'server-site-insight' ) : __( 'Disabled', 'server-site-insight' ),
			$enabled ? __( 'XML-RPC active — brute-force vector.', 'server-site-insight' ) : __( 'XML-RPC disabled.', 'server-site-insight' ),
			__( 'XML-RPC is a legacy API used in brute-force and DDoS attacks. Disable unless required.', 'server-site-insight' ),
			$enabled ? __( 'Disable XML-RPC in the Tools tab.', 'server-site-insight' ) : '' );
	}

	private static function check_debug_log( $on ) {
		$internal_on = get_option( 'ssi_debug_log', false );
		$effective_on = $on || $internal_on;
		return self::make( $effective_on ? self::STATUS_WARNING : self::STATUS_GOOD, __( 'Debug Log', 'server-site-insight' ), $effective_on ? __( 'Enabled', 'server-site-insight' ) : __( 'Disabled', 'server-site-insight' ),
			$effective_on ? __( 'Debug log is writing errors.', 'server-site-insight' ) : __( 'Debug logging is off.', 'server-site-insight' ),
			__( 'The debug.log file may expose sensitive info if publicly accessible via URL.', 'server-site-insight' ),
			$effective_on ? __( 'Disable debug logging in the Tools tab.', 'server-site-insight' ) : '' );
	}

	private static function check_file_editor( $disabled ) {
		$internal_off = get_option( 'ssi_disallow_file_edit', false );
		$effective_off = $disabled || $internal_off;
		return self::make( $effective_off ? self::STATUS_GOOD : self::STATUS_WARNING, __( 'File Editor', 'server-site-insight' ), $effective_off ? __( 'Disabled', 'server-site-insight' ) : __( 'Enabled', 'server-site-insight' ),
			$effective_off ? __( 'File editor is disabled.', 'server-site-insight' ) : __( 'File editor is active.', 'server-site-insight' ),
			__( 'The built-in file editor allows code injection if an admin account is compromised.', 'server-site-insight' ),
			$effective_off ? '' : __( 'Disable the file editor in the Tools tab.', 'server-site-insight' ) );
	}

	private static function make( $status, $label, $value, $message, $explain, $recommendation ) {
		return compact( 'status', 'label', 'value', 'message', 'explain', 'recommendation' );
	}

	public static function overall_status( array $checks ) {
		$has_warn = false;
		foreach ( $checks as $c ) {
			if ( self::STATUS_CRITICAL === $c['status'] ) { return self::STATUS_CRITICAL; }
			if ( self::STATUS_WARNING === $c['status'] )  { $has_warn = true; }
		}
		return $has_warn ? self::STATUS_WARNING : self::STATUS_GOOD;
	}
}
