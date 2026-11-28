<?php
/**
 * Alert decisions.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Unit;

use IWorksIE\SpeedMonitor\Alerts;

/**
 * @covers \IWorksIE\SpeedMonitor\Alerts
 */
final class AlertsTest extends UnitTestCase {

	/**
	 * @param int|null $score Score.
	 * @return array<string,mixed>
	 */
	private function row( $score ) {
		return array(
			'url'      => 'https://example.org/',
			'strategy' => 'mobile',
			'score'    => $score,
		);
	}

	public function test_drop_of_threshold_alerts() {
		$alert = Alerts::evaluate( $this->row( 70 ), $this->row( 80 ), 10, 50 );
		$this->assertSame( 'drop', $alert['type'] );
		$this->assertSame( 80, $alert['previous'] );
	}

	public function test_small_drop_is_ignored() {
		$this->assertNull( Alerts::evaluate( $this->row( 71 ), $this->row( 80 ), 10, 50 ) );
	}

	public function test_crossing_below_floor_alerts_once() {
		$this->assertSame( 'below', Alerts::evaluate( $this->row( 48 ), $this->row( 52 ), 10, 50 )['type'] );
		// Already below last time and no big drop: no repeat alert.
		$this->assertNull( Alerts::evaluate( $this->row( 45 ), $this->row( 48 ), 10, 50 ) );
	}

	public function test_still_below_but_big_drop_alerts_as_drop() {
		$this->assertSame( 'drop', Alerts::evaluate( $this->row( 20 ), $this->row( 40 ), 10, 50 )['type'] );
	}

	public function test_first_result_below_floor_alerts() {
		$this->assertSame( 'below', Alerts::evaluate( $this->row( 30 ), null, 10, 50 )['type'] );
	}

	public function test_first_result_above_floor_is_quiet() {
		$this->assertNull( Alerts::evaluate( $this->row( 80 ), null, 10, 50 ) );
	}

	public function test_floor_zero_disables_floor_alerts() {
		$this->assertNull( Alerts::evaluate( $this->row( 5 ), null, 10, 0 ) );
	}

	public function test_missing_score_never_alerts() {
		$this->assertNull( Alerts::evaluate( $this->row( null ), $this->row( 90 ), 10, 50 ) );
	}
}
