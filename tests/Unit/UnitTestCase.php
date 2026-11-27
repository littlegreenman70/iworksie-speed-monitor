<?php
/**
 * Base for unit tests: Brain Monkey with common WordPress stubs.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Shared setup.
 */
abstract class UnitTestCase extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\stubs(
			array(
				'sanitize_text_field' => static function ( $s ) {
					return trim( strip_tags( (string) $s ) );
				},
				'sanitize_key'        => static function ( $s ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
				},
				'wp_strip_all_tags'   => static function ( $s ) {
					return strip_tags( (string) $s );
				},
				'wp_parse_url'        => static function ( $url, $component = -1 ) {
					return parse_url( $url, $component );
				},
				'esc_url_raw'         => static function ( $url ) {
					return (string) $url;
				},
				'untrailingslashit'   => static function ( $s ) {
					return rtrim( (string) $s, '/\\' );
				},
				'home_url'            => static function ( $path = '' ) {
					return 'https://example.org' . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) );
				},
				'site_url'            => static function ( $path = '' ) {
					return 'https://example.org' . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) );
				},
				'number_format_i18n'  => static function ( $n, $d = 0 ) {
					return number_format( (float) $n, (int) $d );
				},
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Load a JSON fixture.
	 *
	 * @param string $name File name.
	 * @return array<string,mixed>
	 */
	protected function fixture( $name ) {
		$json = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/' . $name ), true );
		$this->assertIsArray( $json );
		return $json;
	}
}
