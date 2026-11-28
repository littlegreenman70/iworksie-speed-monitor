<?php
/**
 * PHPUnit bootstrap.
 *
 * @package IWorksIE\SpeedMonitor
 */

$iwiesp_root = dirname( __DIR__ );

require_once $iwiesp_root . '/vendor/autoload.php';

$iwiesp_prefixed = $iwiesp_root . '/vendor-prefixed/autoload.php';
if ( is_readable( $iwiesp_prefixed ) ) {
	require_once $iwiesp_prefixed;
}

$iwiesp_suite = getenv( 'IWIESP_TESTSUITE' );
if ( ! $iwiesp_suite ) {
	$iwiesp_suite = 'unit';
}

if ( 'integration' === $iwiesp_suite ) {
	$iwiesp_wp_tests = getenv( 'WP_TESTS_DIR' );
	if ( ! $iwiesp_wp_tests ) {
		$iwiesp_wp_tests = $iwiesp_root . '/tests/wordpress/wordpress-tests-lib';
	}
	if ( ! is_readable( $iwiesp_wp_tests . '/includes/functions.php' ) ) {
		fwrite( STDERR, "WP test suite not found at {$iwiesp_wp_tests}. Run bin/install-wp-tests.sh first.\n" );
		exit( 1 );
	}
	require_once $iwiesp_wp_tests . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $iwiesp_root ) {
			$plugin = $iwiesp_root . '/iworksie-speed-monitor.php';
			if ( is_readable( $plugin ) ) {
				require $plugin;
			}
			$ex1 = dirname( $iwiesp_root ) . '/iworks-tools-core/examples/iworksie-example-one/iworksie-example-one.php';
			$ex2 = dirname( $iwiesp_root ) . '/iworks-tools-core/examples/iworksie-example-two/iworksie-example-two.php';
			if ( is_readable( $ex1 ) ) {
				require $ex1;
			}
			if ( is_readable( $ex2 ) ) {
				require $ex2;
			}
		}
	);

	require_once $iwiesp_wp_tests . '/includes/bootstrap.php';
}
