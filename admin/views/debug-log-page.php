<?php
/**
 * Debug Log page view.
 *
 * Standalone admin page rendered when WP_DEBUG_LOG is enabled.
 * Included by ssi_render_debug_log_page() in server-site-insight.php.
 *
 * @package Server_Site_Insight
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

// ── Data ──────────────────────────────────────────────────────────────────────
$ssi_log          = SSI_Debug_Log::get_status();
$ssi_log_nonce    = wp_create_nonce( SSI_Tools::NONCE_ACTION );
$ssi_log_dl_url   = wp_nonce_url(
	admin_url( 'admin-post.php?action=ssi_download_log' ),
	'ssi_download_log'
);
?>

<div class="wrap ssi-notices-container" style="margin-bottom:0; padding-bottom:0;">
	<?php do_action( 'admin_notices' ); ?>
	<?php do_action( 'all_admin_notices' ); ?>
</div>

<div id="ssi-app" class="ssi-wrap<?php echo esc_attr( get_option( 'ssi_dark_mode' ) ? ' ssi-dark' : '' ); ?>" id="ssi-log-page">

	<!-- ═══ PAGE HEADER ══════════════════════════════════════════════════ -->
	<div class="ssi-logpage-header">
		<div class="ssi-logpage-header__left">
			<span class="dashicons dashicons-media-text ssi-logpage-header__icon" aria-hidden="true"></span>
			<div>
				<h1 class="ssi-logpage-header__title">
					<?php esc_html_e( 'Debug Log Viewer', 'server-site-insight' ); ?>
				</h1>
				<p class="ssi-logpage-header__sub">
					<?php
					if ( $ssi_log['exists'] ) {
						echo esc_html(
							sprintf(
								/* translators: 1: file path, 2: formatted file size */
								__( '%1$s · %2$s', 'server-site-insight' ),
								$ssi_log['path'],
								$ssi_log['size_fmt']
							)
						);
					} else {
						echo esc_html( $ssi_log['path'] ? $ssi_log['path'] : __( 'Log file path unavailable.', 'server-site-insight' ) );
					}
					?>
				</p>
			</div>
		</div>
		<div class="ssi-logpage-header__right">
			<?php if ( ! $ssi_log['exists'] ) : ?>
				<span class="ssi-badge ssi-badge--warning"><?php esc_html_e( 'No log file yet', 'server-site-insight' ); ?></span>
			<?php else : ?>
				<span class="ssi-badge ssi-badge--good"><?php esc_html_e( 'Active', 'server-site-insight' ); ?></span>
			<?php endif; ?>
		</div>
	</div><!-- /.ssi-logpage-header -->

	<!-- ═══ META BAR ═════════════════════════════════════════════════════ -->
	<?php if ( $ssi_log['exists'] ) : ?>
	<div class="ssi-log-meta" style="margin-bottom:16px;">
		<span class="ssi-log-meta__item">
			<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: file size string e.g. "2.4 MB" */
					__( 'Size: %s', 'server-site-insight' ),
					$ssi_log['size_fmt']
				)
			);
			?>
		</span>
		<?php if ( $ssi_log['modified'] ) : ?>
		<span class="ssi-log-meta__item">
			<span class="dashicons dashicons-clock" aria-hidden="true"></span>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: human-readable time diff e.g. "5 minutes" */
					__( 'Modified: %s ago', 'server-site-insight' ),
					human_time_diff( $ssi_log['modified'], current_time( 'timestamp' ) )
				)
			);
			?>
		</span>
		<?php endif; ?>
		<?php if ( $ssi_log['large'] ) : ?>
		<span class="ssi-log-meta__item ssi-log-meta__item--warn">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<?php esc_html_e( 'Large file — only last 512 KB shown.', 'server-site-insight' ); ?>
		</span>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- ═══ ACTION TOOLBAR ═══════════════════════════════════════════════ -->
	<div class="ssi-logpage-toolbar">
		<div class="ssi-log-actions">
			<button type="button" id="ssi-view-log"
				class="ssi-tool-btn ssi-tool-btn--primary"
				data-nonce="<?php echo esc_attr( $ssi_log_nonce ); ?>"
				<?php disabled( ! $ssi_log['exists'] ); ?>>
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<span class="ssi-view-log-label"><?php esc_html_e( 'View Log', 'server-site-insight' ); ?></span>
			</button>

			<?php if ( $ssi_log['exists'] ) : ?>
			<a href="<?php echo esc_url( $ssi_log_dl_url ); ?>"
				class="ssi-tool-btn ssi-tool-btn--primary"
				id="ssi-download-log">
				<span class="dashicons dashicons-download" aria-hidden="true"></span>
				<?php esc_html_e( 'Download', 'server-site-insight' ); ?>
			</a>
			<?php endif; ?>

			<button type="button" id="ssi-clear-log"
				class="ssi-tool-btn ssi-tool-btn--warning"
				data-nonce="<?php echo esc_attr( $ssi_log_nonce ); ?>"
				data-confirm="<?php esc_attr_e( 'Permanently clear the entire debug.log file? This cannot be undone.', 'server-site-insight' ); ?>"
				<?php disabled( ! $ssi_log['exists'] || ! $ssi_log['writable'] ); ?>>
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<?php esc_html_e( 'Clear Log', 'server-site-insight' ); ?>
			</button>
		</div>

		<!-- Filter pills (hidden until log is loaded) -->
		<div class="ssi-log-filters" id="ssi-log-filters" hidden>
			<span class="ssi-log-filters__label"><?php esc_html_e( 'Filter:', 'server-site-insight' ); ?></span>
			<button type="button" class="ssi-log-filter ssi-log-filter--active" data-type="all">
				<?php esc_html_e( 'All', 'server-site-insight' ); ?>
				<span class="ssi-log-filter__count" id="ssi-count-all"></span>
			</button>
			<button type="button" class="ssi-log-filter ssi-log-filter--warning" data-type="warning">
				<?php esc_html_e( 'Warning', 'server-site-insight' ); ?>
				<span class="ssi-log-filter__count" id="ssi-count-warning"></span>
			</button>
			<button type="button" class="ssi-log-filter ssi-log-filter--fatal" data-type="fatal">
				<?php esc_html_e( 'Fatal', 'server-site-insight' ); ?>
				<span class="ssi-log-filter__count" id="ssi-count-fatal"></span>
			</button>
			<button type="button" class="ssi-log-filter ssi-log-filter--notice" data-type="notice">
				<?php esc_html_e( 'Notice', 'server-site-insight' ); ?>
				<span class="ssi-log-filter__count" id="ssi-count-notice"></span>
			</button>
			<button type="button" class="ssi-log-filter ssi-log-filter--deprecated" data-type="deprecated">
				<?php esc_html_e( 'Deprecated', 'server-site-insight' ); ?>
			</button>
			<button type="button" class="ssi-log-filter ssi-log-filter--parse" data-type="parse">
				<?php esc_html_e( 'Parse Error', 'server-site-insight' ); ?>
			</button>
		</div>
	</div><!-- /.ssi-logpage-toolbar -->

	<!-- ═══ LOG OUTPUT ═══════════════════════════════════════════════════ -->
	<div class="ssi-log-output ssi-logpage-output" id="ssi-log-output" hidden>
		<div class="ssi-log-toolbar">
			<span class="ssi-log-entry-count" id="ssi-log-entry-count"></span>
			<span class="ssi-log-tail-note"><?php esc_html_e( 'Showing newest entries first', 'server-site-insight' ); ?></span>
		</div>
		<pre class="ssi-log-pre ssi-logpage-pre"
			id="ssi-log-pre"
			aria-live="polite"
			aria-label="<?php esc_attr_e( 'Debug log output', 'server-site-insight' ); ?>"><code id="ssi-log-code"></code></pre>
	</div><!-- /.ssi-log-output -->

	<?php if ( ! $ssi_log['exists'] ) : ?>
	<div class="ssi-logpage-empty">
		<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
		<p><?php esc_html_e( 'No debug.log file exists yet. Errors will appear here once WordPress logs something.', 'server-site-insight' ); ?></p>
	</div>
	<?php endif; ?>

</div><!-- /.ssi-wrap -->
