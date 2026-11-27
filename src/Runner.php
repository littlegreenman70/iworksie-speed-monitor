<?php
/**
 * Runs one test and stores the result.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor;

use IWorksIE\SpeedMonitor\Psi\Client;
use IWorksIE\SpeedMonitor\Psi\Parser;
use IWorksIE\SpeedMonitor\Storage\Results;

/**
 * Test one URL on one device.
 */
final class Runner {

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @param Client|null $client Client (injectable for tests).
	 */
	public function __construct( $client = null ) {
		$this->client = $client instanceof Client ? $client : new Client();
	}

	/**
	 * Run and store.
	 *
	 * @param string $url      URL.
	 * @param string $strategy mobile|desktop.
	 * @param string $source   manual|scheduled.
	 * @return array<string,mixed> Stored row (with id) plus 'retry' flag.
	 */
	public function run( $url, $strategy, $source ) {
		Installer::maybe_upgrade();

		$strategy = 'desktop' === $strategy ? 'desktop' : 'mobile';
		$base     = array(
			'url'       => (string) $url,
			'strategy'  => $strategy,
			'source'    => 'scheduled' === $source ? 'scheduled' : 'manual',
			'tested_at' => gmdate( 'Y-m-d H:i:s' ),
		);

		$response = $this->client->run( (string) $url, $strategy, Settings::api_key() );
		$retry    = ! empty( $response['retry'] );
		$parsed   = ( $response['ok'] && is_array( $response['data'] ) ) ? Parser::parse( $response['data'] ) : null;

		if ( null === $parsed ) {
			$row = array_merge(
				$base,
				array(
					'status'        => 'error',
					'error_message' => '' !== $response['error'] ? $response['error'] : __( 'Google PageSpeed Insights returned no result.', 'iworksie-speed-monitor' ),
				)
			);
		} elseif ( null === $parsed['score'] ) {
			// Lighthouse ran but could not score the page (e.g. the page timed out or returned an error page).
			$message = '' !== $parsed['runtime_error'] ? $parsed['runtime_error'] : __( 'Lighthouse could not score this page.', 'iworksie-speed-monitor' );
			$row     = array_merge(
				$base,
				array(
					'status'        => 'error',
					/* translators: %s: Lighthouse error message */
					'error_message' => sprintf( __( 'Google could not test the page: %s', 'iworksie-speed-monitor' ), $message ),
				)
			);
			$retry   = false;
		} else {
			unset( $parsed['runtime_error'] );
			$row = array_merge( $base, $parsed, array( 'status' => 'ok' ) );
		}

		$row['id']    = Results::insert( $row );
		$row['retry'] = $retry;

		/**
		 * Fires after a speed test is stored.
		 *
		 * @param array<string,mixed> $row Stored row.
		 */
		do_action( 'iwiesp_test_completed', $row );

		return $row;
	}
}
