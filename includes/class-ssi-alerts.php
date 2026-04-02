<?php
/**
 * SSI_Alerts — dismissible admin notices and optional HTML email alerts.
 *
 * All output is properly escaped. Notices respect per-user dismiss transients.
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSI_Alerts
 */
class SSI_Alerts {

	/**
	 * Output admin notices for non-good checks.
	 *
	 * Runs on admin_notices hook. Each notice is dismissible once per day
	 * per user per check key (stored as a transient).
	 */
	public static function maybe_show_notices() {
		$info    = SSI_System_Info::get_all();
		$checks  = SSI_Health_Check::run( $info );
		$overall = SSI_Health_Check::overall_status( $checks );

		if ( 'good' === $overall ) {
			return;
		}

		$uid     = absint( get_current_user_id() );
		$page    = admin_url( 'admin.php?page=server-site-insight' );
		$nonce   = wp_create_nonce( 'ssi_ajax_nonce' );

		foreach ( $checks as $key => $check ) {
			if ( SSI_Health_Check::STATUS_GOOD === $check['status'] ) {
				continue;
			}

			$dismiss_key = sanitize_key( $key );
			if ( get_transient( 'ssi_notice_dismissed_' . $uid . '_' . $dismiss_key ) ) {
				continue;
			}

			$css_class = SSI_Health_Check::STATUS_CRITICAL === $check['status'] ? 'notice-error' : 'notice-warning';

			printf(
				'<div class="notice %1$s ssi-admin-notice"><p><strong>%2$s:</strong> %3$s &mdash; <a href="%4$s">%5$s</a> <a href="#" class="ssi-dismiss-notice" data-key="%6$s" data-nonce="%7$s" style="margin-left:8px;opacity:.6" aria-label="%8$s">[%9$s]</a></p></div>',
				esc_attr( $css_class ),
				esc_html( $check['label'] ),
				esc_html( $check['message'] ),
				esc_url( $page ),
				esc_html__( 'View Insight Panel', 'server-site-insight' ),
				esc_attr( $dismiss_key ),
				esc_attr( $nonce ),
				esc_attr__( 'Dismiss this notice', 'server-site-insight' ),
				esc_html_x( 'Dismiss', 'dismiss notice link text', 'server-site-insight' )
			);
		}
	}

	/**
	 * Send an HTML email summarising critical issues.
	 *
	 * @param array $checks  Return value of SSI_Health_Check::run().
	 */
	public static function send_email( array $checks ) {
		$email = SSI_Settings::get( 'alert_email', get_bloginfo( 'admin_email' ) );
		if ( ! is_email( $email ) ) {
			return;
		}

		$critical = array_filter(
			$checks,
			function ( $c ) {
				return SSI_Health_Check::STATUS_CRITICAL === $c['status'];
			}
		);

		if ( empty( $critical ) ) {
			return;
		}

		$site    = sanitize_text_field( get_bloginfo( 'name' ) );
		$url     = admin_url( 'admin.php?page=server-site-insight' );
		$rows    = '';

		foreach ( $critical as $check ) {
			$rows .= sprintf(
				'<tr><td style="padding:8px 12px;border-bottom:1px solid #eee"><strong>%s</strong></td><td style="padding:8px 12px;border-bottom:1px solid #eee">%s</td></tr>',
				esc_html( $check['label'] ),
				esc_html( $check['message'] )
			);
		}

		$body = sprintf(
			'<!DOCTYPE html><html><body style="font-family:sans-serif;color:#1a1d2e;line-height:1.6">
<h2 style="color:#ef4444">&#9888; %1$s</h2>
<p>%2$s</p>
<table style="width:100%%;border-collapse:collapse;font-size:14px">%3$s</table>
<p style="margin-top:24px">
<a href="%4$s" style="background:#6366f1;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600">%5$s</a>
</p>
<p style="color:#9ca3af;font-size:12px;margin-top:32px">%6$s</p>
</body></html>',
			/* 1 */ esc_html(
				sprintf(
					/* translators: %s: website/blog name */
					__( 'Critical Issues on %s', 'server-site-insight' ),
					$site
				)
			),
			/* 2 */ esc_html__( 'Your site has critical health issues that need immediate attention:', 'server-site-insight' ),
			/* 3 */ $rows, // Already escaped above.
			/* 4 */ esc_url( $url ),
			/* 5 */ esc_html__( 'View Insight Panel', 'server-site-insight' ),
			/* 6 */ esc_html__( 'This email was sent by the Server & Site Insight plugin. You can disable these alerts in the plugin settings.', 'server-site-insight' )
		);

		$subject = sprintf(
			/* translators: 1: site name in square brackets, 2: alert description string */
			'[%1$s] %2$s',
			$site,
			__( 'Critical Site Health Alert — Immediate Action Required', 'server-site-insight' )
		);

		wp_mail(
			$email,
			$subject,
			$body,
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}
}
