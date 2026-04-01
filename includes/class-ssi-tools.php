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
 *class SSI_Tools {

	/** Nonce action shared between PHP and JS. */
	const NONCE_ACTION = 'ssi_tools_nonce';

	/**
	 * Whitelisted wp-config.php constants this class may simulate.
	 *
	 * @var string[]
	 */
	private static $allowed_constants = array(
		'WP_DEBUG',
		'WP_DEBUG_LOG',
		'WP_DEBUG_DISPLAY',
		'SAVEQUERIES',
		'DISALLOW_FILE_EDIT',
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
	 * Toggle Production Mode (disables internal debug settings, XML-RPC).
	 */
	private static function ajax_toggle_production_mode() {
		$enable = ! empty( $_POST['enable'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enable'] ) );

		if ( $enable ) {
			// Turning ON Production Mode.
			update_option( 'ssi_debug_enabled', false );
			update_option( 'ssi_debug_log', false );
			update_option( 'ssi_debug_display', false );
			update_option( 'ssi_savequeries_enabled', false );
			update_option( 'ssi_disallow_file_edit', true ); // Simulator
			update_option( 'ssi_xmlrpc_disabled', true );
			update_option( 'ssi_production_mode', true );
			
			SSI_System_Info::purge_cache();
			SSI_History_Tracker::maybe_capture( true );

			wp_send_json_success( array(
				'reload'  => true,
				'message' => __( 'Production Mode enabled. System locked down safely without touching core files.', 'server-site-insight' ),
			) );
		} else {
			// Turning OFF Production Mode. Enable debug mode internally.
			update_option( 'ssi_debug_enabled', true );
			update_option( 'ssi_debug_log', true );
			update_option( 'ssi_debug_display', true );
			update_option( 'ssi_savequeries_enabled', true );
			update_option( 'ssi_disallow_file_edit', false );
			delete_option( 'ssi_xmlrpc_disabled' );
			delete_option( 'ssi_production_mode' );
			
			SSI_System_Info::purge_cache();
			SSI_History_Tracker::maybe_capture( true );

			wp_send_json_success( array(
				'reload'  => true,
				'message' => __( 'Production Mode deactivated. Internal debugging enabled.', 'server-site-insight' ),
			) );
		}
	}

	/**
	 * Toggle an internal debug/security setting.
	 */
	private static function ajax_toggle_config_constant() {
		$constant = isset( $_POST['constant'] ) ? sanitize_text_field( wp_unslash( $_POST['constant'] ) ) : '';

		if ( ! in_array( $constant, self::$allowed_constants, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid setting.', 'server-site-insight' ) ) );
		}

		$value = ! empty( $_POST['value'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['value'] ) );
		
		// Map the "constant" name to our internal option name.
		$option_map = array(
			'WP_DEBUG'           => 'ssi_debug_enabled',
			'WP_DEBUG_LOG'       => 'ssi_debug_log',
			'WP_DEBUG_DISPLAY'   => 'ssi_debug_display',
			'SAVEQUERIES'        => 'ssi_savequeries_enabled',
			'DISALLOW_FILE_EDIT' => 'ssi_disallow_file_edit',
		);

		if ( isset( $option_map[ $constant ] ) ) {
			update_option( $option_map[ $constant ], $value );
		}

		SSI_System_Info::purge_cache();
		SSI_History_Tracker::maybe_capture( true );

		wp_send_json_success( array(
			'reload'  => true,
			'message' => sprintf( __( '%s updated successfully.', 'server-site-insight' ), $constant ),
		) );
	}

	/**
	 * Enable/disable XML-RPC via wp_options.
	 */
	private static function ajax_toggle_xmlrpc() {
		$disabled = ! empty( $_POST['disabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['disabled'] ) );
		update_option( 'ssi_xmlrpc_disabled', $disabled );
		SSI_History_Tracker::maybe_capture( true );

		wp_send_json_success( array(
			'message' => $disabled ? __( 'XML-RPC has been disabled.', 'server-site-insight' ) : __( 'XML-RPC has been re-enabled.', 'server-site-insight' ),
		) );
	}

	/**
	 * Delete all transients.
	 */
	private static function ajax_clear_transients() {
		global $wpdb;
		$count = (int) $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%'" );
		wp_send_json_success( array( 'count'   => $count, 'message' => sprintf( _n( '%d transient cleared.', '%d transients cleared.', $count, 'server-site-insight' ), $count ) ) );
	}

	/**
	 * Flush WordPress rewrite rules.
	 */
	private static function ajax_flush_rewrites() {
		flush_rewrite_rules( true );
		wp_send_json_success( array( 'message' => __( 'Rewrite rules flushed successfully.', 'server-site-insight' ) ) );
	}

	// ── Utilities ──────────────────────────────────────────────────────────

	/**
	 * Returns true if production mode is active.
	 * Now checks our internal database options.
	 *
	 * @return bool
	 */
	public static function is_production_mode_active() {
		$internal_prod = get_option( 'ssi_production_mode', false );
		if ( $internal_prod ) {
			return true;
		}

		// Fallback to checking effective constants if not explicitly set in our plugin.
		$debug = self::get_constant_value( 'WP_DEBUG' );
		return ( false === $debug || null === $debug );
	}

	/**
	 * Return the current live value of a constant, or null if not defined.
	 */
	public static function get_constant_value( $constant ) {
		return defined( $constant ) ? constant( $constant ) : null;
	}

	/**
	 * Legacy check - now always returns true as we don't touch files.
	 */
	public static function config_is_writable() {
		return true;
	}

	/**
	 * Returns false as we no longer help with manual path discovery.
	 */
	public static function get_config_path() {
		return false;
	}

	/**
	 * Clean up plugin options on uninstall.
	 */
	public static function cleanup() {
		delete_option( 'ssi_xmlrpc_disabled' );
		delete_option( 'ssi_production_mode' );
		delete_option( 'ssi_debug_enabled' );
		delete_option( 'ssi_debug_log' );
		delete_option( 'ssi_debug_display' );
		delete_option( 'ssi_savequeries_enabled' );
		delete_option( 'ssi_disallow_file_edit' );
	}
}
