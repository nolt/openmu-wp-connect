<?php
/**
 * Shared helpers: picking the data source, the armory address, class names,
 * and the admin notice shown while demo data is in use.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the data source the blocks read from.
 *
 * The demo source by default; settings.php swaps in the OpenMU API source through the
 * "openmu_wp_connect_data_source" filter when a server address and an API key are set,
 * and other code can use the same filter to supply its own source.
 */
function openmu_wp_connect_source(): OpenMU_WP_Connect_Data_Source {
	static $source = null;
	if ( null === $source ) {
		$source = apply_filters( 'openmu_wp_connect_data_source', new OpenMU_WP_Connect_Demo_Source() );
		if ( ! $source instanceof OpenMU_WP_Connect_Data_Source ) {
			$source = new OpenMU_WP_Connect_Demo_Source();
		}
	}
	return $source;
}

/**
 * Address of the page that holds the Armory block; ranking rows link to it.
 * Change it with the "openmu_wp_connect_armory_url" filter.
 *
 * @param string $character Character name to open.
 */
function openmu_wp_connect_armory_url( string $character ): string {
	$base = apply_filters( 'openmu_wp_connect_armory_url', home_url( '/armory/' ) );
	return add_query_arg( 'character', rawurlencode( $character ), $base );
}

/**
 * Character classes of Season 6, in the game's own (English) names, which
 * players use whatever the site language is.
 *
 * @return string[]
 */
function openmu_wp_connect_character_classes(): array {
	return array(
		'Dark Wizard',
		'Soul Master',
		'Grand Master',
		'Dark Knight',
		'Blade Knight',
		'Blade Master',
		'Fairy Elf',
		'Muse Elf',
		'High Elf',
		'Magic Gladiator',
		'Duel Master',
		'Dark Lord',
		'Lord Emperor',
		'Summoner',
		'Bloody Summoner',
		'Dimension Master',
		'Rage Fighter',
		'Fist Master',
	);
}

/**
 * Tells administrators, on the dashboard and plugin screens, that the blocks
 * show demo data, so nobody publishes fake numbers by accident.
 */
function openmu_wp_connect_demo_notice(): void {
	$screen = get_current_screen();
	if ( ! current_user_can( 'manage_options' ) || openmu_wp_connect_source()->is_live() || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'OpenMU WP Connect shows demo data: its blocks are not connected to your game server yet. Set the server address and the API key in Settings > OpenMU WP Connect.', 'openmu-wp-connect' )
	);
}
add_action( 'admin_notices', 'openmu_wp_connect_demo_notice' );
