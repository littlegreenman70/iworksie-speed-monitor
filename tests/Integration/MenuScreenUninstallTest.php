<?php
/**
 * Menu, admin screens render, and uninstall.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Integration;

use IWorksIE\SpeedMonitor\Admin\Screens;
use IWorksIE\SpeedMonitor\Plugin;
use IWorksIE\SpeedMonitor\Settings;
use IWorksIE\SpeedMonitor\Storage\Results;

/**
 * Menu + screens + uninstall.
 */
final class MenuScreenUninstallTest extends IntegrationTestCase {

	public function test_submenu_under_iworks_tools() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		do_action( 'admin_menu' );
		global $submenu;
		$this->assertArrayHasKey( 'iworks-tools', $submenu );
		$this->assertContains( 'iworksie-speed-monitor', array_column( $submenu['iworks-tools'], 2 ) );
	}

	/**
	 * Render a tab and return the HTML.
	 *
	 * @param string $tab Tab.
	 * @return string
	 */
	private function render_tab( $tab ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET['tab'] = $tab;
		$screens     = new Screens( Plugin::instance()->core() );
		ob_start();
		$screens->render();
		$html = (string) ob_get_clean();
		unset( $_GET['tab'] );
		return $html;
	}

	public function test_overview_empty_state() {
		$html = $this->render_tab( 'overview' );
		$this->assertStringContainsString( 'Run your first speed test', $html );
		$this->assertStringContainsString( 'data-iwiesm-run', $html );
		$this->assertStringContainsString( 'Only the page address is sent to Google', $html );
	}

	public function test_overview_with_results_labels_field_and_lab() {
		$url = home_url( '/' );
		Settings::save( array_merge( Settings::defaults(), array( 'urls' => array( $url ) ) ) );
		$this->responses[] = array( 200, $this->fixture( 'psi-mobile.json', 80 ) );
		$this->responses[] = array( 200, $this->fixture( 'psi-mobile.json', 62 ) );
		$this->responses[] = array( 200, $this->fixture( 'psi-desktop-nofield.json' ) );
		$runner            = new \IWorksIE\SpeedMonitor\Runner();
		$runner->run( $url, 'mobile', 'manual' );
		$runner->run( $url, 'mobile', 'scheduled' );
		$runner->run( $url, 'desktop', 'manual' );

		$html = $this->render_tab( 'overview' );
		$this->assertStringContainsString( 'Real visitors', $html );
		$this->assertStringContainsString( 'Whole site (not enough data for this page alone)', $html );
		$this->assertStringContainsString( 'Lab test', $html );
		$this->assertStringContainsString( 'Down 18 since the previous test', $html );
		$this->assertStringContainsString( 'Not enough real-visitor data yet', $html );
		$this->assertStringContainsString( 'Files are holding up the first paint', $html );
		$this->assertStringContainsString( 'iwiesm-spark', $html );
		$this->assertStringContainsString( 'iWorks can fix them for you', $html );
	}

	public function test_history_and_settings_render() {
		Results::insert(
			array(
				'url'           => home_url( '/' ),
				'strategy'      => 'mobile',
				'status'        => 'error',
				'error_message' => 'Google could not load <b>the page</b>',
			)
		);
		$html = $this->render_tab( 'history' );
		$this->assertStringContainsString( 'Failed', $html );
		$this->assertStringContainsString( '&lt;b&gt;the page&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'iwiesm_export_csv', $html );

		Settings::save_api_key( 'AIzaSyD-abcdefghijklmnopqrstuvwxyz_0123' );
		$html = $this->render_tab( 'settings' );
		$this->assertStringContainsString( 'name="iwiesm[urls]"', $html );
		$this->assertStringNotContainsString( 'abcdefghijklmnopqrstuvwxyz', $html, 'Saved key must never be printed' );
		$this->assertStringContainsString( '••••••••0123', $html );
	}

	public function test_uninstall_removes_everything() {
		Settings::save( Settings::defaults() );
		Settings::save_api_key( 'AIzaSyD-abcdefghijklmnopqrstuvwxyz_0123' );
		update_option( 'iwiesm_queue', array( 'items' => array( 1 ) ), false );
		set_transient( 'iwiesm_notice_1', array( 'message' => 'x' ), 60 );
		wp_schedule_event( time() + 100, 'daily', 'iwiesm_scheduled_run' );
		Results::insert(
			array(
				'url'      => home_url( '/' ),
				'strategy' => 'mobile',
				'score'    => 50,
			)
		);

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		global $wpdb;
		$this->assertFalse( get_option( Settings::OPTION ) );
		$this->assertFalse( get_option( Settings::KEY_OPTION ) );
		$this->assertFalse( get_option( 'iwiesm_queue' ) );
		$this->assertFalse( get_option( 'iwiesm_db_version' ) );
		$this->assertFalse( get_transient( 'iwiesm_notice_1' ) );
		$this->assertFalse( wp_next_scheduled( 'iwiesm_scheduled_run' ) );
		$wpdb->suppress_errors( true );
		$exists = $wpdb->get_results( 'DESCRIBE ' . Results::table() );
		$wpdb->suppress_errors( false );
		$this->assertEmpty( $exists );
	}
}
