<?php
/**
 * SSI_Tools — Admin Tools tab: debug toggles, security toggles, maintenance actions.
 *
 * Editing wp-config.php uses an atomic write (temp-file + rename) guarded by
 * nonce + manage_options capability. If the file is not writable the handler
 * returns the exact code snippet so the admin can edit manually.
 *
 * @package Server_Site_Insight
 * @since   3.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSI_Tools
 */
class SSI_Tools {

	/** Nonce action shared between PHP and JS. */
	const NONCE_ACTION = 'ssi_tools_nonce';

	/**
	 * Whitelisted wp-config.php constants this class may write.
	 *
	 * @var string[]
	 */
	private static $allowed_constants = array(
		'WP_DEBUG',
		'WP_DEBUG_LOG',
		'WP_DEBUG_DISPLAY',
		'SAVEQUERIES',
		'DISALLOW_FILE_EDIT',
		'DISALLOW_FILE_MODS',
	);

	// ── Bootstrap ──────────────────────────────────────────────────────────

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_ssi_tool_action', array( __CLASS__, 'handle_action' ) );
		add_filter( 'xmlrpc_enabled', array( __CLASS__, 'maybe_disable_xmlrpc' ) );
	}

	/**
	 * Disable XML-RPC when the plugin option is set.
	 *
	 * @param bool $enabled Current enabled state.
	 * @return bool
	 */
	public static function maybe_disable_xmlrpc( $enabled ) {
		if ( get_option( 'ssi_xmlrpc_disabled', false ) ) {
			return false;
		}
		return $enabled;
	}

	// ── Main AJAX dispatcher ───────────────────────────────────────────────

	/**
	 * Unified AJAX handler — routes to sub-handlers by 'tool' value.
	 */
	public static function handle_action() {
		// Security: verify nonce first, then capability.
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'server-site-insight' ) ),
				403
			);
		}

		$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';

		switch ( $tool ) {
			case 'toggle_production_mode':
				self::ajax_toggle_production_mode();
				break;
			case 'toggle_config_constant':
				self::ajax_toggle_config_constant();
				break;
			case 'toggle_xmlrpc':
				self::ajax_toggle_xmlrpc();
				break;
			case 'clear_transients':
				self::ajax_clear_transients();
				break;
			case 'flush_rewrites':
				self::ajax_flush_rewrites();
				break;
			default:
				wp_send_json_error(
					array( 'message' => __( 'Unknown tool action.', 'server-site-insight' ) )
				);
		}
	}

	// ── Sub-handlers ───────────────────────────────────────────────────────

	/**
	 * Toggle Production Mode (disables WP_DEBUG, disables file editor, disables XML-RPC).
	 */
	private static function ajax_toggle_production_mode() {
		$enable = ! empty( $_POST['enable'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enable'] ) );

		if ( $enable ) {
			// Turning ON Production Mode.
			$res1 = self::write_config_constant( 'WP_DEBUG', 'false' );
			$res2 = self::write_config_constant( 'WP_DEBUG_LOG', 'false' );
			$res3 = self::write_config_constant( 'WP_DEBUG_DISPLAY', 'false' );
			$res_sq = self::write_config_constant( 'SAVEQUERIES', 'false' );
			$res4 = self::write_config_constant( 'DISALLOW_FILE_EDIT', 'true' );
			update_option( 'ssi_xmlrpc_disabled', true );
			update_option( 'ssi_production_mode', true );
			
			if ( is_wp_error( $res1 ) || is_wp_error( $res2 ) || is_wp_error( $res3 ) || is_wp_error( $res4 ) ) {
				$snippet  = "define( 'WP_DEBUG', false );\n";
				$snippet .= "define( 'WP_DEBUG_LOG', false );\n";
				$snippet .= "define( 'WP_DEBUG_DISPLAY', false );\n";
				$snippet .= "define( 'DISALLOW_FILE_EDIT', true );";
				wp_send_json_success( array(
					'requires_manual' => true,
					'snippet'         => $snippet,
					'message'         => __( 'wp-config.php is not writable. Please add these lines manually.', 'server-site-insight' ),
				) );
			}
			
			SSI_System_Info::purge_cache();
			// Force an immediate history snapshot for the timeline.
			SSI_History_Tracker::maybe_capture( true );

			wp_send_json_success( array(
				'requires_manual' => false,
				'reload'          => true,
				'message'         => __( 'Production Mode enabled. Debugging disabled and system locked down safely.', 'server-site-insight' ),
			) );
		} else {
			// Turning OFF Production Mode. Enable debug mode as requested.
			$res1 = self::write_config_constant( 'WP_DEBUG', 'true' );
			$res2 = self::write_config_constant( 'WP_DEBUG_LOG', 'true' );
			$res3 = self::write_config_constant( 'WP_DEBUG_DISPLAY', 'true' );
			$res_sq = self::write_config_constant( 'SAVEQUERIES', 'true' );
			$res4 = self::write_config_constant( 'DISALLOW_FILE_EDIT', 'false' );
			delete_option( 'ssi_xmlrpc_disabled' );
			delete_option( 'ssi_production_mode' );
			
			if ( is_wp_error( $res1 ) || is_wp_error( $res2 ) || is_wp_error( $res3 ) || is_wp_error( $res4 ) ) {
				$snippet  = "define( 'WP_DEBUG', true );\n";
				$snippet .= "define( 'WP_DEBUG_LOG', true );\n";
				$snippet .= "define( 'WP_DEBUG_DISPLAY', true );\n";
				$snippet .= "define( 'DISALLOW_FILE_EDIT', false );";
				wp_send_json_success( array(
					'requires_manual' => true,
					'snippet'         => $snippet,
					'message'         => __( 'wp-config.php is not writable. Please modify these lines manually.', 'server-site-insight' ),
				) );
			}
			
			SSI_System_Info::purge_cache();
			// Force an immediate history snapshot for the timeline.
			SSI_History_Tracker::maybe_capture( true );

			wp_send_json_success( array(
				'requires_manual' => false,
				'reload'          => true,
				'message'         => __( 'Production Mode deactivated. Debugging enabled.', 'server-site-insight' ),
			) );
		}
	}

	/**
	 * Toggle a wp-config.php boolean constant.
	 * Returns a code snippet when the file cannot be written automatically.
	 */
	private static function ajax_toggle_config_constant() {
		$constant = isset( $_POST['constant'] )
			? sanitize_text_field( wp_unslash( $_POST['constant'] ) )
			: '';

		// Strict whitelist — never allow arbitrary constant names.
		if ( ! in_array( $constant, self::$allowed_constants, true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid constant name.', 'server-site-insight' ) )
			);
		}

		$value     = ! empty( $_POST['value'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['value'] ) );
		$value_str = $value ? 'true' : 'false';
		$result    = self::write_config_constant( $constant, $value_str );

		if ( is_wp_error( $result ) ) {
			// File not writable — return a safe snippet for manual editing.
			$snippet = "define( '" . $constant . "', " . $value_str . ' );';
			wp_send_json_success(
				array(
					'requires_manual' => true,
					'snippet'         => $snippet,
					/* translators: %s: PHP code snippet for manual insertion */
					'message'         => sprintf(
						__( 'wp-config.php is not writable. Please add this line manually: %s', 'server-site-insight' ),
						$snippet
					),
				)
			);
		}

		// Purge the 5-minute system info transient cache so the next page load
		// reads the updated constant value instead of stale cached data.
		SSI_System_Info::purge_cache();

		// Force an immediate history snapshot for the timeline.
		SSI_History_Tracker::maybe_capture( true );

		wp_send_json_success(
			array(
				'requires_manual' => false,
				'reload'          => true,
				/* translators: 1: PHP constant name (e.g. WP_DEBUG), 2: value (true or false) */
				'message'         => sprintf(
					__( '%1$s set to %2$s successfully.', 'server-site-insight' ),
					$constant,
					$value_str
				),
			)
		);
	}

	/**
	 * Enable/disable XML-RPC via wp_options (no file editing needed).
	 */
	private static function ajax_toggle_xmlrpc() {
		$disabled = ! empty( $_POST['disabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['disabled'] ) );
		update_option( 'ssi_xmlrpc_disabled', $disabled );

		// Force an immediate history snapshot for the timeline.
		SSI_History_Tracker::maybe_capture( true );

		wp_send_json_success(
			array(
				'message' => $disabled
					? __( 'XML-RPC has been disabled.', 'server-site-insight' )
					: __( 'XML-RPC has been re-enabled.', 'server-site-insight' ),
			)
		);
	}

	/**
	 * Delete all transients (option-table based).
	 */
	private static function ajax_clear_transients() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\_transient\_%'
			    OR option_name LIKE '\_site\_transient\_%'"
		);

		wp_send_json_success(
			array(
				'count'   => $count,
				/* translators: %d: number of transient rows deleted */
				'message' => sprintf(
					_n(
						'%d transient cleared.',
						'%d transients cleared.',
						$count,
						'server-site-insight'
					),
					$count
				),
			)
		);
	}

	/**
	 * Flush WordPress rewrite rules (hard flush).
	 */
	private static function ajax_flush_rewrites() {
		flush_rewrite_rules( true );
		wp_send_json_success(
			array(
				'message' => __( 'Rewrite rules flushed successfully.', 'server-site-insight' ),
			)
		);
	}

	// ── Utilities ──────────────────────────────────────────────────────────

	/**
	 * Returns true if production mode is considered active.
	 * Active means WP_DEBUG is false, DISALLOW_FILE_EDIT is true, and XML-RPC is disabled.
	 *
	 * @return bool
	 */
	public static function is_production_mode_active() {
		$debug       = self::get_constant_value( 'WP_DEBUG' );
		$debug_log   = self::get_constant_value( 'WP_DEBUG_LOG' );
		$debug_disp  = self::get_constant_value( 'WP_DEBUG_DISPLAY' );

		// We consider Production Mode to be active securely if all debugging features are disabled. 
		// This ensures accurate UI state even if users modify wp-config.php directly.
		return ( false === $debug || null === $debug ) &&
		       ( false === $debug_log || null === $debug_log ) &&
		       ( false === $debug_disp || null === $debug_disp );
	}

	// ── wp-config.php utilities ────────────────────────────────────────────

	/**
	 * Locate wp-config.php (supports above-root hardened installs).
	 *
	 * @return string|false Absolute path or false.
	 */
	public static function get_config_path() {
		$standard = ABSPATH . 'wp-config.php';
		if ( file_exists( $standard ) ) {
			return $standard;
		}

		// One directory up (hardened: moved above web-root).
		$above = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $above ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return $above;
		}

		return false;
	}

	/**
	 * Whether wp-config.php exists and is writable.
	 *
	 * @return bool
	 */
	public static function config_is_writable() {
		$path = self::get_config_path();
		return $path && is_writable( $path );
	}

	/**
	 * Return the current live value of a constant, or null if not defined.
	 *
	 * @param string $constant Constant name.
	 * @return mixed|null
	 */
	public static function get_constant_value( $constant ) {
		return defined( $constant ) ? constant( $constant ) : null;
	}

	/**
	 * Write or update a `define( 'CONSTANT', value );` line in wp-config.php.
	 *
	 * Uses an atomic write (write to temp file, rename) to avoid partial writes.
	 * Invalidates the OPcode cache afterwards.
	 *
	 * @param string $constant  Whitelisted constant name.
	 * @param string $value_str 'true' or 'false'.
	 * @return true|WP_Error
	 */
	private static function write_config_constant( $constant, $value_str ) {
		$path = self::get_config_path();

		if ( ! $path ) {
			return new WP_Error(
				'not_found',
				__( 'wp-config.php not found.', 'server-site-insight' )
			);
		}

		if ( ! is_writable( $path ) ) {
			return new WP_Error(
				'not_writable',
				__( 'wp-config.php is not writable.', 'server-site-insight' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $path );
		if ( false === $content ) {
			return new WP_Error(
				'read_error',
				__( 'Could not read wp-config.php.', 'server-site-insight' )
			);
		}

		$new_define = "define( '" . $constant . "', " . $value_str . ' );';
		$escaped    = preg_quote( $constant, '/' );
		// Matches: define('CONSTANT', anything);  — with any spacing/quote style.
		$pattern = "/define\s*\(\s*['\"]" . $escaped . "['\"]\s*,[^)]+\)\s*;/";

		if ( preg_match( $pattern, $content ) ) {
			// Replace the existing define.
			$content = preg_replace( $pattern, $new_define, $content );
		} else {
			// Insert before the "stop editing" marker, or append.
			$marker = "/* That's all, stop editing!";
			$pos    = strpos( $content, $marker );
			if ( false !== $pos ) {
				$content = substr_replace( $content, $new_define . "\n", $pos, 0 );
			} else {
				$content .= "\n" . $new_define . "\n";
			}
		}

		// Atomic write: temp file in the same directory, then rename.
		$tmp = $path . '.ssi-' . uniqid( '', true ) . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		if ( false === file_put_contents( $tmp, $content ) ) {
			return new WP_Error(
				'write_error',
				__( 'Could not write temporary config file.', 'server-site-insight' )
			);
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! @rename( $tmp, $path ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $tmp );
			return new WP_Error(
				'rename_error',
				__( 'Could not replace wp-config.php.', 'server-site-insight' )
			);
		}

		// Invalidate OPcode cache so PHP picks up the new file immediately.
		if ( function_exists( 'opcache_invalidate' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@opcache_invalidate( $path, true );
		}

		return true;
	}

	/**
	 * Clean up plugin options on uninstall (called from uninstall.php).
	 */
	public static function cleanup() {
		delete_option( 'ssi_xmlrpc_disabled' );
		delete_option( 'ssi_production_mode' );
	}
}
