<?php
/**
 * Cleans up when the plugin is deleted in wp-admin (Plugins > Delete), not when it is
 * only deactivated.
 *
 * Removes, on every site of a multisite network too:
 * - the settings: the server address and the API key (openmu_wp_connect_api_url, _api_key);
 * - every transient of the plugin in the database: cached API answers, player sessions,
 *   form limits;
 * - the item pictures cached from the game server (uploads/openmu-wp-connect/).
 *
 * The optional game client files (uploads/openmu-wp-connect-client/, or a folder given
 * by a theme) are the site owner's own copy and stay. The wp-config.php constants
 * OPENMU_WP_CONNECT_API_URL / _API_KEY have to be removed by hand.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the data of the plugin from the current site.
 */
function openmu_wp_connect_uninstall_site(): void {
	global $wpdb;

	delete_option( 'openmu_wp_connect_api_url' );
	delete_option( 'openmu_wp_connect_api_key' );

	// Transients have random parts in their names (session tokens, address hashes), so they are found by prefix.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API lists transients by prefix; runs once, on delete.
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_openmu_wp_connect_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_openmu_wp_connect_' ) . '%'
		)
	);
	// With a persistent object cache (Redis, Memcached) the transients live there instead; they
	// all expire within an hour, and flushing the whole cache would hit other plugins too.

	$uploads = wp_upload_dir( null, false );
	if ( empty( $uploads['error'] ) ) {
		openmu_wp_connect_uninstall_remove_dir( trailingslashit( $uploads['basedir'] ) . 'openmu-wp-connect' );
	}
}

/**
 * Deletes a folder of cached item pictures with its files.
 *
 * @param string $dir Absolute path of the folder.
 */
function openmu_wp_connect_uninstall_remove_dir( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	global $wp_filesystem;
	if ( WP_Filesystem() && $wp_filesystem ) {
		$wp_filesystem->delete( $dir, true );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $openmu_site_id ) {
		switch_to_blog( $openmu_site_id );
		openmu_wp_connect_uninstall_site();
		restore_current_blog();
	}
} else {
	openmu_wp_connect_uninstall_site();
}
