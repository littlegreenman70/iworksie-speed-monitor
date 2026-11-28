<?php
/**
 * Settings sanitising.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Unit;

use Brain\Monkey\Functions;
use IWorksIE\SpeedMonitor\Settings;

/**
 * @covers \IWorksIE\SpeedMonitor\Settings
 */
final class SettingsTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			static function ( $name, $fallback = false ) {
				return 'admin_email' === $name ? 'admin@example.org' : $fallback;
			}
		);
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'is_email' )->alias(
			static function ( $email ) {
				return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
			}
		);
	}

	public function test_sanitize_clamps_and_filters() {
		$out = Settings::sanitize(
			array(
				'enabled'        => '1',
				'urls'           => "/\n/about/\nhttps://elsewhere.test/",
				'strategies'     => array( 'desktop', 'tablet', 'desktop' ),
				'frequency'      => 'hourly',
				'alert_email'    => 'not-an-email',
				'alert_drop'     => '0',
				'alert_below'    => '150',
				'retention_days' => 'abc',
			)
		);
		$s = $out['settings'];
		$this->assertTrue( $s['enabled'] );
		$this->assertSame( array( 'https://example.org/', 'https://example.org/about/' ), $s['urls'] );
		$this->assertSame( array( 'https://elsewhere.test/' ), $out['rejected'] );
		$this->assertSame( array( 'desktop' ), $s['strategies'] );
		$this->assertSame( 'weekly', $s['frequency'] );
		$this->assertSame( 'admin@example.org', $s['alert_email'] );
		$this->assertSame( 1, $s['alert_drop'] );
		$this->assertSame( 100, $s['alert_below'] );
		$this->assertSame( 365, $s['retention_days'] );
		$this->assertFalse( $s['alerts_enabled'] );
	}

	public function test_empty_input_falls_back_to_home_and_mobile() {
		$s = Settings::sanitize( array() )['settings'];
		$this->assertSame( array( 'https://example.org/' ), $s['urls'] );
		$this->assertSame( array( 'mobile' ), $s['strategies'] );
		$this->assertFalse( $s['enabled'] );
	}

	public function test_api_key_validation_and_mask() {
		$this->assertSame( '', Settings::sanitize_api_key( 'short' ) );
		$this->assertSame( '', Settings::sanitize_api_key( 'AIza<script>alert(1)</script>xxxxxxxxxxxx' ) );
		$key = 'AIzaSyD-abcdefghijklmnopqrstuvwxyz_0123';
		$this->assertSame( $key, Settings::sanitize_api_key( "  {$key} " ) );
		$this->assertSame( '••••••••0123', Settings::mask_key( $key ) );
		$this->assertSame( '', Settings::mask_key( '' ) );
	}
}
