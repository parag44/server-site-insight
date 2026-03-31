<?php
/**
 * History tab view — Professional Activity Audit Log.
 *
 * Included by admin-page.php inside the ssi-panel-history tab panel.
 *
 * @package Server_Site_Insight
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ssi_timeline = SSI_History_Storage::get_timeline();

// Pagination setup
$items_per_page = 20;
$total_items    = count( $ssi_timeline );
$total_pages    = ceil( $total_items / $items_per_page );
$current_page   = isset( $_GET['ssi_paged'] ) ? max( 1, intval( $_GET['ssi_paged'] ) ) : 1;
$offset         = ( $current_page - 1 ) * $items_per_page;
$ssi_timeline_paged = array_slice( $ssi_timeline, $offset, $items_per_page );
?>
<div id="ssi-panel-history" role="tabpanel" aria-labelledby="ssi-tab-history" class="ssi-tab-panel" hidden>

	<section class="ssi-history-section" aria-labelledby="ssi-ttl-audit">
		<div class="ssi-flex-between" style="margin-bottom:var(--ssi-lg);">
			<h2 class="ssi-section-title" id="ssi-ttl-audit" style="margin-bottom:0;">
				<span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
				<?php esc_html_e( 'Activity Audit Log', 'server-site-insight' ); ?>
				<?php if ( $total_items > 0 ) : ?>
					<span class="ssi-badge" style="margin-left:8px;"><?php echo esc_html( $total_items ); ?></span>
				<?php endif; ?>
			</h2>
			
			<div class="ssi-history-actions" style="display:flex;gap:10px;">
				<?php if ( ! empty( $ssi_timeline ) ) : ?>
				<button type="button" class="ssi-tool-btn ssi-tool-btn--outline" id="ssi-clear-history" style="color:#dc2626;">
					<span class="dashicons dashicons-trash" aria-hidden="true" style="margin-right:4px;"></span>
					<?php esc_html_e( 'Clear Log', 'server-site-insight' ); ?>
				</button>
				<?php endif; ?>
				<button type="button" class="ssi-tool-btn ssi-tool-btn--outline" id="ssi-refresh-history">
					<span class="dashicons dashicons-update" aria-hidden="true" style="margin-right:4px;"></span>
					<?php esc_html_e( 'Refresh', 'server-site-insight' ); ?>
				</button>
			</div>
		</div>

		<?php if ( empty( $ssi_timeline ) ) : ?>
			<div class="ssi-empty-state">
				<span class="dashicons dashicons-info" aria-hidden="true"></span>
				<p><?php esc_html_e( 'No activity recorded yet. The log will update automatically as site changes are detected.', 'server-site-insight' ); ?></p>
			</div>
		<?php else : ?>

			<!-- Professional Filters -->
			<div class="ssi-timeline-filters" id="ssi-timeline-filters" style="margin-bottom: var(--ssi-md); gap: 8px;">
				<button type="button" class="ssi-log-filter ssi-log-filter--active" data-filter="all"><?php esc_html_e( 'All Activity', 'server-site-insight' ); ?></button>
				<button type="button" class="ssi-log-filter" data-filter="content"><?php esc_html_e( 'Content', 'server-site-insight' ); ?></button>
				<button type="button" class="ssi-log-filter" data-filter="user"><?php esc_html_e( 'Users', 'server-site-insight' ); ?></button>
				<button type="button" class="ssi-log-filter" data-filter="system"><?php esc_html_e( 'System', 'server-site-insight' ); ?></button>
				<button type="button" class="ssi-log-filter" data-filter="plugin"><?php esc_html_e( 'Plugins', 'server-site-insight' ); ?></button>
			</div>

			<div class="ssi-audit-table-wrap">
				<table class="ssi-audit-table">
					<thead>
						<tr>
							<th class="ssi-audit-col--time"><?php esc_html_e( 'Date & Time', 'server-site-insight' ); ?></th>
							<th class="ssi-audit-col--user"><?php esc_html_e( 'User', 'server-site-insight' ); ?></th>
							<th class="ssi-audit-col--event"><?php esc_html_e( 'Event & Description', 'server-site-insight' ); ?></th>
							<th class="ssi-audit-col--ip"><?php esc_html_e( 'Source IP', 'server-site-insight' ); ?></th>
						</tr>
					</thead>
					<tbody id="ssi-audit-log-body">
						<?php foreach ( $ssi_timeline_paged as $event ) : ?>
							<?php
							$icon = 'dashicons-admin-generic';
							$cat_label = 'System';
							switch ( $event['type'] ) {
								case 'content':
									$icon = 'dashicons-admin-post';
									$cat_label = 'Content';
									break;
								case 'user':
									$icon = 'dashicons-admin-users';
									$cat_label = 'User';
									break;
								case 'plugin':
									$icon = 'dashicons-admin-plugins';
									$cat_label = 'Plugin';
									break;
								case 'theme':
									$icon = 'dashicons-admin-appearance';
									$cat_label = 'Theme';
									break;
							}
							
							// Format timestamp with fallback for ms
							$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
							$display_time = date_i18n( $date_format, floor( $event['timestamp'] ) );
							$ms = round( ( $event['timestamp'] - floor( $event['timestamp'] ) ) * 1000 );
							?>
							<tr class="ssi-audit-row" data-type="<?php echo esc_attr( $event['type'] ); ?>">
								<td class="ssi-audit-col--time">
									<div class="ssi-audit-time" title="<?php echo esc_attr( sprintf( __( 'Precise: %s.%03d', 'server-site-insight' ), $display_time, $ms ) ); ?>">
										<strong><?php echo esc_html( date_i18n( 'M j, Y', $event['timestamp'] ) ); ?></strong>
										<span><?php echo esc_html( date_i18n( 'H:i:s', $event['timestamp'] ) ); ?><small>.<?php echo esc_html( sprintf( '%03d', $ms ) ); ?></small></span>
									</div>
								</td>
								<td class="ssi-audit-col--user">
									<div class="ssi-audit-user">
										<strong><?php echo esc_html( $event['user_name'] ); ?></strong>
										<span class="ssi-audit-role"><?php echo esc_html( ucfirst( $event['user_role'] ) ); ?></span>
									</div>
								</td>
								<td class="ssi-audit-col--event">
									<div class="ssi-audit-event">
										<span class="ssi-audit-icon dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
										<div class="ssi-audit-event-desc">
											<span class="ssi-audit-category ssi-audit-category--<?php echo esc_attr( $event['type'] ); ?>"><?php echo esc_html( $cat_label ); ?></span>
											<p class="ssi-audit-message"><?php echo esc_html( $event['message'] ); ?></p>
											
											<?php if ( ! empty( $event['context']['diffs'] ) ) : ?>
												<div class="ssi-audit-diffs">
													<?php foreach ( $event['context']['diffs'] as $label => $diff ) : ?>
														<div class="ssi-audit-diff-item">
															<span class="ssi-audit-diff-label"><?php echo esc_html( str_replace( '_', ' ', ucfirst( $label ) ) ); ?>:</span>
															<span class="ssi-audit-diff-from"><?php echo esc_html( $diff['from'] ?: 'None' ); ?></span>
															<span class="dashicons dashicons-arrow-right-alt2"></span>
															<span class="ssi-audit-diff-to"><?php echo esc_html( $diff['to'] ); ?></span>
														</div>
													<?php endforeach; ?>
												</div>
											<?php endif; ?>
										</div>
									</div>
								</td>
								<td class="ssi-audit-col--ip">
									<code class="ssi-audit-ip"><?php echo esc_html( $event['user_ip'] ); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div><!-- /.ssi-audit-table-wrap -->

			<?php if ( $total_pages > 1 ) : ?>
				<div class="ssi-audit-pagination">
					<div class="ssi-audit-pagination__info">
						<?php printf( esc_html__( 'Page %1$d of %2$d', 'server-site-insight' ), $current_page, $total_pages ); ?>
					</div>
					<div class="ssi-audit-pagination__links">
						<?php if ( $current_page > 1 ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'ssi_paged', $current_page - 1 ) ); ?>#ssi-tab-history" class="ssi-tool-btn ssi-tool-btn--outline">
								<span class="dashicons dashicons-arrow-left-alt2"></span>
								<?php esc_html_e( 'Previous', 'server-site-insight' ); ?>
							</a>
						<?php endif; ?>

						<?php if ( $current_page < $total_pages ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'ssi_paged', $current_page + 1 ) ); ?>#ssi-tab-history" class="ssi-tool-btn ssi-tool-btn--outline">
								<?php esc_html_e( 'Next', 'server-site-insight' ); ?>
								<span class="dashicons dashicons-arrow-right-alt2"></span>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

		<?php endif; ?>
	</section>

	<p class="ssi-footer" style="margin-top:20px;">
		<?php
		echo wp_kses(
			sprintf(
				/* translators: 1: plugin name and version in <strong>, 2: timestamp string */
				__( '%1$s &mdash; Activity History v%2$s', 'server-site-insight' ),
				'<strong>Server &amp; Site Insight</strong>',
				esc_html( SSI_VERSION )
			),
			array( 'strong' => array() )
		);
		?>
	</p>

</div><!-- /#ssi-panel-history -->
