<?php
/**
 * PHPUnit bootstrap.
 *
 * @package IWorksIE\SpeedMonitor
 */

$iwiesm_root = dirname( __DIR__ );

require_once $iwiesm_root . '/vendor/autoload.php';

$iwiesm_prefixed = $iwiesm_root . '/vendor-prefixed/autoload.php';
if ( is_readable( $iwiesm_prefixed ) ) {
	require_once $iwiesm_prefixed;
}

$iwiesm_suite = getenv( 'IWIESM_TESTSUITE' );
if ( ! $iwiesm_suite ) {
	$iwiesm_suite = 'unit';
}

if ( 'integration' === $iwiesm_suite ) {
	$iwiesm_wp_tests = getenv( 'WP_TESTS_DIR' );
	if ( ! $iwiesm_wp_tests ) {
		$iwiesm_wp_tests = $iwiesm_root . '/tests/wordpress/wordpress-tests-lib';
	}
	if ( ! is_readable( $iwiesm_wp_tests . '/includes/functions.php' ) ) {
		fwrite( STDERR, "WP test suite not found at {$iwiesm_wp_tests}. Run bin/install-wp-tests.sh first.\n" );
		exit( 1 );
	}
	require_once $iwiesm_wp_tests . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $iwiesm_root ) {
			$plugin = $iwiesm_root . '/iworksie-speed-monitor.php';
			if ( is_readable( $plugin ) ) {
				require $plugin;
			}
			$ex1 = dirname( $iwiesm_root ) . '/iworks-tools-core/examples/iworksie-example-one/iworksie-example-one.php';
			$ex2 = dirname( $iwiesm_root ) . '/iworks-tools-core/examples/iworksie-example-two/iworksie-example-two.php';
			if ( is_readable( $ex1 ) ) {
				require $ex1;
			}
			if ( is_readable( $ex2 ) ) {
				require $ex2;
			}
		}
	);

	require_once $iwiesm_wp_tests . '/includes/bootstrap.php';
}
