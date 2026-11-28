<?php
/**
 * Turns a PageSpeed Insights v5 response into the small row we store.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Psi;

/**
 * Pure parser (no WordPress calls apart from sanitising), easy to unit test.
 */
final class Parser {

	const MAX_OPPORTUNITIES = 3;

	/**
	 * Parse a decoded PSI response.
	 *
	 * @param array<string,mixed> $json Decoded JSON.
	 * @return array<string,mixed>|null Null when the response has no Lighthouse result.
	 */
	public static function parse( array $json ) {
		if ( empty( $json['lighthouseResult'] ) || ! is_array( $json['lighthouseResult'] ) ) {
			return null;
		}
		$lh     = $json['lighthouseResult'];
		$audits = isset( $lh['audits'] ) && is_array( $lh['audits'] ) ? $lh['audits'] : array();

		$score = null;
		if ( isset( $lh['categories']['performance']['score'] ) && is_numeric( $lh['categories']['performance']['score'] ) ) {
			$score = (int) round( (float) $lh['categories']['performance']['score'] * 100 );
			$score = max( 0, min( 100, $score ) );
		}

		$row = array(
			'score'              => $score,
			'lab_lcp'            => self::audit_number( $audits, 'largest-contentful-paint' ),
			'lab_cls'            => self::audit_number( $audits, 'cumulative-layout-shift' ),
			'lab_tbt'            => self::audit_number( $audits, 'total-blocking-time' ),
			'lab_fcp'            => self::audit_number( $audits, 'first-contentful-paint' ),
			'lab_si'             => self::audit_number( $audits, 'speed-index' ),
			'lighthouse_version' => isset( $lh['lighthouseVersion'] ) ? substr( sanitize_text_field( (string) $lh['lighthouseVersion'] ), 0, 20 ) : '',
			'runtime_error'      => '',
			'opportunities'      => self::opportunities( $lh, $audits ),
		);

		if ( ! empty( $lh['runtimeError']['code'] ) && 'NO_ERROR' !== $lh['runtimeError']['code'] ) {
			$row['runtime_error'] = substr( sanitize_text_field( (string) ( isset( $lh['runtimeError']['message'] ) ? $lh['runtimeError']['message'] : $lh['runtimeError']['code'] ) ), 0, 250 );
		}

		return array_merge( $row, self::field_data( $json ) );
	}

	/**
	 * Real-visitor (CrUX) data. Prefers this URL's data, falls back to whole-site (origin) data.
	 *
	 * @param array<string,mixed> $json Decoded JSON.
	 * @return array<string,mixed>
	 */
	public static function field_data( array $json ) {
		$empty = array(
			'field_lcp'      => null,
			'field_inp'      => null,
			'field_cls'      => null,
			'field_fcp'      => null,
			'field_ttfb'     => null,
			'field_category' => '',
			'field_scope'    => '',
		);

		$scope = '';
		$data  = null;
		if ( ! empty( $json['loadingExperience']['metrics'] ) && is_array( $json['loadingExperience']['metrics'] ) ) {
			$data  = $json['loadingExperience'];
			$scope = ! empty( $data['origin_fallback'] ) ? 'origin' : 'url';
		} elseif ( ! empty( $json['originLoadingExperience']['metrics'] ) && is_array( $json['originLoadingExperience']['metrics'] ) ) {
			$data  = $json['originLoadingExperience'];
			$scope = 'origin';
		}
		if ( null === $data ) {
			return $empty;
		}
		$metrics = $data['metrics'];

		$cls = self::percentile( $metrics, 'CUMULATIVE_LAYOUT_SHIFT_SCORE' );
		// CrUX reports CLS multiplied by 100 (e.g. 5 means 0.05).
		if ( null !== $cls ) {
			$cls = $cls / 100;
		}
		$ttfb = self::percentile( $metrics, 'EXPERIMENTAL_TIME_TO_FIRST_BYTE' );
		if ( null === $ttfb ) {
			$ttfb = self::percentile( $metrics, 'TIME_TO_FIRST_BYTE' );
		}

		$category = isset( $data['overall_category'] ) ? strtoupper( sanitize_key( (string) $data['overall_category'] ) ) : '';
		if ( ! in_array( $category, array( 'FAST', 'AVERAGE', 'SLOW' ), true ) ) {
			$category = '';
		}

		return array(
			'field_lcp'      => self::percentile( $metrics, 'LARGEST_CONTENTFUL_PAINT_MS' ),
			'field_inp'      => self::percentile( $metrics, 'INTERACTION_TO_NEXT_PAINT' ),
			'field_cls'      => $cls,
			'field_fcp'      => self::percentile( $metrics, 'FIRST_CONTENTFUL_PAINT_MS' ),
			'field_ttfb'     => $ttfb,
			'field_category' => $category,
			'field_scope'    => $scope,
		);
	}

	/**
	 * Top fixes: failing performance audits ranked by estimated savings, one per topic.
	 *
	 * @param array<string,mixed> $lh     Lighthouse result.
	 * @param array<string,mixed> $audits Audits map.
	 * @return array<int,array<string,mixed>>
	 */
	public static function opportunities( array $lh, array $audits ) {
		$refs = isset( $lh['categories']['performance']['auditRefs'] ) && is_array( $lh['categories']['performance']['auditRefs'] )
			? $lh['categories']['performance']['auditRefs']
			: array();

		$candidates = array();
		foreach ( $refs as $ref ) {
			if ( ! is_array( $ref ) || empty( $ref['id'] ) ) {
				continue;
			}
			$group = isset( $ref['group'] ) ? (string) $ref['group'] : '';
			// Metrics are reported separately; hidden audits are duplicates kept for compatibility.
			if ( in_array( $group, array( 'metrics', 'hidden' ), true ) ) {
				continue;
			}
			if ( isset( $ref['weight'] ) && (float) $ref['weight'] > 0 ) {
				continue;
			}
			$id = (string) $ref['id'];
			if ( empty( $audits[ $id ] ) || ! is_array( $audits[ $id ] ) ) {
				continue;
			}
			$audit = $audits[ $id ];
			$mode  = isset( $audit['scoreDisplayMode'] ) ? (string) $audit['scoreDisplayMode'] : '';
			if ( in_array( $mode, array( 'notApplicable', 'manual', 'error', 'informative' ), true ) ) {
				continue;
			}
			if ( ! isset( $audit['score'] ) || ! is_numeric( $audit['score'] ) || (float) $audit['score'] >= 0.9 ) {
				continue;
			}

			$savings_ms    = self::savings_ms( $audit );
			$savings_bytes = isset( $audit['details']['overallSavingsBytes'] ) && is_numeric( $audit['details']['overallSavingsBytes'] )
				? (int) $audit['details']['overallSavingsBytes']
				: 0;

			$candidates[] = array(
				'id'            => sanitize_key( $id ),
				'topic'         => Advice::topic_for( sanitize_key( $id ) ),
				'title'         => substr( sanitize_text_field( isset( $audit['title'] ) ? (string) $audit['title'] : $id ), 0, 200 ),
				'description'   => substr( sanitize_text_field( isset( $audit['description'] ) ? (string) $audit['description'] : '' ), 0, 500 ),
				'display'       => substr( sanitize_text_field( isset( $audit['displayValue'] ) ? (string) $audit['displayValue'] : '' ), 0, 100 ),
				'savings_ms'    => $savings_ms,
				'savings_bytes' => max( 0, $savings_bytes ),
				'score'         => (float) $audit['score'],
			);
		}

		usort(
			$candidates,
			static function ( $a, $b ) {
				if ( $a['savings_ms'] !== $b['savings_ms'] ) {
					return $b['savings_ms'] <=> $a['savings_ms'];
				}
				if ( $a['savings_bytes'] !== $b['savings_bytes'] ) {
					return $b['savings_bytes'] <=> $a['savings_bytes'];
				}
				return $a['score'] <=> $b['score'];
			}
		);

		$picked = array();
		$topics = array();
		foreach ( $candidates as $candidate ) {
			if ( isset( $topics[ $candidate['topic'] ] ) ) {
				continue;
			}
			$topics[ $candidate['topic'] ] = true;
			unset( $candidate['score'] );
			$picked[] = $candidate;
			if ( count( $picked ) >= self::MAX_OPPORTUNITIES ) {
				break;
			}
		}
		return $picked;
	}

	/**
	 * Estimated time saved by fixing an audit.
	 *
	 * @param array<string,mixed> $audit Audit.
	 * @return int Milliseconds.
	 */
	private static function savings_ms( array $audit ) {
		if ( isset( $audit['details']['overallSavingsMs'] ) && is_numeric( $audit['details']['overallSavingsMs'] ) ) {
			return max( 0, (int) round( (float) $audit['details']['overallSavingsMs'] ) );
		}
		$total = 0.0;
		if ( isset( $audit['metricSavings'] ) && is_array( $audit['metricSavings'] ) ) {
			foreach ( array( 'LCP', 'FCP', 'TBT', 'INP' ) as $metric ) {
				if ( isset( $audit['metricSavings'][ $metric ] ) && is_numeric( $audit['metricSavings'][ $metric ] ) ) {
					$total += (float) $audit['metricSavings'][ $metric ];
				}
			}
		}
		return max( 0, (int) round( $total ) );
	}

	/**
	 * Numeric value of an audit.
	 *
	 * @param array<string,mixed> $audits Audits.
	 * @param string              $id     Audit ID.
	 * @return float|null
	 */
	private static function audit_number( array $audits, $id ) {
		if ( isset( $audits[ $id ]['numericValue'] ) && is_numeric( $audits[ $id ]['numericValue'] ) ) {
			return (float) $audits[ $id ]['numericValue'];
		}
		return null;
	}

	/**
	 * 75th percentile from a CrUX metric.
	 *
	 * @param array<string,mixed> $metrics Metrics.
	 * @param string              $key     Metric key.
	 * @return float|null
	 */
	private static function percentile( array $metrics, $key ) {
		if ( isset( $metrics[ $key ]['percentile'] ) && is_numeric( $metrics[ $key ]['percentile'] ) ) {
			return (float) $metrics[ $key ]['percentile'];
		}
		return null;
	}
}
