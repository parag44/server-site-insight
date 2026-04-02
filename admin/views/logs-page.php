<?php
/**
 * Unified Logs View — Debug Log + Activity Audit.
 *
 * @package Server_Site_Insight
 * @since   4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ssi_log        = SSI_Debug_Log::get_status();
$ssi_log_nonce  = wp_create_nonce( SSI_Tools::NONCE_ACTION );
$ssi_log_dl_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=ssi_download_log' ),
	'ssi_download_log'
);


?>

<div class="ssi-logs-container">
	


	<!-- ═══ 1: DEBUG LOG VIEWER ════════════════════════════════════════ -->
	<div id="ssi-log-view-debug" class="ssi-log-view-pane">
		
		<div class="ssi-logpage-header" style="background:var(--ssi-surface-2); border-radius:var(--ssi-radius-lg); padding:var(--ssi-md); margin-bottom:var(--ssi-md);">
			<div class="ssi-logpage-header__left">
				<span class="dashicons dashicons-media-document ssi-logpage-header__icon"></span>
				<div>
					<h3 style="margin:0;font-size:16px;"><?php esc_html_e( 'Debug Log Viewer', 'server-site-insight' ); ?></h3>
					<p class="ssi-logpage-header__sub" style="margin:4px 0 0;font-size:12px;color:var(--ssi-text-muted);">
						<?php echo esc_html( $ssi_log['path'] ?: __( 'Not configured', 'server-site-insight' ) ); ?>
					</p>
				</div>
			</div>
			<div class="ssi-logpage-header__right">
				<?php if ( $ssi_log['exists'] ) : ?>
					<span class="ssi-badge ssi-badge--good"><?php echo esc_html( $ssi_log['size_fmt'] ); ?></span>
				<?php else : ?>
					<span class="ssi-badge ssi-badge--warning"><?php esc_html_e( 'No Log File', 'server-site-insight' ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="ssi-logpage-toolbar">
			<div class="ssi-log-actions">
				<button type="button" id="ssi-view-log" class="ssi-tool-btn ssi-tool-btn--primary" data-nonce="<?php echo esc_attr( $ssi_log_nonce ); ?>" <?php disabled( ! $ssi_log['exists'] ); ?>>
					<span class="dashicons dashicons-update"></span>
					<span class="ssi-view-log-label"><?php esc_html_e( 'Refresh', 'server-site-insight' ); ?></span>
				</button>

				<?php if ( $ssi_log['exists'] ) : ?>
					<a href="<?php echo esc_url( $ssi_log_dl_url ); ?>" class="ssi-tool-btn ssi-tool-btn--outline" id="ssi-download-log">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download', 'server-site-insight' ); ?>
					</a>
				<?php endif; ?>

				<button type="button" id="ssi-clear-log" class="ssi-tool-btn ssi-tool-btn--outline" style="color:var(--ssi-critical-text);" data-nonce="<?php echo esc_attr( $ssi_log_nonce ); ?>" data-confirm="<?php esc_attr_e( 'Clear the entire debug.log file?', 'server-site-insight' ); ?>" <?php disabled( ! $ssi_log['exists'] || ! $ssi_log['writable'] ); ?>>
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e( 'Clear', 'server-site-insight' ); ?>
				</button>
			</div>

			<div class="ssi-log-filters" id="ssi-log-filters">
				<button type="button" class="ssi-log-filter ssi-log-filter--active" data-type="all"><?php esc_html_e( 'All errors', 'server-site-insight' ); ?> <span id="ssi-count-all"></span></button>
				<button type="button" class="ssi-log-filter" data-type="fatal"><?php esc_html_e( 'Fatal error', 'server-site-insight' ); ?> <span id="ssi-count-fatal"></span></button>
				<button type="button" class="ssi-log-filter" data-type="warning"><?php esc_html_e( 'Warning', 'server-site-insight' ); ?> <span id="ssi-count-warning"></span></button>
				<button type="button" class="ssi-log-filter" data-type="parse"><?php esc_html_e( 'Parse error', 'server-site-insight' ); ?> <span id="ssi-count-parse"></span></button>
				<button type="button" class="ssi-log-filter" data-type="notice"><?php esc_html_e( 'Notice', 'server-site-insight' ); ?> <span id="ssi-count-notice"></span></button>
				<button type="button" class="ssi-log-filter" data-type="deprecated"><?php esc_html_e( 'Deprecated', 'server-site-insight' ); ?> <span id="ssi-count-deprecated"></span></button>
			</div>
		</div>

		<div class="ssi-log-output" id="ssi-log-output">
			<div class="ssi-log-toolbar">
				<span id="ssi-log-entry-count" class="ssi-log-entry-count"></span>
				<span class="ssi-log-tail-note"><?php esc_html_e( 'Showing last 200 entries', 'server-site-insight' ); ?></span>
			</div>
			<pre class="ssi-log-pre"><code id="ssi-log-code"></code></pre>
		</div>

		<?php if ( ! $ssi_log['exists'] ) : ?>
			<div class="ssi-empty-state">
				<span class="dashicons dashicons-info"></span>
				<?php if ( ! $ssi_log['enabled'] ) : ?>
					<p><?php esc_html_e( 'WordPress Debug Log is disabled. You need to enable debug mode from tools tab.', 'server-site-insight' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'The debug.log file does not exist yet. This is usually good! It means no PHP errors have been recorded.', 'server-site-insight' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	</div>



</div>
