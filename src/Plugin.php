<?php
/**
 * Plugin bootstrap.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor;

use IWorksIE\SpeedMonitor\Admin\Actions;
use IWorksIE\SpeedMonitor\Admin\Screens;
use IWorksIE\SpeedMonitor\Vendor\IWorksIE\Core\Core;

/**
 * Singleton plugin entry.
 */
final class Plugin {

	const SLUG       = 'iworksie-speed-monitor';
	const CAPABILITY = 'manage_options';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var Core|null
	 */
	private $core = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return Core|null
	 */
	public function core() {
		return $this->core;
	}

	/**
	 * Admin page URL.
	 *
	 * @param string              $tab  Tab key.
	 * @param array<string,mixed> $args Extra query args.
	 * @return string
	 */
	public static function page_url( $tab = '', array $args = array() ) {
		$query = array( 'page' => self::SLUG );
		if ( '' !== $tab ) {
			$query['tab'] = $tab;
		}
		return add_query_arg( array_merge( $query, $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Boot core, menu, admin actions.
	 *
	 * @return void
	 */
	public function boot() {
		if ( null !== $this->core ) {
			return;
		}

		$this->core = Core::boot(
			array(
				'plugin_file' => IWIESP_FILE,
				'slug'        => self::SLUG,
				'prefix'      => 'iwiesp',
				'name'        => __( 'Speed Monitor', 'iworksie-speed-monitor' ),
				'version'     => IWIESP_VERSION,
				'text_domain' => 'iworksie-speed-monitor',
				'capability'  => self::CAPABILITY,
				'wporg_url'   => 'https://wordpress.org/plugins/iworksie-speed-monitor/',
				'description' => __( 'Scheduled speed tests with history, drop alerts and plain-English fixes, using Google PageSpeed Insights.', 'iworksie-speed-monitor' ),
				'icon'        => 'dashicons-performance',
				'tagline'     => __( 'Tracks your speed over time. Real-visitor and lab numbers are always labelled.', 'iworksie-speed-monitor' ),
			)
		);

		$screens = new Screens( $this->core );
		$this->core->menu()->add_submenu(
			__( 'Speed Monitor', 'iworksie-speed-monitor' ),
			__( 'Speed Monitor', 'iworksie-speed-monitor' ),
			self::SLUG,
			array( $screens, 'render' )
		);

		$actions = new Actions();
		$actions->hooks();

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( IWIESP_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Keep the schema and schedule healthy (e.g. after an update or re-activation).
	 *
	 * @return void
	 */
	public function admin_init() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		Installer::maybe_upgrade();
		if ( Settings::value( 'enabled' ) && ! Scheduler::next_run() ) {
			Scheduler::sync();
		}
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array<int|string,string> $links Links.
	 * @return array<int|string,string>
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Open', 'iworksie-speed-monitor' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Plugin JS/CSS on our screen.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( null === $this->core || false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'iwiesp-admin',
			IWIESP_URL . 'assets/css/admin.css',
			array( 'iwiesp-iwt-core' ),
			IWIESP_VERSION
		);
		wp_enqueue_script(
			'iwiesp-admin',
			IWIESP_URL . 'assets/js/admin.js',
			array( 'iwiesp-iwt-core' ),
			IWIESP_VERSION,
			true
		);

		$tests = array();
		foreach ( (array) Settings::value( 'urls' ) as $url ) {
			foreach ( (array) Settings::value( 'strategies' ) as $strategy ) {
				$tests[] = array(
					'url'      => (string) $url,
					'strategy' => (string) $strategy,
				);
			}
		}

		wp_localize_script(
			'iwiesp-admin',
			'iwiespAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'iwiesp_test' ),
				'tests'   => $tests,
				'strings' => array(
					/* translators: 1: current test number, 2: total tests, 3: page path, 4: device */
					'testing'      => __( 'Testing %1$d of %2$d: %3$s (%4$s). Each test takes 15–60 seconds…', 'iworksie-speed-monitor' ),
					'mobile'       => __( 'mobile', 'iworksie-speed-monitor' ),
					'desktop'      => __( 'desktop', 'iworksie-speed-monitor' ),
					/* translators: %d: number of failed tests */
					'doneErrors'   => __( 'Finished with %d failed test(s). Reloading…', 'iworksie-speed-monitor' ),
					'done'         => __( 'All tests finished. Reloading…', 'iworksie-speed-monitor' ),
					'failed'       => __( 'Test failed:', 'iworksie-speed-monitor' ),
					'requestError' => __( 'The request failed. Your server may have timed out; try again, or rely on scheduled tests.', 'iworksie-speed-monitor' ),
					'confirmClear' => __( 'Delete all stored test history? This cannot be undone.', 'iworksie-speed-monitor' ),
				),
			)
		);
	}
}
