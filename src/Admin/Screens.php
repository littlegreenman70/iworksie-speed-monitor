<?php
/**
 * Admin screens: Overview, History, Settings.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Admin;

use IWorksIE\SpeedMonitor\Plugin;
use IWorksIE\SpeedMonitor\Psi\Advice;
use IWorksIE\SpeedMonitor\Scheduler;
use IWorksIE\SpeedMonitor\Settings;
use IWorksIE\SpeedMonitor\Storage\Results;
use IWorksIE\SpeedMonitor\Support\Metrics;
use IWorksIE\SpeedMonitor\Support\Sparkline;
use IWorksIE\SpeedMonitor\Support\Urls;
use IWorksIE\SpeedMonitor\Vendor\IWorksIE\Core\Checks\Result;
use IWorksIE\SpeedMonitor\Vendor\IWorksIE\Core\Core;
use IWorksIE\SpeedMonitor\Vendor\IWorksIE\Core\UI;

/**
 * Renders the plugin page. All output is escaped.
 */
final class Screens {

	const PER_PAGE = 25;

	/**
	 * @var Core
	 */
	private $core;

	/**
	 * @param Core $core Core.
	 */
	public function __construct( Core $core ) {
		$this->core = $core;
	}

	/**
	 * Menu callback.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'iworksie-speed-monitor' ) );
		}

		$tabs = array(
			'overview' => __( 'Overview', 'iworksie-speed-monitor' ),
			'history'  => __( 'History', 'iworksie-speed-monitor' ),
			'settings' => __( 'Settings', 'iworksie-speed-monitor' ),
		);

		$this->core->page()->render(
			array(
				'title'   => (string) $this->core->config( 'name' ),
				'version' => (string) $this->core->config( 'version' ),
				'tagline' => (string) $this->core->config( 'tagline' ),
				'tabs'    => $tabs,
			),
			function ( $active ) {
				$notice = Actions::take_flash();
				if ( null !== $notice ) {
					echo UI::notice( $notice['type'], $notice['message'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::notice escapes.
				}
				switch ( $active ) {
					case 'history':
						$this->render_history();
						break;
					case 'settings':
						$this->render_settings();
						break;
					default:
						$this->render_overview();
						break;
				}
			}
		);
	}

	/**
	 * Device label.
	 *
	 * @param string $strategy mobile|desktop.
	 * @return string
	 */
	private static function device_label( $strategy ) {
		return 'desktop' === $strategy ? __( 'Desktop', 'iworksie-speed-monitor' ) : __( 'Mobile', 'iworksie-speed-monitor' );
	}

	/**
	 * "3 hours ago" from a UTC MySQL datetime.
	 *
	 * @param string $gmt_datetime Datetime.
	 * @return string
	 */
	private static function ago( $gmt_datetime ) {
		$ts = strtotime( $gmt_datetime . ' UTC' );
		if ( ! $ts ) {
			return '';
		}
		/* translators: %s: human time difference, e.g. "3 hours" */
		return sprintf( __( '%s ago', 'iworksie-speed-monitor' ), human_time_diff( $ts, time() ) );
	}

	/**
	 * Site-time display of a UTC MySQL datetime.
	 *
	 * @param string $gmt_datetime Datetime.
	 * @return string
	 */
	private static function local_time( $gmt_datetime ) {
		$ts = strtotime( $gmt_datetime . ' UTC' );
		if ( ! $ts ) {
			return '';
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}

	/**
	 * Badge in Google's wording, styled with core status colours.
	 *
	 * @param string $rating good|recommended|critical.
	 * @return string
	 */
	private static function rating_badge( $rating ) {
		if ( '' === $rating ) {
			return '';
		}
		return '<span class="iwt-badge iwt-badge--' . esc_attr( $rating ) . '">' . esc_html( Metrics::rating_label( $rating ) ) . '</span>';
	}

	/**
	 * Score circle.
	 *
	 * @param int|null $score Score.
	 * @param string   $size  normal|small.
	 * @return string
	 */
	private static function score_ring( $score, $size = 'normal' ) {
		if ( null === $score ) {
			return '<div class="iwiesm-score iwiesm-score--none iwiesm-score--' . esc_attr( $size ) . '"><span class="iwiesm-score__value">—</span></div>';
		}
		$band = Metrics::score_band( (int) $score );
		return '<div class="iwiesm-score iwiesm-score--' . esc_attr( $band ) . ' iwiesm-score--' . esc_attr( $size ) . '" style="--iwiesm-score:' . esc_attr( (string) (int) $score ) . '">'
			. '<span class="iwiesm-score__value">' . esc_html( (string) (int) $score ) . '</span>'
			. '<span class="screen-reader-text">' . esc_html__( 'out of 100', 'iworksie-speed-monitor' ) . '</span></div>';
	}

	/**
	 * Overview tab.
	 *
	 * @return void
	 */
	private function render_overview() {
		$settings   = Settings::get();
		$has_any    = Results::count() > 0;
		$has_key    = '' !== Settings::api_key();
		$tests      = count( (array) $settings['urls'] ) * count( (array) $settings['strategies'] );
		$disclosure = __( 'Tests run on Google\'s servers using PageSpeed Insights. Only the page address is sent to Google; nothing else leaves your site.', 'iworksie-speed-monitor' );

		if ( ! $has_key ) {
			echo '<div class="notice notice-info inline iwiesm-key-notice"><p>';
			echo esc_html__( 'Tip: add your own free Google API key for reliable testing. Without one, Google often limits requests from shared hosting.', 'iworksie-speed-monitor' ) . ' ';
			echo '<a href="' . esc_url( Plugin::page_url( 'settings' ) ) . '">' . esc_html__( 'Add API key', 'iworksie-speed-monitor' ) . '</a>';
			echo '</p></div>';
		}

		if ( ! $has_any ) {
			echo '<div class="iwt-empty">';
			echo '<div class="iwt-empty__icon" aria-hidden="true"><span class="dashicons dashicons-performance"></span></div>';
			echo '<h2 class="iwt-empty__title">' . esc_html__( 'Run your first speed test', 'iworksie-speed-monitor' ) . '</h2>';
			echo '<p class="iwt-empty__body">' . esc_html(
				sprintf(
					/* translators: %d: number of tests */
					_n( 'This runs %d test. Each one takes 15 to 60 seconds.', 'This runs %d tests. Each one takes 15 to 60 seconds.', $tests, 'iworksie-speed-monitor' ),
					$tests
				)
			) . '</p>';
			echo '<p class="iwt-empty__body">' . esc_html( $disclosure ) . '</p>';
			echo '<p class="iwt-empty__action"><button type="button" class="button button-primary button-hero" data-iwiesm-run>' . esc_html__( 'Test now', 'iworksie-speed-monitor' ) . '</button></p>';
			echo '</div>';
			echo UI::progress( 'iwiesm-progress' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::progress escapes.
			echo '<div class="iwiesm-errors" data-iwiesm-errors aria-live="polite"></div>';
			return;
		}

		echo '<div class="iwt-toolbar iwiesm-toolbar">';
		echo '<button type="button" class="button button-primary" data-iwiesm-run>' . esc_html__( 'Test now', 'iworksie-speed-monitor' ) . '</button>';
		echo '<p class="iwiesm-schedule">' . esc_html( $this->schedule_text( $settings ) ) . ' ';
		echo '<a href="' . esc_url( Plugin::page_url( 'settings' ) ) . '">' . esc_html__( 'Change', 'iworksie-speed-monitor' ) . '</a></p>';
		echo '</div>';
		echo UI::progress( 'iwiesm-progress' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::progress escapes.
		echo '<div class="iwiesm-errors" data-iwiesm-errors aria-live="polite"></div>';

		foreach ( (array) $settings['urls'] as $url ) {
			$this->render_page_section( (string) $url, (array) $settings['strategies'] );
		}

		echo '<p class="iwiesm-disclosure">' . esc_html( $disclosure ) . '</p>';
	}

	/**
	 * Schedule status line.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function schedule_text( array $settings ) {
		if ( empty( $settings['enabled'] ) ) {
			return __( 'Scheduled tests are off.', 'iworksie-speed-monitor' );
		}
		$remaining = Scheduler::remaining();
		if ( $remaining > 0 ) {
			return sprintf(
				/* translators: %d: number of tests left */
				_n( 'Scheduled run in progress: %d test left.', 'Scheduled run in progress: %d tests left.', $remaining, 'iworksie-speed-monitor' ),
				$remaining
			);
		}
		$freq = 'daily' === $settings['frequency'] ? __( 'Tests run daily.', 'iworksie-speed-monitor' ) : __( 'Tests run weekly.', 'iworksie-speed-monitor' );
		$next = Scheduler::next_run();
		if ( $next > 0 ) {
			$freq .= ' ' . sprintf(
				/* translators: %s: date and time of next run */
				__( 'Next run: %s.', 'iworksie-speed-monitor' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next )
			);
		}
		return $freq;
	}

	/**
	 * One monitored page.
	 *
	 * @param string            $url        URL.
	 * @param array<int,string> $strategies Devices.
	 * @return void
	 */
	private function render_page_section( $url, array $strategies ) {
		$latest = array();
		foreach ( $strategies as $strategy ) {
			$latest[ $strategy ] = Results::latest( $url, $strategy );
		}

		echo '<section class="iwiesm-page">';
		echo '<header class="iwiesm-page__header">';
		echo '<h2 class="iwiesm-page__title">' . esc_html( Urls::label( $url ) ) . '</h2>';
		echo '<a class="iwiesm-page__url" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'iworksie-speed-monitor' ) . '</span></a>';
		echo '</header>';

		echo '<div class="iwiesm-devices">';
		foreach ( $strategies as $strategy ) {
			$this->render_device( $url, $strategy, $latest[ $strategy ] );
		}
		echo '</div>';

		// Top fixes from the mobile run when available (Google ranks sites on mobile).
		$source = null;
		foreach ( array( 'mobile', 'desktop' ) as $strategy ) {
			if ( ! empty( $latest[ $strategy ]['opportunities'] ) ) {
				$source = $latest[ $strategy ];
				break;
			}
		}
		$code_fix = false;
		if ( null !== $source ) {
			echo '<div class="iwiesm-fixes">';
			echo '<h3 class="iwiesm-fixes__title">' . esc_html(
				sprintf(
					/* translators: %s: device name */
					__( 'Top fixes (from the %s test)', 'iworksie-speed-monitor' ),
					strtolower( self::device_label( (string) $source['strategy'] ) )
				)
			) . '</h3>';
			foreach ( (array) $source['opportunities'] as $opportunity ) {
				if ( ! is_array( $opportunity ) ) {
					continue;
				}
				$advice = Advice::for_opportunity( $opportunity );
				if ( 'code' === $advice['who'] ) {
					$code_fix = true;
				}
				$result = Result::recommended(
					$advice['title'],
					$this->savings_text( $opportunity, $advice['who'] ),
					array(
						'why' => $advice['why'],
						'fix' => $advice['fix'],
					)
				);
				echo UI::result_card( $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::result_card escapes.
			}
			if ( $code_fix ) {
				$cta = 'https://www.iworks.ie/?utm_source=wp-plugin&utm_medium=iworksie-speed-monitor&utm_campaign=fixes';
				echo '<p class="iwiesm-cta">' . esc_html__( 'Some of these usually need changes to the theme or plugins.', 'iworksie-speed-monitor' ) . ' ';
				echo '<a href="' . esc_url( $cta ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'iWorks can fix them for you →', 'iworksie-speed-monitor' ) . '</a></p>';
			}
			echo '</div>';
		}
		echo '</section>';
	}

	/**
	 * Description line for a fix: estimated savings and who usually fixes it.
	 *
	 * @param array<string,mixed> $opportunity Opportunity.
	 * @param string              $who         code|hosting|content.
	 * @return string
	 */
	private function savings_text( array $opportunity, $who ) {
		$parts = array();
		$ms    = isset( $opportunity['savings_ms'] ) ? (int) $opportunity['savings_ms'] : 0;
		$bytes = isset( $opportunity['savings_bytes'] ) ? (int) $opportunity['savings_bytes'] : 0;
		if ( $ms >= 50 ) {
			/* translators: %s: time, e.g. "1.2 s" */
			$parts[] = sprintf( __( 'Google estimates fixing this could save about %s.', 'iworksie-speed-monitor' ), Metrics::format( 'lcp', (float) $ms ) );
		} elseif ( $bytes >= 10240 ) {
			/* translators: %s: size, e.g. "340 KB" */
			$parts[] = sprintf( __( 'Google estimates fixing this could save about %s of downloads.', 'iworksie-speed-monitor' ), size_format( $bytes ) );
		} elseif ( ! empty( $opportunity['display'] ) ) {
			$parts[] = (string) $opportunity['display'];
		}
		switch ( $who ) {
			case 'hosting':
				$parts[] = __( 'Usually fixed in caching or hosting settings.', 'iworksie-speed-monitor' );
				break;
			case 'content':
				$parts[] = __( 'Usually fixed in your content or media library.', 'iworksie-speed-monitor' );
				break;
			default:
				$parts[] = __( 'Usually fixed in the theme or plugins.', 'iworksie-speed-monitor' );
				break;
		}
		return implode( ' ', $parts );
	}

	/**
	 * One device column.
	 *
	 * @param string                   $url      URL.
	 * @param string                   $strategy Device.
	 * @param array<string,mixed>|null $row      Latest successful row.
	 * @return void
	 */
	private function render_device( $url, $strategy, $row ) {
		$last = Results::last_attempt( $url, $strategy );

		echo '<div class="iwiesm-device">';
		echo '<h3 class="iwiesm-device__title"><span class="dashicons ' . esc_attr( 'desktop' === $strategy ? 'dashicons-desktop' : 'dashicons-smartphone' ) . '" aria-hidden="true"></span> ' . esc_html( self::device_label( $strategy ) ) . '</h3>';

		if ( null !== $last && 'error' === $last['status'] && ( null === $row || (int) $last['id'] > (int) $row['id'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html(
				sprintf(
					/* translators: 1: time ago, 2: error message */
					__( 'Last test failed (%1$s): %2$s', 'iworksie-speed-monitor' ),
					self::ago( (string) $last['tested_at'] ),
					(string) $last['error_message']
				)
			) . '</p></div>';
		}

		if ( null === $row ) {
			echo '<p class="iwiesm-muted">' . esc_html__( 'Not tested yet.', 'iworksie-speed-monitor' ) . '</p>';
			echo '</div>';
			return;
		}

		$series = Results::series( $url, $strategy, 30 );
		$scores = array();
		foreach ( $series as $point ) {
			$scores[] = (int) $point['score'];
		}
		$previous = Results::latest( $url, $strategy, (int) $row['id'] );

		echo '<div class="iwiesm-device__top">';
		echo self::score_ring( $row['score'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in score_ring().
		echo '<div class="iwiesm-device__meta">';
		echo '<p class="iwiesm-device__label">' . esc_html__( 'Performance score (lab)', 'iworksie-speed-monitor' ) . '</p>';
		if ( null !== $previous && null !== $previous['score'] && null !== $row['score'] ) {
			$diff = (int) $row['score'] - (int) $previous['score'];
			if ( 0 === $diff ) {
				$change = __( 'No change since the previous test', 'iworksie-speed-monitor' );
				$class  = 'same';
			} elseif ( $diff > 0 ) {
				/* translators: %d: points */
				$change = sprintf( __( 'Up %d since the previous test', 'iworksie-speed-monitor' ), $diff );
				$class  = 'up';
			} else {
				/* translators: %d: points */
				$change = sprintf( __( 'Down %d since the previous test', 'iworksie-speed-monitor' ), abs( $diff ) );
				$class  = 'down';
			}
			echo '<p class="iwiesm-change iwiesm-change--' . esc_attr( $class ) . '">' . esc_html( $change ) . '</p>';
		}
		echo '<p class="iwiesm-muted" title="' . esc_attr( self::local_time( (string) $row['tested_at'] ) ) . '">' . esc_html(
			sprintf(
				/* translators: %s: time ago */
				__( 'Tested %s', 'iworksie-speed-monitor' ),
				self::ago( (string) $row['tested_at'] )
			)
		) . '</p>';
		$spark = Sparkline::svg(
			$scores,
			sprintf(
				/* translators: 1: number of tests, 2: lowest score, 3: highest score, 4: latest score */
				__( 'Score trend over the last %1$d tests: lowest %2$d, highest %3$d, latest %4$d.', 'iworksie-speed-monitor' ),
				count( $scores ),
				empty( $scores ) ? 0 : min( $scores ),
				empty( $scores ) ? 0 : max( $scores ),
				empty( $scores ) ? 0 : (int) end( $scores )
			)
		);
		if ( '' !== $spark ) {
			echo $spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sparkline escapes every attribute.
		}
		echo '</div></div>';

		// Real visitors.
		$who = 'desktop' === $strategy ? __( 'desktop visitors', 'iworksie-speed-monitor' ) : __( 'phone visitors', 'iworksie-speed-monitor' );
		echo '<div class="iwiesm-block iwiesm-block--field">';
		echo '<h4 class="iwiesm-block__title">' . esc_html__( 'Real visitors', 'iworksie-speed-monitor' ) . ' <span class="iwiesm-tag iwiesm-tag--field">' . esc_html__( 'Field data', 'iworksie-speed-monitor' ) . '</span></h4>';
		if ( '' === (string) $row['field_scope'] ) {
			echo '<p class="iwiesm-muted">' . esc_html(
				sprintf(
					/* translators: %s: "phone visitors" or "desktop visitors" */
					__( 'Not enough real-visitor data yet. Google only reports this when enough Chrome users (%s) visit the site; until then, use the lab test below.', 'iworksie-speed-monitor' ),
					$who
				)
			) . '</p>';
		} else {
			$scope = 'url' === $row['field_scope']
				/* translators: %s: "phone visitors" or "desktop visitors" */
				? sprintf( __( 'This page, %s, last 28 days (Chrome UX Report, 75th percentile).', 'iworksie-speed-monitor' ), $who )
				/* translators: %s: "phone visitors" or "desktop visitors" */
				: sprintf( __( 'Whole site (not enough data for this page alone), %s, last 28 days (Chrome UX Report, 75th percentile).', 'iworksie-speed-monitor' ), $who );
			echo '<p class="iwiesm-muted iwiesm-block__scope">' . esc_html( $scope ) . '</p>';
			$this->metric_list(
				array(
					'lcp'  => $row['field_lcp'],
					'inp'  => $row['field_inp'],
					'cls'  => $row['field_cls'],
					'ttfb' => $row['field_ttfb'],
				)
			);
		}
		echo '</div>';

		// Lab.
		echo '<div class="iwiesm-block iwiesm-block--lab">';
		echo '<h4 class="iwiesm-block__title">' . esc_html__( 'Lab test', 'iworksie-speed-monitor' ) . ' <span class="iwiesm-tag iwiesm-tag--lab">' . esc_html__( 'Simulated', 'iworksie-speed-monitor' ) . '</span></h4>';
		echo '<p class="iwiesm-muted iwiesm-block__scope">' . esc_html(
			'desktop' === $strategy
				? __( 'One simulated visit on a desktop connection, from this test. Useful for spotting changes; can vary a few points between runs.', 'iworksie-speed-monitor' )
				: __( 'One simulated visit on a mid-range phone with a slow mobile connection, from this test. Useful for spotting changes; can vary a few points between runs.', 'iworksie-speed-monitor' )
		) . '</p>';
		$this->metric_list(
			array(
				'lcp' => $row['lab_lcp'],
				'tbt' => $row['lab_tbt'],
				'cls' => $row['lab_cls'],
				'fcp' => $row['lab_fcp'],
				'si'  => $row['lab_si'],
			)
		);
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Metric definition list with ratings.
	 *
	 * @param array<string,float|null> $values Metric => value.
	 * @return void
	 */
	private function metric_list( array $values ) {
		$names = Metrics::names();
		$help  = Metrics::explanations();
		echo '<dl class="iwiesm-metrics">';
		foreach ( $values as $metric => $value ) {
			if ( null === $value ) {
				continue;
			}
			echo '<div class="iwiesm-metric">';
			echo '<dt title="' . esc_attr( $help[ $metric ] ) . '">' . esc_html( $names[ $metric ] ) . '</dt>';
			echo '<dd><span class="iwiesm-metric__value">' . esc_html( Metrics::format( $metric, $value ) ) . '</span> ';
			echo self::rating_badge( Metrics::rate( $metric, $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in rating_badge().
			echo '</dd></div>';
		}
		echo '</dl>';
	}

	/**
	 * History tab.
	 *
	 * @return void
	 */
	private function render_history() {
		$settings = Settings::get();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$filter_url = isset( $_GET['iwiesm_url'] ) ? esc_url_raw( wp_unslash( (string) $_GET['iwiesm_url'] ) ) : '';
		$filter_dev = isset( $_GET['iwiesm_device'] ) ? sanitize_key( wp_unslash( (string) $_GET['iwiesm_device'] ) ) : '';
		$paged      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		if ( ! in_array( $filter_dev, array( 'mobile', 'desktop' ), true ) ) {
			$filter_dev = '';
		}

		$data  = Results::history( $filter_url, $filter_dev, self::PER_PAGE, $paged );
		$total = Results::count();

		echo '<form method="get" class="iwiesm-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( Plugin::SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="history" />';
		echo '<label for="iwiesm-filter-url">' . esc_html__( 'Page', 'iworksie-speed-monitor' ) . '</label> ';
		echo '<select id="iwiesm-filter-url" name="iwiesm_url"><option value="">' . esc_html__( 'All pages', 'iworksie-speed-monitor' ) . '</option>';
		foreach ( (array) $settings['urls'] as $url ) {
			echo '<option value="' . esc_attr( (string) $url ) . '"' . selected( $filter_url, $url, false ) . '>' . esc_html( Urls::label( (string) $url ) ) . '</option>';
		}
		echo '</select> ';
		echo '<label for="iwiesm-filter-device">' . esc_html__( 'Device', 'iworksie-speed-monitor' ) . '</label> ';
		echo '<select id="iwiesm-filter-device" name="iwiesm_device">';
		echo '<option value="">' . esc_html__( 'All devices', 'iworksie-speed-monitor' ) . '</option>';
		echo '<option value="mobile"' . selected( $filter_dev, 'mobile', false ) . '>' . esc_html__( 'Mobile', 'iworksie-speed-monitor' ) . '</option>';
		echo '<option value="desktop"' . selected( $filter_dev, 'desktop', false ) . '>' . esc_html__( 'Desktop', 'iworksie-speed-monitor' ) . '</option>';
		echo '</select> ';
		submit_button( __( 'Filter', 'iworksie-speed-monitor' ), 'secondary', '', false );
		echo '</form>';

		echo '<div class="iwiesm-table-wrap"><table class="widefat striped iwiesm-history">';
		echo '<thead><tr>';
		$columns = array(
			__( 'Tested', 'iworksie-speed-monitor' ),
			__( 'Page', 'iworksie-speed-monitor' ),
			__( 'Device', 'iworksie-speed-monitor' ),
			__( 'Score', 'iworksie-speed-monitor' ),
			__( 'LCP (lab)', 'iworksie-speed-monitor' ),
			__( 'TBT (lab)', 'iworksie-speed-monitor' ),
			__( 'CLS (lab)', 'iworksie-speed-monitor' ),
			__( 'LCP (real visitors)', 'iworksie-speed-monitor' ),
			__( 'INP (real visitors)', 'iworksie-speed-monitor' ),
			__( 'CLS (real visitors)', 'iworksie-speed-monitor' ),
			__( 'Run by', 'iworksie-speed-monitor' ),
		);
		foreach ( $columns as $column ) {
			echo '<th scope="col">' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $data['rows'] ) ) {
			echo '<tr><td colspan="' . esc_attr( (string) count( $columns ) ) . '">' . esc_html__( 'No tests yet.', 'iworksie-speed-monitor' ) . '</td></tr>';
		}
		foreach ( $data['rows'] as $row ) {
			echo '<tr>';
			echo '<td title="' . esc_attr( (string) $row['tested_at'] ) . ' UTC">' . esc_html( self::local_time( (string) $row['tested_at'] ) ) . '</td>';
			echo '<td><span title="' . esc_attr( (string) $row['url'] ) . '">' . esc_html( Urls::label( (string) $row['url'] ) ) . '</span></td>';
			echo '<td>' . esc_html( self::device_label( (string) $row['strategy'] ) ) . '</td>';
			if ( 'ok' !== $row['status'] ) {
				echo '<td colspan="7" class="iwiesm-history__error"><span class="iwt-badge iwt-badge--critical">' . esc_html__( 'Failed', 'iworksie-speed-monitor' ) . '</span> ' . esc_html( (string) $row['error_message'] ) . '</td>';
			} else {
				$band = Metrics::score_band( $row['score'] );
				echo '<td><span class="iwiesm-score-pill iwiesm-score-pill--' . esc_attr( $band ) . '">' . esc_html( null === $row['score'] ? '—' : (string) $row['score'] ) . '</span></td>';
				foreach ( array(
					'lab_lcp'   => 'lcp',
					'lab_tbt'   => 'tbt',
					'lab_cls'   => 'cls',
					'field_lcp' => 'lcp',
					'field_inp' => 'inp',
					'field_cls' => 'cls',
				) as $column => $metric ) {
					$value  = $row[ $column ];
					$rating = Metrics::rate( $metric, $value );
					echo '<td class="iwiesm-cell' . ( '' !== $rating ? ' iwiesm-cell--' . esc_attr( $rating ) : '' ) . '">' . esc_html( Metrics::format( $metric, $value ) ) . '</td>';
				}
			}
			echo '<td>' . esc_html( 'scheduled' === $row['source'] ? __( 'Schedule', 'iworksie-speed-monitor' ) : __( 'Manual', 'iworksie-speed-monitor' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		$pages = (int) ceil( $data['total'] / self::PER_PAGE );
		if ( $pages > 1 ) {
			$links = paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'prev_text' => __( '‹ Newer', 'iworksie-speed-monitor' ),
					'next_text' => __( 'Older ›', 'iworksie-speed-monitor' ),
				)
			);
			if ( is_string( $links ) ) {
				echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
			}
		}

		echo '<div class="iwiesm-history-actions">';
		if ( $total > 0 ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="iwiesm_export_csv" />';
			wp_nonce_field( 'iwiesm_export_csv' );
			submit_button( __( 'Download CSV', 'iworksie-speed-monitor' ), 'secondary', 'submit', false );
			echo '</form>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-iwiesm-confirm>';
			echo '<input type="hidden" name="action" value="iwiesm_clear_history" />';
			wp_nonce_field( 'iwiesm_clear_history' );
			submit_button( __( 'Delete all history', 'iworksie-speed-monitor' ), 'delete', 'submit', false );
			echo '</form>';
		}
		echo '<p class="iwiesm-muted">' . esc_html(
			sprintf(
				/* translators: 1: number of stored tests, 2: number of days kept */
				_n( '%1$d test stored. Tests older than %2$d days are removed automatically.', '%1$d tests stored. Tests older than %2$d days are removed automatically.', $total, 'iworksie-speed-monitor' ),
				$total,
				(int) $settings['retention_days']
			)
		) . '</p>';
		echo '</div>';
	}

	/**
	 * Settings tab.
	 *
	 * @return void
	 */
	private function render_settings() {
		$s          = Settings::get();
		$key        = Settings::api_key();
		$from_const = Settings::api_key_from_constant();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="iwiesm-settings">';
		echo '<input type="hidden" name="action" value="iwiesm_save_settings" />';
		wp_nonce_field( 'iwiesm_save_settings' );

		echo '<h2>' . esc_html__( 'Pages to monitor', 'iworksie-speed-monitor' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="iwiesm-urls">' . esc_html__( 'Pages', 'iworksie-speed-monitor' ) . '</label></th><td>';
		echo '<textarea id="iwiesm-urls" name="iwiesm[urls]" rows="5" class="large-text code" aria-describedby="iwiesm-urls-help">' . esc_textarea( implode( "\n", (array) $s['urls'] ) ) . '</textarea>';
		echo '<p class="description" id="iwiesm-urls-help">' . esc_html(
			sprintf(
				/* translators: %d: max number of pages */
				__( 'One address per line, up to %d. Only pages on this site. You can also write a path such as /contact/. Pick your most important pages: home, a popular post, a product or contact page.', 'iworksie-speed-monitor' ),
				Settings::MAX_URLS
			)
		) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Devices', 'iworksie-speed-monitor' ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html__( 'Devices', 'iworksie-speed-monitor' ) . '</legend>';
		foreach ( array( 'mobile', 'desktop' ) as $strategy ) {
			echo '<label><input type="checkbox" name="iwiesm[strategies][]" value="' . esc_attr( $strategy ) . '"' . checked( in_array( $strategy, (array) $s['strategies'], true ), true, false ) . ' /> ' . esc_html( self::device_label( $strategy ) ) . '</label><br />';
		}
		echo '<p class="description">' . esc_html__( 'Google ranks pages on their mobile speed, so keep Mobile on.', 'iworksie-speed-monitor' ) . '</p>';
		echo '</fieldset></td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Schedule', 'iworksie-speed-monitor' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Scheduled tests', 'iworksie-speed-monitor' ) . '</th><td>';
		echo '<label><input type="checkbox" name="iwiesm[enabled]" value="1"' . checked( ! empty( $s['enabled'] ), true, false ) . ' /> ' . esc_html__( 'Test my pages automatically', 'iworksie-speed-monitor' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Runs one test at a time in the background (one page every 30 seconds or so), starting around 3am site time. Uses WordPress cron, which needs some site traffic or a real server cron job to run on time.', 'iworksie-speed-monitor' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="iwiesm-frequency">' . esc_html__( 'How often', 'iworksie-speed-monitor' ) . '</label></th><td>';
		echo '<select id="iwiesm-frequency" name="iwiesm[frequency]">';
		echo '<option value="daily"' . selected( $s['frequency'], 'daily', false ) . '>' . esc_html__( 'Daily', 'iworksie-speed-monitor' ) . '</option>';
		echo '<option value="weekly"' . selected( $s['frequency'], 'weekly', false ) . '>' . esc_html__( 'Weekly', 'iworksie-speed-monitor' ) . '</option>';
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="iwiesm-retention">' . esc_html__( 'Keep history for', 'iworksie-speed-monitor' ) . '</label></th><td>';
		echo '<input type="number" id="iwiesm-retention" name="iwiesm[retention_days]" min="30" max="1095" step="1" class="small-text" value="' . esc_attr( (string) $s['retention_days'] ) . '" /> ' . esc_html__( 'days', 'iworksie-speed-monitor' );
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Alerts', 'iworksie-speed-monitor' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Email alerts', 'iworksie-speed-monitor' ) . '</th><td>';
		echo '<label><input type="checkbox" name="iwiesm[alerts_enabled]" value="1"' . checked( ! empty( $s['alerts_enabled'] ), true, false ) . ' /> ' . esc_html__( 'Email me when a scheduled test shows a speed drop', 'iworksie-speed-monitor' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'One email per run at most, listing every page that dropped.', 'iworksie-speed-monitor' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="iwiesm-email">' . esc_html__( 'Send alerts to', 'iworksie-speed-monitor' ) . '</label></th><td>';
		echo '<input type="email" id="iwiesm-email" name="iwiesm[alert_email]" class="regular-text" value="' . esc_attr( (string) $s['alert_email'] ) . '" />';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="iwiesm-drop">' . esc_html__( 'Alert when the score drops by', 'iworksie-speed-monitor' ) . '</label></th><td>';
		echo '<input type="number" id="iwiesm-drop" name="iwiesm[alert_drop]" min="1" max="100" class="small-text" value="' . esc_attr( (string) $s['alert_drop'] ) . '" /> ' . esc_html__( 'points or more since the previous test', 'iworksie-speed-monitor' );
		echo '<p class="description">' . esc_html__( 'Lab scores naturally move a few points between runs, so 10 is a sensible minimum.', 'iworksie-speed-monitor' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="iwiesm-below">' . esc_html__( 'Or falls below', 'iworksie-speed-monitor' ) . '</label></th><td>';
		echo '<input type="number" id="iwiesm-below" name="iwiesm[alert_below]" min="0" max="100" class="small-text" value="' . esc_attr( (string) $s['alert_below'] ) . '" />';
		echo '<p class="description">' . esc_html__( 'You are told once when a page crosses below this score, not on every run. Set 0 to turn this off.', 'iworksie-speed-monitor' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Google API key', 'iworksie-speed-monitor' ) . '</h2>';
		echo '<p>' . esc_html__( 'Optional but recommended. Tests work without a key, but Google often limits keyless requests, especially from shared hosting. A key is free and allows thousands of tests a day.', 'iworksie-speed-monitor' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="iwiesm-key">' . esc_html__( 'API key', 'iworksie-speed-monitor' ) . '</label></th><td>';
		if ( $from_const ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %s: masked key */
					__( 'Set in wp-config.php (IWIESM_PSI_API_KEY): %s', 'iworksie-speed-monitor' ),
					Settings::mask_key( $key )
				)
			) . '</p>';
		} else {
			echo '<input type="password" id="iwiesm-key" name="iwiesm[api_key]" class="regular-text code" autocomplete="off" spellcheck="false" value="" placeholder="' . esc_attr( '' !== $key ? Settings::mask_key( $key ) : '' ) . '" aria-describedby="iwiesm-key-help" />';
			if ( '' !== $key ) {
				echo '<p><label><input type="checkbox" name="iwiesm[remove_api_key]" value="1" /> ' . esc_html__( 'Remove the saved key', 'iworksie-speed-monitor' ) . '</label></p>';
			}
			echo '<p class="description" id="iwiesm-key-help">';
			echo esc_html( '' !== $key ? __( 'A key is saved. Leave blank to keep it.', 'iworksie-speed-monitor' ) . ' ' : '' );
			echo esc_html__( 'Get one in two minutes:', 'iworksie-speed-monitor' ) . ' ';
			echo '<a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google\'s instructions', 'iworksie-speed-monitor' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'iworksie-speed-monitor' ) . '</span></a>. ';
			echo esc_html__( 'You can also define IWIESM_PSI_API_KEY in wp-config.php.', 'iworksie-speed-monitor' );
			echo '</p>';
		}
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<p class="iwiesm-disclosure">' . esc_html__( 'Privacy: tests call Google PageSpeed Insights directly from your server. Only the page address (and your API key, if set) is sent to Google. No data goes to iWorks or anyone else.', 'iworksie-speed-monitor' ) . '</p>';

		submit_button( __( 'Save settings', 'iworksie-speed-monitor' ) );
		echo '</form>';
	}
}
