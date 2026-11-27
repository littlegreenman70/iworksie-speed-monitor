<?php
/**
 * URL validation for monitored pages.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Support;

/**
 * Only this site's own pages can be monitored.
 */
final class Urls {

	/**
	 * Hosts this site answers on (home and site URL).
	 *
	 * @return array<int,string>
	 */
	public static function allowed_hosts() {
		$hosts = array();
		foreach ( array( home_url( '/' ), site_url( '/' ) ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$hosts[] = strtolower( $host );
			}
		}
		return array_values( array_unique( $hosts ) );
	}

	/**
	 * Normalise one URL. Relative paths ("/contact/") resolve against the home URL.
	 *
	 * @param string            $raw   Raw input.
	 * @param array<int,string> $hosts Allowed hosts.
	 * @return string Clean URL, or empty string when not allowed.
	 */
	public static function normalize( $raw, array $hosts ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( 0 === strpos( $raw, '/' ) && 0 !== strpos( $raw, '//' ) ) {
			$raw = untrailingslashit( home_url() ) . $raw;
		}
		$parts = wp_parse_url( $raw );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		$host = strtolower( (string) $parts['host'] );
		if ( ! in_array( $host, $hosts, true ) ) {
			return '';
		}
		$url = $scheme . '://' . $host;
		if ( isset( $parts['port'] ) ) {
			$url .= ':' . (int) $parts['port'];
		}
		$url .= isset( $parts['path'] ) && '' !== $parts['path'] ? $parts['path'] : '/';
		if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
			$url .= '?' . $parts['query'];
		}
		return esc_url_raw( $url, array( 'http', 'https' ) );
	}

	/**
	 * Parse a newline-separated list.
	 *
	 * @param string            $raw   Textarea input.
	 * @param array<int,string> $hosts Allowed hosts.
	 * @param int               $max   Max URLs kept.
	 * @return array{urls: array<int,string>, rejected: array<int,string>}
	 */
	public static function parse_list( $raw, array $hosts, $max ) {
		$urls     = array();
		$rejected = array();
		$lines    = preg_split( '/[\r\n]+/', (string) $raw );
		if ( ! is_array( $lines ) ) {
			$lines = array();
		}
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$url = self::normalize( $line, $hosts );
			if ( '' === $url ) {
				$rejected[] = sanitize_text_field( $line );
				continue;
			}
			if ( in_array( $url, $urls, true ) ) {
				continue;
			}
			if ( count( $urls ) >= $max ) {
				$rejected[] = $url;
				continue;
			}
			$urls[] = $url;
		}
		return array(
			'urls'     => $urls,
			'rejected' => $rejected,
		);
	}

	/**
	 * Short label for a URL: path only when on this site ("/" for the home page).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function label( $url ) {
		$path  = wp_parse_url( (string) $url, PHP_URL_PATH );
		$query = wp_parse_url( (string) $url, PHP_URL_QUERY );
		$label = is_string( $path ) && '' !== $path ? $path : '/';
		if ( is_string( $query ) && '' !== $query ) {
			$label .= '?' . $query;
		}
		return $label;
	}
}
