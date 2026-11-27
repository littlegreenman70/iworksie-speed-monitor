<?php
/**
 * Monitored URL validation.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Unit;

use IWorksIE\SpeedMonitor\Support\Urls;

/**
 * @covers \IWorksIE\SpeedMonitor\Support\Urls
 */
final class UrlsTest extends UnitTestCase {

	public function test_accepts_own_site_and_paths() {
		$hosts = array( 'example.org' );
		$this->assertSame( 'https://example.org/', Urls::normalize( 'https://example.org', $hosts ) );
		$this->assertSame( 'https://example.org/contact/', Urls::normalize( '/contact/', $hosts ) );
		$this->assertSame( 'https://example.org/shop/?p=2', Urls::normalize( 'https://EXAMPLE.org/shop/?p=2#top', $hosts ) );
	}

	public function test_rejects_other_hosts_and_schemes() {
		$hosts = array( 'example.org' );
		$this->assertSame( '', Urls::normalize( 'https://evil.test/', $hosts ) );
		$this->assertSame( '', Urls::normalize( 'https://example.org.evil.test/', $hosts ) );
		$this->assertSame( '', Urls::normalize( '//evil.test/x', $hosts ) );
		$this->assertSame( '', Urls::normalize( 'javascript:alert(1)', $hosts ) );
		$this->assertSame( '', Urls::normalize( 'ftp://example.org/', $hosts ) );
		$this->assertSame( '', Urls::normalize( 'https://user:pw@example.org/', $hosts ) );
		$this->assertSame( '', Urls::normalize( 'not a url', $hosts ) );
	}

	public function test_parse_list_dedupes_and_caps() {
		$raw    = "https://example.org/\n\n/a/\nhttps://example.org/\nhttps://other.test/\n/b/\n/c/";
		$result = Urls::parse_list( $raw, array( 'example.org' ), 3 );
		$this->assertSame( array( 'https://example.org/', 'https://example.org/a/', 'https://example.org/b/' ), $result['urls'] );
		$this->assertSame( array( 'https://other.test/', 'https://example.org/c/' ), $result['rejected'] );
	}

	public function test_label() {
		$this->assertSame( '/', Urls::label( 'https://example.org' ) );
		$this->assertSame( '/shop/?p=2', Urls::label( 'https://example.org/shop/?p=2' ) );
	}
}
