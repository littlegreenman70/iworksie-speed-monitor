<?php
/**
 * Results table.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Integration;

use IWorksIE\SpeedMonitor\Storage\Results;

/**
 * Insert, read back, history, prune.
 */
final class StorageTest extends IntegrationTestCase {

	/**
	 * @param array<string,mixed> $extra Extra.
	 * @return int
	 */
	private function add( array $extra ) {
		return Results::insert(
			array_merge(
				array(
					'url'      => 'https://example.org/',
					'strategy' => 'mobile',
					'status'   => 'ok',
					'score'    => 80,
				),
				$extra
			)
		);
	}

	public function test_round_trip_types() {
		$id = $this->add(
			array(
				'lab_lcp'       => 2450.6,
				'lab_cls'       => 0.0812,
				'field_inp'     => null,
				'field_scope'   => 'url',
				'opportunities' => array( array( 'id' => 'x', 'topic' => 'images' ) ),
			)
		);
		$this->assertGreaterThan( 0, $id );
		$row = Results::latest( 'https://example.org/', 'mobile' );
		$this->assertSame( $id, $row['id'] );
		$this->assertSame( 80, $row['score'] );
		$this->assertSame( 2451.0, $row['lab_lcp'] );
		$this->assertEqualsWithDelta( 0.0812, $row['lab_cls'], 0.00001 );
		$this->assertNull( $row['field_inp'] );
		$this->assertSame( 'images', $row['opportunities'][0]['topic'] );
	}

	public function test_latest_ignores_errors_and_other_devices() {
		$ok = $this->add( array( 'score' => 70 ) );
		$this->add(
			array(
				'status'        => 'error',
				'score'         => null,
				'error_message' => 'nope',
			)
		);
		$this->add(
			array(
				'strategy' => 'desktop',
				'score'    => 99,
			)
		);
		$this->assertSame( $ok, Results::latest( 'https://example.org/', 'mobile' )['id'] );
		$this->assertSame( 'error', Results::last_attempt( 'https://example.org/', 'mobile' )['status'] );
		$this->assertNull( Results::latest( 'https://example.org/', 'mobile', $ok ) );
	}

	public function test_series_oldest_first_and_history_filters() {
		foreach ( array( 50, 60, 70 ) as $score ) {
			$this->add( array( 'score' => $score ) );
		}
		$this->add(
			array(
				'url'   => 'https://example.org/b/',
				'score' => 10,
			)
		);
		$this->assertSame( array( 50, 60, 70 ), array_column( Results::series( 'https://example.org/', 'mobile' ), 'score' ) );
		$page = Results::history( 'https://example.org/', 'mobile', 2, 1 );
		$this->assertSame( 3, $page['total'] );
		$this->assertSame( array( 70, 60 ), array_column( $page['rows'], 'score' ) );
		$this->assertSame( 4, Results::history( '', '', 10, 1 )['total'] );
	}

	public function test_prune_removes_old_rows() {
		$this->add( array( 'tested_at' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ) );
		$this->add( array() );
		$this->assertSame( 1, Results::prune( 30 ) );
		$this->assertSame( 1, Results::count() );
	}

	public function test_batch_pages_by_id() {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->add( array() );
		}
		$first = Results::batch( 0, 3 );
		$this->assertCount( 3, $first );
		$rest = Results::batch( end( $first )['id'], 3 );
		$this->assertCount( 2, $rest );
	}
}
