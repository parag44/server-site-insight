<?php
/**
 * Developer Insights View Template
 *
 * @package Server_Site_Insight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dev_nonce   = wp_create_nonce( 'ssi_tools_nonce' );
$ssi_dev_inf = isset( $ssi_info['developer'] ) ? $ssi_info['developer'] : array();

$autoload_stats = SSI_Developer_Insights::get_autoload_stats();
$object_cache   = SSI_Developer_Insights::get_object_cache_stats();
$rest_routes    = SSI_Developer_Insights::get_rest_routes_enriched();
$trans_stats    = SSI_Developer_Insights::get_expired_transients_stats();
$cron_stats     = SSI_Developer_Insights::get_cron_stats();
$db_size_mb     = isset( $ssi_dev_inf['database_size_mb'] ) ? $ssi_dev_inf['database_size_mb'] : 0;
$top_tables     = isset( $ssi_dev_inf['top_tables'] ) ? $ssi_dev_inf['top_tables'] : array();
$php_extensions = isset( $ssi_dev_inf['php_extensions'] ) ? $ssi_dev_inf['php_extensions'] : array();
$query_count    = isset( $ssi_dev_inf['query_count'] ) ? $ssi_dev_inf['query_count'] : 0;

?>
<div class="ssi-panel-actions" style="display:flex;justify-content:flex-end;margin-bottom:var(--ssi-xl);">
	<button type="button" class="ssi-tool-btn ssi-tool-btn--primary" id="ssi-copy-report-btn">
		<span class="dashicons dashicons-clipboard" aria-hidden="true" style="margin-right:4px;"></span>
		<?php esc_html_e( 'Copy System Report', 'server-site-insight' ); ?>
	</button>
</div>

<div class="ssi-grid ssi-grid--half" id="ssi-dev-dashboard" data-nonce="<?php echo esc_attr( $dev_nonce ); ?>">

	<!-- Left Column -->
	<div class="ssi-grid-col" style="display:flex;flex-direction:column;gap:var(--ssi-lg);">
		
		<!-- Plugin Impact Analyzer (Lazy) -->
		<details class="ssi-card ssi-card--dev ssi-lazy-panel ssi-card--highlight-persist" id="ssi-dev-impact" data-action="ssi_lazy_plugin_impact" style="border-color:var(--ssi-primary);box-shadow:0 0 15px rgba(79,70,229,0.15);">
			<summary class="ssi-card__header ssi-flex-between" style="cursor:pointer;list-style:none;background:var(--ssi-surface-2);">
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="ssi-card__icon dashicons dashicons-dashboard" aria-hidden="true" style="color:var(--ssi-primary);"></span>
					<h2 class="ssi-card__title ssi-inline-block" style="color:var(--ssi-primary);"><?php esc_html_e( 'Plugin Impact Analyzer', 'server-site-insight' ); ?></h2>
				</div>
				<span class="dashicons dashicons-arrow-down-alt2 ssi-toggle-icon"></span>
			</summary>
			<div class="ssi-card__body ssi-lazy-content">
				<div class="ssi-loader" style="padding:20px;text-align:left;color:var(--ssi-text-muted);"><span class="dashicons dashicons-update dashicons-update-spin"></span> Crunching plugin performance data...</div>
			</div>
		</details>
		
		<!-- DB & Autoload Insights -->
		<details class="ssi-card ssi-card--dev" id="ssi-dev-db">
			<summary class="ssi-card__header ssi-flex-between" style="cursor:pointer;list-style:none;">
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="ssi-card__icon dashicons dashicons-database" aria-hidden="true"></span>
					<h2 class="ssi-card__title" id="ssi-ttl-dev-db"><?php esc_html_e( 'Database & Autoload', 'server-site-insight' ); ?></h2>
				</div>
				<span class="dashicons dashicons-arrow-down-alt2 ssi-toggle-icon"></span>
			</summary>
			<div class="ssi-card__body ssi-copy-target">
				<table class="ssi-table"><tbody>
					<?php ssi_row( __( 'Total Database Size', 'server-site-insight' ), esc_html( $db_size_mb . ' MB' ) ); ?>
					<?php
						$al_label = __( 'Autoloaded Options', 'server-site-insight' );
						$al_val   = esc_html( $autoload_stats['total_size_mb'] . ' MB' );
						if ( $autoload_stats['is_warning'] ) {
							$al_val .= ' <span class="ssi-badge ssi-badge--warning" style="margin-left:6px;" data-tooltip="Options > 1MB can degrade performance.">High Load</span>';
						}
						ssi_row( $al_label, $al_val );
					?>
					<?php
						$tr_label = __( 'Expired Transients', 'server-site-insight' );
						$tr_val   = sprintf( '%d (%.2f KB)', $trans_stats['count'], $trans_stats['size_kb'] );
						if ( $trans_stats['count'] > 0 ) {
							$tr_val .= ' <button type="button" class="button button-small" id="ssi-clear-transients-btn" style="margin-left:8px;vertical-align:middle;">Clear</button>';
						}
						ssi_row( $tr_label, $tr_val );
					?>
				</tbody></table>

				<?php if ( ! empty( $top_tables ) ) : ?>
					<h4 class="ssi-subheading" style="margin:20px 0 10px;font-size:11px;text-transform:uppercase;color:var(--ssi-text-muted);font-weight:700;letter-spacing:0.5px;"><?php esc_html_e( 'Top 5 Largest Tables', 'server-site-insight' ); ?></h4>
					<table class="ssi-table ssi-table--condensed">
						<thead>
							<tr>
								<th style="text-align:left;padding-bottom:8px;border:none;"><span class="ssi-dev-badge"><?php esc_html_e( 'Table Name', 'server-site-insight' ); ?></span></th>
								<th style="text-align:left;padding-bottom:8px;border:none;"><span class="ssi-dev-badge"><?php esc_html_e( 'Size MB', 'server-site-insight' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $top_tables as $table ) : ?>
								<tr>
									<td style="font-family:monospace;font-size:12px;padding:8px 0;border-top:1px solid var(--ssi-border);"><?php echo esc_html( $table['name'] ); ?></td>
									<td style="text-align:left;font-size:12px;padding:8px 0;border-top:1px solid var(--ssi-border);">
										<?php if ( $table['size_mb'] > 50 ) : ?>
											<span style="color:var(--ssi-warn-text);background:var(--ssi-warn-bg);padding:2px 6px;border-radius:4px;font-weight:600;border:1px solid var(--ssi-warn-border);display:inline-block;">
												<?php echo esc_html( $table['size_mb'] ); ?>
											</span>
										<?php else : ?>
											<span style="color:var(--ssi-text-muted);font-weight:600;"><?php echo esc_html( $table['size_mb'] ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( ! empty( $autoload_stats['top_options'] ) ) : ?>
					<h4 class="ssi-subheading" style="margin:20px 0 10px;font-size:11px;text-transform:uppercase;color:var(--ssi-text-muted);font-weight:700;letter-spacing:0.5px;"><?php esc_html_e( 'Top 5 Autoloaded Options', 'server-site-insight' ); ?></h4>
					<table class="ssi-table ssi-table--condensed">
						<thead>
							<tr>
								<th style="text-align:left;padding-bottom:8px;border:none;"><span class="ssi-dev-badge"><?php esc_html_e( 'Option Name', 'server-site-insight' ); ?></span></th>
								<th style="text-align:left;padding-bottom:8px;border:none;"><span class="ssi-dev-badge"><?php esc_html_e( 'Size KB', 'server-site-insight' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $autoload_stats['top_options'] as $opt ) : ?>
								<tr>
									<td style="font-family:monospace;font-size:12px;padding:8px 0;border-top:1px solid var(--ssi-border);word-break:break-all;"><?php echo esc_html( $opt['name'] ); ?></td>
									<td style="text-align:left;font-size:12px;padding:8px 0;border-top:1px solid var(--ssi-border);whitespace:nowrap;">
										<span style="color:var(--ssi-text-muted);font-weight:600;"><?php echo esc_html( $opt['size_kb'] ); ?></span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</details>

		<!-- Environment & Cache -->
		<details class="ssi-card ssi-card--dev" id="ssi-dev-env">
			<summary class="ssi-card__header ssi-flex-between" style="cursor:pointer;list-style:none;">
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="ssi-card__icon dashicons dashicons-admin-settings" aria-hidden="true"></span>
					<h2 class="ssi-card__title" id="ssi-ttl-dev-env"><?php esc_html_e( 'Environment & Cache', 'server-site-insight' ); ?></h2>
				</div>
				<span class="dashicons dashicons-arrow-down-alt2 ssi-toggle-icon"></span>
			</summary>
			<div class="ssi-card__body ssi-copy-target">
				<table class="ssi-table"><tbody>
					<?php ssi_row( __( 'Object Cache', 'server-site-insight' ), $object_cache['enabled'] ? esc_html__( 'Active', 'server-site-insight' ) : esc_html__( 'Disabled', 'server-site-insight' ) ); ?>
					<?php ssi_row( __( 'Cache Type', 'server-site-insight' ), esc_html( $object_cache['type'] ) ); ?>
					<?php 
						if ( $object_cache['stats'] ) {
							ssi_row( __( 'Cache Stats', 'server-site-insight' ), esc_html( $object_cache['stats'] ) );
						}
					?>
					<?php ssi_row( __( 'Total Queries', 'server-site-insight' ), esc_html( $query_count ) ); ?>
					<?php ssi_row( __( 'PHP Ext Loaded', 'server-site-insight' ), esc_html( count( $php_extensions ) ) ); ?>
				</tbody></table>
			</div>
		</details>

		<!-- WP Cron Inspector -->
		<details class="ssi-card ssi-card--dev" id="ssi-dev-cron">
			<summary class="ssi-card__header ssi-flex-between" style="cursor:pointer;list-style:none;">
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="ssi-card__icon dashicons dashicons-clock" aria-hidden="true"></span>
					<h2 class="ssi-card__title"><?php esc_html_e( 'WP-Cron Jobs', 'server-site-insight' ); ?></h2>
				</div>
				<span class="dashicons dashicons-arrow-down-alt2 ssi-toggle-icon"></span>
			</summary>
			<div class="ssi-card__body">
				<?php if ( ! empty( $cron_stats['overdue'] ) ) : ?>
					<h4 class="ssi-subheading" style="margin:0 0 10px;font-size:11px;text-transform:uppercase;color:var(--ssi-warn-text);font-weight:700;">Overdue Tasks</h4>
					<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
						<?php foreach ( $cron_stats['overdue'] as $task ) : ?>
							<div class="ssi-endpoint" style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fff5f5;border:1px solid #feb2b2;border-radius:4px;">
								<div style="display:flex;flex-direction:column;">
									<code style="background:transparent;padding:0;color:#c53030;font-weight:600;font-size:13px;"><?php echo esc_html( $task['hook'] ); ?></code>
									<span style="font-size:11px;color:#e53e3e;">Due: <?php echo esc_html( $task['due'] ); ?> ago</span>
								</div>
								<button type="button" class="button button-small ssi-run-cron-btn" data-hook="<?php echo esc_attr( $task['hook'] ); ?>">Run Now</button>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<h4 class="ssi-subheading" style="margin:0 0 10px;font-size:11px;text-transform:uppercase;color:var(--ssi-text-muted);font-weight:700;">Upcoming (Next 5)</h4>
				<table class="ssi-table ssi-table--condensed">
					<tbody>
						<?php if ( empty( $cron_stats['upcoming'] ) ) : ?>
							<tr><td colspan="2" style="color:var(--ssi-text-muted);font-size:13px;"><?php esc_html_e('No upcoming tasks scheduled.', 'server-site-insight'); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $cron_stats['upcoming'] as $task ) : ?>
								<tr>
									<td style="font-family:monospace;font-size:12px;padding:8px 0;border-top:1px solid var(--ssi-border);"><?php echo esc_html( $task['hook'] ); ?></td>
									<td style="text-align:left;font-size:11px;color:var(--ssi-text-muted);padding:8px 0;border-top:1px solid var(--ssi-border);"><?php printf( esc_html__('In %s', 'server-site-insight'), $task['due'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</details>

		<!-- Core Checksums -->
		<details class="ssi-card ssi-card--dev" id="ssi-dev-core">
			<summary class="ssi-card__header ssi-flex-between" style="cursor:pointer;list-style:none;">
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="ssi-card__icon dashicons dashicons-shield" aria-hidden="true"></span>
					<h2 class="ssi-card__title"><?php esc_html_e( 'Core Checksums', 'server-site-insight' ); ?></h2>
				</div>
				<span class="dashicons dashicons-arrow-down-alt2 ssi-toggle-icon"></span>
			</summary>
			<div class="ssi-card__body">
				<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
					<p style="margin:0;font-size:13px;color:var(--ssi-text-muted);">Verify core files against the official WordPress repository API to detect malicious modifications.</p>
					<button type="button" class="button button-primary" id="ssi-verify-core-btn">Scan Core</button>
				</div>
				<div id="ssi-core-results" style="margin-top:16px;display:none;"></div>
			</div>
		</details>

	</div><!-- /.ssi-grid-col -->

	<!-- Right Column -->
	<div class="ssi-grid-col" style="display:flex;flex-direction:column;gap:var(--ssi-lg);">

		<!-- Query Performance (Lazy) -->
		<details class="ssi-card ssi-card--dev ssi-lazy-panel" id="ssi-dev-queries" data-action="ssi_lazy_queries">
			<summary class="ssi-card__header ssi-flex-between" style="cursor:pointer;list-style:none;">
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="ssi-card__icon dashicons dashicons-performance" aria-hidden="true"></span>
					<h2 class="ssi-card__title ssi-inline-block"><?php esc_html_e( 'Query Performance', 'server-site-insight' ); ?></h2>
				</div>
				<span class="dashicons dashicons-arrow-down-alt2 ssi-toggle-icon"></span>
			</summary>
			<div class="ssi-card__body ssi-lazy-content">
				<div class="ssi-loader" style="padding:20px;text-align:left;color:var(--ssi-text-muted);"><span class="dashicons dashicons-update dashicons-update-spin"></span> Loading query data...</div>
			</div>
		</details>

		<!-- Hooks & Filters (Lazy) -->
		<details class="ssi-card ssi-card--dev ssi-lazy-panel" id="ssi-dev-hooks" data-action="ssi_lazy_hooks">
			<summary class="ssi-card__header ssi-flex-between" style="cursor:pointer;list-style:none;">
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="ssi-card__icon dashicons dashicons-admin-links" aria-hidden="true"></span>
					<h2 class="ssi-card__title ssi-inline-block"><?php esc_html_e( 'Hooks & Filters', 'server-site-insight' ); ?></h2>
				</div>
				<span class="dashicons dashicons-arrow-down-alt2 ssi-toggle-icon"></span>
			</summary>
			<div class="ssi-card__body ssi-lazy-content">
				<div class="ssi-loader" style="padding:20px;text-align:left;color:var(--ssi-text-muted);"><span class="dashicons dashicons-update dashicons-update-spin"></span> Counting hooks...</div>
			</div>
		</details>

		<!-- Scripts & Styles (Lazy) -->
		<details class="ssi-card ssi-card--dev ssi-lazy-panel" id="ssi-dev-scripts" data-action="ssi_lazy_scripts">
			<summary class="ssi-card__header ssi-flex-between" style="cursor:pointer;list-style:none;">
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="ssi-card__icon dashicons dashicons-media-code" aria-hidden="true"></span>
					<h2 class="ssi-card__title ssi-inline-block"><?php esc_html_e( 'Scripts & Styles', 'server-site-insight' ); ?></h2>
				</div>
				<span class="dashicons dashicons-arrow-down-alt2 ssi-toggle-icon"></span>
			</summary>
			<div class="ssi-card__body ssi-lazy-content">
				<div class="ssi-loader" style="padding:20px;text-align:left;color:var(--ssi-text-muted);"><span class="dashicons dashicons-update dashicons-update-spin"></span> Fetching enqueued assets...</div>
			</div>
		</details>

		<!-- Plugin Performance Footprint (Lazy) -->
		<details class="ssi-card ssi-card--dev ssi-lazy-panel" id="ssi-dev-plugins" data-action="ssi_lazy_plugins">
			<summary class="ssi-card__header ssi-flex-between" style="cursor:pointer;list-style:none;">
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="ssi-card__icon dashicons dashicons-plugins-checked" aria-hidden="true"></span>
					<h2 class="ssi-card__title ssi-inline-block"><?php esc_html_e( 'Plugin Hook Footprint', 'server-site-insight' ); ?></h2>
				</div>
				<span class="dashicons dashicons-arrow-down-alt2 ssi-toggle-icon"></span>
			</summary>
			<div class="ssi-card__body ssi-lazy-content">
				<div class="ssi-loader" style="padding:20px;text-align:left;color:var(--ssi-text-muted);">
					<span class="dashicons dashicons-update dashicons-update-spin"></span> Using reflection to identify plugin hooks...
				</div>
			</div>
		</details>

		<!-- REST API Inspector -->
		<details class="ssi-card ssi-card--dev" id="ssi-dev-rest">
			<summary class="ssi-card__header ssi-flex-between" style="cursor:pointer;list-style:none;">
				<div style="display:flex;align-items:center;gap:8px;">
					<span class="ssi-card__icon dashicons dashicons-rest-api" aria-hidden="true"></span>
					<h2 class="ssi-card__title ssi-inline-block"><?php esc_html_e( 'REST API Endpoints', 'server-site-insight' ); ?></h2>
					<span class="ssi-badge" style="margin-left:8px;"><?php echo count($rest_routes); ?></span>
				</div>
				<span class="dashicons dashicons-arrow-up-alt2 ssi-toggle-icon"></span>
			</summary>
			<div class="ssi-card__body">
				<div style="margin-bottom:12px;">
					<input type="text" id="ssi-rest-filter" placeholder="<?php esc_attr_e('Filter namespaces...', 'server-site-insight'); ?>" class="regular-text" style="width:100%;padding:4px 8px;font-size:13px;">
				</div>
				<div class="ssi-api-panel__list" style="max-height:300px;overflow-y:auto;padding-right:8px;border:1px solid var(--ssi-border);border-radius:4px;padding:8px;" id="ssi-rest-list">
					<?php foreach ( $rest_routes as $ep ) : ?>
					<div class="ssi-endpoint" style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--ssi-border);padding:6px 0;">
						<div style="display:flex;align-items:center;gap:10px;flex:1;overflow:hidden;">
							<span class="ssi-endpoint__method" style="font-size:10px;background:var(--ssi-surface-2);padding:2px 6px;border-radius:3px;font-weight:600;min-width:45px;text-align:center;"><?php echo esc_html( $ep['methods'] ); ?></span>
							<code class="ssi-endpoint__url" style="background:transparent;padding:0;font-size:12px;color:var(--ssi-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo esc_attr( $ep['route'] ); ?>"><?php echo esc_html( $ep['route'] ); ?></code>
						</div>
						<button type="button" class="ssi-icon-btn ssi-copy-route-btn" data-clipboard="<?php echo esc_attr( $ep['route'] ); ?>" style="padding:2px 4px;min-height:unset;line-height:1;" title="<?php esc_attr_e( 'Copy Route', 'server-site-insight' ); ?>">
							<span class="dashicons dashicons-welcome-documents" style="font-size:14px!important;width:14px!important;height:14px!important;"></span>
						</button>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</details>

	</div><!-- /.ssi-grid-col -->
</div><!-- /.ssi-grid -->
