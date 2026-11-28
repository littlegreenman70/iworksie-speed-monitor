<?php
/**
 * Metric ratings and formatting.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Unit;

use IWorksIE\SpeedMonitor\Support\Metrics;

/**
 * @covers \IWorksIE\SpeedMonitor\Support\Metrics
 */
final class MetricsTest extends UnitTestCase {

	public function test_core_web_vitals_boundaries() {
		$this->assertSame( 'good', Metrics::rate( 'lcp', 2500 ) );
		$this->assertSame( 'recommended', Metrics::rate( 'lcp', 2501 ) );
		$this->assertSame( 'recommended', Metrics::rate( 'lcp', 4000 ) );
		$this->assertSame( 'critical', Metrics::rate( 'lcp', 4001 ) );
		$this->assertSame( 'good', Metrics::rate( 'inp', 200 ) );
		$this->assertSame( 'critical', Metrics::rate( 'inp', 501 ) );
		$this->assertSame( 'good', Metrics::rate( 'cls', 0.1 ) );
		$this->assertSame( 'recommended', Metrics::rate( 'cls', 0.11 ) );
		$this->assertSame( 'critical', Metrics::rate( 'cls', 0.26 ) );
		$this->assertSame( '', Metrics::rate( 'lcp', null ) );
		$this->assertSame( '', Metrics::rate( 'nope', 1 ) );
	}

	public function test_score_bands() {
		$this->assertSame( 'good', Metrics::score_band( 90 ) );
		$this->assertSame( 'recommended', Metrics::score_band( 89 ) );
		$this->assertSame( 'recommended', Metrics::score_band( 50 ) );
		$this->assertSame( 'critical', Metrics::score_band( 49 ) );
		$this->assertSame( '', Metrics::score_band( null ) );
	}

	public function test_format() {
		$this->assertSame( '2.4 s', Metrics::format( 'lcp', 2449.0 ) );
		$this->assertSame( '180 ms', Metrics::format( 'inp', 180.4 ) );
		$this->assertSame( '0.05', Metrics::format( 'cls', 0.05 ) );
		$this->assertSame( '—', Metrics::format( 'lcp', null ) );
	}
}
