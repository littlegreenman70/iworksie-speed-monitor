<?php
/**
 * Manual test endpoint.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Integration;

use IWorksIE\SpeedMonitor\Settings;
use WP_Ajax_UnitTestCase;

/**
 * Auth and validation for wp_ajax_iwiesp_run_test.
 *
 * @group ajax
 */
final class AjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * @var string
	 */
	private $body = '';

	public function set_up() {
		parent::set_up();
		\IWorksIE\SpeedMonitor\Installer::install();
		Settings::save( array_merge( Settings::defaults(), array( 'urls' => array( home_url( '/' ) ) ) ) );
		$this->body = (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/psi-mobile.json' );
		add_filter( 'pre_http_request', array( $this, 'fake_http' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_http' ), 10 );
		parent::tear_down();
	}

	/**
	 * @param false|array<string,mixed> $pre  Pre.
	 * @param array<string,mixed>       $args Args.
	 * @param string                    $url  URL.
	 * @return array<string,mixed>|false
	 */
	public function fake_http( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://www.googleapis.com/' ) ) {
			return $pre;
		}
		return array(
			'headers'  => array(),
			'body'     => $this->body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function call() {
		try {
			$this->_handleAjax( 'iwiesp_run_test' );
		} catch ( \WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		return (array) json_decode( $this->_last_response, true );
	}

	public function test_bad_nonce_rejected() {
		$this->_setRole( 'administrator' );
		$_POST['nonce']    = 'bad';
		$_POST['url']      = home_url( '/' );
		$_POST['strategy'] = 'mobile';
		$this->expectException( \WPAjaxDieStopException::class );
		$this->_handleAjax( 'iwiesp_run_test' );
	}

	public function test_subscriber_rejected() {
		$this->_setRole( 'subscriber' );
		$_POST['nonce']    = wp_create_nonce( 'iwiesp_test' );
		$_POST['url']      = home_url( '/' );
		$_POST['strategy'] = 'mobile';
		$response          = $this->call();
		$this->assertFalse( $response['success'] );
	}

	public function test_url_not_in_settings_rejected() {
		$this->_setRole( 'administrator' );
		$_POST['nonce']    = wp_create_nonce( 'iwiesp_test' );
		$_POST['url']      = 'https://someone-else.test/';
		$_POST['strategy'] = 'mobile';
		$response          = $this->call();
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'not in your monitored list', $response['data']['message'] );
	}

	public function test_admin_runs_test() {
		$this->_setRole( 'administrator' );
		$_POST['nonce']    = wp_create_nonce( 'iwiesp_test' );
		$_POST['url']      = home_url( '/' );
		$_POST['strategy'] = 'mobile';
		$response          = $this->call();
		$this->assertTrue( $response['success'] );
		$this->assertSame( 62, $response['data']['score'] );
		$this->assertSame( 'recommended', $response['data']['band'] );
	}
}
