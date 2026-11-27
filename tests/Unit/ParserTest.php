<?php
/**
 * PSI response parsing.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Unit;

use IWorksIE\SpeedMonitor\Psi\Advice;
use IWorksIE\SpeedMonitor\Psi\Parser;

/**
 * @covers \IWorksIE\SpeedMonitor\Psi\Parser
 */
final class ParserTest extends UnitTestCase {

	public function test_parses_score_and_lab_metrics() {
		$row = Parser::parse( $this->fixture( 'psi-mobile.json' ) );
		$this->assertIsArray( $row );
		$this->assertSame( 62, $row['score'] );
		$this->assertEqualsWithDelta( 5480.2, $row['lab_lcp'], 0.01 );
		$this->assertEqualsWithDelta( 0.0812, $row['lab_cls'], 0.0001 );
		$this->assertEqualsWithDelta( 310.0, $row['lab_tbt'], 0.01 );
		$this->assertEqualsWithDelta( 3120.5, $row['lab_fcp'], 0.01 );
		$this->assertEqualsWithDelta( 4890.0, $row['lab_si'], 0.01 );
		$this->assertSame( '12.8.2', $row['lighthouse_version'] );
		$this->assertSame( '', $row['runtime_error'] );
	}

	public function test_field_data_marks_origin_fallback_and_scales_cls() {
		$row = Parser::parse( $this->fixture( 'psi-mobile.json' ) );
		$this->assertSame( 'origin', $row['field_scope'] );
		$this->assertSame( 'AVERAGE', $row['field_category'] );
		$this->assertEqualsWithDelta( 2900.0, $row['field_lcp'], 0.01 );
		$this->assertEqualsWithDelta( 180.0, $row['field_inp'], 0.01 );
		$this->assertEqualsWithDelta( 0.05, $row['field_cls'], 0.0001 );
		$this->assertEqualsWithDelta( 950.0, $row['field_ttfb'], 0.01 );
	}

	public function test_field_data_for_this_url() {
		$json = $this->fixture( 'psi-mobile.json' );
		$json['loadingExperience']['origin_fallback'] = false;
		$row  = Parser::parse( $json );
		$this->assertSame( 'url', $row['field_scope'] );
	}

	public function test_origin_only_data_is_used_when_page_has_none() {
		$json = $this->fixture( 'psi-mobile.json' );
		unset( $json['loadingExperience'] );
		$row = Parser::parse( $json );
		$this->assertSame( 'origin', $row['field_scope'] );
		$this->assertEqualsWithDelta( 2900.0, $row['field_lcp'], 0.01 );
		$this->assertNull( $row['field_inp'] );
	}

	public function test_no_field_data() {
		$row = Parser::parse( $this->fixture( 'psi-desktop-nofield.json' ) );
		$this->assertSame( 97, $row['score'] );
		$this->assertSame( '', $row['field_scope'] );
		$this->assertNull( $row['field_lcp'] );
	}

	public function test_top_three_opportunities_ranked_and_deduplicated_by_topic() {
		$row  = Parser::parse( $this->fixture( 'psi-mobile.json' ) );
		$opps = $row['opportunities'];
		$this->assertCount( 3, $opps );
		$this->assertSame( array( 'render-blocking-insight', 'image-delivery-insight', 'unused-javascript' ), array_column( $opps, 'id' ) );
		$this->assertSame( 2900, $opps[0]['savings_ms'] );
		$this->assertSame( 245760, $opps[2]['savings_bytes'] );
		// Hidden duplicates, passing audits and not-applicable audits are never picked.
		$ids = array_column( $opps, 'id' );
		$this->assertNotContains( 'render-blocking-resources', $ids );
		$this->assertNotContains( 'dom-size-insight', $ids );
		$this->assertNotContains( 'viewport-insight', $ids );
	}

	public function test_unknown_audit_falls_back_to_lighthouse_text() {
		$json = $this->fixture( 'psi-mobile.json' );
		// Make everything else pass so only the unknown insight is left.
		foreach ( array( 'render-blocking-insight', 'image-delivery-insight', 'unused-javascript', 'third-parties-insight' ) as $id ) {
			$json['lighthouseResult']['audits'][ $id ]['score'] = 1;
		}
		$row = Parser::parse( $json );
		$this->assertCount( 1, $row['opportunities'] );
		$advice = Advice::for_opportunity( $row['opportunities'][0] );
		$this->assertSame( 'A brand new insight', $advice['title'] );
		$this->assertSame( 'Something new.', $advice['why'] );
	}

	public function test_runtime_error_without_score() {
		$row = Parser::parse( $this->fixture( 'psi-runtime-error.json' ) );
		$this->assertNull( $row['score'] );
		$this->assertSame( 'The page did not paint any content.', $row['runtime_error'] );
	}

	public function test_missing_lighthouse_result_returns_null() {
		$this->assertNull( Parser::parse( array( 'error' => array( 'code' => 500 ) ) ) );
	}

	public function test_every_known_audit_maps_to_a_defined_topic() {
		$topics = Advice::topics();
		foreach ( Advice::audit_topics() as $audit => $topic ) {
			$this->assertArrayHasKey( $topic, $topics, $audit );
		}
	}
}
