<?php
/**
 * Uninstall cleanup for iWorks.ie Speed Monitor.
 *
 * Removes the results table, every iwiesm_* option and transient, and scheduled events.
 *
 * @package IWorksIE\SpeedMonitor
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove everything for the current site.
 *
 * @return void
 */
function iwiesm_uninstall_site() {
	global $wpdb;

	wp_clear_scheduled_hook( 'iwiesm_scheduled_run' );
	wp_clear_scheduled_hook( 'iwiesm_process_queue' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping our own table on uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'iwiesm_results' ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'iwiesm_' ) . '%',
			$wpdb->esc_like( '_transient_iwiesm_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_iwiesm_' ) . '%'
		)
	);
	if ( is_array( $names ) ) {
		foreach ( $names as $name ) {
			if ( 0 === strpos( $name, '_transient_timeout_' ) ) {
				continue;
			}
			if ( 0 === strpos( $name, '_transient_' ) ) {
				delete_transient( substr( $name, strlen( '_transient_' ) ) );
				continue;
			}
			delete_option( $name );
		}
	}
}

if ( is_multisite() ) {
	$iwiesm_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $iwiesm_sites as $iwiesm_site_id ) {
		switch_to_blog( (int) $iwiesm_site_id );
		iwiesm_uninstall_site();
		restore_current_blog();
	}
} else {
	iwiesm_uninstall_site();
}
