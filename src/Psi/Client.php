<?php
/**
 * Google PageSpeed Insights API client.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Psi;

/**
 * One request = one URL + one device. Calls Google directly; no third-party relay.
 */
final class Client {

	const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
	const TIMEOUT  = 55;

	/**
	 * Build the request URL.
	 *
	 * @param string $url      Page URL.
	 * @param string $strategy mobile|desktop.
	 * @param string $api_key  Optional key.
	 * @param string $locale   Optional locale for Lighthouse text, e.g. en-GB.
	 * @return string
	 */
	public static function request_url( $url, $strategy, $api_key = '', $locale = '' ) {
		$args = array(
			'url'      => $url,
			'strategy' => 'desktop' === $strategy ? 'desktop' : 'mobile',
			'category' => 'performance',
		);
		if ( '' !== $locale ) {
			$args['locale'] = $locale;
		}
		if ( '' !== $api_key ) {
			$args['key'] = $api_key;
		}
		return self::ENDPOINT . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Run a test.
	 *
	 * @param string $url      Page URL.
	 * @param string $strategy mobile|desktop.
	 * @param string $api_key  Optional API key.
	 * @return array{ok: bool, data: array<string,mixed>|null, error: string, code: int, retry: bool}
	 */
	public function run( $url, $strategy, $api_key = '' ) {
		$locale   = str_replace( '_', '-', (string) determine_locale() );
		$response = wp_remote_get(
			self::request_url( $url, $strategy, $api_key, $locale ),
			array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => 'iWorksIE-Speed-Monitor/' . ( defined( 'IWIESM_VERSION' ) ? IWIESM_VERSION : '1' ) . '; WordPress',
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::failure(
				/* translators: %s: error message from the HTTP library */
				sprintf( __( 'Could not reach Google PageSpeed Insights: %s', 'iworksie-speed-monitor' ), self::redact( $response->get_error_message(), $api_key ) ),
				0,
				true
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( 200 === $code && is_array( $json ) && ! empty( $json['lighthouseResult'] ) ) {
			return array(
				'ok'    => true,
				'data'  => $json,
				'error' => '',
				'code'  => 200,
				'retry' => false,
			);
		}

		$google = '';
		if ( is_array( $json ) && isset( $json['error']['message'] ) ) {
			$google = self::redact( sanitize_text_field( (string) $json['error']['message'] ), $api_key );
		}
		return self::failure( self::explain( $code, $google, '' !== $api_key ), $code, 429 === $code || $code >= 500 );
	}

	/**
	 * Plain-English explanation of an API failure.
	 *
	 * @param int    $code    HTTP status.
	 * @param string $google  Google's message (already redacted).
	 * @param bool   $has_key Whether a key was used.
	 * @return string
	 */
	public static function explain( $code, $google, $has_key ) {
		if ( 429 === $code ) {
			return $has_key
				? __( 'Google\'s daily limit for your API key was reached. Tests will continue tomorrow.', 'iworksie-speed-monitor' )
				: __( 'Google is limiting tests without an API key. Add your own free API key in Settings for reliable testing.', 'iworksie-speed-monitor' );
		}
		if ( 400 === $code && false !== stripos( $google, 'API key' ) ) {
			return __( 'Google rejected the API key. Check it in Settings and make sure the PageSpeed Insights API is enabled for it.', 'iworksie-speed-monitor' );
		}
		if ( 403 === $code ) {
			return __( 'Google refused the request. Make sure the PageSpeed Insights API is enabled for your API key and the key is not restricted to other sites or IP addresses.', 'iworksie-speed-monitor' );
		}
		if ( 400 === $code || 500 === $code ) {
			if ( false !== stripos( $google, 'FAILED_DOCUMENT_REQUEST' ) || false !== stripos( $google, 'ERRORED_DOCUMENT_REQUEST' ) || false !== stripos( $google, 'DNS' ) ) {
				return __( 'Google could not load the page. It must be publicly reachable on the internet (local, staging and password-protected sites cannot be tested).', 'iworksie-speed-monitor' );
			}
		}
		if ( '' !== $google ) {
			/* translators: 1: HTTP status code, 2: Google's error message */
			return sprintf( __( 'Google PageSpeed Insights returned an error (%1$d): %2$s', 'iworksie-speed-monitor' ), $code, $google );
		}
		/* translators: %d: HTTP status code */
		return sprintf( __( 'Google PageSpeed Insights returned an unexpected response (HTTP %d). Try again later.', 'iworksie-speed-monitor' ), $code );
	}

	/**
	 * Remove the API key from any text before it is stored or shown.
	 *
	 * @param string $text    Text.
	 * @param string $api_key Key.
	 * @return string
	 */
	public static function redact( $text, $api_key ) {
		$text = (string) $text;
		if ( '' !== $api_key ) {
			$text = str_replace( $api_key, '[key]', $text );
		}
		return (string) preg_replace( '/([?&]key=)[^&\s]+/', '$1[key]', $text );
	}

	/**
	 * Failure shape.
	 *
	 * @param string $message Message.
	 * @param int    $code    HTTP code.
	 * @param bool   $retry   Whether it is worth retrying later.
	 * @return array{ok: bool, data: null, error: string, code: int, retry: bool}
	 */
	private static function failure( $message, $code, $retry ) {
		return array(
			'ok'    => false,
			'data'  => null,
			'error' => substr( (string) $message, 0, 250 ),
			'code'  => (int) $code,
			'retry' => (bool) $retry,
		);
	}
}
