<?php
/**
 * SSI_Settings — all plugin options in a single wp_options row.
 *
 * Defaults are defined here. Callers use SSI_Settings::get() — never
 * read raw options directly.
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSI_Settings
 */
class SSI_Settings {

	/** WP option key. @var string */
	const OPTION_KEY = 'ssi_settings';

	/**
	 * Default values used when the option does not exist.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'enable_email_alerts' => false,
			'alert_email'         => get_bloginfo( 'admin_email' ),
			'enable_notices'      => true,
			'developer_mode'      => false,
			'dark_mode'           => false,
		);
	}

	/**
	 * Return all settings merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		$saved = get_option( self::OPTION_KEY, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Return a single setting value.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $default  Fallback if key not found.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist settings from an already-sanitized array.
	 *
	 * Caller is responsible for sanitising each value before passing here.
	 * This method performs a final pass to ensure type safety.
	 *
	 * @param array $data  Pre-sanitized settings array.
	 */
	public static function save( array $data ) {
		$current = self::all();

		// Booleans.
		foreach ( array( 'enable_email_alerts', 'enable_notices', 'developer_mode', 'dark_mode' ) as $k ) {
			if ( array_key_exists( $k, $data ) ) {
				$current[ $k ] = (bool) $data[ $k ];
			}
		}

		// Email address — extra validation pass.
		if ( ! empty( $data['alert_email'] ) ) {
			$email = sanitize_email( $data['alert_email'] );
			if ( is_email( $email ) ) {
				$current['alert_email'] = $email;
			}
		}

		update_option( self::OPTION_KEY, $current );
	}
}
