<?php
/**
 * Settings page view — Server & Site Insight v2.
 *
 * Form submission handled here using wp_verify_nonce; each field is
 * sanitized individually before passing to SSI_Settings::save().
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Capability guard (redundant — caller already checks, but defense-in-depth).
if ( ! current_user_can( SSI_CAPABILITY ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions.', 'server-site-insight' ) );
}

$ssi_saved = false;

// ── Process form submission ──────────────────────────────────────────────────
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ssi_settings_nonce'] ) ) {
	$nonce = sanitize_key( wp_unslash( $_POST['ssi_settings_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'ssi_save_settings' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'server-site-insight' ) );
	}

	// Sanitize each expected field individually — never pass raw $_POST.
	$raw = isset( $_POST['ssi'] ) && is_array( $_POST['ssi'] ) ? $_POST['ssi'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-key below

	$sanitized = array(
		'enable_email_alerts' => ! empty( $raw['enable_email_alerts'] ),
		'enable_notices'      => ! empty( $raw['enable_notices'] ),
		'developer_mode'      => ! empty( $raw['developer_mode'] ),
		'alert_email'         => isset( $raw['alert_email'] ) ? sanitize_email( wp_unslash( $raw['alert_email'] ) ) : '',
	);

	SSI_Settings::save( $sanitized );

	// Purge cached system info so settings take effect immediately.
	SSI_System_Info::purge_cache();

	$ssi_saved = true;
}

$s = SSI_Settings::all(); // Current settings.
?>
<div class="wrap ssi-notices-container" style="margin-bottom:0; padding-bottom:0;">
	<?php do_action( 'admin_notices' ); ?>
	<?php do_action( 'all_admin_notices' ); ?>
</div>

<div id="ssi-app" class="ssi-wrap ssi-settings-wrap">

	<!-- Header -->
	<div class="ssi-settings-header">
		<span class="dashicons dashicons-chart-area ssi-settings-header__icon" aria-hidden="true"></span>
		<div>
			<h1 class="ssi-settings-header__title"><?php esc_html_e( 'Insight Settings', 'server-site-insight' ); ?></h1>
			<p class="ssi-settings-header__sub"><?php esc_html_e( 'Configure alerts, developer mode, and display preferences.', 'server-site-insight' ); ?></p>
		</div>
	</div>

	<?php if ( $ssi_saved ) : ?>
		<div class="notice notice-success is-dismissible ssi-notice-saved">
			<p><?php esc_html_e( 'Settings saved successfully.', 'server-site-insight' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="" class="ssi-settings-form">
		<?php wp_nonce_field( 'ssi_save_settings', 'ssi_settings_nonce' ); ?>

		<!-- ── Email Alerts ─────────────────────────────────────────────── -->
		<div class="ssi-settings-card">
			<h2 class="ssi-settings-card__title">
				<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Email Alerts', 'server-site-insight' ); ?>
			</h2>
			<p class="ssi-settings-card__desc">
				<?php esc_html_e( 'Receive an HTML email when critical health issues are detected. Runs daily via WP-Cron.', 'server-site-insight' ); ?>
			</p>

			<div class="ssi-field">
				<label class="ssi-toggle">
					<input type="checkbox" name="ssi[enable_email_alerts]" value="1"
						id="ssi-f-email-alerts"
						<?php checked( $s['enable_email_alerts'] ); ?>>
					<span class="ssi-toggle__slider"></span>
				</label>
				<label for="ssi-f-email-alerts" class="ssi-field__label">
					<?php esc_html_e( 'Enable email alerts for critical issues', 'server-site-insight' ); ?>
				</label>
			</div>

			<div class="ssi-field">
				<label for="ssi-f-alert-email" class="ssi-field__label">
					<?php esc_html_e( 'Alert email address', 'server-site-insight' ); ?>
				</label>
				<input type="email" id="ssi-f-alert-email" name="ssi[alert_email]"
					value="<?php echo esc_attr( $s['alert_email'] ); ?>"
					class="ssi-field__input"
					placeholder="<?php echo esc_attr( get_bloginfo( 'admin_email' ) ); ?>">
				<p class="ssi-field__hint">
					<?php esc_html_e( 'Defaults to the WordPress admin email if left blank.', 'server-site-insight' ); ?>
				</p>
			</div>
		</div>

		<!-- ── Admin Notices ─────────────────────────────────────────────── -->
		<div class="ssi-settings-card">
			<h2 class="ssi-settings-card__title">
				<span class="dashicons dashicons-flag" aria-hidden="true"></span>
				<?php esc_html_e( 'Admin Notices', 'server-site-insight' ); ?>
			</h2>
			<p class="ssi-settings-card__desc">
				<?php esc_html_e( 'Show dismissible warning notices on all admin pages for detected health issues.', 'server-site-insight' ); ?>
			</p>
			<div class="ssi-field">
				<label class="ssi-toggle">
					<input type="checkbox" name="ssi[enable_notices]" value="1"
						id="ssi-f-notices"
						<?php checked( $s['enable_notices'] ); ?>>
					<span class="ssi-toggle__slider"></span>
				</label>
				<label for="ssi-f-notices" class="ssi-field__label">
					<?php esc_html_e( 'Show admin notices for warnings and critical issues', 'server-site-insight' ); ?>
				</label>
			</div>
		</div>

		<!-- ── Developer Mode ────────────────────────────────────────────── -->
		<div class="ssi-settings-card">
			<h2 class="ssi-settings-card__title">
				<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
				<?php esc_html_e( 'Developer Mode', 'server-site-insight' ); ?>
			</h2>
			<p class="ssi-settings-card__desc">
				<?php esc_html_e( 'Reveal extended diagnostics: PHP extensions, database size, query count, REST routes, and object cache type. Visible to administrators only.', 'server-site-insight' ); ?>
			</p>
			<div class="ssi-field">
				<label class="ssi-toggle">
					<input type="checkbox" name="ssi[developer_mode]" value="1"
						id="ssi-f-dev-mode"
						<?php checked( $s['developer_mode'] ); ?>>
					<span class="ssi-toggle__slider"></span>
				</label>
				<label for="ssi-f-dev-mode" class="ssi-field__label">
					<?php esc_html_e( 'Enable Developer Mode', 'server-site-insight' ); ?>
				</label>
			</div>
		</div>

		<!-- ── Footer ────────────────────────────────────────────────────── -->
		<div class="ssi-settings-footer">
			<?php submit_button( __( 'Save Settings', 'server-site-insight' ), 'primary ssi-save-btn', 'submit', false ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=server-site-insight' ) ); ?>" class="button ssi-back-btn">
				<?php esc_html_e( '← Back to Dashboard', 'server-site-insight' ); ?>
			</a>
		</div>

	</form>
</div><!-- /.ssi-settings-wrap -->
