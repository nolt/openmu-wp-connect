<?php
/**
 * Registers the plugin's blocks, their editor script and their stylesheet.
 *
 * Each block lives in blocks/<name>/ with a block.json and a render.php: the
 * markup is built on the server on every page view, so the data stays current
 * and the editor preview uses the same code (through ServerSideRender).
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the shared editor script and stylesheet, then every block folder.
 */
function openmu_wp_connect_register_blocks(): void {
	// Editor-side definitions for all three blocks; plain JS, no build step.
	wp_register_script(
		'openmu-wp-connect-editor',
		plugins_url( 'assets/editor.js', OPENMU_WP_CONNECT_DIR . 'openmu-wp-connect.php' ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
		OPENMU_WP_CONNECT_VERSION,
		true
	);
	wp_set_script_translations( 'openmu-wp-connect-editor', 'openmu-wp-connect', OPENMU_WP_CONNECT_DIR . 'languages' );

	// Class list for the ranking block's filter, handed to the editor script.
	wp_add_inline_script(
		'openmu-wp-connect-editor',
		'window.openmuWpConnect = ' . wp_json_encode( array( 'classes' => openmu_wp_connect_character_classes() ) ) . ';',
		'before'
	);

	// Neutral look that works with any theme; the OpenMU Site theme restyles it.
	wp_register_style(
		'openmu-wp-connect-blocks',
		plugins_url( 'assets/blocks.css', OPENMU_WP_CONNECT_DIR . 'openmu-wp-connect.php' ),
		array(),
		OPENMU_WP_CONNECT_VERSION
	);

	foreach ( array( 'server-status', 'ranking', 'armory' ) as $block ) {
		register_block_type( OPENMU_WP_CONNECT_DIR . 'blocks/' . $block );
	}
}
add_action( 'init', 'openmu_wp_connect_register_blocks' );

/**
 * Adds the "OpenMU" category to the block inserter, first in the list.
 *
 * All blocks of the plugin are in it (the "category" in each block.json), so they are found
 * together instead of among WordPress's own widgets.
 *
 * @param array<int, array<string, mixed>> $categories Block categories.
 * @return array<int, array<string, mixed>> The categories with "OpenMU" added.
 */
function openmu_wp_connect_block_category( array $categories ): array {
	array_unshift(
		$categories,
		array(
			'slug'  => 'openmu',
			'title' => __( 'OpenMU', 'openmu-wp-connect' ),
			'icon'  => null,
		)
	);
	return $categories;
}
add_filter( 'block_categories_all', 'openmu_wp_connect_block_category' );
