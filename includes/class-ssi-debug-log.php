<?php
/**
 * SSI_Debug_Log — Secure debug log reader, clearer, and downloader.
 *
 * All public-facing actions require:
 *   – nonce:             ssi_tools_nonce
 *   – capability:        manage_options
 *
 * @package Server_Site_Insight
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSI_Debug_Log
 */
class SSI_Debug_Log {

	/** Maximum bytes to read from the end of the file (512 KB). */
	const MAX_BYTES = 524288;

	/** Maximum lines returned per request. */
	const MAX_LINES = 200;

	/** Warn admin if log is larger than this (5 MB). */
	const WARN_BYTES = 5242880;

	// ── Bootstrap ──────────────────────────────────────────────────────────

	/**
	 * Register AJAX hooks and the admin-post download handler.
	 */
	public static function init() {
		add_action( 'wp_ajax_ssi_log_view',              array( __CLASS__, 'ajax_view' ) );
		add_action( 'wp_ajax_ssi_log_clear',             array( __CLASS__, 'ajax_clear' ) );
		add_action( 'admin_post_ssi_download_log',       array( __CLASS__, 'handle_download' ) );
	}

	// ── Public helpers ─────────────────────────────────────────────────────

	/**
	 * Locate the debug.log file.
	 *
	 * Handles three cases:
	 *   – WP_DEBUG_LOG = true     → wp-content/debug.log
	 *   – WP_DEBUG_LOG = '/path'  → absolute custom path
	 *   – WP_DEBUG_LOG not set / false → logging off → return false
	 *
	 * @return string|false Absolute path or false when logging is disabled.
	 */
	public static function get_log_path() {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return false;
		}
		// Custom path: WP_DEBUG_LOG is a non-empty string.
		if ( is_string( WP_DEBUG_LOG ) && strlen( WP_DEBUG_LOG ) > 1 ) {
			return WP_DEBUG_LOG;
		}
		return WP_CONTENT_DIR . '/debug.log';
	}

	/**
	 * Return an associative array describing the current log state.
	 *
	 * @return array{
	 *   enabled:  bool,
	 *   path:     string|false,
	 *   exists:   bool,
	 *   readable: bool,
	 *   writable: bool,
	 *   size:     int,
	 *   size_fmt: string,
	 *   modified: int,
	 *   large:    bool,
	 * }
	 */
	public static function get_status() {
		$log_on  = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
		$path    = self::get_log_path();
		$exists  = $path && file_exists( $path );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$size    = $exists ? (int) @filesize( $path ) : 0;
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$mtime   = $exists ? (int) @filemtime( $path ) : 0;

		return array(
			'enabled'  => $log_on,
			'path'     => $path,
			'exists'   => $exists,
			'readable' => $exists && is_readable( $path ),
			'writable' => $exists && is_writable( $path ),
			'size'     => $size,
			'size_fmt' => $size > 0 ? size_format( $size, 1 ) : '0 B',
			'modified' => $mtime,
			'large'    => $size > self::WARN_BYTES,
		);
	}

	// ── AJAX handlers ──────────────────────────────────────────────────────

	/**
	 * AJAX: return the last N log lines as JSON.
	 */
	public static function ajax_view() {
		check_ajax_referer( SSI_Tools::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'server-site-insight' ) ),
				403
			);
		}

		$status = self::get_status();

		if ( ! $status['enabled'] ) {
			wp_send_json_error(
				array( 'message' => __( 'WP_DEBUG_LOG is not enabled.', 'server-site-insight' ) )
			);
		}

		if ( ! $status['exists'] ) {
			wp_send_json_error(
				array( 'message' => __( 'Log file does not exist yet — no errors have been logged.', 'server-site-insight' ) )
			);
		}

		if ( ! $status['readable'] ) {
			wp_send_json_error(
				array( 'message' => __( 'Log file is not readable.', 'server-site-insight' ) )
			);
		}

		$lines = self::tail_lines();

		// Strip HTML from log content as defence-in-depth (log is plain text).
		$lines = array_map( 'wp_strip_all_tags', $lines );

		wp_send_json_success(
			array(
				'lines'  => $lines,
				'total'  => count( $lines ),
				'status' => $status,
			)
		);
	}

	/**
	 * AJAX: truncate the log file.
	 */
	public static function ajax_clear() {
		check_ajax_referer( SSI_Tools::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'server-site-insight' ) ),
				403
			);
		}

		$path = self::get_log_path();

		if ( ! $path || ! file_exists( $path ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Log file not found.', 'server-site-insight' ) )
			);
		}

		if ( ! is_writable( $path ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Log file is not writable.', 'server-site-insight' ) )
			);
		}

		// Truncate (empty) the file — preserves file permissions and inode.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		if ( false === file_put_contents( $path, '' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Could not clear log file.', 'server-site-insight' ) )
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Debug log cleared successfully.', 'server-site-insight' ) )
		);
	}

	/**
	 * admin-post handler: stream the full log as a downloadable file.
	 *
	 * URL: admin-post.php?action=ssi_download_log  + _wpnonce
	 */
	public static function handle_download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'server-site-insight' ) );
		}

		check_admin_referer( 'ssi_download_log' );

		$path = self::get_log_path();

		if ( ! $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'Log file not found or not readable.', 'server-site-insight' ) );
		}

		$size     = (int) filesize( $path );
		$filename = 'debug-log-' . gmdate( 'Y-m-d-His' ) . '.log';

		nocache_headers();
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		if ( $size > 0 ) {
			header( 'Content-Length: ' . $size );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		readfile( $path );
		exit;
	}

	// ── Private ────────────────────────────────────────────────────────────

	/**
	 * Read the last N lines from the log file using a tail-like strategy.
	 *
	 * Only the last MAX_BYTES of the file are read into memory to avoid
	 * loading huge files. Lines are returned newest-first.
	 *
	 * @param int $max_lines Maximum lines to return.
	 * @return string[]
	 */
	private static function tail_lines( $max_lines = self::MAX_LINES ) {
		$path = self::get_log_path();
		if ( ! $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$fp = @fopen( $path, 'rb' );
		if ( ! $fp ) {
			return array();
		}

		fseek( $fp, 0, SEEK_END );
		$file_size = ftell( $fp );
		$read_size = min( $file_size, self::MAX_BYTES );

		if ( $read_size <= 0 ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			fclose( $fp );
			return array();
		}

		fseek( $fp, -$read_size, SEEK_END );
		$content = fread( $fp, $read_size );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		fclose( $fp );

		if ( false === $content || '' === $content ) {
			return array();
		}

		// Split, reverse (newest first), strip empties, limit.
		$lines = array_reverse(
			array_filter( explode( "\n", $content ), 'strlen' )
		);

		return array_values( array_slice( $lines, 0, $max_lines ) );
	}
}
