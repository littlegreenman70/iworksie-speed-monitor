<?php
/**
 * Speed drop alerts.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor;

use IWorksIE\SpeedMonitor\Support\Urls;

/**
 * Decides when a scheduled result deserves an email, and sends one digest per run.
 */
final class Alerts {

	/**
	 * Compare a new result with the previous one.
	 *
	 * Alerts once when the score crosses below the floor (not on every run while it stays low),
	 * or when it drops by at least the configured number of points since the previous test.
	 *
	 * @param array<string,mixed>      $current  New successful row.
	 * @param array<string,mixed>|null $previous Previous successful row for the same URL and device.
	 * @param int                      $drop     Points drop that triggers an alert.
	 * @param int                      $below    Score floor.
	 * @return array<string,mixed>|null Alert data, or null.
	 */
	public static function evaluate( array $current, $previous, $drop, $below ) {
		if ( ! isset( $current['score'] ) ) {
			return null;
		}
		$score      = (int) $current['score'];
		$prev_score = ( is_array( $previous ) && isset( $previous['score'] ) && null !== $previous['score'] ) ? (int) $previous['score'] : null;

		$type = '';
		if ( $score < $below && ( null === $prev_score || $prev_score >= $below ) ) {
			$type = 'below';
		} elseif ( null !== $prev_score && ( $prev_score - $score ) >= $drop ) {
			$type = 'drop';
		}
		if ( '' === $type ) {
			return null;
		}
		return array(
			'type'     => $type,
			'url'      => (string) $current['url'],
			'strategy' => (string) $current['strategy'],
			'score'    => $score,
			'previous' => $prev_score,
			'below'    => (int) $below,
		);
	}

	/**
	 * Plain-text email body.
	 *
	 * @param array<int,array<string,mixed>> $alerts Alerts.
	 * @return string
	 */
	public static function body( array $alerts ) {
		$lines   = array();
		$lines[] = sprintf(
			/* translators: %s: site name */
			__( 'Speed Monitor found a speed drop on %s:', 'iworksie-speed-monitor' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$lines[] = '';
		foreach ( $alerts as $alert ) {
			$device = 'desktop' === $alert['strategy'] ? __( 'desktop', 'iworksie-speed-monitor' ) : __( 'mobile', 'iworksie-speed-monitor' );
			if ( 'drop' === $alert['type'] ) {
				$lines[] = sprintf(
					/* translators: 1: page URL, 2: device, 3: old score, 4: new score */
					__( '- %1$s (%2$s): score dropped from %3$d to %4$d', 'iworksie-speed-monitor' ),
					$alert['url'],
					$device,
					(int) $alert['previous'],
					(int) $alert['score']
				);
			} elseif ( null !== $alert['previous'] ) {
				$lines[] = sprintf(
					/* translators: 1: page URL, 2: device, 3: old score, 4: new score, 5: threshold */
					__( '- %1$s (%2$s): score dropped from %3$d to %4$d, below your alert level of %5$d', 'iworksie-speed-monitor' ),
					$alert['url'],
					$device,
					(int) $alert['previous'],
					(int) $alert['score'],
					(int) $alert['below']
				);
			} else {
				$lines[] = sprintf(
					/* translators: 1: page URL, 2: device, 3: score, 4: threshold */
					__( '- %1$s (%2$s): score is %3$d, below your alert level of %4$d', 'iworksie-speed-monitor' ),
					$alert['url'],
					$device,
					(int) $alert['score'],
					(int) $alert['below']
				);
			}
		}
		$lines[] = '';
		$lines[] = __( 'Lab scores can move a few points between runs. A drop that shows up on the next test too is worth a look, especially after installing or updating a plugin or theme.', 'iworksie-speed-monitor' );
		$lines[] = '';
		$lines[] = __( 'See the details and suggested fixes:', 'iworksie-speed-monitor' );
		$lines[] = admin_url( 'admin.php?page=iworksie-speed-monitor' );
		$lines[] = '';
		$lines[] = __( 'Need help fixing it? iWorks builds and speeds up WordPress sites: https://www.iworks.ie', 'iworksie-speed-monitor' );
		return implode( "\n", $lines );
	}

	/**
	 * Send the digest.
	 *
	 * @param array<int,array<string,mixed>> $alerts Alerts.
	 * @param string                         $to     Recipient.
	 * @return bool
	 */
	public static function send( array $alerts, $to ) {
		if ( empty( $alerts ) || ! is_email( $to ) ) {
			return false;
		}
		$pages = array();
		foreach ( $alerts as $alert ) {
			$pages[ Urls::label( (string) $alert['url'] ) ] = true;
		}
		$subject = sprintf(
			/* translators: 1: site name, 2: number of pages */
			_n( '[%1$s] Speed drop on %2$d page', '[%1$s] Speed drop on %2$d pages', count( $pages ), 'iworksie-speed-monitor' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			count( $pages )
		);
		return (bool) wp_mail( $to, $subject, self::body( $alerts ) );
	}
}
