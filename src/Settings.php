<?php
/**
 * Plugin settings: defaults, load, sanitise.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor;

use IWorksIE\SpeedMonitor\Support\Urls;

/**
 * Settings stored in the iwiesm_settings option (API key stored separately).
 */
final class Settings {

	const OPTION     = 'iwiesm_settings';
	const KEY_OPTION = 'iwiesm_api_key';
	const MAX_URLS   = 10;

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'        => false,
			'urls'           => array( home_url( '/' ) ),
			'strategies'     => array( 'mobile', 'desktop' ),
			'frequency'      => 'weekly',
			'alerts_enabled' => true,
			'alert_email'    => (string) get_option( 'admin_email' ),
			'alert_drop'     => 10,
			'alert_below'    => 50,
			'retention_days' => 365,
		);
	}

	/**
	 * Current settings merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$settings = array_merge( self::defaults(), $stored );
		if ( empty( $settings['urls'] ) || ! is_array( $settings['urls'] ) ) {
			$settings['urls'] = array( home_url( '/' ) );
		}
		if ( empty( $settings['strategies'] ) || ! is_array( $settings['strategies'] ) ) {
			$settings['strategies'] = array( 'mobile' );
		}
		return $settings;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function value( $key ) {
		$settings = self::get();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Sanitise raw form input into a settings array.
	 *
	 * Returns the clean settings plus a list of rejected URLs so the screen can explain them.
	 *
	 * @param array<string,mixed> $input Raw (already unslashed) input.
	 * @return array{settings: array<string,mixed>, rejected: array<int,string>}
	 */
	public static function sanitize( array $input ) {
		$current = self::get();
		$clean   = array();

		$clean['enabled'] = ! empty( $input['enabled'] );

		$raw_urls      = isset( $input['urls'] ) ? (string) $input['urls'] : '';
		$parsed        = Urls::parse_list( $raw_urls, Urls::allowed_hosts(), self::MAX_URLS );
		$rejected      = $parsed['rejected'];
		$clean['urls'] = empty( $parsed['urls'] ) ? array( home_url( '/' ) ) : $parsed['urls'];

		$strategies = array();
		if ( isset( $input['strategies'] ) && is_array( $input['strategies'] ) ) {
			foreach ( $input['strategies'] as $strategy ) {
				$strategy = sanitize_key( (string) $strategy );
				if ( in_array( $strategy, array( 'mobile', 'desktop' ), true ) ) {
					$strategies[] = $strategy;
				}
			}
		}
		$clean['strategies'] = empty( $strategies ) ? array( 'mobile' ) : array_values( array_unique( $strategies ) );

		$frequency          = isset( $input['frequency'] ) ? sanitize_key( (string) $input['frequency'] ) : 'weekly';
		$clean['frequency'] = in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'weekly';

		$clean['alerts_enabled'] = ! empty( $input['alerts_enabled'] );

		$email                = isset( $input['alert_email'] ) ? sanitize_email( (string) $input['alert_email'] ) : '';
		$clean['alert_email'] = is_email( $email ) ? $email : (string) $current['alert_email'];

		$clean['alert_drop']     = self::clamp_int( isset( $input['alert_drop'] ) ? $input['alert_drop'] : 10, 1, 100, 10 );
		$clean['alert_below']    = self::clamp_int( isset( $input['alert_below'] ) ? $input['alert_below'] : 50, 0, 100, 50 );
		$clean['retention_days'] = self::clamp_int( isset( $input['retention_days'] ) ? $input['retention_days'] : 365, 30, 1095, 365 );

		return array(
			'settings' => $clean,
			'rejected' => $rejected,
		);
	}

	/**
	 * Save sanitised settings.
	 *
	 * @param array<string,mixed> $settings Clean settings.
	 * @return void
	 */
	public static function save( array $settings ) {
		update_option( self::OPTION, $settings, false );
	}

	/**
	 * Google API key: constant wins, then the stored option.
	 *
	 * @return string
	 */
	public static function api_key() {
		if ( defined( 'IWIESM_PSI_API_KEY' ) && is_string( constant( 'IWIESM_PSI_API_KEY' ) ) ) {
			return trim( (string) constant( 'IWIESM_PSI_API_KEY' ) );
		}
		$key = get_option( self::KEY_OPTION, '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Whether the key comes from wp-config.php.
	 *
	 * @return bool
	 */
	public static function api_key_from_constant() {
		return defined( 'IWIESM_PSI_API_KEY' ) && '' !== trim( (string) constant( 'IWIESM_PSI_API_KEY' ) );
	}

	/**
	 * Sanitise an API key (Google keys are URL-safe: letters, digits, dash, underscore).
	 *
	 * @param string $key Raw key.
	 * @return string Empty when invalid.
	 */
	public static function sanitize_api_key( $key ) {
		$key = trim( (string) $key );
		if ( '' === $key || ! preg_match( '/^[A-Za-z0-9_\-]{20,100}$/', $key ) ) {
			return '';
		}
		return $key;
	}

	/**
	 * Store or remove the API key.
	 *
	 * @param string $key Clean key, or empty to delete.
	 * @return void
	 */
	public static function save_api_key( $key ) {
		if ( '' === $key ) {
			delete_option( self::KEY_OPTION );
			return;
		}
		update_option( self::KEY_OPTION, $key, false );
	}

	/**
	 * Masked key for display, e.g. "••••••••3xQz".
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function mask_key( $key ) {
		$key = (string) $key;
		if ( '' === $key ) {
			return '';
		}
		return str_repeat( '•', 8 ) . substr( $key, -4 );
	}

	/**
	 * Clamp a value to an integer range.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $min      Min.
	 * @param int   $max      Max.
	 * @param int   $fallback Fallback when not numeric.
	 * @return int
	 */
	public static function clamp_int( $value, $min, $max, $fallback ) {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}
		return max( $min, min( $max, (int) $value ) );
	}
}
