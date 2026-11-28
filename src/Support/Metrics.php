<?php
/**
 * Metric thresholds, ratings and formatting.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Support;

/**
 * Google's published thresholds for Core Web Vitals and Lighthouse metrics.
 */
final class Metrics {

	/**
	 * Metric definitions: [good upper bound, poor lower bound, unit].
	 *
	 * @return array<string,array{0: float, 1: float, 2: string}>
	 */
	public static function thresholds() {
		return array(
			'lcp'  => array( 2500.0, 4000.0, 'ms' ),
			'inp'  => array( 200.0, 500.0, 'ms' ),
			'cls'  => array( 0.1, 0.25, 'score' ),
			'fcp'  => array( 1800.0, 3000.0, 'ms' ),
			'ttfb' => array( 800.0, 1800.0, 'ms' ),
			'tbt'  => array( 200.0, 600.0, 'ms' ),
			'si'   => array( 3400.0, 5800.0, 'ms' ),
		);
	}

	/**
	 * Human metric names.
	 *
	 * @return array<string,string>
	 */
	public static function names() {
		return array(
			'lcp'  => __( 'Largest Contentful Paint', 'iworksie-speed-monitor' ),
			'inp'  => __( 'Interaction to Next Paint', 'iworksie-speed-monitor' ),
			'cls'  => __( 'Cumulative Layout Shift', 'iworksie-speed-monitor' ),
			'fcp'  => __( 'First Contentful Paint', 'iworksie-speed-monitor' ),
			'ttfb' => __( 'Time to First Byte', 'iworksie-speed-monitor' ),
			'tbt'  => __( 'Total Blocking Time', 'iworksie-speed-monitor' ),
			'si'   => __( 'Speed Index', 'iworksie-speed-monitor' ),
		);
	}

	/**
	 * Plain-English one-liners for each metric.
	 *
	 * @return array<string,string>
	 */
	public static function explanations() {
		return array(
			'lcp'  => __( 'How long until the main content (usually the biggest image or heading) appears.', 'iworksie-speed-monitor' ),
			'inp'  => __( 'How quickly the page reacts when someone taps or clicks.', 'iworksie-speed-monitor' ),
			'cls'  => __( 'How much the page jumps around while loading. Lower is better.', 'iworksie-speed-monitor' ),
			'fcp'  => __( 'How long until anything at all appears on screen.', 'iworksie-speed-monitor' ),
			'ttfb' => __( 'How long your server takes to start sending the page.', 'iworksie-speed-monitor' ),
			'tbt'  => __( 'How long scripts keep the page too busy to respond while it loads.', 'iworksie-speed-monitor' ),
			'si'   => __( 'How quickly the visible part of the page fills in.', 'iworksie-speed-monitor' ),
		);
	}

	/**
	 * Rate a metric value: good | recommended | critical (core badge statuses).
	 *
	 * @param string     $metric Metric key.
	 * @param float|null $value  Value (ms or unitless).
	 * @return string Empty when there is no value or the metric is unknown.
	 */
	public static function rate( $metric, $value ) {
		$all = self::thresholds();
		if ( null === $value || ! isset( $all[ $metric ] ) ) {
			return '';
		}
		$value = (float) $value;
		if ( $value <= $all[ $metric ][0] ) {
			return 'good';
		}
		if ( $value <= $all[ $metric ][1] ) {
			return 'recommended';
		}
		return 'critical';
	}

	/**
	 * Google's wording for a rating.
	 *
	 * @param string $rating good|recommended|critical.
	 * @return string
	 */
	public static function rating_label( $rating ) {
		switch ( $rating ) {
			case 'good':
				return __( 'Good', 'iworksie-speed-monitor' );
			case 'recommended':
				return __( 'Needs improvement', 'iworksie-speed-monitor' );
			case 'critical':
				return __( 'Poor', 'iworksie-speed-monitor' );
		}
		return '';
	}

	/**
	 * Band for a 0–100 performance score (Lighthouse bands).
	 *
	 * @param int|null $score Score.
	 * @return string good|recommended|critical, or empty.
	 */
	public static function score_band( $score ) {
		if ( null === $score ) {
			return '';
		}
		$score = (int) $score;
		if ( $score >= 90 ) {
			return 'good';
		}
		if ( $score >= 50 ) {
			return 'recommended';
		}
		return 'critical';
	}

	/**
	 * Format a metric value for display.
	 *
	 * @param string     $metric Metric key.
	 * @param float|null $value  Value.
	 * @return string
	 */
	public static function format( $metric, $value ) {
		if ( null === $value ) {
			return '—';
		}
		$all  = self::thresholds();
		$unit = isset( $all[ $metric ] ) ? $all[ $metric ][2] : 'ms';
		if ( 'score' === $unit ) {
			return number_format_i18n( (float) $value, 2 );
		}
		$value = (float) $value;
		if ( $value >= 1000 ) {
			/* translators: %s: number of seconds, e.g. 2.4 */
			return sprintf( __( '%s s', 'iworksie-speed-monitor' ), number_format_i18n( $value / 1000, 1 ) );
		}
		/* translators: %s: number of milliseconds */
		return sprintf( __( '%s ms', 'iworksie-speed-monitor' ), number_format_i18n( round( $value ) ) );
	}
}
