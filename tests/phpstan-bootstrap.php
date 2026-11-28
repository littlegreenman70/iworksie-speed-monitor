<?php
/**
 * PHPStan bootstrap stubs.
 *
 * @package IWorksIE\SpeedMonitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'IWIESP_VERSION' ) ) {
	define( 'IWIESP_VERSION', '1.0.0' );
}
if ( ! defined( 'IWIESP_FILE' ) ) {
	define( 'IWIESP_FILE', '/tmp/iworksie-speed-monitor/iworksie-speed-monitor.php' );
}
if ( ! defined( 'IWIESP_PATH' ) ) {
	define( 'IWIESP_PATH', '/tmp/iworksie-speed-monitor/' );
}
if ( ! defined( 'IWIESP_URL' ) ) {
	define( 'IWIESP_URL', 'https://example.com/wp-content/plugins/iworksie-speed-monitor/' );
}
if ( ! defined( 'IWIESP_MIN_PHP' ) ) {
	define( 'IWIESP_MIN_PHP', '7.4' );
}
if ( ! defined( 'IWIESP_MIN_WP' ) ) {
	define( 'IWIESP_MIN_WP', '6.5' );
}

if ( ! class_exists( '\IWorksIE\SpeedMonitor\Vendor\IWorksIE\Core\Core' ) && is_readable( dirname( __DIR__ ) . '/vendor-prefixed/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor-prefixed/autoload.php';
}
