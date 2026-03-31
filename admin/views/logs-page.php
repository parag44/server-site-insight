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

// Activity Log Data (Paging)
$ssi_timeline       = SSI_History_Storage::get_timeline();
$items_per_page     = 20;
$total_items        = count( $ssi_timeline );
$total_pages        = ceil( $total_items / $items_per_page );
$current_page       = isset( $_GET['ssi_paged'] ) ? max( 1, intval( $_GET['ssi_paged'] ) ) : 1;
$offset             = ( $current_page - 1 ) * $items_per_page;
$ssi_timeline_paged = array_slice( $ssi_timeline, $offset, $items_per_page );
?>

<div class="ssi-logs-container">
	
	<!-- ═══ Log Type Switcher ══════════════════════════════════════════ -->
	<div class="ssi-logs-nav">
		<button type="button" class="ssi-logs-nav__btn ssi-logs-nav__btn--active" data-show="debug">
			<span class="dashicons dashicons-media-text"></span>
			<?php esc_html_e( 'System Errors (debug.log)', 'server-site-insight' ); ?>
		</button>
		<button type="button" class="ssi-logs-nav__btn" data-show="audit">
			<span class="dashicons dashicons-media-spreadsheet"></span>
			<?php esc_html_e( 'Activity Audit', 'server-site-insight' ); ?>
		</button>
	</div>

	<!-- ═══ 1: DEBUG LOG VIEWER ════════════════════════════════════════ -->
	<div id="ssi-log-view-debug" class="ssi-log-view-pane">
		
		<div class="ssi-logpage-header" style="background:var(--ssi-surface-2); border-radius:var(--ssi-radius-lg); padding:var(--ssi-md); margin-bottom:var(--ssi-md);">
			<div class="ssi-logpage-header__left">
				<span class="dashicons dashicons-visibility ssi-logpage-header__icon"></span>
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
					<span class="ssi-view-log-label"><?php esc_html_e( 'View/Refresh', 'server-site-insight' ); ?></span>
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

			<div class="ssi-log-filters" id="ssi-log-filters" hidden>
				<button type="button" class="ssi-log-filter ssi-log-filter--active" data-type="fatal"><?php esc_html_e( 'Fatal error', 'server-site-insight' ); ?> <span id="ssi-count-fatal"></span></button>
				<button type="button" class="ssi-log-filter" data-type="warning"><?php esc_html_e( 'Warning', 'server-site-insight' ); ?> <span id="ssi-count-warning"></span></button>
				<button type="button" class="ssi-log-filter" data-type="parse"><?php esc_html_e( 'Parse error', 'server-site-insight' ); ?> <span id="ssi-count-parse"></span></button>
				<button type="button" class="ssi-log-filter" data-type="notice"><?php esc_html_e( 'Notice', 'server-site-insight' ); ?> <span id="ssi-count-notice"></span></button>
				<button type="button" class="ssi-log-filter" data-type="deprecated"><?php esc_html_e( 'Deprecated', 'server-site-insight' ); ?> <span id="ssi-count-deprecated"></span></button>
				<button type="button" class="ssi-log-filter" data-type="all"><?php esc_html_e( 'All errors', 'server-site-insight' ); ?> <span id="ssi-count-all"></span></button>
			</div>
		</div>

		<div class="ssi-log-output" id="ssi-log-output" hidden>
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

	<!-- ═══ 2: ACTIVITY AUDIT LOG ══════════════════════════════════════ -->
	<div id="ssi-log-view-audit" class="ssi-log-view-pane" style="display:none;">
		
		<div class="ssi-flex-between" style="margin-bottom:var(--ssi-md);">
			<h3 style="margin:0;font-size:16px;"><?php esc_html_e( 'Activity Audit History', 'server-site-insight' ); ?></h3>
			<div class="ssi-history-actions" style="display:flex;gap:8px;">
				<button type="button" class="ssi-tool-btn ssi-tool-btn--outline" id="ssi-clear-history" style="color:var(--ssi-critical-text);">
					<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear Audit', 'server-site-insight' ); ?>
				</button>
				<button type="button" class="ssi-tool-btn ssi-tool-btn--outline" id="ssi-refresh-history">
					<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh', 'server-site-insight' ); ?>
				</button>
			</div>
		</div>

		<?php if ( empty( $ssi_timeline ) ) : ?>
			<div class="ssi-empty-state">
				<span class="dashicons dashicons-info"></span>
				<p><?php esc_html_e( 'No activity recorded yet.', 'server-site-insight' ); ?></p>
			</div>
		<?php else : ?>
			<div class="ssi-timeline-filters" id="ssi-timeline-filters" style="margin-bottom:15px;">
				<button type="button" class="ssi-log-filter ssi-log-filter--active" data-filter="all"><?php esc_html_e( 'All Activity', 'server-site-insight' ); ?></button>
				<button type="button" class="ssi-log-filter" data-filter="content"><?php esc_html_e( 'Content', 'server-site-insight' ); ?></button>
				<button type="button" class="ssi-log-filter" data-filter="user"><?php esc_html_e( 'Users', 'server-site-insight' ); ?></button>
				<button type="button" class="ssi-log-filter" data-filter="system"><?php esc_html_e( 'System', 'server-site-insight' ); ?></button>
			</div>

			<div class="ssi-audit-table-wrap">
				<table class="ssi-audit-table">
					<thead>
						<tr>
							<th style="width:120px;"><?php esc_html_e( 'Time', 'server-site-insight' ); ?></th>
							<th style="width:150px;"><?php esc_html_e( 'User', 'server-site-insight' ); ?></th>
							<th><?php esc_html_e( 'Event & Description', 'server-site-insight' ); ?></th>
						</tr>
					</thead>
					<tbody id="ssi-audit-log-body">
						<?php foreach ( $ssi_timeline_paged as $event ) : ?>
							<tr class="ssi-audit-row" data-type="<?php echo esc_attr( $event['type'] ); ?>">
								<td>
									<div class="ssi-audit-time">
										<strong><?php echo esc_html( date_i18n( 'M j', $event['timestamp'] ) ); ?></strong>
										<span><?php echo esc_html( date_i18n( 'H:i:s', $event['timestamp'] ) ); ?></span>
									</div>
								</td>
								<td>
									<strong><?php echo esc_html( $event['user_name'] ); ?></strong>
								</td>
								<td>
									<div class="ssi-audit-event">
										<span class="ssi-audit-category ssi-audit-category--<?php echo esc_attr( $event['type'] ); ?>"><?php echo esc_html( ucfirst( $event['type'] ) ); ?></span>
										<p style="margin:4px 0 0;"><?php echo esc_html( $event['message'] ); ?></p>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="ssi-audit-pagination" style="margin-top:16px;">
					<div class="ssi-audit-pagination__info">
						<?php printf( esc_html__( 'Page %1$d of %2$d', 'server-site-insight' ), $current_page, $total_pages ); ?>
					</div>
					<div class="ssi-audit-pagination__links">
						<?php if ( $current_page > 1 ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'ssi_paged', $current_page - 1 ) ); ?>#ssi-tab-logs" class="ssi-tool-btn ssi-tool-btn--outline">
								<span class="dashicons dashicons-arrow-left-alt2"></span>
							</a>
						<?php endif; ?>

						<?php if ( $current_page < $total_pages ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'ssi_paged', $current_page + 1 ) ); ?>#ssi-tab-logs" class="ssi-tool-btn ssi-tool-btn--outline">
								<span class="dashicons dashicons-arrow-right-alt2"></span>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

		<?php endif; ?>

	</div>

</div>
