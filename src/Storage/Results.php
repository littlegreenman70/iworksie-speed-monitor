<?php
/**
 * Test results repository (custom table).
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor\Storage;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; results change on every test so caching would only serve stale data.

/**
 * Stores one small row per test. The full Lighthouse report is never stored.
 */
final class Results {

	/**
	 * Numeric columns and their cast when read back.
	 *
	 * @var array<string,string>
	 */
	private static $numeric = array(
		'score'      => 'int',
		'lab_lcp'    => 'float',
		'lab_cls'    => 'float',
		'lab_tbt'    => 'float',
		'lab_fcp'    => 'float',
		'lab_si'     => 'float',
		'field_lcp'  => 'float',
		'field_inp'  => 'float',
		'field_cls'  => 'float',
		'field_fcp'  => 'float',
		'field_ttfb' => 'float',
	);

	/**
	 * Table name for the current site.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'iwiesp_results';
	}

	/**
	 * Insert a row.
	 *
	 * @param array<string,mixed> $row Row (url, strategy, source, status, metrics, opportunities array).
	 * @return int Insert ID (0 on failure).
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$url     = (string) $row['url'];
		$data    = array(
			'url_hash'           => md5( $url ),
			'url'                => $url,
			'strategy'           => 'desktop' === $row['strategy'] ? 'desktop' : 'mobile',
			'source'             => 'scheduled' === ( isset( $row['source'] ) ? $row['source'] : '' ) ? 'scheduled' : 'manual',
			'status'             => 'error' === ( isset( $row['status'] ) ? $row['status'] : '' ) ? 'error' : 'ok',
			'tested_at'          => isset( $row['tested_at'] ) ? (string) $row['tested_at'] : gmdate( 'Y-m-d H:i:s' ),
			'field_category'     => isset( $row['field_category'] ) ? (string) $row['field_category'] : '',
			'field_scope'        => isset( $row['field_scope'] ) ? (string) $row['field_scope'] : '',
			'opportunities'      => wp_json_encode( isset( $row['opportunities'] ) && is_array( $row['opportunities'] ) ? $row['opportunities'] : array() ),
			'error_message'      => isset( $row['error_message'] ) ? substr( (string) $row['error_message'], 0, 250 ) : '',
			'lighthouse_version' => isset( $row['lighthouse_version'] ) ? substr( (string) $row['lighthouse_version'], 0, 20 ) : '',
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		foreach ( array_keys( self::$numeric ) as $column ) {
			if ( ! isset( $row[ $column ] ) || ! is_numeric( $row[ $column ] ) ) {
				continue;
			}
			if ( 'lab_cls' === $column || 'field_cls' === $column ) {
				$data[ $column ] = round( max( 0.0, (float) $row[ $column ] ), 4 );
				$formats[]       = '%f';
			} else {
				// Milliseconds and scores are whole numbers.
				$data[ $column ] = max( 0, (int) round( (float) $row[ $column ] ) );
				$formats[]       = '%d';
			}
		}

		$ok = $wpdb->insert( self::table(), $data, $formats );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Latest successful result for a URL and device.
	 *
	 * @param string $url       URL.
	 * @param string $strategy  mobile|desktop.
	 * @param int    $before_id Only rows older than this ID (0 = any).
	 * @return array<string,mixed>|null
	 */
	public static function latest( $url, $strategy, $before_id = 0 ) {
		global $wpdb;
		if ( $before_id > 0 ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE url_hash = %s AND strategy = %s AND status = 'ok' AND id < %d ORDER BY id DESC LIMIT 1",
					self::table(),
					md5( (string) $url ),
					(string) $strategy,
					(int) $before_id
				),
				ARRAY_A
			);
		} else {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE url_hash = %s AND strategy = %s AND status = 'ok' ORDER BY id DESC LIMIT 1",
					self::table(),
					md5( (string) $url ),
					(string) $strategy
				),
				ARRAY_A
			);
		}
		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/**
	 * Most recent row of any status (to show the last error).
	 *
	 * @param string $url      URL.
	 * @param string $strategy Device.
	 * @return array<string,mixed>|null
	 */
	public static function last_attempt( $url, $strategy ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE url_hash = %s AND strategy = %s ORDER BY id DESC LIMIT 1',
				self::table(),
				md5( (string) $url ),
				(string) $strategy
			),
			ARRAY_A
		);
		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/**
	 * Scores over time, oldest first (for sparklines).
	 *
	 * @param string $url      URL.
	 * @param string $strategy Device.
	 * @param int    $limit    Points.
	 * @return array<int,array{tested_at: string, score: int}>
	 */
	public static function series( $url, $strategy, $limit = 30 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tested_at, score FROM %i WHERE url_hash = %s AND strategy = %s AND status = 'ok' AND score IS NOT NULL ORDER BY id DESC LIMIT %d",
				self::table(),
				md5( (string) $url ),
				(string) $strategy,
				max( 1, (int) $limit )
			),
			ARRAY_A
		);
		$out  = array();
		if ( is_array( $rows ) ) {
			foreach ( array_reverse( $rows ) as $row ) {
				$out[] = array(
					'tested_at' => (string) $row['tested_at'],
					'score'     => (int) $row['score'],
				);
			}
		}
		return $out;
	}

	/**
	 * History page.
	 *
	 * @param string $url      Filter URL ('' = all).
	 * @param string $strategy Filter device ('' = all).
	 * @param int    $per_page Rows per page.
	 * @param int    $page     1-based page.
	 * @return array{rows: array<int,array<string,mixed>>, total: int}
	 */
	public static function history( $url, $strategy, $per_page, $page ) {
		global $wpdb;
		$hash     = '' !== $url ? md5( $url ) : '';
		$strategy = (string) $strategy;
		$per_page = max( 1, (int) $per_page );
		$offset   = max( 0, ( (int) $page - 1 ) * $per_page );

		// Empty filter values match everything, so the SQL stays static.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE ( %s = '' OR url_hash = %s ) AND ( %s = '' OR strategy = %s )",
				self::table(),
				$hash,
				$hash,
				$strategy,
				$strategy
			)
		);
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE ( %s = '' OR url_hash = %s ) AND ( %s = '' OR strategy = %s ) ORDER BY id DESC LIMIT %d OFFSET %d",
				self::table(),
				$hash,
				$hash,
				$strategy,
				$strategy,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[] = self::hydrate( $row );
			}
		}
		return array(
			'rows'  => $out,
			'total' => $total,
		);
	}

	/**
	 * Rows in ID order, in batches (for CSV export).
	 *
	 * @param int $after_id Last ID seen.
	 * @param int $limit    Batch size.
	 * @return array<int,array<string,mixed>>
	 */
	public static function batch( $after_id, $limit = 500 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id > %d ORDER BY id ASC LIMIT %d',
				self::table(),
				(int) $after_id,
				max( 1, (int) $limit )
			),
			ARRAY_A
		);
		$out  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[] = self::hydrate( $row );
			}
		}
		return $out;
	}

	/**
	 * Number of stored rows.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
	}

	/**
	 * Delete rows older than N days.
	 *
	 * @param int $days Days to keep.
	 * @return int Rows deleted.
	 */
	public static function prune( $days ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $days ) * DAY_IN_SECONDS ) );
		$result = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE tested_at < %s', self::table(), $cutoff ) );
		return is_int( $result ) ? $result : 0;
	}

	/**
	 * Delete every row.
	 *
	 * @return void
	 */
	public static function delete_all() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::table() ) );
	}

	/**
	 * Cast a DB row to proper types.
	 *
	 * @param array<string,mixed> $row Raw row.
	 * @return array<string,mixed>
	 */
	public static function hydrate( array $row ) {
		foreach ( self::$numeric as $column => $cast ) {
			if ( ! array_key_exists( $column, $row ) ) {
				continue;
			}
			if ( null === $row[ $column ] || '' === $row[ $column ] ) {
				$row[ $column ] = null;
				continue;
			}
			$row[ $column ] = 'int' === $cast ? (int) $row[ $column ] : (float) $row[ $column ];
		}
		if ( isset( $row['id'] ) ) {
			$row['id'] = (int) $row['id'];
		}
		$opps = array();
		if ( isset( $row['opportunities'] ) && is_string( $row['opportunities'] ) && '' !== $row['opportunities'] ) {
			$decoded = json_decode( $row['opportunities'], true );
			if ( is_array( $decoded ) ) {
				$opps = $decoded;
			}
		}
		$row['opportunities'] = $opps;
		return $row;
	}
}
