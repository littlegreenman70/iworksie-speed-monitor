<?php
/**
 * Scheduled tests: one URL per cron tick so nothing times out.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor;

use IWorksIE\SpeedMonitor\Storage\Results;

/**
 * Recurring event fills a queue; a single event works through it one test at a time.
 */
final class Scheduler {

	const RUN_HOOK     = 'iwiesp_scheduled_run';
	const QUEUE_HOOK   = 'iwiesp_process_queue';
	const QUEUE_OPTION = 'iwiesp_queue';
	const LOCK_OPTION  = 'iwiesp_queue_lock';
	const LAST_OPTION  = 'iwiesp_last_run';
	const GAP          = 30;
	const RETRY_GAP    = 300;
	const MAX_ATTEMPTS = 2;
	const LOCK_TTL     = 300;

	/**
	 * Register cron handlers (every request).
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action( self::RUN_HOOK, array( __CLASS__, 'start_run' ) );
		add_action( self::QUEUE_HOOK, array( __CLASS__, 'process_queue' ) );
	}

	/**
	 * Make the recurring event match the settings.
	 *
	 * @return void
	 */
	public static function sync() {
		$settings  = Settings::get();
		$scheduled = wp_get_scheduled_event( self::RUN_HOOK );

		if ( empty( $settings['enabled'] ) ) {
			wp_clear_scheduled_hook( self::RUN_HOOK );
			return;
		}

		$recurrence = 'daily' === $settings['frequency'] ? 'daily' : 'weekly';
		if ( $scheduled && $scheduled->schedule === $recurrence ) {
			return;
		}
		wp_clear_scheduled_hook( self::RUN_HOOK );
		wp_schedule_event( self::first_run_time(), $recurrence, self::RUN_HOOK );
	}

	/**
	 * Next 03:00 in the site's time zone (quiet hours), as a UTC timestamp.
	 *
	 * @param int|null $now Current timestamp (for tests).
	 * @return int
	 */
	public static function first_run_time( $now = null ) {
		$now  = null === $now ? time() : (int) $now;
		$zone = wp_timezone();
		$date = new \DateTimeImmutable( '@' . $now );
		$date = $date->setTimezone( $zone )->setTime( 3, 0, 0 );
		if ( $date->getTimestamp() <= $now + 60 ) {
			$date = $date->modify( '+1 day' );
		}
		return (int) $date->getTimestamp();
	}

	/**
	 * Next scheduled run timestamp, or 0.
	 *
	 * @return int
	 */
	public static function next_run() {
		$next = wp_next_scheduled( self::RUN_HOOK );
		return $next ? (int) $next : 0;
	}

	/**
	 * Build the queue for a scheduled run.
	 *
	 * @return void
	 */
	public static function start_run() {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		Installer::maybe_upgrade();
		Results::prune( (int) $settings['retention_days'] );

		$existing = get_option( self::QUEUE_OPTION );
		if ( is_array( $existing ) && ! empty( $existing['items'] ) ) {
			// Previous run still in progress (very slow WP-Cron). Let it finish first.
			self::schedule_next( self::GAP );
			return;
		}

		$items = array();
		foreach ( (array) $settings['urls'] as $url ) {
			foreach ( (array) $settings['strategies'] as $strategy ) {
				$items[] = array(
					'url'        => (string) $url,
					'strategy'   => (string) $strategy,
					'attempts'   => 0,
					'not_before' => 0,
				);
			}
		}
		update_option(
			self::QUEUE_OPTION,
			array(
				'items'   => $items,
				'alerts'  => array(),
				'started' => time(),
			),
			false
		);
		self::schedule_next( 0 );
	}

	/**
	 * Run the next queued test. Called by cron, one test per call.
	 *
	 * @param Runner|null $runner Runner (injectable for tests).
	 * @return void
	 */
	public static function process_queue( $runner = null ) {
		if ( ! self::acquire_lock() ) {
			self::schedule_next( self::GAP );
			return;
		}
		try {
			self::process_one( $runner instanceof Runner ? $runner : new Runner() );
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * Body of process_queue, inside the lock.
	 *
	 * @param Runner $runner Runner.
	 * @return void
	 */
	private static function process_one( Runner $runner ) {
		$queue = get_option( self::QUEUE_OPTION );
		if ( ! is_array( $queue ) || empty( $queue['items'] ) || ! is_array( $queue['items'] ) ) {
			delete_option( self::QUEUE_OPTION );
			return;
		}
		$settings = Settings::get();

		// Take the first item that is not waiting for a retry slot.
		$index = null;
		foreach ( $queue['items'] as $i => $candidate ) {
			if ( empty( $candidate['not_before'] ) || (int) $candidate['not_before'] <= time() ) {
				$index = $i;
				break;
			}
		}
		if ( null === $index ) {
			self::schedule_next( self::next_delay( $queue['items'] ) );
			return;
		}
		$item = $queue['items'][ $index ];
		array_splice( $queue['items'], (int) $index, 1 );

		// Skip pages removed from settings since the run started.
		if ( in_array( $item['url'], (array) $settings['urls'], true ) ) {
			$row = $runner->run( (string) $item['url'], (string) $item['strategy'], 'scheduled' );

			if ( 'ok' === $row['status'] ) {
				$alert = Alerts::evaluate(
					$row,
					Results::latest( (string) $item['url'], (string) $item['strategy'], (int) $row['id'] ),
					(int) $settings['alert_drop'],
					(int) $settings['alert_below']
				);
				if ( null !== $alert ) {
					$queue['alerts'][] = $alert;
				}
			} elseif ( ! empty( $row['retry'] ) && ( (int) $item['attempts'] + 1 ) < self::MAX_ATTEMPTS ) {
				$item['attempts']   = (int) $item['attempts'] + 1;
				$item['not_before'] = time() + self::RETRY_GAP;
				$queue['items'][]   = $item;
			}
		}

		if ( ! empty( $queue['items'] ) ) {
			update_option( self::QUEUE_OPTION, $queue, false );
			self::schedule_next( self::next_delay( $queue['items'] ) );
			return;
		}

		// Run finished.
		delete_option( self::QUEUE_OPTION );
		update_option( self::LAST_OPTION, time(), false );
		if ( ! empty( $settings['alerts_enabled'] ) && ! empty( $queue['alerts'] ) ) {
			Alerts::send( $queue['alerts'], (string) $settings['alert_email'] );
		}
	}

	/**
	 * Seconds until the next item may run (at least the normal gap).
	 *
	 * @param array<int,array<string,mixed>> $items Queue items.
	 * @return int
	 */
	private static function next_delay( array $items ) {
		$soonest = PHP_INT_MAX;
		foreach ( $items as $item ) {
			$at      = empty( $item['not_before'] ) ? 0 : (int) $item['not_before'];
			$soonest = min( $soonest, $at );
		}
		return max( self::GAP, $soonest - time() );
	}

	/**
	 * Whether a scheduled run is in progress.
	 *
	 * @return int Remaining tests (0 = idle).
	 */
	public static function remaining() {
		$queue = get_option( self::QUEUE_OPTION );
		return ( is_array( $queue ) && ! empty( $queue['items'] ) && is_array( $queue['items'] ) ) ? count( $queue['items'] ) : 0;
	}

	/**
	 * Schedule the queue worker.
	 *
	 * @param int $delay Seconds from now.
	 * @return void
	 */
	private static function schedule_next( $delay ) {
		if ( wp_next_scheduled( self::QUEUE_HOOK ) ) {
			return;
		}
		wp_schedule_single_event( time() + max( 0, (int) $delay ), self::QUEUE_HOOK );
	}

	/**
	 * Atomic lock using add_option (fails if the row exists).
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return true;
		}
		$since = (int) get_option( self::LOCK_OPTION, 0 );
		if ( $since > 0 && ( time() - $since ) > self::LOCK_TTL ) {
			delete_option( self::LOCK_OPTION );
			return (bool) add_option( self::LOCK_OPTION, time(), '', false );
		}
		return false;
	}

	/**
	 * Deactivation: stop all scheduled work.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::RUN_HOOK );
		wp_clear_scheduled_hook( self::QUEUE_HOOK );
		delete_option( self::QUEUE_OPTION );
		delete_option( self::LOCK_OPTION );
	}
}
