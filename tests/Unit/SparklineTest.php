<?php
/**
 * Sparkline geometry.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Unit;

use IWorksIE\SpeedMonitor\Support\Sparkline;

/**
 * @covers \IWorksIE\SpeedMonitor\Support\Sparkline
 */
final class SparklineTest extends UnitTestCase {

	public function test_points_span_width_and_fixed_scale() {
		$points = Sparkline::points( array( 100, 0 ), 106, 106, 3 );
		$this->assertSame( array( array( 3.0, 3.0 ), array( 103.0, 103.0 ) ), $points );
	}

	public function test_needs_two_points_for_svg() {
		$this->assertSame( '', Sparkline::svg( array( 50 ), 'x' ) );
		$svg = Sparkline::svg( array( 40, 95 ), 'Trend' );
		$this->assertStringContainsString( 'role="img"', $svg );
		$this->assertStringContainsString( 'aria-label="Trend"', $svg );
		$this->assertStringContainsString( 'iwiesm-spark--good', $svg );
	}
}
