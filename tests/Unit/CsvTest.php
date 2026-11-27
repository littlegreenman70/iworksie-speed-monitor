<?php
/**
 * CSV export escaping.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Unit;

use IWorksIE\SpeedMonitor\Support\Csv;

/**
 * @covers \IWorksIE\SpeedMonitor\Support\Csv
 */
final class CsvTest extends UnitTestCase {

	public function test_quotes_and_neutralises_formulas() {
		$line = Csv::line( array( '=HYPERLINK("x")', 'a,b', 'plain', '-5', '@sum', '' ) );
		$this->assertSame( "\"'=HYPERLINK(\"\"x\"\")\",\"a,b\",plain,-5,'@sum,\r\n", $line );
	}

	public function test_row_formats_numbers() {
		$row = Csv::row(
			array(
				'tested_at' => '2026-11-26 03:00:00',
				'url'       => 'https://example.org/',
				'strategy'  => 'mobile',
				'source'    => 'scheduled',
				'status'    => 'ok',
				'score'     => 62,
				'lab_lcp'   => 5480.2,
				'lab_cls'   => 0.0812,
				'field_inp' => null,
			)
		);
		$this->assertCount( count( Csv::headers() ), $row );
		$this->assertSame( '62', $row[5] );
		$this->assertSame( '5480', $row[6] );
		$this->assertSame( '0.081', $row[7] );
		$this->assertSame( '', $row[12] );
	}
}
