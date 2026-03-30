<?php
/**
 * Plugin uninstall script.
 *
 * Executed by WordPress when the plugin is deleted from the dashboard.
 * Removes all options and transients created by Server & Site Insight.
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

// WordPress checks this constant before running uninstall.php.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all plugin options.
delete_option( 'ssi_activated_at' );
delete_option( 'ssi_settings' );
delete_option( 'ssi_history' );

// Remove cached transients.
delete_transient( 'ssi_rest_api_check' );
delete_transient( 'ssi_db_size' );
delete_transient( 'ssi_system_info' );
delete_transient( 'ssi_system_info_dev' );

// Remove scheduled cron event.
wp_clear_scheduled_hook( 'ssi_daily_check' );
