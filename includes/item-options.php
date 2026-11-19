<?php
/**
 * Item tooltip lines for the armory, in the words and order of the Season 6 game client.
 *
 * The website API of OpenMU describes the options of an equipped item in a neutral,
 * technical way: the option type of the server configuration (e.g. "Excellent Option"),
 * the option number, its level, the target attribute (e.g. "Excellent Damage Chance")
 * and the boost value (e.g. 0.1). The functions here turn that into the lines the
 * player knows from the in-game tooltip, e.g. "Excellent Damage rate +10%".
 *
 * Sources of the texts and values:
 * - texts: the English strings of the MuMain client (Localization/Game.en.resx,
 *   Data/Local/Eng/JewelOfHarmonyOption_eng.bmd and socketitem_eng.bmd);
 * - meaning of each (type, attribute) and its value format: the Season 6 seed of
 *   OpenMU (ExcellentOptions.cs, Wings.cs, Jewelery.cs, HarmonyOptions.cs,
 *   SocketSystem.cs, GuardianOptions.cs, AncientSets.cs, Pets.cs).
 * The client writes fixed numbers into some texts ("+10%"); here the number comes
 * from the server, so a changed server configuration is shown as it is.
 *
 * All texts go through the "openmu-wp-connect" text domain, so they can be translated
 * in languages/*.po. An option this file doesn't know is shown as "attribute +value",
 * so nothing disappears silently.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the tooltip title of an item of the website API, like the game:
 * "Excellent" for excellent items, the ancient set name before the item name, and "+level".
 *
 * @param array $item Item of the website API (keys name, level, isExcellent, ancientSetName).
 * @return string The title, e.g. "Excellent Knight Blade +15" or "Hyon Dragon Helm +13".
 */
function openmu_wp_connect_item_title( array $item ): string {
	$title = (string) ( $item['name'] ?? '' );

	$set = (string) ( $item['ancientSetName'] ?? '' );
	if ( '' !== $set ) {
		/* translators: 1: ancient set name, e.g. "Hyon"; 2: item name. */
		$title = sprintf( __( '%1$s %2$s', 'openmu-wp-connect' ), $set, $title );
	}

	if ( ! empty( $item['isExcellent'] ) ) {
		/* translators: %s: item name; the game prefixes excellent items. */
		$title = sprintf( __( 'Excellent %s', 'openmu-wp-connect' ), $title );
	}

	$level = (int) ( $item['level'] ?? 0 );
	if ( $level > 0 ) {
		$title .= ' +' . $level;
	}

	// The Horn of Fenrir is named after its option, like in the client's item data (Group13_Helper.json).
	$types = array_map( static fn( $option ) => (string) ( $option['type'] ?? '' ), (array) ( $item['options'] ?? array() ) );
	if ( in_array( 'Gold Fenrir Option', $types, true ) ) {
		/* translators: %s: item name (Horn of Fenrir with the golden option). */
		$title = sprintf( __( '%s +Illusion', 'openmu-wp-connect' ), $title );
	} elseif ( in_array( 'Blue Fenrir Option', $types, true ) ) {
		/* translators: %s: item name (Horn of Fenrir with the protecting option). */
		$title = sprintf( __( '%s +Protect', 'openmu-wp-connect' ), $title );
	} elseif ( in_array( 'Black Fenrir Option', $types, true ) ) {
		/* translators: %s: item name (Horn of Fenrir with the destroying option). */
		$title = sprintf( __( '%s +Destroy', 'openmu-wp-connect' ), $title );
	}

	return $title;
}

/**
 * Picks the colour of the item title, in the client's order (StandardItemNameColor in
 * ZzzInventory.cpp): ancient, socket, excellent, +7 and higher, with options, else plain.
 * The Divine Archangel weapons have their own purple name in the client's item data.
 *
 * @param array $item  Item of the website API (keys group, number, level, isExcellent, ancientSetName, sockets, hasSkill).
 * @param array $lines The option lines of the item, see openmu_wp_connect_item_lines().
 * @return string Colour kind for the CSS class openmu-line--{kind}: purple, set (ancient set: green on blue bar), excellent, violet, ancient (gold), option or plain.
 */
function openmu_wp_connect_item_title_kind( array $item, array $lines ): string {
	// Divine Archangel weapons ("color": "purple" in the client's Group00/02/04/05 item files), as "group/number".
	$archangel = array( '0/19', '2/13', '4/18', '5/10', '5/36' );
	if ( in_array( (int) ( $item['group'] ?? -1 ) . '/' . (int) ( $item['number'] ?? -1 ), $archangel, true ) ) {
		return 'purple';
	}

	// Ancient (set) items come first, over excellent: bright green on a blue bar (TEXT_COLOR_GREEN_BLUE).
	if ( ! empty( $item['isAncient'] ) || '' !== (string) ( $item['ancientSetName'] ?? '' ) ) {
		return 'set';
	}

	if ( ! empty( $item['isExcellent'] ) ) {
		return 'excellent';
	}

	if ( (int) ( $item['sockets'] ?? 0 ) > 0 ) {
		return 'violet';
	}

	if ( (int) ( $item['level'] ?? 0 ) >= 7 ) {
		return 'ancient';
	}

	return array() !== $lines || ! empty( $item['hasSkill'] ) ? 'option' : 'plain';
}

/**
 * Builds the option lines of an item of the website API, in the order of the game tooltip.
 *
 * The client order: Guardian (380) options, Jewel of Harmony option, ancient bonus
 * (+5/+10 stat), wing options, skill, luck, the regular "+option", excellent options
 * (highest number first, like the client's bit order), pet options, then the ancient
 * set and the sockets. Unknown option types go after the excellent options.
 *
 * Used when the plugin's client item data doesn't know the item; otherwise
 * openmu_wp_connect_item_tooltip_lines() (item-data.php) builds the full tooltip around the options.
 *
 * @param array $item            Item of the website API (keys hasSkill, ancientSetName, sockets, options).
 * @param int   $character_level Total level of the wearer; options that depend on it (Gold Fenrir)
 *                               show the number then, like the client. 0: show the formula, e.g. "level/2".
 * @return array<int, array{text: string, kind: string}> The lines; kind is one of
 *         option, excellent, skill, luck, wing, harmony, guardian, ancient, socket, note
 *         (green remark, e.g. "Golden Fenrir"), darkred, muted or gap (empty row).
 */
function openmu_wp_connect_item_lines( array $item, int $character_level = 0 ): array {
	$parts = openmu_wp_connect_item_option_parts( $item, $character_level );
	$lines = $parts['options'];

	$set = (string) ( $item['ancientSetName'] ?? '' );
	if ( '' !== $set ) {
		$lines[] = array(
			/* translators: %s: ancient set name. */
			'text' => sprintf( __( 'Set: %s', 'openmu-wp-connect' ), $set ),
			'kind' => 'ancient',
		);
	}

	return array_merge( $lines, $parts['sockets'] );
}

/**
 * Builds the option lines and the socket block of an item of the website API, apart, so the
 * full tooltip can put the ancient set block between them like the client.
 *
 * @param array $item            Item of the website API (keys hasSkill, sockets, options).
 * @param int   $character_level Total level of the wearer, see openmu_wp_connect_item_lines().
 * @return array{options: array, sockets: array} The option lines and the socket lines.
 */
function openmu_wp_connect_item_option_parts( array $item, int $character_level = 0 ): array {
	// Section rank per option kind, so the lines come out in the client's order.
	$rank = array(
		'guardian'     => 10,
		'harmony'      => 20,
		'ancientBonus' => 30,
		'wing'         => 40,
		'skill'        => 50,
		'luck'         => 60,
		'option'       => 70,
		'excellent'    => 80,
		'pet'          => 90,
		'other'        => 100,
	);

	$entries        = array();
	$socket_options = array();
	$socket_bonus   = array();
	$has_luck       = false;

	foreach ( (array) ( $item['options'] ?? array() ) as $option ) {
		if ( ! is_array( $option ) ) {
			continue;
		}

		$section = openmu_wp_connect_item_option_section( (string) ( $option['type'] ?? '' ) );
		if ( 'luck' === $section ) {
			$has_luck = true;
			continue;
		}

		// Sockets are listed in their own block below the set, as in the client.
		if ( 'socket' === $section ) {
			$socket_options[] = $option;
			continue;
		}

		if ( 'socketBonus' === $section ) {
			$socket_bonus[] = $option;
			continue;
		}

		$number = (int) ( $option['number'] ?? 0 );
		// The client lists excellent options from the highest bit (number 6) down, and the four
		// Gold Fenrir options (all number 4) as life, mana, attack, wizardry.
		$order = match ( $section ) {
			'excellent' => -$number,
			'pet'       => (int) array_search( (string) ( $option['attribute'] ?? '' ), array( 'Maximum Health', 'Maximum Mana', 'Physical Base Damage (min and max)', 'Wizardry Base Damage (min and max)' ), true ),
			default     => $number,
		};
		$entries[] = array(
			'rank'  => $rank[ $section ] ?? $rank['other'],
			'order' => $order,
			'index' => count( $entries ),
			'lines' => openmu_wp_connect_item_option_lines( $option, $section, $character_level ),
		);
	}

	// The client closes the Gold Fenrir options with two green lines.
	if ( in_array( 'Gold Fenrir Option', array_map( static fn( $option ) => (string) ( $option['type'] ?? '' ), (array) ( $item['options'] ?? array() ) ), true ) ) {
		$entries[] = array(
			'rank'  => $rank['pet'],
			'order' => PHP_INT_MAX,
			'index' => count( $entries ),
			'lines' => array(
				array(
					'text' => '',
					'kind' => 'gap',
				),
				array(
					'text' => __( 'Golden Fenrir', 'openmu-wp-connect' ),
					'kind' => 'note',
				),
				array(
					'text' => __( 'Exclusive edition only given to MU Heroes', 'openmu-wp-connect' ),
					'kind' => 'note',
				),
			),
		);
	}

	// The black and blue Fenrir also make the rider faster; the client says so below the option.
	if ( array() !== array_intersect( array( 'Black Fenrir Option', 'Blue Fenrir Option' ), array_map( static fn( $option ) => (string) ( $option['type'] ?? '' ), (array) ( $item['options'] ?? array() ) ) ) ) {
		$entries[] = array(
			'rank'  => $rank['pet'],
			'order' => PHP_INT_MAX,
			'index' => count( $entries ),
			'lines' => array(
				array(
					'text' => __( 'Increase speed', 'openmu-wp-connect' ),
					'kind' => 'option',
				),
			),
		);
	}

	if ( ! empty( $item['hasSkill'] ) ) {
		// The skill's name and mana from the client data (item-data.php), else a plain "Skill".
		$skill_lines = function_exists( 'openmu_wp_connect_item_skill_lines' ) ? openmu_wp_connect_item_skill_lines( $item ) : array();
		$entries[]   = array(
			'rank'  => $rank['skill'],
			'order' => 0,
			'index' => count( $entries ),
			'lines' => array() !== $skill_lines ? $skill_lines : array(
				array(
					'text' => __( 'Skill', 'openmu-wp-connect' ),
					'kind' => 'skill',
				),
			),
		);
	}

	if ( $has_luck ) {
		// The client shows both effects of luck, one line each.
		$entries[] = array(
			'rank'  => $rank['luck'],
			'order' => 0,
			'index' => count( $entries ),
			'lines' => array(
				array(
					'text' => __( 'Luck (success rate of Jewel of Soul +25%)', 'openmu-wp-connect' ),
					'kind' => 'luck',
				),
				array(
					'text' => __( 'Luck (critical damage rate +5%)', 'openmu-wp-connect' ),
					'kind' => 'luck',
				),
			),
		);
	}

	// Stable sort by section, then option number, then the order of the API.
	usort(
		$entries,
		static fn( $a, $b ) => array( $a['rank'], $a['order'], $a['index'] ) <=> array( $b['rank'], $b['order'], $b['index'] )
	);

	$lines = array();
	foreach ( $entries as $entry ) {
		foreach ( $entry['lines'] as $line ) {
			$lines[] = $line;
		}
	}

	return array(
		'options' => $lines,
		'sockets' => openmu_wp_connect_item_socket_lines( (int) ( $item['sockets'] ?? 0 ), $socket_options, $socket_bonus ),
	);
}

/**
 * Maps the neutral option type name of the server configuration to a section of the tooltip.
 *
 * The names are those of ItemOptionTypes in the OpenMU data model.
 *
 * @param string $type Option type name, e.g. "Excellent Option".
 * @return string Section: excellent, wing, luck, option, harmony, guardian, ancientBonus,
 *                socket, socketBonus, pet or other.
 */
function openmu_wp_connect_item_option_section( string $type ): string {
	if ( str_starts_with( $type, 'Luck' ) ) {
		return 'luck';
	}

	return match ( $type ) {
		'Excellent Option'                                     => 'excellent',
		'Wing Option'                                          => 'wing',
		'Option'                                               => 'option',
		'Jewel of Harmony Option', 'Harmony Option'            => 'harmony',
		'Guardian Option'                                      => 'guardian',
		'Ancient Bonus Option'                                 => 'ancientBonus',
		'Socket Option'                                        => 'socket',
		'Socket Bonus Option'                                  => 'socketBonus',
		'Blue Fenrir Option', 'Black Fenrir Option', 'Gold Fenrir Option', 'Dark Horse Option' => 'pet',
		default                                                => 'other',
	};
}

/**
 * Turns one option of the website API into its tooltip lines.
 *
 * @param array  $option  Option of the website API (keys type, number, level, attribute, value,
 *                        aggregate, relatedAttribute, relatedOperator, relatedOperand).
 * @param string $section         Section of the option type, see openmu_wp_connect_item_option_section().
 * @param int    $character_level Total level of the wearer, or 0 if unknown.
 * @return array<int, array{text: string, kind: string}> The lines; empty for options the game doesn't show.
 */
function openmu_wp_connect_item_option_lines( array $option, string $section, int $character_level = 0 ): array {
	$attribute = (string) ( $option['attribute'] ?? '' );
	$type      = (string) ( $option['type'] ?? '' );

	// A "+option" of level 0 has no power-up (value null): the item can take the option, but hasn't got it yet.
	if ( 'option' === $section && null === ( $option['value'] ?? null ) && '' === (string) ( $option['relatedAttribute'] ?? '' ) ) {
		return array();
	}

	// The movement speed bonus of the Fenrir is shown by the client as a plain "Increase speed" text
	// below the Fenrir option (added once in openmu_wp_connect_item_option_parts()), not as an option of its own.
	if ( 'pet' === $section && str_starts_with( $attribute, 'Movement Speed' ) ) {
		return array();
	}

	$texts = openmu_wp_connect_item_option_texts();
	$kind  = match ( $section ) {
		'excellent' => 'excellent',
		'wing'      => 'wing',
		'harmony'   => 'harmony',
		'guardian'  => 'guardian',
		default     => 'option',
	};

	$rules = $texts[ $section ][ $attribute ] ?? null;
	if ( 'pet' === $section ) {
		$rules = $texts['pet'][ $type . '|' . $attribute ] ?? null;
	}

	// Some attributes mean different things by their aggregate, e.g. the Dinorant's "+50 AG"
	// against the Pendant of Ability's "+1% AG".
	if ( is_array( $rules ) && isset( $rules['by_aggregate'] ) ) {
		$rules = $rules['by_aggregate'][ (string) ( $option['aggregate'] ?? '' ) ] ?? $rules['by_aggregate']['*'] ?? null;
	}

	if ( ! is_array( $rules ) ) {
		return array(
			array(
				'text' => openmu_wp_connect_item_option_fallback( $option ),
				'kind' => $kind,
			),
		);
	}

	// A rule is [format, value mode], or a list of them for options with more than one line.
	$rules = is_array( $rules[0] ) ? $rules : array( $rules );
	$lines = array();
	foreach ( $rules as $rule ) {
		[ $format, $mode ] = $rule;
		if ( 'none' === $mode ) {
			$text = $format;
		} elseif ( 'level' === $mode ) {
			// The value per character level: the number for a known wearer, e.g. "200", else the formula "level/2".
			$divisor = openmu_wp_connect_item_option_number( $option, 'per_level' );
			$text    = sprintf(
				$format,
				$character_level > 0 && $divisor > 0
					? (string) intdiv( $character_level, $divisor )
					/* translators: %d: divisor of the character level, e.g. 2. */
					: sprintf( __( 'level/%d', 'openmu-wp-connect' ), $divisor )
			);
		} else {
			$text = sprintf( $format, openmu_wp_connect_item_option_number( $option, $mode ) );
		}

		$lines[] = array(
			'text' => $text,
			'kind' => $kind,
		);
	}

	return $lines;
}

/**
 * Computes the number shown in an option text from the value of the API.
 *
 * Modes:
 * - int: the value rounded, e.g. 16 for the "+option" +16;
 * - percent: a chance (0.1) or a multiplier (1.04 or 0.9) in percent, e.g. 10, 4 or 10;
 * - inverse: the divisor of a fraction, e.g. 8 for 1/8 of the maximum life;
 * - per_level: the divisor of a value per character level, e.g. 20 for level/20
 *   (the format mode "level" of the text table uses it for the number or the formula);
 * - fraction: like percent for values below 1 (harmony shows "SD bypass rate increase +15"), else int.
 *
 * @param array  $option Option of the website API.
 * @param string $mode   One of the modes above.
 * @return int The number for the text.
 */
function openmu_wp_connect_item_option_number( array $option, string $mode ): int {
	$value     = (float) ( $option['value'] ?? 0 );
	$aggregate = (string) ( $option['aggregate'] ?? '' );
	$operand   = (float) ( $option['relatedOperand'] ?? 0 );

	return match ( $mode ) {
		'percent'   => (int) round( 'Multiplicate' === $aggregate ? abs( $value - 1 ) * 100 : $value * 100 ),
		'inverse'   => $value > 0 ? (int) round( 1 / $value ) : 0,
		'per_level' => $operand > 0 ? (int) round( 1 / $operand ) : 0,
		'fraction'  => (int) round( abs( $value ) < 1 ? $value * 100 : $value ),
		default     => (int) round( $value ),
	};
}

/**
 * Describes an option this file has no text for: "attribute +value", with the relative
 * part when the bonus depends on another attribute.
 *
 * @param array $option Option of the website API.
 * @return string The text, e.g. "Defense Rate (PvP) +5" or "Base Defense +5 + Horse Level × 2".
 */
function openmu_wp_connect_item_option_fallback( array $option ): string {
	$name = (string) ( $option['attribute'] ?? '' );
	if ( '' === $name ) {
		$name = (string) ( $option['type'] ?? '' );
	}

	$text  = $name;
	$value = isset( $option['value'] ) ? (float) $option['value'] : 0.0;
	if ( 0.0 !== $value ) {
		$text .= ' +' . openmu_wp_connect_item_format_float( $value );
	}

	$related = (string) ( $option['relatedAttribute'] ?? '' );
	if ( '' !== $related ) {
		/* translators: 1: option text so far, 2: attribute the bonus depends on, 3: factor. */
		$text = sprintf( __( '%1$s + %2$s × %3$s', 'openmu-wp-connect' ), $text, $related, openmu_wp_connect_item_format_float( (float) ( $option['relatedOperand'] ?? 0 ) ) );
	}

	return '' !== $text ? $text : __( 'Unknown option', 'openmu-wp-connect' );
}

/**
 * Formats a float for the fallback text: at most three decimals, without trailing zeros.
 *
 * @param float $value The value.
 * @return string The value, e.g. "0.125" or "50".
 */
function openmu_wp_connect_item_format_float( float $value ): string {
	return rtrim( rtrim( number_format( $value, 3, '.', '' ), '0' ), '.' );
}

/**
 * Builds the socket block of the tooltip: a heading, one line per socket and the socket bonus.
 *
 * The API doesn't say in which socket a seed sphere sits, so the spheres are numbered in the
 * order of the API; the remaining sockets are shown as empty, like the client does.
 *
 * @param int   $sockets        Number of sockets of the item.
 * @param array $socket_options Options of type "Socket Option".
 * @param array $socket_bonus   Options of type "Socket Bonus Option".
 * @return array<int, array{text: string, kind: string}> The lines; empty for items without sockets.
 */
function openmu_wp_connect_item_socket_lines( int $sockets, array $socket_options, array $socket_bonus ): array {
	$sockets = max( $sockets, count( $socket_options ) );
	if ( 0 === $sockets ) {
		return array();
	}

	$texts = openmu_wp_connect_item_option_texts();
	$lines = array(
		array(
			'text' => __( 'Socket Item option info', 'openmu-wp-connect' ),
			'kind' => 'socket',
		),
	);

	for ( $index = 0; $index < $sockets; $index++ ) {
		$option = $socket_options[ $index ] ?? null;
		if ( null === $option ) {
			$option_text = __( 'No item application', 'openmu-wp-connect' );
			$kind        = 'muted';
		} else {
			$attribute = (string) ( $option['attribute'] ?? '' );
			$key       = $attribute;
			// The Fire seed has a fixed and a per-level "Attack/Wizardry increase"; the Earth seed's
			// "Health increase" is absolute, the Wind seed's "Maximum Life increase" a multiplier.
			if ( '' !== (string) ( $option['relatedAttribute'] ?? '' ) ) {
				$key .= '|related';
			} elseif ( 'Multiplicate' === ( $option['aggregate'] ?? '' ) && isset( $texts['socket'][ $attribute . '|Multiplicate' ] ) ) {
				$key .= '|Multiplicate';
			}

			$rule = $texts['socket'][ $key ] ?? null;
			if ( is_array( $rule ) ) {
				[ $element, $name, $mode ] = $rule;
				$number                    = openmu_wp_connect_item_option_number( $option, $mode );
				$value                     = match ( $mode ) {
					'percent'   => '+' . $number . '%',
					/* translators: %d: divisor, e.g. 8 for one eighth of the maximum life. */
					'inverse'   => sprintf( __( '+life/%d', 'openmu-wp-connect' ), $number ),
					/* translators: %d: divisor of the character level, e.g. 20. */
					'per_level' => sprintf( __( '+level/%d', 'openmu-wp-connect' ), $number ),
					default     => '+' . $number,
				};
				/* translators: 1: seed element, e.g. "Fire"; 2: option name; 3: value, e.g. "+7" or "+10%". */
				$option_text = sprintf( __( '%1$s(%2$s %3$s)', 'openmu-wp-connect' ), $element, $name, $value );
			} else {
				$option_text = openmu_wp_connect_item_option_fallback( $option );
			}

			$kind = 'option';
		}

		/* translators: 1: socket number, 2: seed sphere of the socket, e.g. "Fire(Attack speed increase +7)". */
		$text = sprintf( __( 'Socket %1$d: %2$s', 'openmu-wp-connect' ), $index + 1, $option_text );

		$lines[] = array(
			'text' => $text,
			'kind' => $kind,
		);
	}

	foreach ( $socket_bonus as $option ) {
		$name    = $texts['socketBonus'][ (int) ( $option['number'] ?? -1 ) ] ?? null;
		$lines[] = array(
			'text' => __( 'Bonus socket option', 'openmu-wp-connect' ),
			'kind' => 'socket',
		);
		$lines[] = array(
			'text' => null !== $name
				/* translators: 1: socket bonus name, 2: its value. */
				? sprintf( __( '%1$s +%2$d', 'openmu-wp-connect' ), $name, openmu_wp_connect_item_option_number( $option, 'int' ) )
				: openmu_wp_connect_item_option_fallback( $option ),
			'kind' => 'option',
		);
	}

	return $lines;
}

/**
 * The text table: per section, the target attribute of the option (its designation in OpenMU)
 * mapped to [format, value mode] (see openmu_wp_connect_item_option_number()), or a list of those
 * for options with two lines, or 'by_aggregate' when the aggregate type decides.
 *
 * The English texts are the ones of the MuMain client; the few typos of the client
 * ("Ignor", "sucess", "Return's", "recover of") are corrected.
 *
 * @return array<string, array> The table, built once per request in the current language.
 */
function openmu_wp_connect_item_option_texts(): array {
	static $texts = null;
	if ( null !== $texts ) {
		return $texts;
	}

	$texts = array(
		// Excellent options of weapons, pendants (ExcellentOptions.cs: attack options), armor and rings (defense options).
		'excellent'    => array(
			/* translators: %d: divisor, e.g. 8 for one eighth of the maximum mana. */
			'Mana recover after Monster kill, multiplier of max mana'     => array( __( 'Increases acquisition rate of Mana after hunting monsters +Mana/%d', 'openmu-wp-connect' ), 'inverse' ),
			/* translators: %d: divisor, e.g. 8 for one eighth of the maximum life. */
			'Health recover after Monster kill, multiplier of max health' => array( __( 'Increases acquisition rate of Life after hunting monsters +life/%d', 'openmu-wp-connect' ), 'inverse' ),
			/* translators: %d: attack speed bonus. */
			'Attack Speed Any'                                            => array( __( 'Increase Attacking(Wizardry)speed +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: damage bonus in percent. */
			'Physical Base Damage Increase'                               => array( __( 'Increase Damage +%d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: wizardry damage bonus in percent. */
			'Wizardry Base Damage Increase'                               => array( __( 'Increase Wizardry Dmg +%d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: divisor of the character level, e.g. 20. */
			'Physical Base Damage (min and max)'                          => array( __( 'Increase Damage +level/%d', 'openmu-wp-connect' ), 'per_level' ),
			/* translators: %d: divisor of the character level, e.g. 20. */
			'Wizardry Base Damage (min and max)'                          => array( __( 'Increase Wizardry Dmg +level/%d', 'openmu-wp-connect' ), 'per_level' ),
			/* translators: %d: chance in percent. */
			'Excellent Damage Chance'                                     => array( __( 'Excellent Damage rate +%d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: bonus in percent. */
			'Money Drop Amount Rate'                                      => array( __( 'Increases acquisition rate of Zen after hunting monsters +%d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: bonus in percent. */
			'Defense Rate (PvM)'                                          => array( __( 'Defense success rate +%d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: reflected damage in percent. */
			'Damage Reflection'                                           => array( __( 'Reflect Damage +%d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: decrease in percent. */
			'Armor Damage Decrease'                                       => array( __( 'Damage Decrease +%d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: bonus in percent. */
			'Maximum Mana'                                                => array( __( 'Increase Max Mana +%d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: bonus in percent. */
			'Maximum Health'                                              => array( __( 'Increase Max HP +%d%%', 'openmu-wp-connect' ), 'percent' ),
		),

		// Options of the 2nd and 3rd wings and the capes (Wings.cs, CreateWingOption).
		'wing'         => array(
			/* translators: %d: added maximum life. */
			'Maximum Health'                                  => array( __( 'HP +%d increased', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: added maximum mana. */
			'Maximum Mana'                                    => array( __( 'Mana +%d increased', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: chance in percent. */
			'Defense Ignore Chance'                           => array( __( "Ignore opponent's defensive power by %d%%", 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: added command (leadership). */
			'Total Leadership'                                => array( __( 'Increase command +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: chance in percent. */
			'Chance to fully reflect damage when getting hit' => array( __( "Returns the enemy's attack power in %d%%", 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: chance in percent. */
			'Chance to fully recover health when getting hit' => array( __( 'Complete recovery of life in %d%% rate', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: chance in percent. */
			'Chance to fully recover mana when getting hit'   => array( __( 'Complete recovery of Mana in %d%% rate', 'openmu-wp-connect' ), 'percent' ),
		),

		// The regular "+option" (Jewel of Life) of weapons, armor, shields, wings and jewelry, and the Dinorant options.
		'option'       => array(
			/* translators: %d: added damage, e.g. 16. */
			'Physical Base Damage (min and max)'                     => array( __( 'Additional Dmg +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: added wizardry damage. */
			'Wizardry Base Damage (min and max)'                     => array( __( 'Additional Wizardry Dmg +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: added curse damage. */
			'Curse Base Damage (min and max)'                        => array( __( 'Additional Curse Spell +%d', 'openmu-wp-connect' ), 'int' ),
			// Magic Gladiator swords: the client writes the damage and the wizardry damage line.
			'Base Damage Bonus (physical and wizardry, min and max)' => array(
				/* translators: %d: added damage. */
				array( __( 'Additional Dmg +%d', 'openmu-wp-connect' ), 'int' ),
				/* translators: %d: added wizardry damage. */
				array( __( 'Additional Wizardry Dmg +%d', 'openmu-wp-connect' ), 'int' ),
			),
			/* translators: %d: added defense. */
			'Base Defense'                                           => array( __( 'Additional defense +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: added defense rate. */
			'Defense Rate (PvM)'                                     => array( __( 'Additional defense rate +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: recovery in percent. */
			'Health Recovery Multiplier'                             => array( __( 'Automatic HP recovery %d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: bonus in percent. */
			'Maximum Mana'                                           => array( __( 'Max mana increased by %d%%', 'openmu-wp-connect' ), 'percent' ),
			'Maximum Ability'                                        => array(
				'by_aggregate' => array(
					/* translators: %d: bonus in percent (Pendant of Ability). */
					'Multiplicate' => array( __( 'Max AG increased by %d%%', 'openmu-wp-connect' ), 'percent' ),
					/* translators: %d: added maximum AG (Horn of Dinorant). */
					'*'            => array( __( 'Max AG +%d increased', 'openmu-wp-connect' ), 'int' ),
				),
			),
			/* translators: %d: absorbed damage in percent (Horn of Dinorant). */
			'Damage Receive Multiplier'                              => array( __( 'Absorb %d%% additional damage', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: attack speed bonus (Horn of Dinorant). */
			'Attack Speed Any'                                       => array( __( 'Increase Attacking(Wizardry)speed +%d', 'openmu-wp-connect' ), 'int' ),
		),

		// Jewel of Harmony options (HarmonyOptions.cs); names of the client's JewelOfHarmonyOption_eng.bmd.
		'harmony'      => array(
			/* translators: %d: harmony option value. */
			'Minimum Physical Base Damage'           => array( __( 'Min. attack increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Maximum Physical Base Damage'           => array( __( 'Max. attack increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Strength Requirement reduction'         => array( __( 'Required strength decrease +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Agility Requirement reduction'          => array( __( 'Required agility decrease +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Physical Base Damage (min and max)'     => array( __( 'Attack increase(Min,Max) +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Wizardry Base Damage (min and max)'     => array( __( 'Wizardry increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Critical Damage Bonus'                  => array( __( 'Critical damage increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Skill Damage Bonus'                     => array( __( 'Skill attack increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Attack Rate (PvP)'                      => array( __( 'Attack success rate increase(PVP) +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'SD decrease rate increase'              => array( __( 'SD decrement increase +%d', 'openmu-wp-connect' ), 'fraction' ),
			/* translators: %d: harmony option value. */
			'Shield Bypass Chance'                   => array( __( 'SD bypass rate increase +%d', 'openmu-wp-connect' ), 'fraction' ),
			/* translators: %d: harmony option value. */
			'Base Defense'                           => array( __( 'Defense increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Maximum Ability'                        => array( __( 'Max. AG increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Maximum Health'                         => array( __( 'Max. HP increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Health Recovery Absolute Increase'      => array( __( 'Life auto increment increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Mana Recovery Absolute Increase'        => array( __( 'Mana auto increment increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: harmony option value. */
			'Defense Rate (PvP)'                     => array( __( 'Defense success rate increase(PVP) +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: decrease in percent. */
			'Armor Damage Decrease'                  => array( __( 'Damage decrement increase +%d%%', 'openmu-wp-connect' ), 'fraction' ),
			/* translators: %d: harmony option value. */
			'SD Rate Increase'                       => array( __( 'SD ratio increase +%d', 'openmu-wp-connect' ), 'fraction' ),
		),

		// Options of the level 380 items, added with a Jewel of Guardian (GuardianOptions.cs).
		'guardian'     => array(
			/* translators: %d: bonus. */
			'Attack Rate (PvP)'                => array( __( 'Attack success rate increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: bonus. */
			'Final Damage Increase (PvP)'      => array( __( 'Additional Damage +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: bonus. */
			'Defense Rate (PvP)'               => array( __( 'Defense success rate increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: bonus. */
			'Defense (PvP)'                    => array( __( 'Defensive skill +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: bonus. */
			'Maximum Health'                   => array( __( 'Max. HP increase +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: bonus. */
			'Maximum Shield'                   => array( __( 'Max. SD increase +%d', 'openmu-wp-connect' ), 'int' ),
			'Shield Recovery Active Everywhere' => array( __( 'SD auto recovery', 'openmu-wp-connect' ), 'none' ),
			/* translators: %d: bonus in percent. */
			'Shield Recovery Multiplier'       => array( __( 'SD recovery rate increase +%d%%', 'openmu-wp-connect' ), 'int' ),
		),

		// The +5/+10 stat option of ancient items (AncientSets.cs); the client calls stamina what OpenMU calls vitality.
		'ancientBonus' => array(
			/* translators: %d: added strength. */
			'Total Strength' => array( __( 'Increase strength +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: added agility. */
			'Total Agility'  => array( __( 'Increase agility +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: added stamina (vitality). */
			'Total Vitality' => array( __( 'Increase stamina +%d', 'openmu-wp-connect' ), 'int' ),
			/* translators: %d: added energy. */
			'Total Energy'   => array( __( 'Increase energy +%d', 'openmu-wp-connect' ), 'int' ),
		),

		// Fenrir options (Pets.cs), keyed by "type|attribute"; the Gold Fenrir has four.
		'pet'          => array(
			/* translators: %d: bonus in percent. */
			'Black Fenrir Option|Attack Damage Increase'                 => array( __( 'Increase final damage %d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %d: absorbed damage in percent. */
			'Blue Fenrir Option|Damage Receive Multiplier'               => array( __( 'Absorb final damage %d%%', 'openmu-wp-connect' ), 'percent' ),
			/* translators: %s: added life, e.g. "200" or "level/2". */
			'Gold Fenrir Option|Maximum Health'                          => array( __( 'Added %s of Life', 'openmu-wp-connect' ), 'level' ),
			/* translators: %s: added mana, e.g. "200" or "level/2". */
			'Gold Fenrir Option|Maximum Mana'                            => array( __( 'Added %s of Mana', 'openmu-wp-connect' ), 'level' ),
			/* translators: %s: added damage, e.g. "33" or "level/12". */
			'Gold Fenrir Option|Physical Base Damage (min and max)'      => array( __( 'Added %s Attack', 'openmu-wp-connect' ), 'level' ),
			/* translators: %s: added wizardry damage, e.g. "16" or "level/25". */
			'Gold Fenrir Option|Wizardry Base Damage (min and max)'      => array( __( 'Added %s Wizardry', 'openmu-wp-connect' ), 'level' ),
		),

		// Seed spheres (SocketSystem.cs): attribute => [element, option name of socketitem_eng.bmd, value mode].
		'socket'       => array(
			'Base Damage Bonus (physical and wizardry, min and max)|related' => array( __( 'Fire', 'openmu-wp-connect' ), __( '(Level type)Attack/Wizardry increase', 'openmu-wp-connect' ), 'per_level' ),
			'Attack Speed Any'                                       => array( __( 'Fire', 'openmu-wp-connect' ), __( 'Attack speed increase', 'openmu-wp-connect' ), 'int' ),
			'Base Max Damage Bonus (physical and wizardry)'          => array( __( 'Fire', 'openmu-wp-connect' ), __( 'Maximum attack/Wizardry increase', 'openmu-wp-connect' ), 'int' ),
			'Base Min Damage Bonus (physical and wizardry)'          => array( __( 'Fire', 'openmu-wp-connect' ), __( 'Minimum attack/Wizardry increase', 'openmu-wp-connect' ), 'int' ),
			'Base Damage Bonus (physical and wizardry, min and max)' => array( __( 'Fire', 'openmu-wp-connect' ), __( 'Attack/Wizardry increase', 'openmu-wp-connect' ), 'int' ),
			'Ability Usage Reduction'                                => array( __( 'Fire', 'openmu-wp-connect' ), __( 'AG cost decrease', 'openmu-wp-connect' ), 'percent' ),
			'Defense Rate (PvM)'                                     => array( __( 'Water', 'openmu-wp-connect' ), __( 'Block rating increase', 'openmu-wp-connect' ), 'percent' ),
			'Final Defense'                                          => array( __( 'Water', 'openmu-wp-connect' ), __( 'Defense increase', 'openmu-wp-connect' ), 'int' ),
			'Shield Item Defense Increase Multiplier'                => array( __( 'Water', 'openmu-wp-connect' ), __( 'Shield protection increase', 'openmu-wp-connect' ), 'percent' ),
			'Armor Damage Decrease'                                  => array( __( 'Water', 'openmu-wp-connect' ), __( 'Damage reduction', 'openmu-wp-connect' ), 'percent' ),
			'Damage Reflection'                                      => array( __( 'Water', 'openmu-wp-connect' ), __( 'Damage reflection', 'openmu-wp-connect' ), 'percent' ),
			'Health recover after Monster kill, multiplier of max health' => array( __( 'Ice', 'openmu-wp-connect' ), __( 'Monster destruction for the Life increase', 'openmu-wp-connect' ), 'inverse' ),
			'Mana recover after Monster kill, multiplier of max mana' => array( __( 'Ice', 'openmu-wp-connect' ), __( 'Monster destruction for the Mana increase', 'openmu-wp-connect' ), 'inverse' ),
			'Skill Damage Bonus'                                     => array( __( 'Ice', 'openmu-wp-connect' ), __( 'Skill attack increase', 'openmu-wp-connect' ), 'int' ),
			'Attack Rate (PvM)'                                      => array( __( 'Ice', 'openmu-wp-connect' ), __( 'Attack rating increase', 'openmu-wp-connect' ), 'int' ),
			'Weapon Duration Increase'                               => array( __( 'Ice', 'openmu-wp-connect' ), __( 'Item durability increase', 'openmu-wp-connect' ), 'percent' ),
			'Health Recovery Absolute Increase'                      => array( __( 'Wind', 'openmu-wp-connect' ), __( 'Automatic Life recovery increase', 'openmu-wp-connect' ), 'int' ),
			'Maximum Health|Multiplicate'                            => array( __( 'Wind', 'openmu-wp-connect' ), __( 'Maximum Life increase', 'openmu-wp-connect' ), 'percent' ),
			'Maximum Mana'                                           => array( __( 'Wind', 'openmu-wp-connect' ), __( 'Maximum Mana increase', 'openmu-wp-connect' ), 'percent' ),
			'Mana Recovery Absolute Increase'                        => array( __( 'Wind', 'openmu-wp-connect' ), __( 'Automatic Mana recovery increase', 'openmu-wp-connect' ), 'int' ),
			'Maximum Ability'                                        => array( __( 'Wind', 'openmu-wp-connect' ), __( 'Maximum AG increase', 'openmu-wp-connect' ), 'int' ),
			'Ability Recovery Absolute Increase'                     => array( __( 'Wind', 'openmu-wp-connect' ), __( 'AG value increase', 'openmu-wp-connect' ), 'int' ),
			'Excellent Damage Bonus'                                 => array( __( 'Lightning', 'openmu-wp-connect' ), __( 'Excellent damage increase', 'openmu-wp-connect' ), 'int' ),
			'Excellent Damage Chance'                                => array( __( 'Lightning', 'openmu-wp-connect' ), __( 'Excellent damage rate increase', 'openmu-wp-connect' ), 'percent' ),
			'Critical Damage Bonus'                                  => array( __( 'Lightning', 'openmu-wp-connect' ), __( 'Critical damage increase', 'openmu-wp-connect' ), 'int' ),
			'Critical Damage Chance'                                 => array( __( 'Lightning', 'openmu-wp-connect' ), __( 'Critical damage rate increase', 'openmu-wp-connect' ), 'percent' ),
			'Maximum Health'                                         => array( __( 'Earth', 'openmu-wp-connect' ), __( 'Health increase', 'openmu-wp-connect' ), 'int' ),
		),

		// Socket bonus options by their number (SocketSystem.cs, CreateSocketBonusOption).
		'socketBonus'  => array(
			0 => __( 'Attack power increase', 'openmu-wp-connect' ),
			1 => __( 'Skill attack increase', 'openmu-wp-connect' ),
			2 => __( 'Attack/Wizardry increase', 'openmu-wp-connect' ),
			3 => __( 'Skill attack increase', 'openmu-wp-connect' ),
			4 => __( 'Defense increase', 'openmu-wp-connect' ),
			5 => __( 'Maximum Life increase', 'openmu-wp-connect' ),
		),
	);

	return $texts;
}
