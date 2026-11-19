<?php
/**
 * Character view: one character drawn like the game's character and inventory windows.
 *
 * Left window: a class picture, name, class, level, master level, resets and guild;
 * for the owner (account section) also the location. Right window: the twelve
 * equipment slots placed as in the client's inventory (legacy interface), each an
 * empty socket or the item's picture. Hovering or focusing an item (tapping on touch
 * screens) shows a tooltip in the game's style, built from the item's title and lines.
 * For the owner the window ends in the zen bar.
 *
 * By default the windows are drawn in a neutral style (borders and the theme's colours)
 * and an unknown class picture is replaced by an emblem. The game look comes from the
 * optional client assets (client-assets.php): with the inventory textures (the client's
 * Data/Interface/newui_item_* at 2x) the view gets the class openmu-cv--textured and the
 * texture addresses as CSS custom properties; with the class renders (full-body pictures
 * of the client's 3D models) the portrait shows the class, see openmu_wp_connect_class_picture_url().
 *
 * The view takes the character array of openmu_wp_connect_source()->get_character(),
 * so the armory block and the account block share it.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/** Query parameter of the account page that opens one of the player's characters. */
const OPENMU_WP_CONNECT_ACCOUNT_CHARACTER_ARG = 'openmu_character';

/**
 * Equipment slots in the order the inventory window reads (top row, middle row,
 * bottom row), so keyboard focus moves through them in that order.
 *
 * The positions themselves are in assets/character-view.css (.openmu-cv__slot--{key}),
 * copied from the client's legacy inventory (themes/legacy/my_inventory.rcss).
 *
 * @return array<string, string> Slot key of get_character() => label.
 */
function openmu_wp_connect_character_view_slots(): array {
	return array(
		'pet'     => __( 'Pet', 'openmu-wp-connect' ),
		'helm'    => __( 'Helm', 'openmu-wp-connect' ),
		'wings'   => __( 'Wings', 'openmu-wp-connect' ),
		'weapon'  => __( 'Weapon', 'openmu-wp-connect' ),
		'pendant' => __( 'Pendant', 'openmu-wp-connect' ),
		'armor'   => __( 'Armor', 'openmu-wp-connect' ),
		'shield'  => __( 'Second hand', 'openmu-wp-connect' ),
		'gloves'  => __( 'Gloves', 'openmu-wp-connect' ),
		'ring1'   => __( 'Ring', 'openmu-wp-connect' ),
		'pants'   => __( 'Pants', 'openmu-wp-connect' ),
		'ring2'   => __( 'Ring', 'openmu-wp-connect' ),
		'boots'   => __( 'Boots', 'openmu-wp-connect' ),
	);
}

/**
 * Returns the class line of a character class: every evolution of a class shares it
 * (Dark Knight, Blade Knight and Blade Master are all "dark-knight").
 *
 * @param string $character_class Class name as the server sends it, e.g. "Blade Master".
 * @return array{0: string, 1: string}|null Slug of the line and its short mark (DK, DW, ...),
 *         or null for a class the plugin doesn't know.
 */
function openmu_wp_connect_class_line( string $character_class ): ?array {
	$lines = array(
		'dark-wizard'     => array( 'DW', array( 'Dark Wizard', 'Soul Master', 'Grand Master' ) ),
		'dark-knight'     => array( 'DK', array( 'Dark Knight', 'Blade Knight', 'Blade Master' ) ),
		'fairy-elf'       => array( 'ELF', array( 'Fairy Elf', 'Muse Elf', 'High Elf' ) ),
		'magic-gladiator' => array( 'MG', array( 'Magic Gladiator', 'Duel Master' ) ),
		'dark-lord'       => array( 'DL', array( 'Dark Lord', 'Lord Emperor' ) ),
		'summoner'        => array( 'SUM', array( 'Summoner', 'Bloody Summoner', 'Dimension Master' ) ),
		'rage-fighter'    => array( 'RF', array( 'Rage Fighter', 'Fist Master' ) ),
	);

	foreach ( $lines as $slug => [ $mark, $classes ] ) {
		if ( in_array( $character_class, $classes, true ) ) {
			return array( $slug, $mark );
		}
	}
	return null;
}

/**
 * Returns the address of the picture of a character class, or null to draw the emblem.
 *
 * The pictures are classes/<slug>.webp of the optional client assets folder
 * (client-assets.php), where the slug is the class name in
 * lower case with hyphens ("Blade Master" => blade-master.webp). They are looked up in
 * this order:
 *
 * 1. The class's own picture, e.g. blade-master.webp for "Blade Master".
 * 2. For a class of a known line without its own picture (e.g. an evolution added
 *    later), the picture of the line's base class, e.g. dark-knight.webp.
 * 3. Otherwise none: an unknown class, or a site without the class pictures, gets the emblem.
 *
 * Sites can replace the result with the "openmu_wp_connect_class_picture" filter.
 * The view draws the picture as a standing figure (fitted to the frame's height,
 * feet at the bottom), so replacements work best as tall, transparent full-body pictures.
 *
 * @param string $character_class Class name as the server sends it, e.g. "Blade Master".
 */
function openmu_wp_connect_class_picture_url( string $character_class ): ?string {
	$line  = openmu_wp_connect_class_line( $character_class );
	$slugs = array( sanitize_title( $character_class ) );
	if ( $line ) {
		$slugs[] = $line[0];
	}

	$url = null;
	if ( openmu_wp_connect_client_assets_status()['classes'] ) {
		foreach ( array_unique( array_filter( $slugs ) ) as $slug ) {
			$url = openmu_wp_connect_client_asset_url( 'classes/' . $slug . '.webp' );
			if ( null !== $url ) {
				break;
			}
		}
	}

	/**
	 * Filters the picture shown for a character class in the character view.
	 *
	 * @param string|null $url             Picture address, null for the emblem.
	 * @param string      $character_class Class name, e.g. "Blade Master".
	 * @param string|null $line            Class line slug, e.g. "dark-knight"; null for an unknown class.
	 */
	$url = apply_filters( 'openmu_wp_connect_class_picture', $url, $character_class, $line ? $line[0] : null );
	return is_string( $url ) && '' !== $url ? $url : null;
}

/**
 * Enqueues the stylesheet and the tooltip script of the character view.
 *
 * Called while a view is rendered, so pages without one load neither file;
 * WordPress prints styles enqueued that late in the footer. With the client
 * textures present, their addresses are added to the stylesheet inline
 * (openmu_wp_connect_client_texture_css()).
 */
function openmu_wp_connect_character_view_enqueue(): void {
	$main = OPENMU_WP_CONNECT_DIR . 'openmu-wp-connect.php';
	wp_enqueue_style(
		'openmu-wp-connect-character-view',
		plugins_url( 'assets/character-view.css', $main ),
		array(),
		OPENMU_WP_CONNECT_VERSION
	);
	// Once per page, however many views it has.
	static $textures_added = false;
	$textures              = openmu_wp_connect_client_texture_css();
	if ( '' !== $textures && ! $textures_added ) {
		wp_add_inline_style( 'openmu-wp-connect-character-view', $textures );
		$textures_added = true;
	}
	wp_enqueue_script(
		'openmu-wp-connect-character-view',
		plugins_url( 'assets/character-view.js', $main ),
		array(),
		OPENMU_WP_CONNECT_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}

/**
 * Picks the colour kind of an item title when the data source doesn't send one:
 * green for excellent and ancient items, gold for +7 and higher, else white.
 *
 * @param array $item Item of get_character()['equipment'].
 */
function openmu_wp_connect_character_view_title_kind( array $item ): string {
	if ( ! empty( $item['titleKind'] ) && is_string( $item['titleKind'] ) ) {
		return sanitize_html_class( $item['titleKind'] );
	}
	if ( ! empty( $item['excellent'] ) || ! empty( $item['ancient'] ) || ! empty( $item['ancientSet'] ) ) {
		return 'excellent';
	}
	return (int) ( $item['level'] ?? 0 ) >= 7 ? 'ancient' : 'plain';
}

/**
 * Builds the tooltip lines of an item.
 *
 * Lines are {text, kind} pairs (item-options.php) or plain strings (older servers and
 * the demo data). The demo data's separate fields (skill, luck, options, ancientSet)
 * are added the same way the old armory showed them.
 *
 * @param array $item Item of get_character()['equipment'].
 * @return array<int, array{text: string, kind: string}>
 */
function openmu_wp_connect_character_view_item_lines( array $item ): array {
	$lines = array();
	foreach ( (array) ( $item['lines'] ?? array() ) as $line ) {
		$text = is_array( $line ) ? (string) ( $line['text'] ?? '' ) : (string) $line;
		$kind = is_array( $line ) ? (string) ( $line['kind'] ?? '' ) : '';
		// An empty row ("gap") separates the blocks of the tooltip, like the client's blank rows.
		if ( 'gap' === $kind ) {
			$lines[] = array(
				'text' => '',
				'kind' => 'gap',
			);
		} elseif ( '' !== trim( $text ) ) {
			$lines[] = array(
				'text' => $text,
				'kind' => '' !== $kind ? sanitize_html_class( $kind ) : 'option',
			);
		}
	}

	if ( ! empty( $item['skill'] ) ) {
		$lines[] = array(
			'text' => __( 'Skill', 'openmu-wp-connect' ),
			'kind' => 'skill',
		);
	}
	if ( ! empty( $item['luck'] ) ) {
		$lines[] = array(
			'text' => __( 'Luck (critical damage rate +5%)', 'openmu-wp-connect' ),
			'kind' => 'luck',
		);
	}
	foreach ( (array) ( $item['options'] ?? array() ) as $option ) {
		if ( is_string( $option ) && '' !== $option ) {
			$lines[] = array(
				'text' => $option,
				'kind' => ! empty( $item['excellent'] ) ? 'excellent' : 'option',
			);
		}
	}
	if ( ! empty( $item['ancientSet'] ) && is_string( $item['ancientSet'] ) ) {
		$lines[] = array(
			/* translators: %s: ancient set name. */
			'text' => sprintf( __( 'Set: %s', 'openmu-wp-connect' ), $item['ancientSet'] ),
			'kind' => 'ancient',
		);
	}

	return $lines;
}

/**
 * Returns the markup of the emblem drawn when there is no class picture:
 * a silver-edged shield with the short mark of the class line.
 *
 * @param string $mark Short mark of the class line, e.g. "DK"; '' for an unknown class.
 */
function openmu_wp_connect_character_view_emblem( string $mark ): string {
	// Long marks (ELF, SUM) need a smaller font to stay inside the shield.
	$size = strlen( $mark ) > 2 ? 26 : 32;
	$text = '' !== $mark
		? sprintf( '<text x="60" y="80" text-anchor="middle" font-size="%d" class="openmu-cv__emblem-mark">%s</text>', $size, esc_html( $mark ) )
		: '<path d="M60 52 L72 72 L60 92 L48 72 Z" class="openmu-cv__emblem-mark" />';

	return '<svg class="openmu-cv__emblem" viewBox="0 0 120 140" aria-hidden="true" focusable="false">'
		. '<defs><linearGradient id="openmu-cv-emblem-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2a2a30"/><stop offset="1" stop-color="#0b0b0d"/></linearGradient></defs>'
		. '<path d="M60 6 L108 22 L108 66 C108 98 88 120 60 134 C32 120 12 98 12 66 L12 22 Z" fill="url(#openmu-cv-emblem-fill)" class="openmu-cv__emblem-edge" />'
		. '<path d="M60 16 L99 29 L99 66 C99 92 83 110 60 123 C37 110 21 92 21 66 L21 29 Z" fill="none" class="openmu-cv__emblem-inner" />'
		. '<path d="M30 38 H90" class="openmu-cv__emblem-inner" />'
		. $text
		. '</svg>';
}

/**
 * Renders the character view.
 *
 * @param array $character Character as returned by openmu_wp_connect_source()->get_character():
 *                         name, characterClass, level, masterLevel, resets, guild, equipment
 *                         (slot key => item with name, level, title, lines, image, ...).
 * @param array $args {
 *     Optional settings.
 *
 *     @type bool   $owner      True when the viewer owns the character (account section):
 *                              shows zen and the location. Default false.
 *     @type int    $money      Zen of the character (owner only).
 *     @type string $mapName    Map name (owner only); empty uses "Map {mapNumber}".
 *     @type int    $mapNumber  Map number (owner only).
 *     @type int    $positionX  X coordinate (owner only).
 *     @type int    $positionY  Y coordinate (owner only).
 *     @type string $heading    Tag of the character name: h2 (default) to h4.
 * }
 * @return string The markup, escaped.
 */
function openmu_wp_connect_render_character_view( array $character, array $args = array() ): string {
	static $instance = 0;
	++$instance;

	openmu_wp_connect_character_view_enqueue();

	$owner   = ! empty( $args['owner'] );
	$heading = (string) ( $args['heading'] ?? 'h2' );
	$heading = in_array( $heading, array( 'h2', 'h3', 'h4' ), true ) ? $heading : 'h2';
	$name    = (string) ( $character['name'] ?? '' );
	$class   = (string) ( $character['characterClass'] ?? '' );
	$line    = openmu_wp_connect_class_line( $class );
	$picture = openmu_wp_connect_class_picture_url( $class );
	$prefix  = 'openmu-cv-' . $instance;
	$classes = 'openmu-cv' . ( $owner ? ' openmu-cv--owner' : '' ) . ( openmu_wp_connect_client_assets_status()['textures'] ? ' openmu-cv--textured' : '' );

	// Character facts, as label => value; the window lists them like the client's character info.
	$facts = array(
		__( 'Level', 'openmu-wp-connect' )        => number_format_i18n( (int) ( $character['level'] ?? 0 ) ),
		__( 'Master level', 'openmu-wp-connect' ) => number_format_i18n( (int) ( $character['masterLevel'] ?? 0 ) ),
		__( 'Resets', 'openmu-wp-connect' )       => number_format_i18n( (int) ( $character['resets'] ?? 0 ) ),
	);
	if ( ! empty( $character['guild'] ) && is_string( $character['guild'] ) ) {
		$facts[ __( 'Guild', 'openmu-wp-connect' ) ] = $character['guild'];
	}
	if ( $owner ) {
		// The map name comes from the server; maps without a name fall back to their number.
		$map = '' !== (string) ( $args['mapName'] ?? '' )
			? (string) $args['mapName']
			/* translators: %d: map number of the game. */
			: sprintf( __( 'Map %d', 'openmu-wp-connect' ), (int) ( $args['mapNumber'] ?? 0 ) );
		$facts[ __( 'Location', 'openmu-wp-connect' ) ] = sprintf(
			/* translators: 1: map name, 2: X coordinate, 3: Y coordinate. */
			__( '%1$s (%2$d, %3$d)', 'openmu-wp-connect' ),
			$map,
			(int) ( $args['positionX'] ?? 0 ),
			(int) ( $args['positionY'] ?? 0 )
		);
	}

	ob_start();
	?>
	<section class="<?php echo esc_attr( $classes ); ?>" aria-labelledby="<?php echo esc_attr( $prefix . '-name' ); ?>">
		<div class="openmu-cv__layout">
			<div class="openmu-cv__window openmu-cv__window--info">
				<<?php echo esc_html( $heading ); ?> id="<?php echo esc_attr( $prefix . '-name' ); ?>" class="openmu-cv__caption openmu-cv__name"><?php echo esc_html( $name ); ?></<?php echo esc_html( $heading ); ?>>
				<div class="openmu-cv__portrait<?php echo $line ? ' openmu-cv__portrait--' . esc_attr( $line[0] ) : ''; ?><?php echo $picture ? ' has-figure' : ''; ?>">
					<?php if ( $picture ) : ?>
						<img src="<?php echo esc_url( $picture ); ?>" alt="" loading="lazy" decoding="async" />
					<?php else : ?>
						<?php echo openmu_wp_connect_character_view_emblem( $line ? $line[1] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG, the mark is escaped inside. ?>
					<?php endif; ?>
				</div>
				<p class="openmu-cv__class"><?php echo esc_html( $class ); ?></p>
				<dl class="openmu-cv__facts">
					<?php foreach ( $facts as $openmu_label => $openmu_value ) : ?>
						<div class="openmu-cv__fact">
							<dt><?php echo esc_html( $openmu_label ); ?></dt>
							<dd><?php echo esc_html( $openmu_value ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</div>

			<div class="openmu-cv__window openmu-cv__window--equipment">
				<p class="openmu-cv__caption" aria-hidden="true"><?php esc_html_e( 'Equipment', 'openmu-wp-connect' ); ?></p>
				<ul class="openmu-cv__slots" aria-label="<?php esc_attr_e( 'Equipment', 'openmu-wp-connect' ); ?>">
					<?php
					foreach ( openmu_wp_connect_character_view_slots() as $openmu_slot => $openmu_label ) :
						$openmu_item = $character['equipment'][ $openmu_slot ] ?? null;
						if ( ! is_array( $openmu_item ) || empty( $openmu_item['name'] ) ) :
							?>
							<li class="openmu-cv__slot openmu-cv__slot--<?php echo esc_attr( $openmu_slot ); ?> is-empty">
								<span class="openmu-cv__sr">
									<?php
									/* translators: %s: equipment slot, e.g. Helm. */
									echo esc_html( sprintf( __( '%s: empty', 'openmu-wp-connect' ), $openmu_label ) );
									?>
								</span>
							</li>
							<?php
							continue;
						endif;

						$openmu_level = (int) ( $openmu_item['level'] ?? 0 );
						// The source's title already has the prefixes and the level; build one only for old data.
						$openmu_title = ! empty( $openmu_item['title'] )
							? (string) $openmu_item['title']
							: (string) $openmu_item['name'] . ( $openmu_level > 0 ? ' +' . $openmu_level : '' );
						$openmu_lines = openmu_wp_connect_character_view_item_lines( $openmu_item );
						$openmu_tip   = $prefix . '-' . $openmu_slot;
						?>
						<li class="openmu-cv__slot openmu-cv__slot--<?php echo esc_attr( $openmu_slot ); ?> is-filled">
							<button type="button" class="openmu-cv__item" data-openmu-tip="<?php echo esc_attr( $openmu_tip ); ?>"<?php echo $openmu_lines ? ' aria-describedby="' . esc_attr( $openmu_tip . '-lines' ) . '"' : ''; ?>>
								<?php if ( ! empty( $openmu_item['image'] ) ) : ?>
									<img src="<?php echo esc_url( $openmu_item['image'] ); ?>" alt="" loading="lazy" decoding="async" />
								<?php else : ?>
									<span class="openmu-cv__item-name" aria-hidden="true"><?php echo esc_html( $openmu_item['name'] ); ?></span>
								<?php endif; ?>
								<span class="openmu-cv__sr">
									<?php
									/* translators: 1: equipment slot, e.g. Helm; 2: item title, e.g. Dragon Helm +13. */
									echo esc_html( sprintf( __( '%1$s: %2$s', 'openmu-wp-connect' ), $openmu_label, $openmu_title ) );
									?>
								</span>
							</button>
							<?php // Tooltip content; the script copies it into one floating box at the end of the page. ?>
							<div class="openmu-cv__tip-source" id="<?php echo esc_attr( $openmu_tip ); ?>" hidden>
								<p class="openmu-cv-tip__title openmu-cv-tip__line--<?php echo esc_attr( openmu_wp_connect_character_view_title_kind( $openmu_item ) ); ?>"><?php echo esc_html( $openmu_title ); ?></p>
								<?php if ( $openmu_lines ) : ?>
									<div class="openmu-cv-tip__lines" id="<?php echo esc_attr( $openmu_tip . '-lines' ); ?>">
										<?php foreach ( $openmu_lines as $openmu_line ) : ?>
											<p class="openmu-cv-tip__line openmu-cv-tip__line--<?php echo esc_attr( $openmu_line['kind'] ); ?>"><?php echo esc_html( $openmu_line['text'] ); ?></p>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( $owner ) : ?>
					<p class="openmu-cv__zen">
						<span class="openmu-cv__zen-label"><?php esc_html_e( 'Zen', 'openmu-wp-connect' ); ?></span>
						<?php echo esc_html( number_format_i18n( (int) ( $args['money'] ?? 0 ) ) ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Address of the account page showing one of the player's characters.
 *
 * Built from the current address, so it works wherever the account block is
 * placed; the message codes of earlier forms are dropped.
 *
 * @param string $character Character name; '' gives the address of the character list.
 */
function openmu_wp_connect_account_character_url( string $character = '' ): string {
	$base = remove_query_arg( array( OPENMU_WP_CONNECT_ACCOUNT_CHARACTER_ARG, OPENMU_WP_CONNECT_ACCOUNT_MESSAGE_ARG, OPENMU_WP_CONNECT_ACCOUNT_RETRY_ARG ) );
	$url  = '' !== $character ? add_query_arg( OPENMU_WP_CONNECT_ACCOUNT_CHARACTER_ARG, rawurlencode( $character ), $base ) : $base;
	return $url;
}

/**
 * Returns the character of the logged-in account that the address asks for.
 *
 * Only names from the session's own character list are accepted, so a player
 * can't open the private view (zen, location) of someone else's character.
 *
 * @param array $account Account data of the session (openmu_wp_connect_account_current()).
 * @return array{0: string, 1: array|null} The requested name ('' when none) and the
 *         matching character of the session, or null when the name isn't on the account.
 */
function openmu_wp_connect_account_requested_character( array $account ): array {
	$requested = isset( $_GET[ OPENMU_WP_CONNECT_ACCOUNT_CHARACTER_ARG ] ) && is_string( $_GET[ OPENMU_WP_CONNECT_ACCOUNT_CHARACTER_ARG ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view, checked against the session below.
		? mb_substr( trim( sanitize_text_field( wp_unslash( $_GET[ OPENMU_WP_CONNECT_ACCOUNT_CHARACTER_ARG ] ) ) ), 0, 10 ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		: '';
	if ( '' === $requested ) {
		return array( '', null );
	}

	foreach ( (array) ( $account['characters'] ?? array() ) as $character ) {
		// Game names are case-insensitive; the session's spelling is the real one.
		if ( 0 === strcasecmp( (string) $character['name'], $requested ) ) {
			return array( $requested, $character );
		}
	}
	return array( $requested, null );
}

/**
 * Prints the character view of the account section: back link, character switch and the view.
 *
 * The equipment comes from the server's character API; when it can't be read, the
 * view still shows what the login data has (class, levels, zen, location) with a notice.
 *
 * @param array $account Account data of the session.
 * @param array $chosen  The chosen character from the session's character list.
 */
function openmu_wp_connect_account_render_character( array $account, array $chosen ): void {
	$character = openmu_wp_connect_source()->get_character( $chosen['name'] );
	$missing   = ! is_array( $character );
	if ( $missing ) {
		$character = array(
			'name'           => $chosen['name'],
			'characterClass' => $chosen['className'],
			'level'          => $chosen['level'],
			'masterLevel'    => $chosen['masterLevel'],
			'resets'         => $chosen['resets'],
			'guild'          => null,
			'equipment'      => array(),
		);
	}

	// A GET form drops the query string of its action, so the page's own parameters
	// (e.g. page_id with plain permalinks) travel as hidden fields.
	$list  = openmu_wp_connect_account_character_url();
	$base  = strtok( $list, '#' );
	$query = array();
	wp_parse_str( (string) wp_parse_url( $base, PHP_URL_QUERY ), $query );
	?>
	<nav class="openmu-account__character-nav" aria-label="<?php esc_attr_e( 'Your characters', 'openmu-wp-connect' ); ?>">
		<a class="openmu-account__back" href="<?php echo esc_url( $list ); ?>"><?php esc_html_e( '‹ All characters', 'openmu-wp-connect' ); ?></a>
		<?php if ( count( $account['characters'] ) > 1 ) : ?>
			<form class="openmu-account__switch" method="get" action="<?php echo esc_url( strtok( $base, '?' ) ); ?>">
				<?php foreach ( $query as $openmu_key => $openmu_value ) : ?>
					<?php if ( is_string( $openmu_value ) ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $openmu_key ); ?>" value="<?php echo esc_attr( $openmu_value ); ?>" />
					<?php endif; ?>
				<?php endforeach; ?>
				<label for="openmu-account-switch"><?php esc_html_e( 'Character', 'openmu-wp-connect' ); ?></label>
				<select id="openmu-account-switch" name="<?php echo esc_attr( OPENMU_WP_CONNECT_ACCOUNT_CHARACTER_ARG ); ?>">
					<?php foreach ( $account['characters'] as $openmu_char ) : ?>
						<option value="<?php echo esc_attr( $openmu_char['name'] ); ?>"<?php selected( $openmu_char['name'], $chosen['name'] ); ?>>
							<?php
							/* translators: 1: character name, 2: class name, 3: level. */
							echo esc_html( sprintf( __( '%1$s – %2$s, level %3$d', 'openmu-wp-connect' ), $openmu_char['name'], $openmu_char['className'], $openmu_char['level'] ) );
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php // A button, not a submit on change: changing pages while arrowing through a list breaks keyboard use. ?>
				<button type="submit" class="wp-element-button"><?php esc_html_e( 'Show', 'openmu-wp-connect' ); ?></button>
			</form>
		<?php endif; ?>
	</nav>

	<?php if ( $missing ) : ?>
		<p class="openmu-line openmu-line--requirement" role="status"><?php esc_html_e( 'The equipment of this character cannot be read from the game server right now.', 'openmu-wp-connect' ); ?></p>
	<?php endif; ?>

	<?php
	echo openmu_wp_connect_render_character_view( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while built.
		$character,
		array(
			'owner'     => true,
			'heading'   => 'h4',
			'money'     => $chosen['money'],
			'mapName'   => $chosen['mapName'],
			'mapNumber' => $chosen['mapNumber'],
			'positionX' => $chosen['positionX'],
			'positionY' => $chosen['positionY'],
		)
	);
}
