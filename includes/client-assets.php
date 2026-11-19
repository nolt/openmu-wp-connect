<?php
/**
 * Optional game client assets: the textures, class renders and item data of the game look.
 *
 * The plugin ships no files of the MU Online game client. Without them the character view
 * is drawn in a neutral style that fits any theme, and item tooltips show what the website
 * API sends (name, level, options, ancient set, sockets). A site owner who has the files
 * from their own game client can put them in a folder, and the plugin uses each part it
 * finds there:
 *
 * - character-view/: the inventory textures (frame, sockets, zen bar) => textured windows;
 * - classes/: one full-body render per class => class pictures instead of the emblem;
 * - data/: the client item data (items.json, item-sets.json, skills.json) => full tooltips
 *   with base stats, requirements and descriptions.
 *
 * The folder is wp-content/uploads/openmu-wp-connect-client/ by default; the
 * "openmu_wp_connect_client_assets" filter moves it, e.g. into a theme.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Texture files of the textured character view, as CSS custom property name => file in character-view/.
 *
 * The textured look is used only when all of them are present, so a half-copied folder
 * never shows a window with holes.
 *
 * @return array<string, string>
 */
function openmu_wp_connect_client_textures(): array {
	return array(
		'frame-top'    => 'frame-top.webp',
		'frame-bottom' => 'frame-bottom.webp',
		'frame-left'   => 'frame-left.webp',
		'frame-right'  => 'frame-right.webp',
		'zen'          => 'zen.webp',
		'slot-pet'     => 'slot-pet.webp',
		'slot-helm'    => 'slot-helm.webp',
		'slot-wings'   => 'slot-wings.webp',
		'slot-weapon'  => 'slot-weapon.webp',
		'slot-armor'   => 'slot-armor.webp',
		'slot-shield'  => 'slot-shield.webp',
		'slot-gloves'  => 'slot-gloves.webp',
		'slot-pants'   => 'slot-pants.webp',
		'slot-boots'   => 'slot-boots.webp',
		'slot-pendant' => 'slot-pendant.webp',
		'slot-ring'    => 'slot-ring.webp',
	);
}

/**
 * Returns the folder of the client assets, once per request.
 *
 * @return array{path: string, url: string} Absolute path and address of the folder, both
 *         with a trailing slash; empty strings when the uploads folder is unavailable.
 */
function openmu_wp_connect_client_assets(): array {
	static $folder = null;
	if ( null !== $folder ) {
		return $folder;
	}

	$uploads = wp_upload_dir( null, false );
	$default = empty( $uploads['error'] )
		? array(
			'path' => trailingslashit( $uploads['basedir'] ) . 'openmu-wp-connect-client/',
			'url'  => trailingslashit( $uploads['baseurl'] ) . 'openmu-wp-connect-client/',
		)
		: array(
			'path' => '',
			'url'  => '',
		);

	/**
	 * Filters the folder with the optional game client assets.
	 *
	 * The folder holds character-view/, classes/ and data/ (see README). Return another
	 * path and address to use files shipped by a theme, or empty strings to turn the
	 * game look off.
	 *
	 * @param array{path: string, url: string} $folder Absolute path and address of the folder.
	 */
	$filtered = apply_filters( 'openmu_wp_connect_client_assets', $default );
	$path     = is_array( $filtered ) ? (string) ( $filtered['path'] ?? '' ) : '';
	$url      = is_array( $filtered ) ? (string) ( $filtered['url'] ?? '' ) : '';

	$folder = array(
		'path' => '' !== $path ? trailingslashit( $path ) : '',
		'url'  => '' !== $url ? trailingslashit( $url ) : '',
	);
	return $folder;
}

/**
 * Tells which parts of the client assets are present, once per request.
 *
 * @return array{textures: bool, classes: bool, data: bool} textures: every file of
 *         openmu_wp_connect_client_textures(); classes: at least one picture in classes/;
 *         data: data/items.json (the item sets and skills are optional extras).
 */
function openmu_wp_connect_client_assets_status(): array {
	static $status = null;
	if ( null !== $status ) {
		return $status;
	}

	$path   = openmu_wp_connect_client_assets()['path'];
	$status = array(
		'textures' => false,
		'classes'  => false,
		'data'     => false,
	);
	if ( '' === $path || ! is_dir( $path ) ) {
		return $status;
	}

	$status['textures'] = true;
	foreach ( openmu_wp_connect_client_textures() as $file ) {
		if ( ! is_readable( $path . 'character-view/' . $file ) ) {
			$status['textures'] = false;
			break;
		}
	}
	$pictures          = glob( $path . 'classes/*.webp', GLOB_NOSORT );
	$status['classes'] = is_array( $pictures ) && array() !== $pictures;
	$status['data']    = is_readable( $path . 'data/items.json' );

	return $status;
}

/**
 * Returns the address of a file in the client assets folder when it exists.
 *
 * @param string $relative File below the folder, e.g. "classes/dark-knight.webp".
 * @return string|null The address, or null when the file is missing or the folder has no address.
 */
function openmu_wp_connect_client_asset_url( string $relative ): ?string {
	$folder = openmu_wp_connect_client_assets();
	if ( '' === $folder['path'] || '' === $folder['url'] || ! is_readable( $folder['path'] . $relative ) ) {
		return null;
	}
	return $folder['url'] . $relative;
}

/**
 * Builds the inline CSS that hands the texture addresses to the stylesheet.
 *
 * The stylesheet (assets/character-view.css) draws the textured look under
 * .openmu-cv--textured with var(--openmu-cv-tx-<name>); this sets those properties to
 * url() values of the assets folder. So the stylesheet itself has no texture addresses
 * and stays the same wherever the folder lives.
 *
 * @return string The CSS rule, or '' when the textures are not all present.
 */
function openmu_wp_connect_client_texture_css(): string {
	if ( ! openmu_wp_connect_client_assets_status()['textures'] ) {
		return '';
	}

	$url   = openmu_wp_connect_client_assets()['url'];
	$props = array();
	foreach ( openmu_wp_connect_client_textures() as $name => $file ) {
		// esc_url_raw() leaves quotes and backslashes alone in some cases; escape them for the CSS string.
		$address = str_replace( array( '\\', '"', "\n", "\r" ), array( '\\\\', '\\"', '', '' ), esc_url_raw( $url . 'character-view/' . $file ) );
		$props[] = sprintf( '--openmu-cv-tx-%s:url("%s");', $name, $address );
	}
	return '.openmu-cv--textured{' . implode( '', $props ) . '}';
}
