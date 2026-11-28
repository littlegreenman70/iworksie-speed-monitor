<?php
/**
 * Base for integration tests: fakes the PageSpeed Insights API.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Integration;

use IWorksIE\SpeedMonitor\Installer;
use IWorksIE\SpeedMonitor\Settings;
use IWorksIE\SpeedMonitor\Storage\Results;
use WP_UnitTestCase;

/**
 * Shared helpers.
 */
abstract class IntegrationTestCase extends WP_UnitTestCase {

	/**
	 * Queue of fake API responses (status, body).
	 *
	 * @var array<int,array{0: int, 1: string}>
	 */
	protected $responses = array();

	/**
	 * URLs requested from the API.
	 *
	 * @var array<int,string>
	 */
	protected $requested = array();

	/**
	 * Mails captured.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	protected $mails = array();

	public function set_up() {
		parent::set_up();
		delete_option( Installer::OPTION );
		Installer::maybe_upgrade();
		Results::delete_all();
		$this->responses = array();
		$this->requested = array();
		$this->mails     = array();
		add_filter( 'pre_http_request', array( $this, 'fake_http' ), 10, 3 );
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_http' ), 10 );
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		parent::tear_down();
	}

	/**
	 * @param false|array<string,mixed> $pre  Pre.
	 * @param array<string,mixed>       $args Args.
	 * @param string                    $url  URL.
	 * @return array<string,mixed>|false
	 */
	public function fake_http( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://www.googleapis.com/pagespeedonline/' ) ) {
			return $pre;
		}
		$this->requested[] = $url;
		$next              = array_shift( $this->responses );
		if ( null === $next ) {
			$next = array( 500, '{}' );
		}
		return array(
			'headers'  => array(),
			'body'     => $next[1],
			'response' => array(
				'code'    => $next[0],
				'message' => 'x',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * @param null|bool           $short Short-circuit.
	 * @param array<string,mixed> $atts  Mail.
	 * @return bool
	 */
	public function capture_mail( $short, $atts ) {
		$this->mails[] = $atts;
		return true;
	}

	/**
	 * Fixture JSON with a given score.
	 *
	 * @param string   $name  Fixture.
	 * @param int|null $score Override score (0–100).
	 * @return string
	 */
	protected function fixture( $name, $score = null ) {
		$json = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/' . $name ), true );
		if ( null !== $score ) {
			$json['lighthouseResult']['categories']['performance']['score'] = $score / 100;
		}
		return (string) wp_json_encode( $json );
	}

	/**
	 * Save settings.
	 *
	 * @param array<string,mixed> $overrides Overrides.
	 * @return void
	 */
	protected function settings( array $overrides ) {
		Settings::save( array_merge( Settings::defaults(), $overrides ) );
	}
}
