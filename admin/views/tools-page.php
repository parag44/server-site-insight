<?php
/**
 * Tools tab view — Debug, Security & Maintenance controls.
 *
 * Included by admin-page.php inside the ssi-panel-tools tab panel.
 * All AJAX actions are handled by SSI_Tools::handle_action().
 *
 * @package Server_Site_Insight
 * @since   3.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

// ── Gather current state ────────────────────────────────────────────────────
$ssi_tools_nonce      = wp_create_nonce( SSI_Tools::NONCE_ACTION );

// Fetch internal settings (no longer reading/writing wp-config.php)
$ssi_wp_debug         = (bool) get_option( 'ssi_debug_enabled', false );
$ssi_wp_debug_log     = (bool) get_option( 'ssi_debug_log', false );
$ssi_wp_debug_display = (bool) get_option( 'ssi_debug_display', false );
$ssi_savequeries      = (bool) get_option( 'ssi_savequeries_enabled', false );

// Security settings
$ssi_disallow_file_edit = (bool) get_option( 'ssi_disallow_file_edit', false );
$ssi_xmlrpc_disabled    = (bool) get_option( 'ssi_xmlrpc_disabled', false );

/**
 * Helper: render a toggle row inside a tools card.
 */
function ssi_tool_row( $id, $label, $desc, $checked, $data_attrs = array(), $extra_class = '' ) {
	$allowed_row = array(
		'div'    => array( 'class' => array() ),
		'label'  => array( 'class' => array(), 'for' => array() ),
		'input'  => array(
			'type'            => array(),
			'id'              => array(),
			'class'           => array(),
			'checked'         => array(),
			'data-nonce'      => array(),
			'data-constant'   => array(),
			'data-disabled'   => array(),
			'data-value'      => array(),
		),
		'span'   => array( 'class' => array() ),
		'strong' => array(),
		'small'  => array( 'class' => array() ),
	);

	$data_str = '';
	foreach ( $data_attrs as $key => $val ) {
		$data_str .= ' ' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
	}

	$row_class = 'ssi-tool-row' . ( $extra_class ? ' ' . $extra_class : '' );

	$html  = '<div class="' . esc_attr( $row_class ) . '">';
	$html .= '<div class="ssi-tool-row__info">';
	$html .= '<label class="ssi-tool-row__label" for="' . esc_attr( $id ) . '">';
	$html .= '<strong>' . esc_html( $label ) . '</strong>';
	$html .= '</label>';
	$html .= '<small class="ssi-tool-row__desc">' . esc_html( $desc ) . '</small>';
	$html .= '</div>';
	$html .= '<div class="ssi-tool-row__control">';
	$html .= '<label class="ssi-toggle ssi-toggle--tool" aria-label="' . esc_attr( $label ) . '">';
	$html .= '<input type="checkbox" id="' . esc_attr( $id ) . '" class="ssi-config-toggle"';
	$html .= $data_str;
	$html .= $checked ? ' checked' : '';
	$html .= '>';
	$html .= '<span class="ssi-toggle__slider"></span>';
	$html .= '</label>';
	$html .= '</div>';
	$html .= '</div>';

	echo wp_kses( $html, $allowed_row );
}
?>

<div id="ssi-panel-tools" role="tabpanel" aria-labelledby="ssi-tab-tools" class="ssi-tab-panel" hidden>

	<div class="ssi-tools-grid">

		<!-- ═══ Card 0: Production Mode / Development Mode ═══════════════════════════════════ -->
		<?php
		$ssi_production_active = SSI_Tools::is_production_mode_active();
		$ssi_prod_card_class   = $ssi_production_active ? 'ssi-card ssi-card--production ssi-card--production-active' : 'ssi-card ssi-card--warning';
		?>
		<section class="<?php echo esc_attr( $ssi_prod_card_class ); ?>" aria-labelledby="ssi-ttl-production">
			<div class="ssi-card__header">
				<span class="ssi-card__icon dashicons <?php echo $ssi_production_active ? 'dashicons-shield' : 'dashicons-hammer'; ?>" aria-hidden="true"></span>
				<h2 class="ssi-card__title" id="ssi-ttl-production">
					<?php echo $ssi_production_active ? esc_html__( 'Production Mode', 'server-site-insight' ) : esc_html__( 'Development Mode', 'server-site-insight' ); ?>
				</h2>
				<?php if ( $ssi_production_active ) : ?>
					<span class="ssi-badge ssi-badge--production-active"><?php esc_html_e( 'Active', 'server-site-insight' ); ?></span>
				<?php else : ?>
					<span class="ssi-badge ssi-badge--warning"><?php esc_html_e( 'Active', 'server-site-insight' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="ssi-card__body">
				<p class="ssi-tools-hint">
					<?php if ( $ssi_production_active ) : ?>
						<?php esc_html_e( 'Production mode is active. Internal debugging and file editing are safely disabled.', 'server-site-insight' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Development mode is active. Internal debugging features are enabled. Disable this before going live.', 'server-site-insight' ); ?>
					<?php endif; ?>
				</p>
				<div class="ssi-tool-row ssi-tool-row--production">
					<div class="ssi-tool-row__info">
						<label class="ssi-tool-row__label" for="ssi-toggle-production-mode">
							<?php if ( $ssi_production_active ) : ?>
								<strong><?php esc_html_e( 'Disable Production Mode', 'server-site-insight' ); ?></strong>
							<?php else : ?>
								<strong><?php esc_html_e( 'Enable Production Mode', 'server-site-insight' ); ?></strong>
							<?php endif; ?>
						</label>
						<?php if ( $ssi_production_active ) : ?>
						<ul class="ssi-production-status">
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Internal Debug is OFF', 'server-site-insight' ); ?></li>
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'File Editor is OFF', 'server-site-insight' ); ?></li>
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'XML-RPC is OFF', 'server-site-insight' ); ?></li>
						</ul>
						<?php else : ?>
						<small class="ssi-tool-row__desc">
							<?php esc_html_e( 'Click to lock down the site and disable all internal debugging utilities.', 'server-site-insight' ); ?>
						</small>
						<?php endif; ?>
					</div>
					<div class="ssi-tool-row__control">
						<label class="ssi-toggle ssi-toggle--tool" aria-label="<?php esc_attr_e( 'Toggle Mode', 'server-site-insight' ); ?>">
							<input type="checkbox"
								id="ssi-toggle-production-mode"
								class="ssi-production-toggle"
								data-nonce="<?php echo esc_attr( $ssi_tools_nonce ); ?>"
								<?php checked( $ssi_production_active ); ?>>
							<span class="ssi-toggle__slider"></span>
						</label>
					</div>
				</div>
			</div>
		</section>

		<!-- ═══ Card 1: Debug Settings ════════════════════════════════════ -->
		<section class="ssi-card" aria-labelledby="ssi-ttl-debug">
			<div class="ssi-card__header">
				<span class="ssi-card__icon dashicons dashicons-editor-code" aria-hidden="true"></span>
				<h2 class="ssi-card__title" id="ssi-ttl-debug"><?php esc_html_e( 'Internal Debug Settings', 'server-site-insight' ); ?></h2>
				<?php if ( $ssi_wp_debug ) : ?>
					<span class="ssi-badge ssi-badge--warning"><?php esc_html_e( 'Active', 'server-site-insight' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="ssi-card__body">
				<p class="ssi-tools-hint">
					<?php esc_html_e( 'Overrides PHP settings to enable diagnostics without touching your wp-config.php file.', 'server-site-insight' ); ?>
				</p>
				<?php
				ssi_tool_row(
					'ssi-toggle-wp-debug',
					'Internal Debugging',
					__( 'Simulates WP_DEBUG. Enables all PHP error output processing.', 'server-site-insight' ),
					$ssi_wp_debug,
					array(
						'data-constant' => 'WP_DEBUG',
						'data-nonce'    => $ssi_tools_nonce,
					),
					$ssi_wp_debug ? 'ssi-tool-row--danger' : ''
				);

				ssi_tool_row(
					'ssi-toggle-wp-debug-log',
					'Log to debug.log',
					__( 'Redirect errors to wp-content/debug.log for forensic analysis.', 'server-site-insight' ),
					$ssi_wp_debug_log,
					array(
						'data-constant' => 'WP_DEBUG_LOG',
						'data-nonce'    => $ssi_tools_nonce,
					)
				);

				ssi_tool_row(
					'ssi-toggle-wp-debug-display',
					'Display Errors',
					__( 'Show PHP errors directly on the screen (Internal Display).', 'server-site-insight' ),
					$ssi_wp_debug_display,
					array(
						'data-constant' => 'WP_DEBUG_DISPLAY',
						'data-nonce'    => $ssi_tools_nonce,
					)
				);

				ssi_tool_row(
					'ssi-toggle-savequeries',
					'Track Queries (SAVEQUERIES)',
					__( 'Monitor and save database query traces for analysis.', 'server-site-insight' ),
					$ssi_savequeries,
					array(
						'data-constant' => 'SAVEQUERIES',
						'data-nonce'    => $ssi_tools_nonce,
					)
				);
				?>
			</div>
		</section>

		<!-- ═══ Card 2: Security Controls ═════════════════════════════════ -->
		<section class="ssi-card" aria-labelledby="ssi-ttl-security-tools">
			<div class="ssi-card__header">
				<span class="ssi-card__icon dashicons dashicons-shield" aria-hidden="true"></span>
				<h2 class="ssi-card__title" id="ssi-ttl-security-tools"><?php esc_html_e( 'Security Controls', 'server-site-insight' ); ?></h2>
			</div>
			<div class="ssi-card__body">
				<p class="ssi-tools-hint">
					<?php esc_html_e( 'Manage site security policies via the database — no core file modifications required.', 'server-site-insight' ); ?>
				</p>

				<?php
				ssi_tool_row(
					'ssi-toggle-disallow-file-edit',
					'Disable File Editor',
					__( 'Block the built-in theme/plugin code editor (Internal Policy).', 'server-site-insight' ),
					$ssi_disallow_file_edit,
					array(
						'data-constant' => 'DISALLOW_FILE_EDIT',
						'data-nonce'    => $ssi_tools_nonce,
					)
				);
				?>

				<div class="ssi-tool-row">
					<div class="ssi-tool-row__info">
						<label class="ssi-tool-row__label" for="ssi-toggle-xmlrpc">
							<strong><?php esc_html_e( 'Disable XML-RPC', 'server-site-insight' ); ?></strong>
						</label>
						<small class="ssi-tool-row__desc">
							<?php esc_html_e( 'Block all XML-RPC access handlers. Prevents brute-force via xmlrpc.php.', 'server-site-insight' ); ?>
						</small>
					</div>
					<div class="ssi-tool-row__control">
						<label class="ssi-toggle ssi-toggle--tool" aria-label="<?php esc_attr_e( 'Disable XML-RPC', 'server-site-insight' ); ?>">
							<input type="checkbox"
								id="ssi-toggle-xmlrpc"
								class="ssi-xmlrpc-toggle"
								data-nonce="<?php echo esc_attr( $ssi_tools_nonce ); ?>"
								<?php checked( $ssi_xmlrpc_disabled ); ?>>
							<span class="ssi-toggle__slider"></span>
						</label>
					</div>
				</div>
			</div>
		</section>

		<!-- ═══ Card 3: Maintenance Actions ═══════════════════════════════ -->
		<section class="ssi-card" aria-labelledby="ssi-ttl-maintenance">
			<div class="ssi-card__header">
				<span class="ssi-card__icon dashicons dashicons-admin-tools" aria-hidden="true"></span>
				<h2 class="ssi-card__title" id="ssi-ttl-maintenance"><?php esc_html_e( 'Maintenance Actions', 'server-site-insight' ); ?></h2>
			</div>
			<div class="ssi-card__body">
				<div class="ssi-tool-action">
					<div class="ssi-tool-action__info">
						<strong><?php esc_html_e( 'Clear All Transients', 'server-site-insight' ); ?></strong>
						<p class="ssi-tool-action__desc">
							<?php esc_html_e( 'Deletes all _transient_ and _site_transient_ rows from the database.', 'server-site-insight' ); ?>
						</p>
					</div>
					<button type="button" id="ssi-clear-transients" class="ssi-tool-btn ssi-tool-btn--warning" data-nonce="<?php echo esc_attr( $ssi_tools_nonce ); ?>">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'Clear Transients', 'server-site-insight' ); ?>
					</button>
				</div>
				<hr class="ssi-tools-divider">
				<div class="ssi-tool-action">
					<div class="ssi-tool-action__info">
						<strong><?php esc_html_e( 'Flush Rewrite Rules', 'server-site-insight' ); ?></strong>
						<p class="ssi-tool-action__desc">
							<?php esc_html_e( 'Regenerates WordPress permalink structures.', 'server-site-insight' ); ?>
						</p>
					</div>
					<button type="button" id="ssi-flush-rewrites" class="ssi-tool-btn ssi-tool-btn--primary" data-nonce="<?php echo esc_attr( $ssi_tools_nonce ); ?>">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php esc_html_e( 'Flush Rewrite Rules', 'server-site-insight' ); ?>
					</button>
				</div>
			</div>
		</section>

	</div>
</div>
