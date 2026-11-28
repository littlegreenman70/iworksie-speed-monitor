<?php
/**
 * Uninstall cleanup for iWorks.ie Speed Monitor.
 *
 * Removes the results table, every iwiesp_* option and transient, and scheduled events.
 *
 * @package IWorksIE\SpeedMonitor
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove everything for the current site.
 *
 * @return void
 */
function iwiesp_uninstall_site() {
	global $wpdb;

	wp_clear_scheduled_hook( 'iwiesp_scheduled_run' );
	wp_clear_scheduled_hook( 'iwiesp_process_queue' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping our own table on uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'iwiesp_results' ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'iwiesp_' ) . '%',
			$wpdb->esc_like( '_transient_iwiesp_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_iwiesp_' ) . '%'
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
	$iwiesp_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $iwiesp_sites as $iwiesp_site_id ) {
		switch_to_blog( (int) $iwiesp_site_id );
		iwiesp_uninstall_site();
		restore_current_blog();
	}
} else {
	iwiesp_uninstall_site();
}
