<?php
/**
 * CSV export helpers.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Support;

/**
 * Builds CSV lines without touching the filesystem.
 */
final class Csv {

	/**
	 * Header row.
	 *
	 * @return array<int,string>
	 */
	public static function headers() {
		return array(
			'tested_at_utc',
			'url',
			'device',
			'source',
			'status',
			'score',
			'lab_lcp_ms',
			'lab_cls',
			'lab_tbt_ms',
			'lab_fcp_ms',
			'lab_speed_index_ms',
			'field_lcp_ms',
			'field_inp_ms',
			'field_cls',
			'field_fcp_ms',
			'field_ttfb_ms',
			'field_category',
			'field_scope',
			'error',
		);
	}

	/**
	 * Values for one stored row.
	 *
	 * @param array<string,mixed> $row Hydrated row.
	 * @return array<int,string>
	 */
	public static function row( array $row ) {
		$keys = array( 'tested_at', 'url', 'strategy', 'source', 'status', 'score', 'lab_lcp', 'lab_cls', 'lab_tbt', 'lab_fcp', 'lab_si', 'field_lcp', 'field_inp', 'field_cls', 'field_fcp', 'field_ttfb', 'field_category', 'field_scope', 'error_message' );
		$out  = array();
		foreach ( $keys as $key ) {
			$value = isset( $row[ $key ] ) ? $row[ $key ] : '';
			if ( is_float( $value ) ) {
				$value = ( 'lab_cls' === $key || 'field_cls' === $key ) ? number_format( $value, 3, '.', '' ) : (string) (int) round( $value );
			}
			$out[] = (string) $value;
		}
		return $out;
	}

	/**
	 * One CSV line (RFC 4180), with spreadsheet formula injection neutralised.
	 *
	 * @param array<int,string> $cells Cells.
	 * @return string
	 */
	public static function line( array $cells ) {
		$escaped = array();
		foreach ( $cells as $cell ) {
			$cell = (string) $cell;
			if ( '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! is_numeric( $cell ) ) {
				$cell = "'" . $cell;
			}
			if ( preg_match( '/[",\r\n]/', $cell ) ) {
				$cell = '"' . str_replace( '"', '""', $cell ) . '"';
			}
			$escaped[] = $cell;
		}
		return implode( ',', $escaped ) . "\r\n";
	}
}
