<?php
/**
 * Plugin Name:       iWorks.ie Speed Monitor
 * Plugin URI:        https://www.iworks.ie
 * Description:       Track your site's speed over time using Google PageSpeed Insights: scheduled tests, history, drop alerts, and plain-English fixes. Real-visitor and lab numbers are clearly labelled.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            iWorks.ie
 * Author URI:        https://www.iworks.ie
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       iworksie-speed-monitor
 * Domain Path:       /languages
 *
 * @package IWorksIE\SpeedMonitor
 */

defined( 'ABSPATH' ) || exit;

define( 'IWIESM_VERSION', '1.0.0' );
define( 'IWIESM_FILE', __FILE__ );
define( 'IWIESM_PATH', plugin_dir_path( __FILE__ ) );
define( 'IWIESM_URL', plugin_dir_url( __FILE__ ) );
define( 'IWIESM_MIN_PHP', '7.4' );
define( 'IWIESM_MIN_WP', '6.5' );

/**
 * Admin notice when requirements are not met.
 *
 * @param string $message Notice text.
 * @return void
 */
function iwiesm_requirements_notice( $message ) {
	add_action(
		'admin_notices',
		static function () use ( $message ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	);
}

if ( version_compare( PHP_VERSION, IWIESM_MIN_PHP, '<' ) ) {
	iwiesm_requirements_notice(
		sprintf(
			/* translators: 1: required PHP version, 2: current PHP version */
			__( 'iWorks.ie Speed Monitor requires PHP %1$s or higher. This site is running PHP %2$s.', 'iworksie-speed-monitor' ),
			IWIESM_MIN_PHP,
			PHP_VERSION
		)
	);
	return;
}

global $wp_version;
if ( isset( $wp_version ) && version_compare( $wp_version, IWIESM_MIN_WP, '<' ) ) {
	iwiesm_requirements_notice(
		sprintf(
			/* translators: 1: required WordPress version, 2: current WordPress version */
			__( 'iWorks.ie Speed Monitor requires WordPress %1$s or higher. This site is running WordPress %2$s.', 'iworksie-speed-monitor' ),
			IWIESM_MIN_WP,
			$wp_version
		)
	);
	return;
}

$iwiesm_prefixed = IWIESM_PATH . 'vendor-prefixed/autoload.php';
if ( ! is_readable( $iwiesm_prefixed ) ) {
	iwiesm_requirements_notice(
		__( 'iWorks.ie Speed Monitor is missing its vendor-prefixed library. Run composer install, or install the release zip from wordpress.org.', 'iworksie-speed-monitor' )
	);
	return;
}
require_once $iwiesm_prefixed;

$iwiesm_autoload = IWIESM_PATH . 'vendor/autoload.php';
if ( is_readable( $iwiesm_autoload ) ) {
	require_once $iwiesm_autoload;
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix = 'IWorksIE\\SpeedMonitor\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}
			$relative = substr( $class_name, strlen( $prefix ) );
			$file     = IWIESM_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook( __FILE__, array( '\IWorksIE\SpeedMonitor\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\IWorksIE\SpeedMonitor\Scheduler', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( '\IWorksIE\SpeedMonitor\Vendor\IWorksIE\Core\Core' ) ) {
			iwiesm_requirements_notice(
				__( 'iWorks.ie Speed Monitor could not load its shared tools library.', 'iworksie-speed-monitor' )
			);
			return;
		}
		// Cron handlers must exist on every request, not only in admin.
		\IWorksIE\SpeedMonitor\Scheduler::hooks();

		// Boot the admin UI after init so translations are not loaded too early (WP 6.7+).
		add_action(
			'init',
			static function () {
				\IWorksIE\SpeedMonitor\Plugin::instance()->boot();
			},
			5
		);
	},
	5
);
