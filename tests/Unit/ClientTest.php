<?php
/**
 * API client helpers.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Unit;

use IWorksIE\SpeedMonitor\Psi\Client;

/**
 * @covers \IWorksIE\SpeedMonitor\Psi\Client
 */
final class ClientTest extends UnitTestCase {

	public function test_request_url() {
		$url = Client::request_url( 'https://example.org/a b/?x=1&y=2', 'desktop', 'KEY123', 'en-GB' );
		$this->assertStringStartsWith( 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?', $url );
		$this->assertStringContainsString( 'url=https%3A%2F%2Fexample.org%2Fa%20b%2F%3Fx%3D1%26y%3D2', $url );
		$this->assertStringContainsString( 'strategy=desktop', $url );
		$this->assertStringContainsString( 'category=performance', $url );
		$this->assertStringContainsString( 'locale=en-GB', $url );
		$this->assertStringContainsString( 'key=KEY123', $url );
		$this->assertStringContainsString( 'strategy=mobile', Client::request_url( 'https://example.org/', 'weird' ) );
		$this->assertStringNotContainsString( 'key=', Client::request_url( 'https://example.org/', 'mobile' ) );
	}

	public function test_redact_never_leaks_key() {
		$this->assertSame( 'failed for [key] at x?key=[key]&y=1', Client::redact( 'failed for SECRETKEY at x?key=SECRETKEY&y=1', 'SECRETKEY' ) );
		$this->assertSame( 'url ?key=[key]', Client::redact( 'url ?key=other', '' ) );
	}

	public function test_explain_rate_limit() {
		$this->assertStringContainsString( 'API key in Settings', Client::explain( 429, '', false ) );
		$this->assertStringContainsString( 'daily limit', Client::explain( 429, '', true ) );
	}

	public function test_explain_unreachable_page() {
		$this->assertStringContainsString( 'publicly reachable', Client::explain( 400, 'Lighthouse returned error: FAILED_DOCUMENT_REQUEST.', true ) );
		$this->assertStringContainsString( 'publicly reachable', Client::explain( 500, 'Lighthouse returned error: ERRORED_DOCUMENT_REQUEST', true ) );
	}

	public function test_explain_bad_key() {
		$this->assertStringContainsString( 'rejected the API key', Client::explain( 400, 'API key not valid. Please pass a valid API key.', true ) );
	}
}
