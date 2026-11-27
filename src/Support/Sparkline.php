<?php
/**
 * Tiny inline SVG trend line (no chart library, no external requests).
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Support;

/**
 * Renders a 0–100 score series as an accessible SVG.
 */
final class Sparkline {

	/**
	 * Polyline points for scores on a fixed 0–100 scale.
	 *
	 * @param array<int,int> $scores Scores, oldest first.
	 * @param int            $width  Width.
	 * @param int            $height Height.
	 * @param int            $pad    Padding.
	 * @return array<int,array{0: float, 1: float}>
	 */
	public static function points( array $scores, $width, $height, $pad = 3 ) {
		$count = count( $scores );
		if ( 0 === $count ) {
			return array();
		}
		$inner_w = max( 1, $width - ( 2 * $pad ) );
		$inner_h = max( 1, $height - ( 2 * $pad ) );
		$step    = $count > 1 ? $inner_w / ( $count - 1 ) : 0;
		$points  = array();
		$i       = 0;
		foreach ( $scores as $score ) {
			$score    = max( 0, min( 100, (int) $score ) );
			$x        = $count > 1 ? $pad + ( $i * $step ) : $pad + ( $inner_w / 2 );
			$y        = $pad + ( ( 100 - $score ) / 100 ) * $inner_h;
			$points[] = array( round( $x, 1 ), round( $y, 1 ) );
			++$i;
		}
		return $points;
	}

	/**
	 * SVG markup.
	 *
	 * @param array<int,int> $scores Scores, oldest first.
	 * @param string         $label  Accessible description.
	 * @param int            $width  Width.
	 * @param int            $height Height.
	 * @return string
	 */
	public static function svg( array $scores, $label, $width = 160, $height = 40 ) {
		$points = self::points( $scores, $width, $height );
		if ( count( $points ) < 2 ) {
			return '';
		}
		$coords = array();
		foreach ( $points as $point ) {
			$coords[] = $point[0] . ',' . $point[1];
		}
		$last = end( $points );
		$band = Metrics::score_band( (int) end( $scores ) );

		// Dashed guide lines at 90 (good) and 50 (poor).
		$guides = '';
		foreach ( array( 90, 50 ) as $level ) {
			$guide   = self::points( array( $level, $level ), $width, $height );
			$guides .= '<line class="iwiesm-spark__guide" x1="0" x2="' . esc_attr( (string) $width ) . '" y1="' . esc_attr( (string) $guide[0][1] ) . '" y2="' . esc_attr( (string) $guide[0][1] ) . '" />';
		}

		return '<svg class="iwiesm-spark iwiesm-spark--' . esc_attr( $band ) . '" width="' . esc_attr( (string) $width ) . '" height="' . esc_attr( (string) $height ) . '" viewBox="0 0 ' . esc_attr( $width . ' ' . $height ) . '" role="img" aria-label="' . esc_attr( $label ) . '" focusable="false">'
			. $guides
			. '<polyline class="iwiesm-spark__line" fill="none" points="' . esc_attr( implode( ' ', $coords ) ) . '" />'
			. '<circle class="iwiesm-spark__dot" r="3" cx="' . esc_attr( (string) $last[0] ) . '" cy="' . esc_attr( (string) $last[1] ) . '" />'
			. '</svg>';
	}
}
