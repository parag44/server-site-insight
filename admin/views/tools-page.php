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
$ssi_config_writable    = SSI_Tools::config_is_writable();
$ssi_tools_nonce        = wp_create_nonce( SSI_Tools::NONCE_ACTION );

// Debug constants — null means not defined in wp-config.php.
$ssi_wp_debug           = (bool) SSI_Tools::get_constant_value( 'WP_DEBUG' );
$ssi_wp_debug_log       = (bool) SSI_Tools::get_constant_value( 'WP_DEBUG_LOG' );
$ssi_debug_display_raw  = SSI_Tools::get_constant_value( 'WP_DEBUG_DISPLAY' );
$ssi_wp_debug_display   = ( null === $ssi_debug_display_raw ) ? true : (bool) $ssi_debug_display_raw;
$ssi_savequeries        = (bool) SSI_Tools::get_constant_value( 'SAVEQUERIES' );

// Security constants / options.
$ssi_disallow_file_edit = (bool) SSI_Tools::get_constant_value( 'DISALLOW_FILE_EDIT' );
$ssi_xmlrpc_disabled    = (bool) get_option( 'ssi_xmlrpc_disabled', false );

/**
 * Helper: render a toggle row inside a tools card.
 *
 * @param string $id         Unique element ID for the checkbox.
 * @param string $label      Human-readable label.
 * @param string $desc       Short description / tooltip text.
 * @param bool   $checked    Current toggle state.
 * @param array  $data_attrs Associative array of data-* attributes.
 * @param string $extra_class CSS class added to the row (e.g. 'ssi-tool-row--danger').
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

	// Build the HTML — each part is escaped individually.
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

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all parts escaped above
	echo wp_kses( $html, $allowed_row );
}
?>

<div id="ssi-panel-tools" role="tabpanel" aria-labelledby="ssi-tab-tools" class="ssi-tab-panel" hidden>

	<?php if ( ! $ssi_config_writable ) : ?>
	<!-- wp-config.php not writable notice -->
	<div class="ssi-tools-notice ssi-tools-notice--warning" role="alert">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<?php esc_html_e( 'wp-config.php is not writable. Debug toggles will provide a code snippet to add manually instead of editing the file automatically.', 'server-site-insight' ); ?>
	</div>
	<?php endif; ?>

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
						<?php esc_html_e( 'Production mode is active. Debugging features and file editing are safely disabled.', 'server-site-insight' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Development mode is active. Debugging features are enabled. Disable this before going live.', 'server-site-insight' ); ?>
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
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Debug Mode is OFF', 'server-site-insight' ); ?></li>
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'File Editor is OFF', 'server-site-insight' ); ?></li>
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'XML-RPC is OFF', 'server-site-insight' ); ?></li>
						</ul>
						<?php else : ?>
						<small class="ssi-tool-row__desc">
							<?php esc_html_e( 'Click to lock down the site and disable all debugging utilities.', 'server-site-insight' ); ?>
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
				<h2 class="ssi-card__title" id="ssi-ttl-debug"><?php esc_html_e( 'Debug Settings', 'server-site-insight' ); ?></h2>
				<?php if ( $ssi_wp_debug ) : ?>
					<span class="ssi-badge ssi-badge--warning"><?php esc_html_e( 'Active', 'server-site-insight' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="ssi-card__body">
				<p class="ssi-tools-hint">
					<?php esc_html_e( 'These constants live in wp-config.php. Disable debug on live sites to avoid exposing errors to visitors.', 'server-site-insight' ); ?>
				</p>
				<?php
				ssi_tool_row(
					'ssi-toggle-wp-debug',
					'WP_DEBUG',
					__( 'Master WordPress debug switch. Enables all PHP error output.', 'server-site-insight' ),
					$ssi_wp_debug,
					array(
						'data-constant' => 'WP_DEBUG',
						'data-nonce'    => $ssi_tools_nonce,
						'data-confirm'  => __( 'Enabling WP_DEBUG on a live site will expose PHP errors to visitors. Continue?', 'server-site-insight' ),
					),
					$ssi_wp_debug ? 'ssi-tool-row--danger' : ''
				);

				ssi_tool_row(
					'ssi-toggle-wp-debug-log',
					'WP_DEBUG_LOG',
					__( 'Write debug messages to wp-content/debug.log instead of the browser.', 'server-site-insight' ),
					$ssi_wp_debug_log,
					array(
						'data-constant' => 'WP_DEBUG_LOG',
						'data-nonce'    => $ssi_tools_nonce,
					)
				);

				ssi_tool_row(
					'ssi-toggle-wp-debug-display',
					'WP_DEBUG_DISPLAY',
					__( 'Display PHP errors on screen. Defaults to true when WP_DEBUG is on. Disable to hide errors from page output.', 'server-site-insight' ),
					$ssi_wp_debug_display,
					array(
						'data-constant' => 'WP_DEBUG_DISPLAY',
						'data-nonce'    => $ssi_tools_nonce,
						'data-confirm'  => __( 'Disabling WP_DEBUG_DISPLAY hides errors from the browser. Ensure WP_DEBUG_LOG is on to capture them.', 'server-site-insight' ),
					)
				);

				ssi_tool_row(
					'ssi-toggle-savequeries',
					'SAVEQUERIES',
					__( 'Monitor and save database query traces. Used by Plugin Impact Analyzer and Query insights to identify slowing queries.', 'server-site-insight' ),
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
					<?php esc_html_e( 'DISALLOW_FILE_EDIT is written to wp-config.php. XML-RPC is toggled via a plugin filter (no file edit needed).', 'server-site-insight' ); ?>
				</p>

				<?php
				// DISALLOW_FILE_EDIT — config constant toggle.
				ssi_tool_row(
					'ssi-toggle-disallow-file-edit',
					'DISALLOW_FILE_EDIT',
					__( 'Disable the built-in theme/plugin code editor. Strongly recommended on production.', 'server-site-insight' ),
					$ssi_disallow_file_edit,
					array(
						'data-constant' => 'DISALLOW_FILE_EDIT',
						'data-nonce'    => $ssi_tools_nonce,
					)
				);
				?>

				<!-- XML-RPC — stored in wp_options, no file edit. -->
				<div class="ssi-tool-row">
					<div class="ssi-tool-row__info">
						<label class="ssi-tool-row__label" for="ssi-toggle-xmlrpc">
							<strong><?php esc_html_e( 'Disable XML-RPC', 'server-site-insight' ); ?></strong>
						</label>
						<small class="ssi-tool-row__desc">
							<?php esc_html_e( 'Block all XML-RPC access. Prevents brute-force via xmlrpc.php. Toggle takes effect immediately — no file edit required.', 'server-site-insight' ); ?>
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

				<!-- Clear transients -->
				<div class="ssi-tool-action">
					<div class="ssi-tool-action__info">
						<strong><?php esc_html_e( 'Clear All Transients', 'server-site-insight' ); ?></strong>
						<p class="ssi-tool-action__desc">
							<?php esc_html_e( 'Deletes all _transient_ and _site_transient_ rows from the options table. Useful when debugging caching issues. Expired transients are regenerated automatically.', 'server-site-insight' ); ?>
						</p>
					</div>
					<button
						type="button"
						id="ssi-clear-transients"
						class="ssi-tool-btn ssi-tool-btn--warning"
						data-nonce="<?php echo esc_attr( $ssi_tools_nonce ); ?>"
						data-confirm="<?php esc_attr_e( 'Clear ALL transients? Cached data will be regenerated on next load.', 'server-site-insight' ); ?>">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'Clear Transients', 'server-site-insight' ); ?>
					</button>
				</div>

				<hr class="ssi-tools-divider">

				<!-- Flush rewrite rules -->
				<div class="ssi-tool-action">
					<div class="ssi-tool-action__info">
						<strong><?php esc_html_e( 'Flush Rewrite Rules', 'server-site-insight' ); ?></strong>
						<p class="ssi-tool-action__desc">
							<?php esc_html_e( 'Regenerates WordPress permalink .htaccess / Nginx rules. Use this after changing permalink settings or registering custom post types.', 'server-site-insight' ); ?>
						</p>
					</div>
					<button
						type="button"
						id="ssi-flush-rewrites"
						class="ssi-tool-btn ssi-tool-btn--primary"
						data-nonce="<?php echo esc_attr( $ssi_tools_nonce ); ?>"
						data-confirm="<?php esc_attr_e( 'Flush rewrite rules? Your permalink structure will be rebuilt.', 'server-site-insight' ); ?>">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php esc_html_e( 'Flush Rewrite Rules', 'server-site-insight' ); ?>
					</button>
				</div>

			</div>
		</section>

	</div><!-- /.ssi-tools-grid -->

</div><!-- /#ssi-panel-tools -->

<!-- ── Manual edit modal ──────────────────────────────────────────────────── -->
<div id="ssi-config-modal" class="ssi-modal" role="dialog" aria-modal="true" aria-labelledby="ssi-modal-title" hidden>
	<div class="ssi-modal__backdrop"></div>
	<div class="ssi-modal__box">
		<div class="ssi-modal__header">
			<h3 class="ssi-modal__title" id="ssi-modal-title">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<?php esc_html_e( 'Manual Edit Required', 'server-site-insight' ); ?>
			</h3>
			<button type="button" class="ssi-modal__close ssi-icon-btn" aria-label="<?php esc_attr_e( 'Close dialog', 'server-site-insight' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</div>
		<div class="ssi-modal__body">
			<p><?php esc_html_e( 'wp-config.php is not writable. Add or update this line in your wp-config.php file:', 'server-site-insight' ); ?></p>
			<div class="ssi-modal__snippet-wrap">
				<pre class="ssi-modal__snippet" aria-live="polite"></pre>
				<button type="button" class="ssi-modal__copy ssi-tool-btn ssi-tool-btn--sm" id="ssi-modal-copy">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<?php esc_html_e( 'Copy', 'server-site-insight' ); ?>
				</button>
			</div>
			<p class="ssi-modal__path">
				<?php
				$ssi_config_path = SSI_Tools::get_config_path();
				if ( $ssi_config_path ) {
					echo esc_html(
						sprintf(
							/* translators: %s: absolute file path to wp-config.php */
							__( 'File path: %s', 'server-site-insight' ),
							$ssi_config_path
						)
					);
				}
				?>
			</p>
		</div>
		<div class="ssi-modal__footer">
			<button type="button" class="ssi-modal__close ssi-tool-btn ssi-tool-btn--primary">
				<?php esc_html_e( 'Got it', 'server-site-insight' ); ?>
			</button>
		</div>
	</div>
</div>
