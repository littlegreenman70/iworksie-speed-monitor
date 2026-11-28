<?php
/**
 * Runner, queue and alerts end to end with a fake API.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Tests\Integration;

use IWorksIE\SpeedMonitor\Runner;
use IWorksIE\SpeedMonitor\Scheduler;
use IWorksIE\SpeedMonitor\Settings;
use IWorksIE\SpeedMonitor\Storage\Results;

/**
 * Scheduled runs.
 */
final class SchedulerTest extends IntegrationTestCase {

	public function tear_down() {
		Scheduler::deactivate();
		parent::tear_down();
	}

	public function test_runner_stores_parsed_result() {
		$this->responses[] = array( 200, $this->fixture( 'psi-mobile.json' ) );
		$row               = ( new Runner() )->run( home_url( '/' ), 'mobile', 'manual' );
		$this->assertSame( 'ok', $row['status'] );
		$this->assertSame( 62, $row['score'] );
		$this->assertSame( 62, Results::latest( home_url( '/' ), 'mobile' )['score'] );
		$this->assertStringContainsString( 'strategy=mobile', $this->requested[0] );
		$this->assertStringNotContainsString( 'key=', $this->requested[0] );
	}

	public function test_runner_sends_api_key_and_stores_errors_without_it() {
		Settings::save_api_key( 'AIzaSyD-abcdefghijklmnopqrstuvwxyz_0123' );
		$this->responses[] = array( 400, '{"error":{"code":400,"message":"API key not valid for AIzaSyD-abcdefghijklmnopqrstuvwxyz_0123"}}' );
		$row               = ( new Runner() )->run( home_url( '/' ), 'desktop', 'manual' );
		$this->assertStringContainsString( 'key=AIzaSyD-abcdefghijklmnopqrstuvwxyz_0123', $this->requested[0] );
		$this->assertSame( 'error', $row['status'] );
		$this->assertStringContainsString( 'rejected the API key', $row['error_message'] );
		$stored = Results::last_attempt( home_url( '/' ), 'desktop' );
		$this->assertStringNotContainsString( 'abcdefghijklmnop', $stored['error_message'] );
		$this->assertFalse( $row['retry'] );
	}

	public function test_runtime_error_is_stored_as_failed_test() {
		$this->responses[] = array( 200, $this->fixture( 'psi-runtime-error.json' ) );
		$row               = ( new Runner() )->run( home_url( '/' ), 'mobile', 'manual' );
		$this->assertSame( 'error', $row['status'] );
		$this->assertStringContainsString( 'did not paint', $row['error_message'] );
	}

	public function test_sync_follows_settings() {
		$this->settings( array( 'enabled' => false ) );
		Scheduler::sync();
		$this->assertFalse( wp_next_scheduled( Scheduler::RUN_HOOK ) );

		$this->settings(
			array(
				'enabled'   => true,
				'frequency' => 'daily',
			)
		);
		Scheduler::sync();
		$this->assertSame( 'daily', wp_get_scheduled_event( Scheduler::RUN_HOOK )->schedule );

		$this->settings(
			array(
				'enabled'   => true,
				'frequency' => 'weekly',
			)
		);
		Scheduler::sync();
		$this->assertSame( 'weekly', wp_get_scheduled_event( Scheduler::RUN_HOOK )->schedule );

		$this->settings( array( 'enabled' => false ) );
		Scheduler::sync();
		$this->assertFalse( wp_next_scheduled( Scheduler::RUN_HOOK ) );
	}

	public function test_first_run_is_next_3am_site_time() {
		update_option( 'timezone_string', 'Europe/Dublin' );
		$now  = strtotime( '2026-11-26 23:00:00 UTC' );
		$next = Scheduler::first_run_time( $now );
		$this->assertSame( strtotime( '2026-11-27 03:00:00 UTC' ), $next );
		$this->assertSame( strtotime( '2026-11-27 03:00:00 UTC' ), Scheduler::first_run_time( strtotime( '2026-11-26 02:59:30 UTC' ) + DAY_IN_SECONDS - 90 ) );
	}

	public function test_scheduled_run_processes_one_test_per_tick_and_sends_one_digest() {
		$a = home_url( '/' );
		$b = home_url( '/about/' );
		$this->settings(
			array(
				'enabled'     => true,
				'urls'        => array( $a, $b ),
				'strategies'  => array( 'mobile' ),
				'alert_email' => 'owner@example.org',
			)
		);
		// Earlier results: both pages were fast.
		Results::insert(
			array(
				'url'      => $a,
				'strategy' => 'mobile',
				'score'    => 90,
			)
		);
		Results::insert(
			array(
				'url'      => $b,
				'strategy' => 'mobile',
				'score'    => 90,
			)
		);

		$this->responses[] = array( 200, $this->fixture( 'psi-mobile.json', 70 ) );
		$this->responses[] = array( 200, $this->fixture( 'psi-mobile.json', 40 ) );

		Scheduler::start_run();
		$this->assertSame( 2, Scheduler::remaining() );
		$this->assertNotFalse( wp_next_scheduled( Scheduler::QUEUE_HOOK ) );

		wp_clear_scheduled_hook( Scheduler::QUEUE_HOOK );
		Scheduler::process_queue();
		$this->assertCount( 1, $this->requested );
		$this->assertSame( 1, Scheduler::remaining() );
		$this->assertCount( 0, $this->mails, 'No email until the run finishes' );

		wp_clear_scheduled_hook( Scheduler::QUEUE_HOOK );
		Scheduler::process_queue();
		$this->assertSame( 0, Scheduler::remaining() );
		$this->assertCount( 2, $this->requested );
		$this->assertFalse( get_option( Scheduler::QUEUE_OPTION ) );
		$this->assertGreaterThan( 0, (int) get_option( Scheduler::LAST_OPTION ) );

		$this->assertCount( 1, $this->mails );
		$this->assertSame( 'owner@example.org', $this->mails[0]['to'] );
		$this->assertStringContainsString( '2 pages', $this->mails[0]['subject'] );
		$this->assertStringContainsString( 'dropped from 90 to 70', $this->mails[0]['message'] );
		$this->assertStringContainsString( 'dropped from 90 to 40', $this->mails[0]['message'] );
		$this->assertSame( 'scheduled', Results::latest( $a, 'mobile' )['source'] );
	}

	public function test_alerts_off_sends_nothing() {
		$this->settings(
			array(
				'enabled'        => true,
				'strategies'     => array( 'mobile' ),
				'alerts_enabled' => false,
			)
		);
		$this->responses[] = array( 200, $this->fixture( 'psi-mobile.json', 10 ) );
		Scheduler::start_run();
		Scheduler::process_queue();
		$this->assertSame( 0, Scheduler::remaining() );
		$this->assertCount( 0, $this->mails );
	}

	public function test_rate_limited_test_is_retried_once_later() {
		$this->settings(
			array(
				'enabled'    => true,
				'strategies' => array( 'mobile' ),
			)
		);
		$this->responses[] = array( 429, '{"error":{"code":429,"message":"Quota exceeded"}}' );
		Scheduler::start_run();
		wp_clear_scheduled_hook( Scheduler::QUEUE_HOOK );
		Scheduler::process_queue();

		$this->assertSame( 1, Scheduler::remaining() );
		$queue = get_option( Scheduler::QUEUE_OPTION );
		$this->assertSame( 1, $queue['items'][0]['attempts'] );
		$this->assertGreaterThan( time() + 200, $queue['items'][0]['not_before'] );
		$next = wp_next_scheduled( Scheduler::QUEUE_HOOK );
		$this->assertGreaterThan( time() + 200, $next );

		// Too early: nothing is requested.
		wp_clear_scheduled_hook( Scheduler::QUEUE_HOOK );
		Scheduler::process_queue();
		$this->assertCount( 1, $this->requested );

		// After the wait, the second failure gives up.
		$queue['items'][0]['not_before'] = time() - 1;
		update_option( Scheduler::QUEUE_OPTION, $queue, false );
		$this->responses[] = array( 429, '{}' );
		wp_clear_scheduled_hook( Scheduler::QUEUE_HOOK );
		Scheduler::process_queue();
		$this->assertCount( 2, $this->requested );
		$this->assertSame( 0, Scheduler::remaining() );
	}

	public function test_lock_prevents_parallel_processing() {
		$this->settings(
			array(
				'enabled'    => true,
				'strategies' => array( 'mobile' ),
			)
		);
		Scheduler::start_run();
		add_option( Scheduler::LOCK_OPTION, time(), '', false );
		Scheduler::process_queue();
		$this->assertCount( 0, $this->requested );
		$this->assertSame( 1, Scheduler::remaining() );

		// A stale lock is taken over.
		update_option( Scheduler::LOCK_OPTION, time() - 600, false );
		$this->responses[] = array( 200, $this->fixture( 'psi-mobile.json' ) );
		Scheduler::process_queue();
		$this->assertCount( 1, $this->requested );
		$this->assertFalse( get_option( Scheduler::LOCK_OPTION ) );
	}

	public function test_disabled_schedule_does_not_start() {
		$this->settings( array( 'enabled' => false ) );
		Scheduler::start_run();
		$this->assertSame( 0, Scheduler::remaining() );
	}
}
