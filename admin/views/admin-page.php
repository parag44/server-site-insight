<?php
/**
 * Main admin dashboard view — Server & Site Insight v2.
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ── Data ───────────────────────────────────────────────────────────────────
$ssi_dev      = SSI_Settings::get( 'developer_mode', false );
$ssi_info     = SSI_System_Info::get_all( $ssi_dev );
$ssi_checks   = SSI_Health_Check::run( $ssi_info );
$ssi_overall  = SSI_Health_Check::overall_status( $ssi_checks );
$ssi_score    = SSI_Health_Score::calculate( $ssi_checks );
$ssi_grade    = SSI_Health_Score::grade( $ssi_score );
$ssi_latest   = SSI_History_Storage::get_latest();
$ssi_changes  = SSI_History_Storage::get_changes();
$ssi_history  = SSI_History_Storage::get_history();

// ── Template helpers ───────────────────────────────────────────────────────
function ssi_badge( $status ) {
	$labels = array( 'good' => __( 'Good', 'server-site-insight' ), 'warning' => __( 'Warning', 'server-site-insight' ), 'critical' => __( 'Critical', 'server-site-insight' ) );
	$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	return '<span class="ssi-badge ssi-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
}

function ssi_row( $label, $value, $tooltip = '' ) {
	$tip_attr  = $tooltip ? ' data-tooltip="' . esc_attr( $tooltip ) . '"' : '';
	$tip_icon  = $tooltip ? ' <span class="ssi-tip-icon" aria-hidden="true">?</span>' : '';
	$allowed   = array(
		'a'    => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
		'span' => array( 'class' => array(), 'aria-hidden' => array() ),
		'code' => array(),
		'em'   => array(),
		'strong' => array(),
	);
	echo wp_kses(
		'<tr><th scope="row"' . $tip_attr . '>' . esc_html( $label ) . $tip_icon . '</th><td>' . wp_kses( $value, $allowed ) . '</td></tr>',
		array(
			'tr' => array(),
			'th' => array( 'scope' => array(), 'data-tooltip' => array() ),
			'td' => array(),
			'a'  => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
			'span' => array( 'class' => array(), 'aria-hidden' => array() ),
			'code' => array(),
			'em'  => array(),
			'strong' => array(),
		)
	);
}
?>
<div class="wrap ssi-notices-container" style="margin-bottom:0; padding-bottom:0;">
	<?php do_action( 'admin_notices' ); ?>
	<?php do_action( 'all_admin_notices' ); ?>
</div>

<div id="ssi-app" class="ssi-wrap" data-score="<?php echo esc_attr( $ssi_score ); ?>" data-overall="<?php echo esc_attr( $ssi_overall ); ?>">

	<!-- ═══ STICKY SUMMARY BAR ════════════════════════════════════════════ -->
	<div class="ssi-sticky-bar ssi-sticky-bar--<?php echo esc_attr( $ssi_overall ); ?>" id="ssi-sticky-bar" role="banner">
		<div class="ssi-sticky-bar__inner">
			<span class="ssi-sticky-bar__name">
				<span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
				<?php esc_html_e( 'Server & Site Insight', 'server-site-insight' ); ?>
			</span>
			<span class="ssi-sticky-bar__score">
				<?php
				/*
				 * translators: %1$s: numeric score wrapped in <strong>, %2$s: grade letter wrapped in <strong>.
				 * The placeholders intentionally contain HTML — rendered with wp_kses, not esc_html__.
				 */
				echo wp_kses(
					sprintf(
						/* translators: 1: health score number, 2: grade letter */
						__( 'Health Score: <strong>%1$d</strong> / 100 &mdash; Grade <strong>%2$s</strong>', 'server-site-insight' ),
						(int) $ssi_score,
						esc_html( $ssi_grade )
					),
					array( 'strong' => array() )
				);
				?>
			</span>
			<div class="ssi-sticky-bar__actions">
				<!-- Filter buttons -->
				<div class="ssi-filter-group" role="group" aria-label="<?php esc_attr_e( 'Filter checks', 'server-site-insight' ); ?>">
					<button class="ssi-filter-btn active" data-filter="all" type="button"><?php esc_html_e( 'All', 'server-site-insight' ); ?></button>
					<button class="ssi-filter-btn" data-filter="warning" type="button"><?php esc_html_e( 'Warnings', 'server-site-insight' ); ?></button>
					<button class="ssi-filter-btn" data-filter="critical" type="button"><?php esc_html_e( 'Critical', 'server-site-insight' ); ?></button>
				</div>
				<!-- Copy report -->
				<button id="ssi-copy-btn" type="button" class="ssi-icon-btn" aria-label="<?php esc_attr_e( 'Copy report', 'server-site-insight' ); ?>" title="<?php esc_attr_e( 'Copy Report', 'server-site-insight' ); ?>">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
				</button>
				<!-- Export JSON -->
				<button id="ssi-export-btn" type="button" class="ssi-icon-btn" aria-label="<?php esc_attr_e( 'Export JSON', 'server-site-insight' ); ?>" title="<?php esc_attr_e( 'Export JSON', 'server-site-insight' ); ?>">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
				</button>
				<!-- Print / PDF -->
				<button id="ssi-print-btn" type="button" class="ssi-icon-btn" aria-label="<?php esc_attr_e( 'Print report', 'server-site-insight' ); ?>" title="<?php esc_attr_e( 'Print / Save PDF', 'server-site-insight' ); ?>">
					<span class="dashicons dashicons-printer" aria-hidden="true"></span>
				</button>
				<!-- Settings -->
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=server-site-insight-settings' ) ); ?>" class="ssi-icon-btn" aria-label="<?php esc_attr_e( 'Settings', 'server-site-insight' ); ?>" title="<?php esc_attr_e( 'Settings', 'server-site-insight' ); ?>">
					<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
				</a>
			</div>
		</div>
	</div>

	<!-- Hidden data island for JS -->
	<script id="ssi-data-json" type="application/json"><?php echo wp_json_encode( $ssi_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); ?></script>

	<!-- ═══ HEALTH SCORE HERO ═════════════════════════════════════════════ -->
	<div class="ssi-hero ssi-hero--<?php echo esc_attr( $ssi_overall ); ?>">
		<div class="ssi-hero__left">
			<div class="ssi-score-ring" role="img" aria-label="<?php
				echo esc_attr(
					sprintf(
						/* translators: %d: health score integer between 0 and 100 */
						__( 'Health score: %d out of 100', 'server-site-insight' ),
						(int) $ssi_score
					)
				);
			?>">
				<svg viewBox="0 0 120 120" class="ssi-score-svg" aria-hidden="true">
					<circle class="ssi-score-track" cx="60" cy="60" r="50" stroke-width="10" fill="none"/>
					<circle class="ssi-score-fill ssi-score-fill--<?php echo esc_attr( $ssi_overall ); ?>" cx="60" cy="60" r="50" stroke-width="10" fill="none"
						stroke-dasharray="<?php echo esc_attr( round( 314 * $ssi_score / 100 ) . ' 314' ); ?>"
						stroke-dashoffset="78.5"
						stroke-linecap="round"/>
				</svg>
				<div class="ssi-score-center">
					<span class="ssi-score-num"><?php echo esc_html( $ssi_score ); ?></span>
					<span class="ssi-score-denom">/100</span>
					<span class="ssi-score-grade"><?php echo esc_html( $ssi_grade ); ?></span>
				</div>
			</div>
		</div>
		<div class="ssi-hero__right">
			<h1 class="ssi-hero__title"><?php esc_html_e( 'Server & Site Insight', 'server-site-insight' ); ?> <span class="ssi-version-pill">v<?php echo esc_html( SSI_VERSION ); ?></span></h1>
			<p class="ssi-hero__desc"><?php echo esc_html( SSI_Health_Score::description( $ssi_score ) ); ?></p>
			<?php if ( $ssi_latest ) : ?>
			<p class="ssi-hero__meta">
				<span class="dashicons dashicons-clock" aria-hidden="true"></span>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: human-readable time elapsed, e.g. "5 minutes ago" */
						__( 'Last checked: <strong>%s</strong>', 'server-site-insight' ),
						esc_html( SSI_History_Storage::time_ago( $ssi_latest['timestamp'] ) )
					),
					array( 'strong' => array() )
				);
				?>
			</p>
			<?php endif; ?>
			<?php if ( ! empty( $ssi_changes ) ) : ?>
			<div class="ssi-changes">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Changes since last check:', 'server-site-insight' ); ?></strong>
				<?php foreach ( $ssi_changes as $key => $change ) : ?>
					<span class="ssi-change-pill">
						<?php echo esc_html( $key . ': ' . $change['from'] . ' → ' . $change['to'] ); ?>
					</span>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- ═══ SMART STATUS BAR ════════════════════════════════════════════ -->
	<?php
	/**
	 * Smart Status Bar — icon map, tab/card routing, tooltip, fix links.
	 *
	 * Priority order: critical → warning → good (max 2 visible, rest collapsible).
	 */

	// Per-check metadata: icon, target tab, target card ID, fix URL.
	$ssi_pill_meta = array(
		'php_version'   => array(
			'icon'  => 'dashicons-editor-code',
			'tab'   => 'ssi-panel-overview',
			'card'  => 'ssi-card-server',
			'fix'   => 'https://wordpress.org/about/requirements/',
			'tip'   => __( 'PHP: Your server PHP version. Outdated PHP = unpatched security holes and slower performance.', 'server-site-insight' ),
		),
		'memory_limit'  => array(
			'icon'  => 'dashicons-performance',
			'tab'   => 'ssi-panel-overview',
			'card'  => 'ssi-card-server',
			'fix'   => 'https://developer.wordpress.org/advanced-administration/wordpress/common-errors/',
			'tip'   => __( 'Memory: PHP memory_limit. Low memory causes fatal errors and white screens.', 'server-site-insight' ),
		),
		'debug_mode'    => array(
			'icon'  => 'dashicons-warning',
			'tab'   => 'ssi-panel-tools',
			'card'  => 'ssi-ttl-debug',
			'fix'   => '',
			'tip'   => __( 'Debug Mode: WP_DEBUG being ON in production exposes file paths and errors to visitors.', 'server-site-insight' ),
		),
		'https'         => array(
			'icon'  => 'dashicons-lock',
			'tab'   => 'ssi-panel-overview',
			'card'  => 'ssi-card-security',
			'fix'   => 'https://wordpress.org/documentation/article/https-for-wordpress/',
			'tip'   => __( 'HTTPS: SSL encryption protects logins, data, and is required by modern browsers for secure features.', 'server-site-insight' ),
		),
		'rest_api'      => array(
			'icon'  => 'dashicons-rest-api',
			'tab'   => 'ssi-panel-overview',
			'card'  => 'ssi-card-wordpress',
			'fix'   => 'https://developer.wordpress.org/rest-api/',
			'tip'   => __( 'REST API: Powers Gutenberg and many plugins. Blocking it breaks core WordPress features.', 'server-site-insight' ),
		),
		'mysql_version' => array(
			'icon'  => 'dashicons-database',
			'tab'   => 'ssi-panel-overview',
			'card'  => 'ssi-card-server',
			'fix'   => 'https://wordpress.org/about/requirements/',
			'tip'   => __( 'MySQL: Database version. Outdated DB software lacks security patches and performance improvements.', 'server-site-insight' ),
		),
		'wp_version'    => array(
			'icon'  => 'dashicons-wordpress-alt',
			'tab'   => 'ssi-panel-overview',
			'card'  => 'ssi-card-wordpress',
			'fix'   => esc_url( admin_url( 'update-core.php' ) ),
			'tip'   => __( 'WordPress: Always keep core updated. Outdated WordPress contains known vulnerabilities.', 'server-site-insight' ),
		),
		'cron'          => array(
			'icon'  => 'dashicons-clock',
			'tab'   => 'ssi-panel-overview',
			'card'  => 'ssi-card-wordpress',
			'fix'   => '',
			'tip'   => __( 'WP Cron: Handles scheduled tasks. Disabling without a real server cron stops all automation.', 'server-site-insight' ),
		),
		'xmlrpc'        => array(
			'icon'  => 'dashicons-shield-alt',
			'tab'   => 'ssi-panel-tools',
			'card'  => 'ssi-ttl-security-tools',
			'fix'   => '',
			'tip'   => __( 'XML-RPC: Legacy API frequently abused for brute-force attacks. Disable unless you specifically need it.', 'server-site-insight' ),
		),
		'debug_log'     => array(
			'icon'  => 'dashicons-media-text',
			'tab'   => 'ssi-panel-tools',
			'card'  => 'ssi-log-card',
			'fix'   => '',
			'tip'   => __( 'Debug Log: wp-content/debug.log may expose sensitive info if publicly accessible via URL.', 'server-site-insight' ),
		),
		'file_editor'   => array(
			'icon'  => 'dashicons-edit',
			'tab'   => 'ssi-panel-tools',
			'card'  => 'ssi-ttl-security-tools',
			'fix'   => '',
			'tip'   => __( 'File Editor: The built-in code editor allows code injection if an admin account is compromised.', 'server-site-insight' ),
		),
	);

	// Sort: critical first, then warning, then good.
	$ssi_sorted_checks = $ssi_checks;
	uasort( $ssi_sorted_checks, function ( $a, $b ) {
		$order = array( 'critical' => 0, 'warning' => 1, 'good' => 2 );
		$oa = isset( $order[ $a['status'] ] ) ? $order[ $a['status'] ] : 3;
		$ob = isset( $order[ $b['status'] ] ) ? $order[ $b['status'] ] : 3;
		return $oa - $ob;
	} );

	// Split into: primary (all critical+warning + up to 2 good) and overflow (remaining good).
	$ssi_primary_pills  = array();
	$ssi_overflow_pills = array();
	$ssi_good_shown     = 0;

	foreach ( $ssi_sorted_checks as $key => $check ) {
		if ( 'good' === $check['status'] ) {
			if ( $ssi_good_shown < 5 ) {
				$ssi_primary_pills[ $key ] = $check;
				++$ssi_good_shown;
			} else {
				$ssi_overflow_pills[ $key ] = $check;
			}
		} else {
			$ssi_primary_pills[ $key ] = $check;
		}
	}

	$ssi_overflow_count = count( $ssi_overflow_pills );

	/**
	 * Render a single smart pill.
	 *
	 * @param string $key    Check key (e.g. 'php_version').
	 * @param array  $check  Health check result array.
	 * @param array  $meta   Pill meta (icon, tab, card, fix, tip).
	 * @param string $class  Extra CSS class.
	 */
	function ssi_smart_pill( $key, $check, $meta, $class = '' ) {
		$status  = esc_attr( $check['status'] );
		$icon    = isset( $meta['icon'] ) ? $meta['icon'] : 'dashicons-yes-alt';
		$tab     = isset( $meta['tab'] )  ? $meta['tab']  : '';
		$card    = isset( $meta['card'] ) ? $meta['card'] : '';
		$fix     = isset( $meta['fix'] )  ? $meta['fix']  : '';
		$tip     = isset( $meta['tip'] )  ? $meta['tip']  : $check['message'];
		$tooltip = $check['message'] . ( $tip !== $check['message'] ? ' ' . $tip : '' );

		$is_actionable  = false; // pills are display-only, not navigable
		$el             = 'span';
		$el_type        = '';

		$aria_desc = esc_attr( $check['label'] . ': ' . $check['message'] );

		echo wp_kses(
			'<' . $el
			. ' class="ssi-health-pill ssi-health-pill--' . $status . ( $class ? ' ' . esc_attr( $class ) : '' ) . '"'
			. ' data-status="' . $status . '"'
			. ' data-key="' . esc_attr( $key ) . '"'
			. ' data-tooltip="' . esc_attr( $tooltip ) . '"'
			. ' role="listitem"'
			. ' tabindex="0"'
			. ' aria-label="' . $aria_desc . '"'
			. '><span class="ssi-health-pill__icon dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>'
			. '<span class="ssi-health-pill__name">' . esc_html( $check['label'] ) . '</span>'
			. '<span class="ssi-health-pill__value">' . esc_html( $check['value'] ) . '</span>'
			. '</' . $el . '>',
			array(
				'span' => array(
					'class'        => array(),
					'data-status'  => array(),
					'data-key'     => array(),
					'data-tooltip' => array(),
					'role'         => array(),
					'tabindex'     => array(),
					'aria-label'   => array(),
					'aria-hidden'  => array(),
				),
			)
		);
	}
	?>

	<div class="ssi-health-strip" role="list" id="ssi-health-strip" aria-label="<?php esc_attr_e( 'Site health status', 'server-site-insight' ); ?>">

		<!-- Primary pills: critical + warning + 2 good -->
		<?php foreach ( $ssi_primary_pills as $key => $check ) : ?>
			<?php ssi_smart_pill( $key, $check, isset( $ssi_pill_meta[ $key ] ) ? $ssi_pill_meta[ $key ] : array() ); ?>
		<?php endforeach; ?>

		<?php if ( $ssi_overflow_count > 0 ) : ?>
			<!-- Overflow: remaining good pills, collapsed by default -->
			<div class="ssi-pill-overflow" id="ssi-pill-overflow" hidden>
				<?php foreach ( $ssi_overflow_pills as $key => $check ) : ?>
					<?php ssi_smart_pill( $key, $check, isset( $ssi_pill_meta[ $key ] ) ? $ssi_pill_meta[ $key ] : array(), 'ssi-health-pill--overflow' ); ?>
				<?php endforeach; ?>
			</div><!-- /.ssi-pill-overflow -->
			<button type="button"
				class="ssi-health-pill ssi-health-pill--more"
				id="ssi-pill-more-btn"
				aria-expanded="false"
				aria-controls="ssi-pill-overflow"
				title="<?php esc_attr_e( 'Show remaining checks', 'server-site-insight' ); ?>">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<span class="ssi-pill-more-label">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of additional hidden checks */
							_n( '+%d more', '+%d more', $ssi_overflow_count, 'server-site-insight' ),
							$ssi_overflow_count
						)
					);
					?>
				</span>
			</button>
		<?php endif; ?>

	</div><!-- /.ssi-health-strip -->

	<!-- ═══ TAB NAVIGATION ═══════════════════════════════════════════════ -->
	<div class="ssi-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Insight Panel sections', 'server-site-insight' ); ?>">
		<button class="ssi-tab-btn ssi-tab-btn--active" role="tab" id="ssi-tab-overview"
			aria-controls="ssi-panel-overview" aria-selected="true" type="button">
			<span class="dashicons dashicons-admin-home" aria-hidden="true"></span>
			<?php esc_html_e( 'Overview', 'server-site-insight' ); ?>
		</button>
		<button class="ssi-tab-btn" role="tab" id="ssi-tab-checks"
			aria-controls="ssi-panel-checks" aria-selected="false" type="button">
			<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
			<?php esc_html_e( 'Health Checks', 'server-site-insight' ); ?>
			<?php
			$ssi_critical_count = count( array_filter( $ssi_checks, function( $c ) { return 'critical' === $c['status']; } ) );
			$ssi_warn_count     = count( array_filter( $ssi_checks, function( $c ) { return 'warning' === $c['status']; } ) );
			if ( $ssi_critical_count ) :
			?>
				<span class="ssi-tab-badge ssi-tab-badge--critical"><?php echo esc_html( $ssi_critical_count ); ?></span>
			<?php elseif ( $ssi_warn_count ) : ?>
				<span class="ssi-tab-badge ssi-tab-badge--warning"><?php echo esc_html( $ssi_warn_count ); ?></span>
			<?php endif; ?>
		</button>
		<?php if ( $ssi_dev && isset( $ssi_info['developer'] ) ) : ?>
		<button class="ssi-tab-btn" role="tab" id="ssi-tab-developer"
			aria-controls="ssi-panel-developer" aria-selected="false" type="button">
			<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
			<?php esc_html_e( 'Developer', 'server-site-insight' ); ?>
			<span class="ssi-tab-badge ssi-tab-badge--dev"><?php esc_html_e( 'DEV', 'server-site-insight' ); ?></span>
		</button>
		<?php endif; ?>
		<!-- Logs Tab -->
		<button class="ssi-tab-btn" role="tab" id="ssi-tab-logs"
			aria-controls="ssi-panel-logs" aria-selected="false" type="button">
			<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
			<?php esc_html_e( 'Logs', 'server-site-insight' ); ?>
		</button>

		<!-- Tools tab — always visible to manage_options users -->
		<button class="ssi-tab-btn" role="tab" id="ssi-tab-tools"
			aria-controls="ssi-panel-tools" aria-selected="false" type="button">
			<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
			<?php esc_html_e( 'Tools', 'server-site-insight' ); ?>
		</button>
	</div>

	<!-- ═══ TAB PANEL 1: OVERVIEW ════════════════════════════════════════ -->
	<div id="ssi-panel-overview" role="tabpanel" aria-labelledby="ssi-tab-overview" class="ssi-tab-panel ssi-tab-panel--active">

		<div class="ssi-grid" id="ssi-card-grid">

			<!-- ── WordPress ───────────────────────────────────────────────── -->
			<section class="ssi-card" id="ssi-card-wordpress" aria-labelledby="ssi-ttl-wp">
				<div class="ssi-card__header">
					<span class="ssi-card__icon dashicons dashicons-wordpress-alt" aria-hidden="true"></span>
					<h2 class="ssi-card__title" id="ssi-ttl-wp"><?php esc_html_e( 'WordPress', 'server-site-insight' ); ?></h2>
					<?php echo ssi_badge( $ssi_checks['wp_version']['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="ssi-card__body">
					<table class="ssi-table"><tbody>
						<?php
						ssi_row( __( 'WP Version', 'server-site-insight' ), esc_html( $ssi_info['wordpress']['wp_version'] ) . ' ' . ssi_badge( $ssi_checks['wp_version']['status'] ), __( 'The installed WordPress version. Keep it updated for security.', 'server-site-insight' ) );
						ssi_row( __( 'Site URL', 'server-site-insight' ), '<a href="' . esc_url( $ssi_info['wordpress']['site_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $ssi_info['wordpress']['site_url'] ) . '</a>' );
						ssi_row( __( 'Home URL', 'server-site-insight' ), '<a href="' . esc_url( $ssi_info['wordpress']['home_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $ssi_info['wordpress']['home_url'] ) . '</a>' );
						ssi_row( __( 'Active Theme', 'server-site-insight' ), esc_html( $ssi_info['wordpress']['active_theme'] ) );
						ssi_row( __( 'Language', 'server-site-insight' ), esc_html( $ssi_info['wordpress']['language'] ) );
						ssi_row( __( 'Charset', 'server-site-insight' ), esc_html( $ssi_info['wordpress']['charset'] ) );
						ssi_row( __( 'Multisite', 'server-site-insight' ), $ssi_info['wordpress']['multisite'] ? esc_html__( 'Yes', 'server-site-insight' ) : esc_html__( 'No', 'server-site-insight' ) );
						?>
					</tbody></table>
					<div class="ssi-subpanel">
						<h3 class="ssi-subpanel__title"><span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
							<?php
							printf(
								/* translators: %d: number of currently active plugins */
								esc_html__( 'Active Plugins (%d)', 'server-site-insight' ),
								count( $ssi_info['wordpress']['active_plugins'] )
							);
							?>
						</h3>
						<ul class="ssi-plugin-list">
							<?php foreach ( $ssi_info['wordpress']['active_plugins'] as $pname ) : ?>
								<li class="ssi-plugin-list__item"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php echo esc_html( $pname ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</section>

			<!-- ── Server ──────────────────────────────────────────────────── -->
			<section class="ssi-card" id="ssi-card-server" aria-labelledby="ssi-ttl-srv">
				<div class="ssi-card__header">
					<span class="ssi-card__icon dashicons dashicons-admin-network" aria-hidden="true"></span>
					<h2 class="ssi-card__title" id="ssi-ttl-srv"><?php esc_html_e( 'Server', 'server-site-insight' ); ?></h2>
					<?php echo ssi_badge( $ssi_checks['php_version']['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="ssi-card__body">
					<table class="ssi-table"><tbody>
						<?php
						ssi_row( __( 'PHP Version', 'server-site-insight' ), esc_html( $ssi_info['server']['php_version'] ) . ' ' . ssi_badge( $ssi_checks['php_version']['status'] ), __( 'PHP powers WordPress. Upgrade to 8.0+ for security and speed.', 'server-site-insight' ) );
						ssi_row( __( 'PHP SAPI', 'server-site-insight' ), esc_html( $ssi_info['server']['php_sapi'] ) );
						ssi_row( __( 'Server Software', 'server-site-insight' ), esc_html( $ssi_info['server']['server_software'] ) );
						ssi_row( __( 'MySQL / MariaDB', 'server-site-insight' ), esc_html( $ssi_info['server']['mysql_version'] ) . ' ' . ssi_badge( $ssi_checks['mysql_version']['status'] ), __( 'Database server version. MySQL 8.0+ or MariaDB 10.6+ recommended.', 'server-site-insight' ) );
						ssi_row( __( 'Memory Limit', 'server-site-insight' ), esc_html( $ssi_info['server']['memory_limit'] ) . ' ' . ssi_badge( $ssi_checks['memory_limit']['status'] ), __( 'PHP memory limit. WordPress recommends 256 MB or more.', 'server-site-insight' ) );
						ssi_row( __( 'Max Upload Size', 'server-site-insight' ), esc_html( $ssi_info['server']['max_upload_size'] ) );
						ssi_row( __( 'Max Execution Time', 'server-site-insight' ), esc_html( $ssi_info['server']['max_exec_time'] ) );
						ssi_row( __( 'Post Max Size', 'server-site-insight' ), esc_html( $ssi_info['server']['post_max_size'] ) );
						ssi_row( __( 'OS / Architecture', 'server-site-insight' ), esc_html( $ssi_info['server']['os'] . ' ' . $ssi_info['server']['architecture'] ) );
						?>
					</tbody></table>
				</div>
			</section>

			<!-- ── Environment ─────────────────────────────────────────────── -->
			<section class="ssi-card" id="ssi-card-environment" aria-labelledby="ssi-ttl-env">
				<div class="ssi-card__header">
					<span class="ssi-card__icon dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
					<h2 class="ssi-card__title" id="ssi-ttl-env"><?php esc_html_e( 'Environment', 'server-site-insight' ); ?></h2>
					<?php echo ssi_badge( $ssi_overall ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="ssi-card__body">
					<table class="ssi-table"><tbody>
						<?php
						ssi_row( __( 'REST API', 'server-site-insight' ), esc_html( $ssi_checks['rest_api']['value'] ) . ' ' . ssi_badge( $ssi_checks['rest_api']['status'] ), __( 'The REST API powers Gutenberg and many plugins. Should always be accessible.', 'server-site-insight' ) );
						ssi_row( __( 'WP_DEBUG', 'server-site-insight' ), esc_html( $ssi_checks['debug_mode']['value'] ) . ' ' . ssi_badge( $ssi_checks['debug_mode']['status'] ), __( 'Debug mode outputs errors publicly. Must be disabled on production.', 'server-site-insight' ) );
						ssi_row( __( 'Debug Log', 'server-site-insight' ), esc_html( $ssi_checks['debug_log']['value'] ) . ' ' . ssi_badge( $ssi_checks['debug_log']['status'] ) );
						ssi_row( __( 'WP-Cron', 'server-site-insight' ), esc_html( $ssi_checks['cron']['value'] ) . ' ' . ssi_badge( $ssi_checks['cron']['status'] ) );
						ssi_row( __( 'HTTPS', 'server-site-insight' ), esc_html( $ssi_checks['https']['value'] ) . ' ' . ssi_badge( $ssi_checks['https']['status'] ), __( 'HTTPS encrypts data in transit and is required by modern browsers.', 'server-site-insight' ) );
						ssi_row( __( 'Environment Type', 'server-site-insight' ), esc_html( $ssi_info['environment']['environment'] ) );
						ssi_row( __( 'Object Cache', 'server-site-insight' ), $ssi_info['environment']['cache'] ? esc_html__( 'Enabled', 'server-site-insight' ) : esc_html__( 'Disabled', 'server-site-insight' ) );
						?>
					</tbody></table>
				</div>
			</section>

			<!-- ── Performance ─────────────────────────────────────────────── -->
			<section class="ssi-card" id="ssi-card-performance" aria-labelledby="ssi-ttl-perf">
				<div class="ssi-card__header">
					<span class="ssi-card__icon dashicons dashicons-performance" aria-hidden="true"></span>
					<h2 class="ssi-card__title" id="ssi-ttl-perf"><?php esc_html_e( 'Performance', 'server-site-insight' ); ?></h2>
				</div>
				<div class="ssi-card__body">
					<?php $perf = $ssi_info['performance']; ?>
					<!-- Memory usage bar -->
					<div class="ssi-memory-bar-wrap">
						<div class="ssi-memory-bar-label">
						<span><?php esc_html_e( 'Memory Usage', 'server-site-insight' ); ?></span>
						<span>
						<?php
						if ( -1 === $ssi_info['server']['memory_limit_mb'] ) {
							echo esc_html(
								sprintf(
									/* translators: %d: memory used in MB. The slash and ∞ are intentional. */
									__( '%d MB / \u221e', 'server-site-insight' ),
									$perf['memory_used_mb']
								)
							);
						} else {
							echo esc_html(
								sprintf(
									/* translators: 1: memory used in MB, 2: memory limit in MB */
									__( '%1$d MB / %2$d MB', 'server-site-insight' ),
									$perf['memory_used_mb'],
									$ssi_info['server']['memory_limit_mb']
								)
							);
						}
						?>
						</span>
						</div>
						<div class="ssi-memory-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( $perf['memory_percent'] ); ?>" aria-valuemin="0" aria-valuemax="100">
							<?php $ssi_mem_status = $perf['memory_percent'] > 80 ? 'critical' : ( $perf['memory_percent'] > 60 ? 'warning' : 'good' ); ?>
							<div class="ssi-memory-bar__fill ssi-memory-bar__fill--<?php echo esc_attr( $ssi_mem_status ); ?>"
								style="width:<?php echo esc_attr( min( 100, $perf['memory_percent'] ) ); ?>%"></div>
						</div>
						<p class="ssi-memory-bar__pct"><?php echo esc_html( $perf['memory_percent'] . '%' ); ?></p>
					</div>
					<table class="ssi-table"><tbody>
						<?php
						ssi_row( __( 'Peak Memory', 'server-site-insight' ), esc_html( $perf['memory_peak_mb'] . ' MB' ), __( 'Maximum memory used during this request.', 'server-site-insight' ) );
						ssi_row( __( 'DB Queries', 'server-site-insight' ), esc_html( $perf['query_count'] ), __( 'Number of database queries executed to render this page.', 'server-site-insight' ) );
						ssi_row( __( 'OPcache', 'server-site-insight' ), $perf['opcache_enabled'] ? esc_html__( 'Enabled', 'server-site-insight' ) : esc_html__( 'Disabled', 'server-site-insight' ), __( 'OPcache caches compiled PHP bytecode, significantly improving speed.', 'server-site-insight' ) );
						ssi_row( __( 'GZip', 'server-site-insight' ), $perf['gzip_enabled'] ? esc_html__( 'Available', 'server-site-insight' ) : esc_html__( 'Unavailable', 'server-site-insight' ) );
						ssi_row( __( 'PHP Time Limit', 'server-site-insight' ), esc_html( $perf['php_time_limit'] . 's' ) );
						?>
					</tbody></table>
				</div>
			</section>

			<!-- ── Security ────────────────────────────────────────────────── -->
			<section class="ssi-card" id="ssi-card-security" aria-labelledby="ssi-ttl-sec">
				<div class="ssi-card__header">
					<span class="ssi-card__icon dashicons dashicons-shield" aria-hidden="true"></span>
					<h2 class="ssi-card__title" id="ssi-ttl-sec"><?php esc_html_e( 'Security', 'server-site-insight' ); ?></h2>
				</div>
				<div class="ssi-card__body">
					<table class="ssi-table"><tbody>
						<?php
						$sec = $ssi_info['security'];
						ssi_row( __( 'XML-RPC', 'server-site-insight' ), esc_html( $ssi_checks['xmlrpc']['value'] ) . ' ' . ssi_badge( $ssi_checks['xmlrpc']['status'] ), __( 'XML-RPC is a legacy API used in brute-force attacks.', 'server-site-insight' ) );
						ssi_row( __( 'File Editor', 'server-site-insight' ), esc_html( $ssi_checks['file_editor']['value'] ) . ' ' . ssi_badge( $ssi_checks['file_editor']['status'] ), __( 'The built-in code editor lets attackers inject code if an admin is compromised.', 'server-site-insight' ) );
						ssi_row( __( 'HTTPS Enforced', 'server-site-insight' ), $sec['https_enforced'] ? esc_html__( 'Yes', 'server-site-insight' ) : esc_html__( 'No', 'server-site-insight' ) );
						ssi_row( __( 'File Mods Disabled', 'server-site-insight' ), $sec['file_mods_disabled'] ? esc_html__( 'Yes', 'server-site-insight' ) : esc_html__( 'No', 'server-site-insight' ), __( 'DISALLOW_FILE_MODS prevents plugin/theme installs from admin.', 'server-site-insight' ) );
						ssi_row( __( 'wp-config.php Above Root', 'server-site-insight' ), $sec['wp_config_above_root'] ? esc_html__( 'Yes (good)', 'server-site-insight' ) : esc_html__( 'No', 'server-site-insight' ), __( 'Moving wp-config.php above the webroot prevents direct URL access.', 'server-site-insight' ) );
						ssi_row( __( 'Auto Core Updates', 'server-site-insight' ), $sec['auto_updates_core'] ? esc_html__( 'Enabled', 'server-site-insight' ) : esc_html__( 'Disabled', 'server-site-insight' ) );
						?>
					</tbody></table>
				</div>
			</section>

		</div><!-- /.ssi-grid -->

	</div><!-- /#ssi-panel-overview -->

	<!-- ═══ TAB PANEL 2: HEALTH CHECKS ══════════════════════════════════ -->
	<div id="ssi-panel-checks" role="tabpanel" aria-labelledby="ssi-tab-checks" class="ssi-tab-panel" hidden>

		<section class="ssi-checks-detail" id="ssi-checks-detail" aria-labelledby="ssi-ttl-checks">
			<h2 class="ssi-section-title" id="ssi-ttl-checks">
				<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
				<?php esc_html_e( 'Health Check Details', 'server-site-insight' ); ?>
			</h2>
			<div class="ssi-checks-list">
				<?php
				$ssi_tools_nonce = wp_create_nonce( SSI_Tools::NONCE_ACTION );
				$ssi_fix_actions = array(
					'debug_mode'  => array( 'type' => 'ajax', 'tool' => 'toggle_config_constant', 'constant' => 'WP_DEBUG', 'value' => '0', 'label' => __( 'Disable Debug', 'server-site-insight' ) ),
					'xmlrpc'      => array( 'type' => 'ajax', 'tool' => 'toggle_xmlrpc', 'disabled' => '1', 'label' => __( 'Disable XML-RPC', 'server-site-insight' ) ),
					'https'       => array( 'type' => 'link', 'href' => 'https://wordpress.org/documentation/article/https-for-wordpress/', 'label' => __( 'View Guide', 'server-site-insight' ) ),
					'file_editor' => array( 'type' => 'ajax', 'tool' => 'toggle_config_constant', 'constant' => 'DISALLOW_FILE_EDIT', 'value' => '1', 'label' => __( 'Disable Editor', 'server-site-insight' ) ),
				);
				?>
				<?php foreach ( $ssi_checks as $key => $check ) : ?>
				<div class="ssi-check-item ssi-check-item--<?php echo esc_attr( $check['status'] ); ?>" data-status="<?php echo esc_attr( $check['status'] ); ?>">
					<div class="ssi-check-item__summary">
						<span class="ssi-check-item__dot" aria-hidden="true"></span>
						<span class="ssi-check-item__label"><?php echo esc_html( $check['label'] ); ?></span>
						<span class="ssi-check-item__value"><?php echo esc_html( $check['value'] ); ?></span>
						<?php echo ssi_badge( $check['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<button class="ssi-explain-btn" type="button" aria-expanded="false" aria-controls="ssi-explain-<?php echo esc_attr( $key ); ?>">
							<?php esc_html_e( 'Explain', 'server-site-insight' ); ?> <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
						</button>
						<?php
						if ( ( 'warning' === $check['status'] || 'critical' === $check['status'] ) && isset( $ssi_fix_actions[ $key ] ) ) {
							$fix = $ssi_fix_actions[ $key ];
							if ( 'ajax' === $fix['type'] ) {
								echo '<button class="ssi-fix-btn" type="button" data-nonce="' . esc_attr( $ssi_tools_nonce ) . '" data-tool="' . esc_attr( $fix['tool'] ) . '"';
								if ( isset( $fix['constant'] ) ) { echo ' data-constant="' . esc_attr( $fix['constant'] ) . '"'; }
								if ( isset( $fix['value'] ) ) { echo ' data-value="' . esc_attr( $fix['value'] ) . '"'; }
								if ( isset( $fix['disabled'] ) ) { echo ' data-disabled="' . esc_attr( $fix['disabled'] ) . '"'; }
								echo '><span class="dashicons dashicons-admin-tools"></span> ' . esc_html( $fix['label'] ) . '</button>';
							} elseif ( 'link' === $fix['type'] ) {
								echo '<a href="' . esc_url( $fix['href'] ) . '" target="_blank" rel="noopener noreferrer" class="ssi-fix-btn ssi-fix-btn--link"><span class="dashicons dashicons-external"></span> ' . esc_html( $fix['label'] ) . '</a>';
							}
						}
						?>
					</div>
					<div class="ssi-check-item__detail" id="ssi-explain-<?php echo esc_attr( $key ); ?>" hidden>
						<p class="ssi-check-item__message"><?php echo esc_html( $check['message'] ); ?></p>
						<?php if ( ! empty( $check['explain'] ) ) : ?>
						<p class="ssi-check-item__explain"><strong><?php esc_html_e( 'Why this matters:', 'server-site-insight' ); ?></strong> <?php echo esc_html( $check['explain'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $check['recommendation'] ) ) : ?>
						<p class="ssi-check-item__rec"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><strong><?php esc_html_e( 'Recommendation:', 'server-site-insight' ); ?></strong> <?php echo esc_html( $check['recommendation'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</section>

	</div><!-- /#ssi-panel-checks -->

	<!-- ═══ TAB PANEL 3: DEVELOPER ═══════════════════════════════════════ -->
	<?php if ( $ssi_dev && isset( $ssi_info['developer'] ) ) : $dev = $ssi_info['developer']; ?>
	<div id="ssi-panel-developer" role="tabpanel" aria-labelledby="ssi-tab-developer" class="ssi-tab-panel" hidden>
		<?php require_once SSI_PLUGIN_DIR . 'admin/views/developer-page.php'; ?>

	</div><!-- /#ssi-panel-developer -->
	<?php endif; ?>

	<!-- ═══ TAB PANEL 4: LOGS ════════════════════════════════════════════ -->
	<div id="ssi-panel-logs" role="tabpanel" aria-labelledby="ssi-tab-logs" class="ssi-tab-panel" hidden>
		<?php require_once SSI_PLUGIN_DIR . 'admin/views/logs-page.php'; ?>
	</div>

	<?php
	// ═══ TAB PANEL 5: TOOLS ══════════════════════════════════════════════
	require_once SSI_PLUGIN_DIR . 'admin/views/tools-page.php';
	?>

</div><!-- /.ssi-wrap -->

<script>
/* Inline dismiss handler for admin notices */
document.querySelectorAll('.ssi-dismiss-notice').forEach(function(btn){
	btn.addEventListener('click',function(e){
		e.preventDefault();
		var notice = btn.closest('.ssi-admin-notice');
		var fd = new FormData();
		fd.append('action','ssi_dismiss_notice');
		fd.append('nonce', btn.dataset.nonce);
		fd.append('key', btn.dataset.key);
		fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',{method:'POST',body:fd,credentials:'same-origin'});
		if(notice){notice.style.display='none';}
	});
});
</script>

