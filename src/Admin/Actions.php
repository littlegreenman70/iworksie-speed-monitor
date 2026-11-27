<?php
/**
 * Form handlers and AJAX endpoints.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Admin;

use IWorksIE\SpeedMonitor\Plugin;
use IWorksIE\SpeedMonitor\Runner;
use IWorksIE\SpeedMonitor\Scheduler;
use IWorksIE\SpeedMonitor\Settings;
use IWorksIE\SpeedMonitor\Storage\Results;
use IWorksIE\SpeedMonitor\Support\Csv;
use IWorksIE\SpeedMonitor\Support\Metrics;

/**
 * Every action checks a nonce and the capability.
 */
final class Actions {

	const NOTICE_TRANSIENT = 'iwiesm_notice_';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_post_iwiesm_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_iwiesm_export_csv', array( $this, 'export_csv' ) );
		add_action( 'admin_post_iwiesm_clear_history', array( $this, 'clear_history' ) );
		add_action( 'wp_ajax_iwiesm_run_test', array( $this, 'run_test' ) );
	}

	/**
	 * Stop unless the current user may use the plugin.
	 *
	 * @return void
	 */
	private function require_cap() {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'iworksie-speed-monitor' ), 403 );
		}
	}

	/**
	 * Save settings form.
	 *
	 * @return void
	 */
	public function save_settings() {
		$this->require_cap();
		check_admin_referer( 'iwiesm_save_settings' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised field by field in Settings::sanitize().
		$input = isset( $_POST['iwiesm'] ) && is_array( $_POST['iwiesm'] ) ? wp_unslash( $_POST['iwiesm'] ) : array();
		$clean = Settings::sanitize( (array) $input );
		Settings::save( $clean['settings'] );

		$messages = array();
		$type     = 'success';

		if ( ! Settings::api_key_from_constant() ) {
			if ( ! empty( $input['remove_api_key'] ) ) {
				Settings::save_api_key( '' );
			} elseif ( isset( $input['api_key'] ) && '' !== trim( (string) $input['api_key'] ) ) {
				$key = Settings::sanitize_api_key( (string) $input['api_key'] );
				if ( '' === $key ) {
					$messages[] = __( 'That API key does not look right, so it was not saved. Google API keys are about 39 letters, numbers, dashes and underscores.', 'iworksie-speed-monitor' );
					$type       = 'warning';
				} else {
					Settings::save_api_key( $key );
				}
			}
		}

		Scheduler::sync();

		array_unshift( $messages, __( 'Settings saved.', 'iworksie-speed-monitor' ) );
		if ( ! empty( $clean['rejected'] ) ) {
			$type       = 'warning';
			$messages[] = sprintf(
				/* translators: 1: max number of pages, 2: list of rejected addresses */
				__( 'Some addresses were not added. Only pages on this site can be monitored (up to %1$d): %2$s', 'iworksie-speed-monitor' ),
				Settings::MAX_URLS,
				implode( ', ', array_slice( $clean['rejected'], 0, 5 ) )
			);
		}
		self::flash( $type, implode( ' ', $messages ) );

		wp_safe_redirect( Plugin::page_url( 'settings' ) );
		exit;
	}

	/**
	 * Download history as CSV.
	 *
	 * @return void
	 */
	public function export_csv() {
		$this->require_cap();
		check_admin_referer( 'iwiesm_export_csv' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="speed-monitor-' . gmdate( 'Y-m-d' ) . '.csv"' );

		echo Csv::line( Csv::headers() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, not HTML; cells are neutralised against formula injection.
		$after = 0;
		do {
			$rows    = Results::batch( $after, 500 );
			$fetched = count( $rows );
			foreach ( $rows as $row ) {
				echo Csv::line( Csv::row( $row ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, see above.
				$after = (int) $row['id'];
			}
		} while ( 500 === $fetched );
		exit;
	}

	/**
	 * Delete all history.
	 *
	 * @return void
	 */
	public function clear_history() {
		$this->require_cap();
		check_admin_referer( 'iwiesm_clear_history' );
		Results::delete_all();
		self::flash( 'success', __( 'Test history deleted.', 'iworksie-speed-monitor' ) );
		wp_safe_redirect( Plugin::page_url( 'history' ) );
		exit;
	}

	/**
	 * Run one manual test (one URL + one device per request, so it cannot time out a whole batch).
	 *
	 * @return void
	 */
	public function run_test() {
		check_ajax_referer( 'iwiesm_test', 'nonce' );
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'iworksie-speed-monitor' ) ), 403 );
		}

		$url      = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['url'] ) ) : '';
		$strategy = isset( $_POST['strategy'] ) ? sanitize_key( wp_unslash( (string) $_POST['strategy'] ) ) : 'mobile';

		// Only pages saved in settings can be tested, so this endpoint cannot be used to test arbitrary sites.
		if ( ! in_array( $url, (array) Settings::value( 'urls' ), true ) || ! in_array( $strategy, array( 'mobile', 'desktop' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'That page is not in your monitored list.', 'iworksie-speed-monitor' ) ), 400 );
		}

		$runner = new Runner();
		$row    = $runner->run( $url, $strategy, 'manual' );

		if ( 'ok' !== $row['status'] ) {
			wp_send_json_error( array( 'message' => (string) $row['error_message'] ), 200 );
		}
		wp_send_json_success(
			array(
				'score' => (int) $row['score'],
				'band'  => Metrics::score_band( (int) $row['score'] ),
			)
		);
	}

	/**
	 * Store a one-off notice for the current user.
	 *
	 * @param string $type    success|warning|error|info.
	 * @param string $message Message.
	 * @return void
	 */
	public static function flash( $type, $message ) {
		set_transient(
			self::NOTICE_TRANSIENT . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	/**
	 * Read and clear the notice for the current user.
	 *
	 * @return array{type: string, message: string}|null
	 */
	public static function take_flash() {
		$key    = self::NOTICE_TRANSIENT . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return null;
		}
		delete_transient( $key );
		return array(
			'type'    => isset( $notice['type'] ) ? (string) $notice['type'] : 'info',
			'message' => (string) $notice['message'],
		);
	}
}
