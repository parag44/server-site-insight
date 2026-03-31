<?php
/**
 * Plugin Name:       Server & Site Insight
 * Plugin URI:        https://parag.bd/server-site-insight
 * Description:       Comprehensive WordPress, server, and environment dashboard with health scoring, historical tracking, alerts, developer mode, security checks, and export tools.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Parag Das
 * Author URI:        https://parag.bd
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       server-site-insight
 * Domain Path:       /languages
 *
 * @package Server_Site_Insight
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define( 'SSI_VERSION',       '2.0.0' );
define( 'SSI_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'SSI_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'SSI_MIN_PHP',       '8.0' );
define( 'SSI_MIN_MEMORY_MB', 128 );
define( 'SSI_HISTORY_LIMIT', 30 );
define( 'SSI_CAPABILITY',    'manage_options' );

// ---------------------------------------------------------------------------
// Text domain
// ---------------------------------------------------------------------------

/**
 * Load plugin text domain for translations.
 */
function ssi_load_textdomain() {
	load_plugin_textdomain(
		'server-site-insight',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', 'ssi_load_textdomain' );

// ---------------------------------------------------------------------------
// Include class files
// ---------------------------------------------------------------------------

/**
 * Require all class files. Runs on plugins_loaded so WP core is ready.
 */
function ssi_load_includes() {
	$files = array(
		'includes/class-ssi-settings.php',
		'includes/class-ssi-system-info.php',
		'includes/class-ssi-health-score.php',
		'includes/class-ssi-health-check.php',
		'includes/class-ssi-history-storage.php',
		'includes/class-ssi-history-tracker.php',
		'includes/class-ssi-alerts.php',
		'includes/class-ssi-rest-api.php',
		'includes/class-ssi-tools.php',
		'includes/class-ssi-debug-log.php',
		'includes/class-ssi-developer-insights.php',
		'includes/class-ssi-activity-logger.php',
	);
	foreach ( $files as $f ) {
		require_once SSI_PLUGIN_DIR . $f;
	}
	// Boot tools AJAX handlers and XML-RPC filter.
	SSI_Tools::init();
	// Boot debug log AJAX + download handler.
	SSI_Debug_Log::init();
	// Boot developer lazy loading.
	SSI_Developer_Insights::init();
	// Boot activity logging.
	SSI_Activity_Logger::init();
}
add_action( 'plugins_loaded', 'ssi_load_includes' );

// ---------------------------------------------------------------------------
// Admin menus
// ---------------------------------------------------------------------------

/**
 * Register top-level admin menu and Settings submenu.
 */
function ssi_register_admin_menu() {
	add_menu_page(
		__( 'Server & Site Insight', 'server-site-insight' ),
		__( 'Insight Panel', 'server-site-insight' ),
		SSI_CAPABILITY,
		'server-site-insight',
		'ssi_render_admin_page',
		'dashicons-chart-area',
		80
	);

	add_submenu_page(
		'server-site-insight',
		__( 'Insight Settings', 'server-site-insight' ),
		__( 'Settings', 'server-site-insight' ),
		SSI_CAPABILITY,
		'server-site-insight-settings',
		'ssi_render_settings_page'
	);

}
add_action( 'admin_menu', 'ssi_register_admin_menu' );

/**
 * Add Debug Log submenu before Settings, conditionally on WP_DEBUG_LOG.
 * Registered on a slightly lower priority (15) so it appears after the
 * auto-created top-level duplicate but before Settings (registered at 10).
 */
function ssi_register_debug_log_menu() {
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
		return;
	}
	add_submenu_page(
		'server-site-insight',
		__( 'Debug Log', 'server-site-insight' ),
		__( 'Debug Log', 'server-site-insight' ),
		SSI_CAPABILITY,
		'server-site-insight-debug-log',
		'ssi_render_debug_log_page'
	);
}
add_action( 'admin_menu', 'ssi_register_debug_log_menu', 15 );

// ---------------------------------------------------------------------------
// Page renderers
// ---------------------------------------------------------------------------

/**
 * Render main dashboard. Capability check + snapshot capture before template.
 */
function ssi_render_admin_page() {
	if ( ! current_user_can( SSI_CAPABILITY ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'server-site-insight' ) );
	}
	
	// Force Refresh
	if ( isset( $_GET['ssi_refresh'] ) && '1' === $_GET['ssi_refresh'] ) {
		SSI_System_Info::purge_cache();
	}

	SSI_History_Tracker::maybe_capture();
	require_once SSI_PLUGIN_DIR . 'admin/views/admin-page.php';
}

/**
 * Render settings page.
 */
function ssi_render_settings_page() {
	if ( ! current_user_can( SSI_CAPABILITY ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'server-site-insight' ) );
	}
	require_once SSI_PLUGIN_DIR . 'admin/views/settings-page.php';
}

/**
 * Render standalone Debug Log page.
 */
function ssi_render_debug_log_page() {
	if ( ! current_user_can( SSI_CAPABILITY ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'server-site-insight' ) );
	}
	require_once SSI_PLUGIN_DIR . 'admin/views/debug-log-page.php';
}

// ---------------------------------------------------------------------------
// Enqueue assets
// ---------------------------------------------------------------------------

/**
 * Enqueue CSS & JS only on plugin-owned admin pages.
 *
 * @param string $hook_suffix  Current admin page hook suffix.
 */
function ssi_enqueue_admin_assets( $hook_suffix ) {
	$allowed = array(
		'toplevel_page_server-site-insight',
		'insight-panel_page_server-site-insight-settings',
		'insight-panel_page_server-site-insight-debug-log',
	);
	if ( ! in_array( $hook_suffix, $allowed, true ) ) {
		return;
	}

	wp_enqueue_style(
		'ssi-admin-style',
		SSI_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		SSI_VERSION
	);

	wp_enqueue_script(
		'ssi-admin-script',
		SSI_PLUGIN_URL . 'assets/js/admin.js',
		array(),
		SSI_VERSION,
		true
	);

	// Localise strings and a nonce for AJAX calls.
	wp_localize_script(
		'ssi-admin-script',
		'ssiData',
		array(
			'nonce'      => wp_create_nonce( 'ssi_ajax_nonce' ),
			'toolsNonce' => wp_create_nonce( SSI_Tools::NONCE_ACTION ),
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'version'    => SSI_VERSION,
			'i18n'       => array(
				'copied'                 => __( 'Copied!', 'server-site-insight' ),
				'copyFailed'             => __( 'Copy failed — please copy manually.', 'server-site-insight' ),
				'loadFailed'             => __( 'Failed to load data.', 'server-site-insight' ),
				'confirmClearTransients' => __( 'Clear ALL transients? Cached data will be regenerated on next load.', 'server-site-insight' ),
				'confirmFlushRewrites'   => __( 'Flush rewrite rules? Your permalink structure will be rebuilt.', 'server-site-insight' ),
				'manualEditRequired'     => __( 'Manual Edit Required', 'server-site-insight' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'ssi_enqueue_admin_assets' );

// ---------------------------------------------------------------------------
// Admin notices
// ---------------------------------------------------------------------------

/**
 * Show dismissible health-issue notices on all admin screens except own pages.
 */
function ssi_admin_notices() {
	if ( ! current_user_can( SSI_CAPABILITY ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && false !== strpos( $screen->id, 'server-site-insight' ) ) {
		return;
	}
	if ( ! SSI_Settings::get( 'enable_notices', true ) ) {
		return;
	}
	SSI_Alerts::maybe_show_notices();
}
add_action( 'admin_notices', 'ssi_admin_notices' );

// ---------------------------------------------------------------------------
// AJAX handlers
// ---------------------------------------------------------------------------

/**
 * AJAX: save settings.
 * Nonce verified first, then each field sanitised individually.
 */
function ssi_ajax_save_settings() {
	// Verify nonce before anything else.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'ssi_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'server-site-insight' ) ), 403 );
	}

	if ( ! current_user_can( SSI_CAPABILITY ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'server-site-insight' ) ), 403 );
	}

	// Sanitize each expected field individually — never trust raw array input.
	$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
		? $_POST['settings'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-key below
		: array();

	$sanitized = array(
		'enable_email_alerts' => ! empty( $raw['enable_email_alerts'] ),
		'enable_notices'      => ! empty( $raw['enable_notices'] ),
		'developer_mode'      => ! empty( $raw['developer_mode'] ),
		'alert_email'         => isset( $raw['alert_email'] ) ? sanitize_email( wp_unslash( $raw['alert_email'] ) ) : '',
	);

	SSI_Settings::save( $sanitized );
	wp_send_json_success( array( 'message' => __( 'Settings saved.', 'server-site-insight' ) ) );
}
add_action( 'wp_ajax_ssi_save_settings', 'ssi_ajax_save_settings' );
// Non-privileged users must never reach this action.
add_action( 'wp_ajax_nopriv_ssi_save_settings', 'ssi_ajax_no_priv' );

/**
 * AJAX: dismiss a notice for the current user (1-day transient).
 */
function ssi_ajax_dismiss_notice() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'ssi_ajax_nonce' ) ) {
		wp_send_json_error( null, 403 );
	}
	if ( ! current_user_can( SSI_CAPABILITY ) ) {
		wp_send_json_error( null, 403 );
	}
	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	if ( $key ) {
		set_transient( 'ssi_notice_dismissed_' . absint( get_current_user_id() ) . '_' . $key, 1, DAY_IN_SECONDS );
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_ssi_dismiss_notice', 'ssi_ajax_dismiss_notice' );
add_action( 'wp_ajax_nopriv_ssi_dismiss_notice', 'ssi_ajax_no_priv' );

/**
 * AJAX: clear all audit history.
 */
function ssi_ajax_clear_history() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'ssi_ajax_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'server-site-insight' ) ), 403 );
	}
	if ( ! current_user_can( SSI_CAPABILITY ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'server-site-insight' ) ), 403 );
	}
	
	SSI_History_Storage::clear_all();
	wp_send_json_success( array( 'message' => __( 'Audit history cleared.', 'server-site-insight' ) ) );
}
add_action( 'wp_ajax_ssi_clear_history', 'ssi_ajax_clear_history' );
add_action( 'wp_ajax_nopriv_ssi_clear_history', 'ssi_ajax_no_priv' );

/**
 * Shared callback for unauthenticated AJAX requests to privileged actions.
 */
function ssi_ajax_no_priv() {
	wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'server-site-insight' ) ), 401 );
}

// ---------------------------------------------------------------------------
// Shortcode [ssi_panel] — admin-only
// ---------------------------------------------------------------------------

/**
 * Display a compact info table on the front end for administrators only.
 *
 * All other users receive an empty string — no server data is leaked.
 *
 * @return string  Escaped HTML or empty string.
 */
function ssi_shortcode_panel() {
	// Bail immediately for non-admins.
	if ( ! current_user_can( SSI_CAPABILITY ) ) {
		return '';
	}

	$info  = SSI_System_Info::get_all();
	$score = SSI_Health_Score::calculate( SSI_Health_Check::run( $info ) );

	ob_start();
	?>
	<div class="ssi-shortcode-panel">
		<h3><?php esc_html_e( 'Server &amp; Site Insight', 'server-site-insight' ); ?></h3>
		<p class="ssi-score-inline">
			<?php
			printf(
				/* translators: %d: health score 0–100 */
				esc_html__( 'Health Score: %d / 100', 'server-site-insight' ),
				(int) $score
			);
			?>
		</p>
		<table>
			<tbody>
				<tr><th><?php esc_html_e( 'WordPress Version', 'server-site-insight' ); ?></th><td><?php echo esc_html( $info['wordpress']['wp_version'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'PHP Version', 'server-site-insight' ); ?></th><td><?php echo esc_html( $info['server']['php_version'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'MySQL Version', 'server-site-insight' ); ?></th><td><?php echo esc_html( $info['server']['mysql_version'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Memory Limit', 'server-site-insight' ); ?></th><td><?php echo esc_html( $info['server']['memory_limit'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'HTTPS', 'server-site-insight' ); ?></th>
					<td><?php echo esc_html( $info['environment']['https'] ? __( 'Yes', 'server-site-insight' ) : __( 'No', 'server-site-insight' ) ); ?></td></tr>
			</tbody>
		</table>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ssi_panel', 'ssi_shortcode_panel' );

// ---------------------------------------------------------------------------
// WP-Cron scheduled event
// ---------------------------------------------------------------------------

/**
 * Register a daily cron event for email alerts.
 * Only scheduled when email alerts are enabled and classes are loaded.
 */
function ssi_schedule_cron() {
	if ( ! class_exists( 'SSI_Settings' ) ) {
		return;
	}
	if ( SSI_Settings::get( 'enable_email_alerts', false ) && ! wp_next_scheduled( 'ssi_daily_check' ) ) {
		wp_schedule_event( time(), 'daily', 'ssi_daily_check' );
	}
}
add_action( 'init', 'ssi_schedule_cron' );

/**
 * Cron callback: run health checks and send email if issues are critical.
 */
function ssi_run_daily_check() {
	if ( ! SSI_Settings::get( 'enable_email_alerts', false ) ) {
		return;
	}
	// Load includes manually — cron runs outside normal request lifecycle.
	ssi_load_includes();

	$info    = SSI_System_Info::get_all();
	$checks  = SSI_Health_Check::run( $info );
	$overall = SSI_Health_Check::overall_status( $checks );

	if ( 'critical' === $overall ) {
		SSI_Alerts::send_email( $checks );
	}
}
add_action( 'ssi_daily_check', 'ssi_run_daily_check' );

// ---------------------------------------------------------------------------
// Activation / deactivation
// ---------------------------------------------------------------------------

/**
 * Plugin activation: store timestamp and flush rewrite rules.
 */
function ssi_activate() {
	update_option( 'ssi_activated_at', current_time( 'mysql' ) );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ssi_activate' );

/**
 * Plugin deactivation: clear scheduled cron and flush rewrite rules.
 */
function ssi_deactivate() {
	wp_clear_scheduled_hook( 'ssi_daily_check' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ssi_deactivate' );

/**
 * Plugin uninstall: remove all options and transients created by this plugin.
 * Runs via uninstall.php for proper WP.org compliance.
 */
function ssi_uninstall() {
	// Options.
	delete_option( 'ssi_activated_at' );
	delete_option( 'ssi_settings' );
	delete_option( 'ssi_history' );

	// Transients.
	delete_transient( 'ssi_rest_api_check' );
	delete_transient( 'ssi_db_size' );
	delete_transient( 'ssi_system_info' );

	// Per-user notice-dismissed transients are short-lived and self-expire.
	wp_clear_scheduled_hook( 'ssi_daily_check' );
}
register_uninstall_hook( __FILE__, 'ssi_uninstall' );
