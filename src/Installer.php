<?php
/**
 * Database table install and upgrade.
 *
 * @package IWorksIE\SpeedMonitor
 */

namespace IWorksIE\SpeedMonitor;

use IWorksIE\SpeedMonitor\Storage\Results;

/**
 * Creates the results table. Runs on activation and lazily when the schema version changes,
 * which also covers network activation on multisite (each site gets its table on first use).
 */
final class Installer {

	const DB_VERSION = '1';
	const OPTION     = 'iwiesm_db_version';

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Install when the stored schema version differs.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( self::OPTION ) ) {
			self::install();
		}
	}

	/**
	 * Create or update the table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = Results::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  url_hash char(32) NOT NULL,
  url varchar(2000) NOT NULL,
  strategy varchar(10) NOT NULL,
  source varchar(10) NOT NULL DEFAULT 'manual',
  status varchar(10) NOT NULL DEFAULT 'ok',
  tested_at datetime NOT NULL,
  score tinyint(3) unsigned DEFAULT NULL,
  lab_lcp int(10) unsigned DEFAULT NULL,
  lab_cls decimal(8,4) DEFAULT NULL,
  lab_tbt int(10) unsigned DEFAULT NULL,
  lab_fcp int(10) unsigned DEFAULT NULL,
  lab_si int(10) unsigned DEFAULT NULL,
  field_lcp int(10) unsigned DEFAULT NULL,
  field_inp int(10) unsigned DEFAULT NULL,
  field_cls decimal(8,4) DEFAULT NULL,
  field_fcp int(10) unsigned DEFAULT NULL,
  field_ttfb int(10) unsigned DEFAULT NULL,
  field_category varchar(10) NOT NULL DEFAULT '',
  field_scope varchar(10) NOT NULL DEFAULT '',
  opportunities text,
  error_message varchar(255) NOT NULL DEFAULT '',
  lighthouse_version varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY url_strategy (url_hash,strategy,tested_at),
  KEY tested_at (tested_at)
) {$charset};";

		dbDelta( $sql );
		update_option( self::OPTION, self::DB_VERSION, false );
	}
}
