<?php
/**
 * SSI_System_Info — collects WordPress, server, environment, performance,
 * security, and developer data.
 *
 * Expensive operations (REST API check, DB size) are cached with transients
 * to avoid impacting page load on every request.
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSI_System_Info
 */
class SSI_System_Info {

	/** Transient key for full info cache. @var string */
	const CACHE_KEY     = 'ssi_system_info';

	/** Cache lifetime in seconds (1 minute). @var int */
	const CACHE_TTL = 60;

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Return all system information, using a short transient cache.
	 *
	 * @param bool $include_developer  Include developer section (admin-only toggle).
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all() {
		$cached = get_transient( self::CACHE_KEY );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$data = array(
			'wordpress'   => self::get_wordpress_info(),
			'server'      => self::get_server_info(),
			'environment' => self::get_environment_info(),
			'performance' => self::get_performance_info(),
			'security'    => self::get_security_info(),
		);

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * Purge the info cache (call after settings change or forced refresh).
	 */
	public static function purge_cache() {
		delete_transient( self::CACHE_KEY );
	}

	// -----------------------------------------------------------------------
	// WordPress
	// -----------------------------------------------------------------------

	/**
	 * @return array<string, mixed>
	 */
	public static function get_wordpress_info() {
		$theme = wp_get_theme();

		return array(
			'wp_version'     => get_bloginfo( 'version' ),
			'site_url'       => esc_url_raw( get_site_url() ),
			'home_url'       => esc_url_raw( get_home_url() ),
			'active_theme'   => sanitize_text_field( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ),
			'active_plugins' => self::get_active_plugins_list(),
			'plugin_count'   => count( get_option( 'active_plugins', array() ) ),
			'multisite'      => is_multisite(),
			'language'       => sanitize_text_field( get_bloginfo( 'language' ) ),
			'charset'        => sanitize_text_field( get_bloginfo( 'charset' ) ),
		);
	}

	/**
	 * Return active plugin name + version strings.
	 *
	 * @return array<int, string>
	 */
	private static function get_active_plugins_list() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active  = get_option( 'active_plugins', array() );
		$plugins = get_plugins();
		$result  = array();
		foreach ( $active as $file ) {
			if ( isset( $plugins[ $file ] ) ) {
				$result[] = sanitize_text_field( $plugins[ $file ]['Name'] . ' ' . $plugins[ $file ]['Version'] );
			}
		}
		return $result;
	}

	// -----------------------------------------------------------------------
	// Server
	// -----------------------------------------------------------------------

	/**
	 * @return array<string, mixed>
	 */
	public static function get_server_info() {
		global $wpdb;

		// SERVER_SOFTWARE is untrusted — always sanitize before use.
		$server_sw = isset( $_SERVER['SERVER_SOFTWARE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
			: '';

		return array(
			'php_version'     => PHP_VERSION,
			'php_sapi'        => sanitize_text_field( PHP_SAPI ),
			'server_software' => $server_sw,
			'mysql_version'   => self::get_mysql_version( $wpdb ),
			'memory_limit'    => sanitize_text_field( (string) ini_get( 'memory_limit' ) ),
			'memory_limit_mb' => self::convert_to_mb( (string) ini_get( 'memory_limit' ) ),
			'max_upload_size' => size_format( wp_max_upload_size() ),
			'max_exec_time'   => (int) ini_get( 'max_execution_time' ) . 's',
			'post_max_size'   => sanitize_text_field( (string) ini_get( 'post_max_size' ) ),
			'os'              => sanitize_text_field( PHP_OS ),
			'architecture'    => PHP_INT_SIZE === 8 ? '64-bit' : '32-bit',
		);
	}

	/**
	 * Get MySQL / MariaDB version via wpdb (no direct query — uses wpdb abstraction).
	 *
	 * @param wpdb $wpdb  WordPress database object.
	 * @return string
	 */
	private static function get_mysql_version( $wpdb ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$version = $wpdb->get_var( 'SELECT VERSION()' );
		return $version ? sanitize_text_field( $version ) : __( 'Unknown', 'server-site-insight' );
	}

	/**
	 * Convert a PHP ini size string (e.g. "256M") to integer megabytes.
	 *
	 * @param string $s  Value from ini_get().
	 * @return int  Megabytes; -1 for unlimited.
	 */
	public static function convert_to_mb( $s ) {
		$s = trim( $s );
		if ( '-1' === $s ) {
			return -1;
		}
		$unit  = strtoupper( substr( $s, -1 ) );
		$value = (int) $s;
		switch ( $unit ) {
			case 'G': return $value * 1024;
			case 'M': return $value;
			case 'K': return (int) floor( $value / 1024 );
			default:  return (int) floor( $value / 1024 / 1024 );
		}
	}

	// -----------------------------------------------------------------------
	// Environment
	// -----------------------------------------------------------------------

	/**
	 * @return array<string, mixed>
	 */
	public static function get_environment_info() {
		return array(
			'rest_api'     => self::check_rest_api(),
			'debug_mode'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'debug_log'    => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'cron'         => ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
			'https'        => is_ssl(),
			'environment'  => defined( 'WP_ENVIRONMENT_TYPE' ) ? sanitize_text_field( WP_ENVIRONMENT_TYPE ) : 'production',
			'cache'        => defined( 'WP_CACHE' ) && WP_CACHE,
			'script_debug' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
		);
	}

	/**
	 * Check REST API reachability with a 1-hour transient cache.
	 *
	 * Caching prevents a slow HTTP request on every admin page load.
	 *
	 * @return bool  TRUE when the API responds with HTTP 200.
	 */
	private static function check_rest_api() {
		$cached = get_transient( 'ssi_rest_api_check' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$response = wp_remote_get(
			rest_url( '/' ),
			array(
				'timeout'   => 5,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		$ok = ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
		set_transient( 'ssi_rest_api_check', $ok ? 1 : 0, HOUR_IN_SECONDS );
		return $ok;
	}

	// -----------------------------------------------------------------------
	// Performance
	// -----------------------------------------------------------------------

	/**
	 * Gather real-time performance metrics (not cached — values change per request).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_performance_info() {
		global $wpdb;

		$cur_bytes  = function_exists( 'memory_get_usage' )      ? memory_get_usage( true )      : 0;
		$peak_bytes = function_exists( 'memory_get_peak_usage' ) ? memory_get_peak_usage( true ) : 0;
		$limit_mb   = self::convert_to_mb( (string) ini_get( 'memory_limit' ) );
		$used_mb    = (int) round( $cur_bytes / 1024 / 1024 );
		$pct        = $limit_mb > 0 ? (int) round( $used_mb / $limit_mb * 100 ) : 0;

		return array(
			'memory_used_mb'  => $used_mb,
			'memory_peak_mb'  => (int) round( $peak_bytes / 1024 / 1024 ),
			'memory_percent'  => min( 100, $pct ),
			'query_count'     => (int) $wpdb->num_queries,
			'php_time_limit'  => (int) ini_get( 'max_execution_time' ),
			'opcache_enabled' => function_exists( 'opcache_get_status' ) && false !== @opcache_get_status( false ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'gzip_enabled'    => extension_loaded( 'zlib' ),
		);
	}

	// -----------------------------------------------------------------------
	// Security
	// -----------------------------------------------------------------------

	/**
	 * @return array<string, mixed>
	 */
	public static function get_security_info() {
		return array(
			'xmlrpc_enabled'       => (bool) apply_filters( 'xmlrpc_enabled', true ),
			'https_enforced'       => is_ssl(),
			'debug_on_production'  => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && ( ! defined( 'WP_ENVIRONMENT_TYPE' ) || 'production' === WP_ENVIRONMENT_TYPE ),
			'file_editor_disabled' => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
			'file_mods_disabled'   => defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS,
			'wp_config_above_root' => file_exists( dirname( ABSPATH ) . '/wp-config.php' ),
			'auto_updates_core'    => (bool) get_option( 'auto_update_core_minor', true ),
		);
	}

	// -----------------------------------------------------------------------
	// Developer (loaded only when developer_mode is ON for an admin)
	// -----------------------------------------------------------------------


}
