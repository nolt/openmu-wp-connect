<?php
/**
 * The full item tooltip of the armory, computed like the Season 6 game client computes it.
 *
 * The website API of OpenMU sends an equipped item as its identity (group, number), its + level,
 * the excellent/ancient flags, the ancient set name and the options. Everything else the game
 * shows in the tooltip comes from the client's own item data. The plugin doesn't ship it: the site
 * owner may put it in data/ of the client assets folder (client-assets.php; items.json,
 * item-sets.json, skills.json, generated from the MuMain client's Data/Items/*.json and the
 * Local/Eng .bmd files, see "_source" in the files). Without it the tooltip has the option lines
 * of item-options.php only. With it the tooltip adds:
 *
 * - the white stat lines: damage, defense, defense rate, attack speed, the item's description
 *   (e.g. the wings' "Increase 69% of Damage"), durability, resistances and the requirements,
 *   reproducing the client's formulas for the + level, excellent and ancient items
 *   (GameLogic/Items/ItemStats.cpp, Data/Items/Stats/ItemStats.json, CalcMaxDurability in
 *   Engine/Object/ZzzInventory.cpp, Engine/Object/ItemTooltipValues.cpp);
 * - the class lines ("Can be equipped by Dark Knight"), without the client's red marks: a visitor
 *   of the site has no class, so every class that can wear the item is listed plainly;
 * - the skill line with the skill's mana ("Cyclone Cutting skill (Mana:9)", GetSpecialOptionText);
 * - the Horn of Fenrir's closing lines and the ancient set block ("Set Item option info" with the
 *   set's bonus list), counting the worn pieces of the set like the client (CSItemOption.cpp).
 *
 * The line order is the client's default tooltip layout (Data/Items/Tooltips/Tooltip.json):
 * stat lines, requirements, class lines, options (item-options.php), set block, sockets.
 *
 * What the API doesn't tell is filled in as follows: the current durability (and the life of a
 * pet) is shown as the maximum, as for a fully repaired item; the number of arrows isn't shown.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the client item data from the client assets folder once per request.
 *
 * @return array{items: array, stats: array, texts: array, sets: array, skills: array} The data; empty
 *         lists when a file is missing, so the armory falls back to the option lines.
 */
function openmu_wp_connect_item_data(): array {
	static $data = null;
	if ( null !== $data ) {
		return $data;
	}

	$data = array(
		'items'  => array(),
		'stats'  => array(),
		'texts'  => array(),
		'sets'   => array(),
		'skills' => array(),
	);
	$folder = openmu_wp_connect_client_assets()['path'];
	if ( '' === $folder ) {
		return $data;
	}
	foreach ( array( 'items.json', 'item-sets.json', 'skills.json' ) as $file ) {
		$path = $folder . 'data/' . $file;
		if ( ! is_readable( $path ) ) {
			continue;
		}
		$json = wp_json_file_decode( $path, array( 'associative' => true ) );
		if ( is_array( $json ) ) {
			unset( $json['_source'] );
			$data = array_merge( $data, $json );
		}
	}

	return $data;
}

/**
 * Returns the client's definition of an item.
 *
 * @param array $item Item of the website API (keys group, number).
 * @return array|null The item record of data/items.json, or null for an unknown item.
 */
function openmu_wp_connect_item_definition( array $item ): ?array {
	$key = (int) ( $item['group'] ?? -1 ) . '-' . (int) ( $item['number'] ?? -1 );
	return openmu_wp_connect_item_data()['items'][ $key ] ?? null;
}

/**
 * Builds all tooltip lines of an item of the website API, in the client's order.
 *
 * Falls back to the option lines of openmu_wp_connect_item_lines() for an item the client data doesn't know.
 *
 * @param array $item      Item of the website API.
 * @param array $character The character of the website API (keys level, equippedItems), for the
 *                         Gold Fenrir values and the number of worn pieces of an ancient set.
 * @return array<int, array{text: string, kind: string}> The lines; kind gap is an empty row.
 */
function openmu_wp_connect_item_tooltip_lines( array $item, array $character = array() ): array {
	$character_level = (int) ( $character['level'] ?? 0 );
	$definition      = openmu_wp_connect_item_definition( $item );
	if ( null === $definition ) {
		return openmu_wp_connect_item_lines( $item, $character_level );
	}

	$values = openmu_wp_connect_item_values( $definition, $item );
	$parts  = openmu_wp_connect_item_option_parts( $item, $character_level );

	$lines   = openmu_wp_connect_item_stat_lines( $definition, $item, $values, $character_level );
	$lines[] = openmu_wp_connect_item_gap();
	$lines   = array_merge( $lines, $parts['options'] );
	$lines[] = openmu_wp_connect_item_gap();
	$lines   = array_merge( $lines, openmu_wp_connect_item_description_lines( $definition, $item, $values, $character_level, true ) );
	$lines   = array_merge( $lines, openmu_wp_connect_item_fenrir_lines( $item ) );
	$lines   = array_merge( $lines, openmu_wp_connect_item_set_lines( $definition, $item, (array) ( $character['equippedItems'] ?? array() ) ) );
	$lines   = array_merge( $lines, $parts['sockets'] );

	return openmu_wp_connect_item_tidy_gaps( $lines );
}

/**
 * Returns an empty row of the tooltip.
 *
 * @return array{text: string, kind: string}
 */
function openmu_wp_connect_item_gap(): array {
	return array(
		'text' => '',
		'kind' => 'gap',
	);
}

/**
 * Drops empty rows at the start and the end and merges empty rows that follow each other,
 * as the client draws its blank rows (the layout has several in a row).
 *
 * @param array $lines Tooltip lines.
 * @return array The lines.
 */
function openmu_wp_connect_item_tidy_gaps( array $lines ): array {
	$result = array();
	foreach ( $lines as $line ) {
		$gap = 'gap' === $line['kind'];
		if ( $gap && ( array() === $result || 'gap' === end( $result )['kind'] ) ) {
			continue;
		}
		$result[] = $line;
	}
	while ( array() !== $result && 'gap' === end( $result )['kind'] ) {
		array_pop( $result );
	}

	return $result;
}

/**
 * Computes the numbers of the tooltip of an item: stats, durability, requirements, resistances.
 *
 * Ports ComputeItemStats() and ComputeRequirements() of GameLogic/Items/ItemStats.cpp,
 * ComputeItemTooltipValues() of Engine/Object/ItemTooltipValues.cpp (harmony bonuses and
 * reductions) and CalcMaxDurability() of Engine/Object/ZzzInventory.cpp.
 *
 * @param array $definition Item record of data/items.json.
 * @param array $item       Item of the website API.
 * @return array<string, mixed> The values, named like the client's tooltip placeholders.
 */
function openmu_wp_connect_item_values( array $definition, array $item ): array {
	$data      = openmu_wp_connect_item_data();
	$profiles  = (array) ( $data['stats']['profiles'] ?? array() );
	$profile   = $profiles[ $definition['profile'] ?? 'default' ] ?? $profiles['default'] ?? array();
	$level     = max( 0, min( 15, (int) ( $item['level'] ?? 0 ) ) );
	$wing      = 'wings' === ( $definition['slot'] ?? '' );
	$excellent = ! empty( $item['isExcellent'] );
	$ancient   = ! empty( $item['isAncient'] ) || '' !== (string) ( $item['ancientSetName'] ?? '' );
	$tags      = (array) ( $definition['tags'] ?? array() );

	// The level of the "+option" (Jewel of Life): its strength requirement grows with it.
	$option_level = 0;
	$harmony      = array(
		'min'       => 0,
		'max'       => 0,
		'defense'   => 0,
		'strength'  => 0,
		'dexterity' => 0,
	);
	foreach ( (array) ( $item['options'] ?? array() ) as $option ) {
		$type = (string) ( $option['type'] ?? '' );
		if ( 'Option' === $type ) {
			$option_level = (int) ( $option['level'] ?? 0 );
		} elseif ( in_array( $type, array( 'Jewel of Harmony Option', 'Harmony Option' ), true ) && $level >= (int) ( $option['level'] ?? 0 ) ) {
			// The client counts a harmony option only from the item level it needs (HarmonyBonus).
			$value   = (int) round( (float) ( $option['value'] ?? 0 ) );
			$targets = match ( (string) ( $option['attribute'] ?? '' ) ) {
				'Minimum Physical Base Damage'       => array( 'min' ),
				'Maximum Physical Base Damage'       => array( 'max' ),
				'Physical Base Damage (min and max)' => array( 'min', 'max' ),
				'Base Defense'                       => array( 'defense' ),
				'Strength Requirement reduction'     => array( 'strength' ),
				'Agility Requirement reduction'      => array( 'dexterity' ),
				default                              => array(),
			};
			foreach ( $targets as $target ) {
				$harmony[ $target ] += $value;
			}
		}
	}

	// Stats (ComputeItemStats): ancient items count as excellent here, wings' options don't.
	$context = array(
		'level'     => $level,
		'dropLevel' => (int) ( $definition['drop'] ?? 0 ),
		'excellent' => ( $excellent && ! $wing ) || $ancient,
		'ancient'   => $ancient,
		'fixed'     => (int) ( $definition['excBonus'] ?? 0 ),
	);
	$damage     = (array) ( $profile['damage'] ?? array() );
	$damage_min = openmu_wp_connect_item_add_up_stat( $damage, (int) ( $definition['dmin'] ?? 0 ), (int) ( $definition['dmin'] ?? 0 ), true, $context );
	$damage_max = openmu_wp_connect_item_add_up_stat( $damage, (int) ( $definition['dmax'] ?? 0 ), (int) ( $definition['dmin'] ?? 0 ), true, $context );

	$magic_power = 0;
	$magic_base  = (int) ( $definition['magic'] ?? 0 );
	if ( $magic_base > 0 ) {
		$terms       = (array) ( $profile['magicPower'] ?? array() );
		$sum         = openmu_wp_connect_item_add_up_stat( $terms, $magic_base, $magic_base, true, $context );
		$magic_power = openmu_wp_connect_item_limit( intdiv( $sum, 2 ) + (int) ( $terms['half'] ?? 0 ) * $level );
	}

	$block_base = (int) ( $definition['block'] ?? 0 );
	$def_base   = (int) ( $definition['def'] ?? 0 );
	$mdef_base  = (int) ( $definition['mdef'] ?? 0 );
	$block_rate = min( 255, openmu_wp_connect_item_add_up_stat( (array) ( $profile['blockRate'] ?? array() ), $block_base, $block_base, false, $context ) );
	$defense    = openmu_wp_connect_item_add_up_stat( (array) ( $profile['defense'] ?? array() ), $def_base, $def_base, false, $context );
	$magic_def  = openmu_wp_connect_item_add_up_stat( (array) ( $profile['magicDefense'] ?? array() ), $mdef_base, $mdef_base, false, $context );

	// Damage as the tooltip shows it (ComputeDamage): a harmony bonus that lifts the minimum to the maximum shows the maximum twice.
	$shown_max = $damage_max + $harmony['max'];
	$shown_min = min( $damage_min + $harmony['min'], $shown_max );

	$requirements = openmu_wp_connect_item_requirements( $definition, (array) ( $profile['req'] ?? array() ), $level, $excellent, $ancient, $wing, $option_level );

	$resistances = array();
	foreach ( array( 'ice', 'poison', 'lightning', 'fire', 'earth', 'wind', 'water' ) as $index => $element ) {
		$resistances[ $element ] = ! empty( $definition['res'][ $index ] ) ? $level + 1 : 0;
	}

	$max_durability = openmu_wp_connect_item_max_durability( $definition, $level, $excellent, $ancient, $tags );

	return array(
		'level'              => $level,
		'excellent'          => $excellent,
		'ancient'            => $ancient,
		'hasDamage'          => $damage_min > 0,
		'damageMin'          => $shown_min,
		'damageMax'          => $shown_max,
		'damageHarmony'      => 0 !== $harmony['min'] || 0 !== $harmony['max'],
		'defense'            => $defense + $harmony['defense'],
		'defenseHarmony'     => 0 !== $harmony['defense'],
		'magicDefense'       => $magic_def,
		'blockRate'          => $block_rate,
		'attackSpeed'        => (int) ( $definition['speed'] ?? 0 ),
		'walkSpeed'          => (int) ( $definition['walk'] ?? 0 ),
		'magicPower'         => $magic_power,
		// The API doesn't send the wear of an item: shown as fully repaired.
		'durability'         => $max_durability,
		'maxDurability'      => $max_durability,
		'resistances'        => $resistances,
		'requiredLevel'      => $requirements['level'],
		'requiredStrength'   => $requirements['strength'] - $harmony['strength'],
		'requiredDexterity'  => $requirements['dexterity'] - $harmony['dexterity'],
		'requiredEnergy'     => $requirements['energy'],
		'requiredVitality'   => $requirements['vitality'],
		'requiredLeadership' => $requirements['leadership'],
		'strengthAsked'      => 0 !== $requirements['strength'],
		'dexterityAsked'     => 0 !== $requirements['dexterity'],
		'strengthReduced'    => 0 !== $harmony['strength'],
		'dexterityReduced'   => 0 !== $harmony['dexterity'],
		'tags'               => $tags,
	);
}

/**
 * Keeps a value in the range of the client's item fields (0 to 65535), like LimitToItemValue().
 *
 * @param int $value The value.
 * @return int The limited value.
 */
function openmu_wp_connect_item_limit( int $value ): int {
	return max( 0, min( 65535, $value ) );
}

/**
 * Returns what the + level adds to a stat, from a bonus table of ItemStats.json (LevelBonusTable::GetBonus).
 *
 * @param string|null $table Table name, or null for none.
 * @param int         $level Item level.
 * @return int The bonus.
 */
function openmu_wp_connect_item_level_bonus( ?string $table, int $level ): int {
	$tables = openmu_wp_connect_item_data()['stats']['tables'] ?? array();
	if ( null === $table || ! isset( $tables[ $table ] ) ) {
		return 0;
	}

	return (int) ( $tables[ $table ]['v'][ max( 0, min( 15, $level ) ) ] ?? 0 ) + (int) $tables[ $table ]['p'] * max( 0, $level );
}

/**
 * Adds up one stat: base + excellent part + ancient part + level bonus (AddUpStat in ItemStats.cpp).
 *
 * Every division is a whole-number division from left to right, as in the client.
 *
 * @param array $terms          Terms of the stat profile (table, exc, anc).
 * @param int   $base           Base value of the item.
 * @param int   $excellent_base Value the excellent part is computed from (the minimum damage for both damage values).
 * @param bool  $fixed_counts   Whether a fixed excellent bonus of the item replaces the excellent part (damage, wizardry).
 * @param array $context        Item level, drop level, excellent and ancient flags, fixed bonus.
 * @return int The value.
 */
function openmu_wp_connect_item_add_up_stat( array $terms, int $base, int $excellent_base, bool $fixed_counts, array $context ): int {
	if ( 0 === $base ) {
		return 0;
	}

	$drop_level = $context['dropLevel'];
	$excellent  = 0;
	$exc        = $terms['exc'] ?? null;
	if ( $context['excellent'] && $drop_level > 0 && is_array( $exc ) ) {
		$excellent = $fixed_counts && 0 !== $context['fixed']
			? $context['fixed']
			: intdiv( $excellent_base * $exc['t'], $drop_level ) + $exc['a'] + ( $exc['d'] > 0 ? intdiv( $drop_level, $exc['d'] ) : 0 );
	}

	$level_bonus = openmu_wp_connect_item_level_bonus( $terms['table'] ?? null, $context['level'] );

	$ancient = 0;
	$anc     = $terms['anc'] ?? null;
	if ( $context['ancient'] && is_array( $anc ) ) {
		$ancient_drop = $drop_level + (int) ( openmu_wp_connect_item_data()['stats']['dropLevelBonus']['ancient'] ?? 0 );
		$value        = $base + $excellent + ( $anc['after'] ? $level_bonus : 0 );
		$ancient      = ( $ancient_drop > 0 ? intdiv( $value * $anc['t'], $ancient_drop ) : 0 ) + $anc['a'] + ( $anc['d'] > 0 ? intdiv( $ancient_drop, $anc['d'] ) : 0 );
	}

	return openmu_wp_connect_item_limit( $base + $excellent + $ancient + $level_bonus );
}

/**
 * Computes the requirements of an item (ComputeRequirements in ItemStats.cpp).
 *
 * @param array $definition   Item record of data/items.json.
 * @param array $terms        Requirement terms of the stat profile.
 * @param int   $level        Item level.
 * @param bool  $excellent    Whether the item has excellent options.
 * @param bool  $ancient      Whether the item is an ancient set item.
 * @param bool  $wing         Whether the item is worn in the wings slot.
 * @param int   $option_level Level of the "+option".
 * @return array{level: int, strength: int, dexterity: int, energy: int, vitality: int, leadership: int}
 */
function openmu_wp_connect_item_requirements( array $definition, array $terms, int $level, bool $excellent, bool $ancient, bool $wing, int $option_level ): array {
	$base     = array_combine( array( 'level', 'strength', 'dexterity', 'energy', 'vitality', 'leadership' ), array_pad( array_map( 'intval', (array) ( $definition['req'] ?? array() ) ), 6, 0 ) );
	$by_level = (array) ( $definition['reqByLevel'] ?? array() );

	if ( isset( $by_level['level'] ) ) {
		$required_level = (int) ( $by_level['level'][ $level ] ?? 0 );
	} elseif ( $base['level'] > 0 ) {
		$required_level = openmu_wp_connect_item_limit( $base['level'] + (int) ( $terms['lvPer'] ?? 0 ) * $level );
	} else {
		$required_level = 0;
	}
	$raises_level = (int) ( $definition['excRaisesLevel'] ?? 1 ) > 0;
	if ( $excellent && $required_level > 0 && ! $wing && $raises_level ) {
		$required_level = openmu_wp_connect_item_limit( $required_level + (int) ( $terms['excAdd'] ?? 0 ) );
	}

	$level_basis = isset( $by_level['level'] ) ? (int) ( $by_level['level'][ $level ] ?? 0 ) : $base['level'];
	$bonuses     = openmu_wp_connect_item_data()['stats']['dropLevelBonus'] ?? array();
	$drop_level  = (int) ( $definition['drop'] ?? 0 ) + ( $excellent ? (int) ( $bonuses['excellent'] ?? 0 ) : ( $ancient ? (int) ( $bonuses['ancient'] ?? 0 ) : 0 ) );

	$result = array( 'level' => $required_level );
	foreach ( array( 'strength', 'dexterity', 'energy', 'vitality', 'leadership' ) as $stat ) {
		if ( isset( $by_level[ $stat ] ) ) {
			$result[ $stat ] = (int) ( $by_level[ $stat ][ $level ] ?? 0 );
			continue;
		}

		$formula = $terms[ $stat ] ?? 'base';
		$value   = $base[ $stat ];
		if ( 0 === $value || ! is_array( $formula ) ) {
			// No requirement, or the formula keeps the base value.
			$result[ $stat ] = $value;
		} elseif ( $formula['basis'] && $level_basis > 0 ) {
			$result[ $stat ] = openmu_wp_connect_item_limit( $formula['add'] + intdiv( $value * $level_basis * $formula['pct'], 100 ) );
		} else {
			$weighted        = min( $value * ( $drop_level + $level * $formula['w'] ), 100 * 65536 );
			$result[ $stat ] = openmu_wp_connect_item_limit( $formula['add'] + intdiv( $weighted * $formula['pct'], 100 ) );
		}
	}
	$result['strength'] = openmu_wp_connect_item_limit( $result['strength'] + (int) ( $terms['strOpt'] ?? 0 ) * $option_level );

	return $result;
}

/**
 * Computes the maximum durability of an item (CalcMaxDurability in Engine/Object/ZzzInventory.cpp).
 *
 * @param array $definition Item record of data/items.json.
 * @param int   $level      Item level.
 * @param bool  $excellent  Whether the item has excellent options.
 * @param bool  $ancient    Whether the item is an ancient set item.
 * @param array $tags       Item tags of the client.
 * @return int The maximum durability.
 */
function openmu_wp_connect_item_max_durability( array $definition, int $level, bool $excellent, bool $ancient, array $tags ): int {
	$durability = (int) ( $definition['mdur'] ?? 0 ) > 0 ? (int) $definition['mdur'] : (int) ( $definition['dur'] ?? 0 );

	// Each + level adds 1 (+1 to +4), 2 (+5 to +9), then 3, 4, 5, 6, 7 and 8; the last two stop at 255.
	for ( $step = 0; $step < $level && empty( $definition['durFixed'] ); $step++ ) {
		if ( $step >= 14 ) {
			$durability = min( 255, $durability + 8 );
		} elseif ( 13 === $step ) {
			$durability = min( 255, $durability + 7 );
		} elseif ( $step >= 9 ) {
			$durability += $step - 6;
		} elseif ( $step >= 4 ) {
			$durability += 2;
		} else {
			++$durability;
		}
	}

	if ( in_array( 'darkLordPet', $tags, true ) ) {
		$durability = 255;
	}

	$upper_wing = in_array( $definition['wing'] ?? '', array( 'second', 'third' ), true );
	if ( $ancient ) {
		$durability += 20;
	} elseif ( $excellent && ! $upper_wing && ! in_array( 'divineArchangelWeapon', $tags, true ) ) {
		$durability += 15;
	}

	return in_array( 'luckyItem', $tags, true ) ? 255 : $durability;
}

/**
 * Builds the lines before the options: stats, description, durability, resistances,
 * requirements, class lines and the wizardry line of staffs, in the client's default layout.
 *
 * @param array $definition      Item record of data/items.json.
 * @param array $item            Item of the website API.
 * @param array $values          The values of openmu_wp_connect_item_values().
 * @param int   $character_level Level of the wearer, for descriptions that name it.
 * @return array<int, array{text: string, kind: string}> The lines.
 */
function openmu_wp_connect_item_stat_lines( array $definition, array $item, array $values, int $character_level ): array {
	$lines  = array();
	$add    = static function ( string $text, string $kind = 'stat' ) use ( &$lines ): void {
		$lines[] = array(
			'text' => $text,
			'kind' => $kind,
		);
	};
	$tags   = $values['tags'];
	$layout = (string) ( $definition['layout'] ?? '' );
	$hidden = (array) ( $definition['layoutChanges']['hide'] ?? array() );
	$armour = in_array( $definition['slot'] ?? '', array( 'helm', 'armor', 'pants', 'gloves', 'boots' ), true );

	if ( $values['hasDamage'] ) {
		$kind = $values['damageHarmony'] ? 'harmony' : ( $values['excellent'] ? 'option' : 'stat' );
		$add(
			! empty( $definition['twoHanded'] )
				/* translators: 1: minimum damage, 2: maximum damage. */
				? sprintf( __( 'Two-Handed Damage: %1$d ~ %2$d', 'openmu-wp-connect' ), $values['damageMin'], $values['damageMax'] )
				/* translators: 1: minimum damage, 2: maximum damage. */
				: sprintf( __( 'One-Handed Damage: %1$d ~ %2$d', 'openmu-wp-connect' ), $values['damageMin'], $values['damageMax'] ),
			$kind
		);
	}
	if ( $values['defense'] > 0 ) {
		/* translators: %d: defense of the item. */
		$add( sprintf( __( 'Defense: %d', 'openmu-wp-connect' ), $values['defense'] ), $values['defenseHarmony'] ? 'harmony' : ( $armour && $values['excellent'] ? 'option' : 'stat' ) );
	}
	if ( $values['magicDefense'] > 0 ) {
		/* translators: %d: spell resistance of the item. */
		$add( sprintf( __( 'Spell resistance: %d', 'openmu-wp-connect' ), $values['magicDefense'] ) );
	}
	if ( $values['blockRate'] > 0 ) {
		/* translators: %d: defense rate of the shield. */
		$add( sprintf( __( 'Defense rate: %d', 'openmu-wp-connect' ), $values['blockRate'] ), $values['excellent'] ? 'option' : 'stat' );
	}
	if ( $values['attackSpeed'] > 0 ) {
		/* translators: %d: attack speed of the weapon. */
		$add( sprintf( __( 'Attack speed: %d', 'openmu-wp-connect' ), $values['attackSpeed'] ) );
	}
	if ( $values['walkSpeed'] > 0 ) {
		/* translators: %d: moving speed bonus of the item. */
		$add( sprintf( __( 'Moving speed: %d', 'openmu-wp-connect' ), $values['walkSpeed'] ) );
	}

	$lines = array_merge( $lines, openmu_wp_connect_item_description_lines( $definition, $item, $values, $character_level, false ) );

	$no_durability = in_array( $layout, array( 'noDurabilityLine', 'noClassOrDurabilityLines' ), true ) || in_array( 'durability', $hidden, true );
	if ( ! empty( $definition['hasDur'] ) && ! $no_durability ) {
		/* translators: 1: current durability, 2: maximum durability. */
		$add( sprintf( __( 'Durability: [%1$d/%2$d]', 'openmu-wp-connect' ), $values['durability'], $values['maxDurability'] ) );
	}

	$resistance_texts = array(
		/* translators: %d: ice resistance. */
		'ice'       => __( 'Ice Resistance: %d', 'openmu-wp-connect' ),
		/* translators: %d: poison resistance. */
		'poison'    => __( 'Poison Resistance: %d', 'openmu-wp-connect' ),
		/* translators: %d: lightning resistance. */
		'lightning' => __( 'Lightning Resistance: %d', 'openmu-wp-connect' ),
		/* translators: %d: fire resistance. */
		'fire'      => __( 'Fire Resistance: %d', 'openmu-wp-connect' ),
		/* translators: %d: earth resistance. */
		'earth'     => __( 'Earth Resistance: %d', 'openmu-wp-connect' ),
		/* translators: %d: wind resistance. */
		'wind'      => __( 'Wind Resistance: %d', 'openmu-wp-connect' ),
		/* translators: %d: water resistance. */
		'water'     => __( 'Water Resistance: %d', 'openmu-wp-connect' ),
	);
	foreach ( $resistance_texts as $element => $format ) {
		if ( $values['resistances'][ $element ] > 0 ) {
			$add( sprintf( $format, $values['resistances'][ $element ] ) );
		}
	}

	// Requirements; the lucky items don't show their stat requirements.
	if ( $values['requiredLevel'] > 0 ) {
		/* translators: %d: minimum character level. */
		$add( sprintf( __( 'Minimum Level Requirement: %d', 'openmu-wp-connect' ), $values['requiredLevel'] ) );
	}
	if ( ! in_array( 'luckyItem', $tags, true ) ) {
		if ( $values['strengthAsked'] ) {
			/* translators: %d: required strength. */
			$add( sprintf( __( 'Strength Requirement: %d', 'openmu-wp-connect' ), $values['requiredStrength'] ), $values['strengthReduced'] ? 'harmony' : 'stat' );
		}
		if ( $values['dexterityAsked'] ) {
			/* translators: %d: required agility. */
			$add( sprintf( __( 'Agility Requirement: %d', 'openmu-wp-connect' ), $values['requiredDexterity'] ), $values['dexterityReduced'] ? 'harmony' : 'stat' );
		}
		if ( $values['requiredVitality'] > 0 ) {
			/* translators: %d: required stamina (vitality). */
			$add( sprintf( __( 'Stamina Requirement: %d', 'openmu-wp-connect' ), $values['requiredVitality'] ) );
		}
		if ( $values['requiredEnergy'] > 0 ) {
			/* translators: %d: required energy. */
			$add( sprintf( __( 'Energy Requirement: %d', 'openmu-wp-connect' ), $values['requiredEnergy'] ) );
		}
		if ( $values['requiredLeadership'] > 0 ) {
			/* translators: %d: required command (leadership). */
			$add( sprintf( __( 'Charisma Requirement: %d', 'openmu-wp-connect' ), $values['requiredLeadership'] ) );
		}
	}

	// Class lines: one per class that can wear the item, unless every first class can.
	$classes    = array_pad( array_map( 'intval', (array) ( $definition['classes'] ?? array() ) ), 7, 0 );
	$no_classes = in_array( $layout, array( 'noClassLines', 'noClassOrDurabilityLines' ), true ) || in_array( 'classLines', $hidden, true );
	if ( ! $no_classes && array( 1, 1, 1, 1, 1, 1, 1 ) !== $classes ) {
		$lines[] = openmu_wp_connect_item_gap();
		foreach ( $classes as $class => $class_level ) {
			$name = 0 !== $class_level ? openmu_wp_connect_item_class_name( $class, $class_level ) : null;
			if ( null !== $name ) {
				/* translators: %s: character class, e.g. "Dark Knight". */
				$add( sprintf( __( 'Can be equipped by %s', 'openmu-wp-connect' ), $name ) );
			}
		}
	}

	// The boots' and gloves' texts from +5, the wizardry line of staffs and books, the sceptres' pet line.
	$level = $values['level'];
	if ( 'boots' === ( $definition['slot'] ?? '' ) && $level >= 5 ) {
		$lines[] = openmu_wp_connect_item_gap();
		$add( __( 'Increases moving speed', 'openmu-wp-connect' ), 'emphasis' );
	}
	if ( 'gloves' === ( $definition['slot'] ?? '' ) && $level >= 5 ) {
		$lines[] = openmu_wp_connect_item_gap();
		$add( __( 'Swimming speed increase', 'openmu-wp-connect' ), 'emphasis' );
	}
	if ( in_array( openmu_wp_connect_item_weapon_kind( $definition ), array( 'staff', 'book' ), true ) || in_array( 'magicSword', $tags, true ) ) {
		$lines[] = openmu_wp_connect_item_gap();
		$add(
			in_array( 'summonerBook', $tags, true )
				/* translators: %d: curse damage bonus in percent. */
				? sprintf( __( 'Curse Spell increment %d%%', 'openmu-wp-connect' ), $values['magicPower'] )
				/* translators: %d: wizardry damage bonus in percent. */
				: sprintf( __( 'Wizardry Dmg %d%% rise', 'openmu-wp-connect' ), $values['magicPower'] ),
			'emphasis'
		);
	}
	if ( in_array( 'sceptre', $tags, true ) ) {
		$lines[] = openmu_wp_connect_item_gap();
		/* translators: %d: pet attack bonus in percent. */
		$add( sprintf( __( 'Increase pet attack as %d%%', 'openmu-wp-connect' ), $values['magicPower'] ), 'emphasis' );
	}

	return $lines;
}

/**
 * Returns the weapon kind of an item of the client data (staffs and books show a wizardry line).
 *
 * @param array $definition Item record of data/items.json.
 * @return string Weapon kind, e.g. "sword", "staff" or "book"; '' for items that aren't weapons.
 */
function openmu_wp_connect_item_weapon_kind( array $definition ): string {
	return (string) ( $definition['kind'] ?? '' );
}

/**
 * Names a class at the class level an item needs (ClassLineName in ZzzInventory.cpp): a level the class
 * skips names its next one (a Magic Gladiator's 2 is the Dual Master).
 *
 * @param int $class       Class index of the client (0 Dark Wizard ... 6 Rage Fighter).
 * @param int $class_level Level the item needs: 1 first class, 2 second, 3 third.
 * @return string|null The class name, or null for a level no character reaches.
 */
function openmu_wp_connect_item_class_name( int $class, int $class_level ): ?string {
	$names = array(
		array( __( 'Dark Wizard', 'openmu-wp-connect' ), __( 'Soul Master', 'openmu-wp-connect' ), __( 'Grand Master', 'openmu-wp-connect' ) ),
		array( __( 'Dark Knight', 'openmu-wp-connect' ), __( 'Blade Knight', 'openmu-wp-connect' ), __( 'Blade Master', 'openmu-wp-connect' ) ),
		array( __( 'Elf', 'openmu-wp-connect' ), __( 'Muse Elf', 'openmu-wp-connect' ), __( 'High Elf', 'openmu-wp-connect' ) ),
		array( __( 'Magic Gladiator', 'openmu-wp-connect' ), null, __( 'Dual Master', 'openmu-wp-connect' ) ),
		array( __( 'Dark Lord', 'openmu-wp-connect' ), null, __( 'Lord Emperor', 'openmu-wp-connect' ) ),
		array( __( 'Summoner', 'openmu-wp-connect' ), __( 'Bloody Summoner', 'openmu-wp-connect' ), __( 'Dimension Master', 'openmu-wp-connect' ) ),
		array( __( 'Rage Fighter', 'openmu-wp-connect' ), null, __( 'Fist Master', 'openmu-wp-connect' ) ),
	);
	for ( $step = max( $class_level, 1 ); $step <= 3; $step++ ) {
		if ( isset( $names[ $class ][ $step - 1 ] ) ) {
			return $names[ $class ][ $step - 1 ];
		}
	}

	return null;
}

/**
 * Builds the item's own description lines of the client data (WriteItemDescriptionLine), e.g. the
 * wings' "Increase 69% of Damage" or the Fenrir's "Life: 255".
 *
 * @param array $definition      Item record of data/items.json.
 * @param array $item            Item of the website API.
 * @param array $values          The values of openmu_wp_connect_item_values().
 * @param int   $character_level Level of the wearer ({heroLevel}).
 * @param bool  $after_options   True for the lines drawn after the options ("after": "options").
 * @return array<int, array{text: string, kind: string}> The lines.
 */
function openmu_wp_connect_item_description_lines( array $definition, array $item, array $values, int $character_level, bool $after_options ): array {
	$data   = openmu_wp_connect_item_data();
	$shared = openmu_wp_connect_item_shared_texts();
	$lines  = array();
	foreach ( (array) ( $definition['desc'] ?? array() ) as $line ) {
		if ( ( 'options' === ( $line['after'] ?? '' ) ) !== $after_options ) {
			continue;
		}
		$levels = (array) ( $line['levels'] ?? array( 0, 15 ) );
		if ( $values['level'] < (int) ( $levels[0] ?? 0 ) || $values['level'] > (int) ( $levels[1] ?? 15 ) ) {
			continue;
		}
		if ( ! openmu_wp_connect_item_conditions_hold( (array) ( $line['when'] ?? array() ), $values, $item ) ) {
			continue;
		}

		$ref = (string) ( $line['ref'] ?? '' );
		// Counts the API doesn't send (arrows in a stack, uses left) aren't shown.
		if ( in_array( $ref, array( 'numberOfItems', 'usableTimes', 'piecesOfTwenty' ), true ) ) {
			continue;
		}
		$template = '' !== $ref ? ( $shared[ $ref ] ?? (string) ( $data['texts'][ $ref ] ?? '' ) ) : (string) ( $line['text'] ?? '' );
		if ( '' === $template ) {
			$lines[] = openmu_wp_connect_item_gap();
			continue;
		}

		// Placeholders: the line's own values (a level table) and the tooltip's built-in values.
		$replace = array(
			'{name}'          => (string) ( $item['name'] ?? $definition['name'] ?? '' ),
			'{level}'         => (string) $values['level'],
			'{durability}'    => (string) $values['durability'],
			'{maxDurability}' => (string) $values['maxDurability'],
			'{heroLevel}'     => (string) $character_level,
			'{magicPower}'    => (string) $values['magicPower'],
			'{defense}'       => (string) $values['defense'],
		);
		foreach ( (array) ( $line['values'] ?? array() ) as $placeholder => $source ) {
			$source = (string) $source;
			$replace[ '{' . $placeholder . '}' ] = str_starts_with( $source, 'table:' )
				? (string) openmu_wp_connect_item_level_bonus( substr( $source, 6 ), $values['level'] )
				: (string) ( $values[ $source ] ?? '' );
		}

		$kind = openmu_wp_connect_item_color_kind( (string) ( $line['color'] ?? 'white' ) );
		foreach ( (array) ( $line['colors'] ?? array() ) as $rule ) {
			if ( openmu_wp_connect_item_conditions_hold( (array) ( $rule['when'] ?? array() ), $values, $item ) ) {
				$kind = openmu_wp_connect_item_color_kind( (string) ( $rule['color'] ?? 'white' ) );
				break;
			}
		}

		$lines[] = array(
			'text' => strtr( $template, $replace ),
			'kind' => ! empty( $line['bold'] ) && 'option' === $kind ? 'emphasis' : $kind,
		);
	}

	return $lines;
}

/**
 * Checks the conditions of a description line of the client data (ItemTooltipConditions):
 * states ("timed", "excellent", "ancient", "hasOptions"), "tag:x" and comparisons of the
 * + level ("level>=5", "level!=1"); a leading "!" negates. An unknown condition doesn't hold.
 *
 * @param array $conditions Conditions, all of which must hold.
 * @param array $values     The values of openmu_wp_connect_item_values().
 * @param array $item       Item of the website API.
 * @return bool Whether all hold.
 */
function openmu_wp_connect_item_conditions_hold( array $conditions, array $values, array $item ): bool {
	foreach ( $conditions as $condition ) {
		$condition = (string) $condition;
		$negated   = str_starts_with( $condition, '!' );
		$condition = ltrim( $condition, '!' );

		if ( preg_match( '/^(\w+)\s*(>=|<=|!=|=|>|<)\s*(-?\d+)$/', $condition, $match ) ) {
			$actual = 'level' === $match[1] ? $values['level'] : ( is_numeric( $values[ $match[1] ] ?? null ) ? (int) $values[ $match[1] ] : null );
			$number = (int) $match[3];
			$holds  = null !== $actual && match ( $match[2] ) {
				'>='    => $actual >= $number,
				'<='    => $actual <= $number,
				'!='    => $actual !== $number,
				'='     => $actual === $number,
				'>'     => $actual > $number,
				default => $actual < $number,
			};
		} elseif ( str_starts_with( $condition, 'tag:' ) ) {
			$holds = in_array( substr( $condition, 4 ), $values['tags'], true );
		} else {
			$holds = match ( $condition ) {
				// The website shows no rented items.
				'timed', 'expired' => false,
				'excellent'        => $values['excellent'],
				'ancient'          => $values['ancient'],
				'hasOptions'       => array() !== (array) ( $item['options'] ?? array() ) || ! empty( $item['hasSkill'] ),
				default            => false,
			};
		}

		if ( $holds === $negated ) {
			return false;
		}
	}

	return true;
}

/**
 * Maps a text colour of the client data to a line kind of the tooltip CSS.
 *
 * @param string $color Colour name of the item data, e.g. "blue".
 * @return string Line kind.
 */
function openmu_wp_connect_item_color_kind( string $color ): string {
	return match ( $color ) {
		'blue', 'darkBlue'             => 'option',
		'red'                          => 'requirement',
		'yellow', 'darkYellow', 'orange' => 'ancient',
		'green'                        => 'note',
		'darkRed'                      => 'darkred',
		'purple'                       => 'purple',
		'violet'                       => 'violet',
		'redPurple'                    => 'guardian',
		'gray'                         => 'muted',
		'greenBlue'                    => 'set',
		default                        => 'stat',
	};
}

/**
 * The shared tooltip texts of the client (Tooltip.json) that item descriptions name, translatable.
 * The placeholders in braces are filled in by openmu_wp_connect_item_description_lines(); a text
 * missing here comes in English from data/items.json.
 *
 * @return array<string, string> Text name => text.
 */
function openmu_wp_connect_item_shared_texts(): array {
	static $texts = null;
	if ( null === $texts ) {
		$texts = array(
			/* translators: {durability} and {maxDurability}: current and maximum durability; keep the braces. */
			'durability'           => __( 'Durability: [{durability}/{maxDurability}]', 'openmu-wp-connect' ),
			/* translators: {durability}: life of the pet; keep the braces. */
			'life'                 => __( 'Life: {durability}', 'openmu-wp-connect' ),
			/* translators: {percent}: damage bonus in percent; keep the braces. */
			'damageIncrease'       => __( 'Increase {percent}% of Damage', 'openmu-wp-connect' ),
			/* translators: {percent}: absorbed damage in percent; keep the braces. */
			'damageAbsorb'         => __( 'Absorb {percent}% of Damage', 'openmu-wp-connect' ),
			'increaseSpeed'        => __( 'Increase speed', 'openmu-wp-connect' ),
			'automaticHpRecovery3' => __( 'Automatic HP recovery 3%', 'openmu-wp-connect' ),
			'cannotRepair'         => __( 'Cannot Repair', 'openmu-wp-connect' ),
			'additionalDamagePlusOne' => __( 'Additional Dmg +1', 'openmu-wp-connect' ),
			'unableToEquipWithADifferentTransformationRing' => __( 'Unable to Equip with a Different Transformation Ring', 'openmu-wp-connect' ),
		);
	}

	return $texts;
}

/**
 * Builds the skill line of an item with its mana cost (GetSpecialOptionText in ZzzInventory.cpp),
 * plus the dark red class note of the Dinorant's and the Dark Horse's skill.
 *
 * @param array $item Item of the website API.
 * @return array<int, array{text: string, kind: string}> The lines; empty when the client data doesn't
 *         know the item's skill (the caller then writes a plain "Skill").
 */
function openmu_wp_connect_item_skill_lines( array $item ): array {
	$definition = openmu_wp_connect_item_definition( $item );
	$skill      = (int) ( $definition['skill'] ?? 0 );
	if ( 0 === $skill ) {
		return array();
	}

	$formats = array(
		/* translators: %d: mana cost of the skill. */
		18  => __( 'Defend skill (Mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		19  => __( 'Falling Slash skill (Mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		20  => __( 'Lunge skill (Mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		21  => __( 'Uppercut skill (Mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		22  => __( 'Cyclone Cutting skill (Mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		23  => __( 'Slashing skill (Mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		24  => __( 'Triple Shot skill (Mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		49  => __( 'Raid Skill (Mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		54  => __( '4 shot skill (Mana: %d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		56  => __( 'Power Slash Skill (Mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		60  => __( 'Long spear skill (mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		62  => __( 'Earth shake skill (mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		66  => __( 'Force wave skill (mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		76  => __( 'Plasma storm skill (Mana:%d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		223 => __( 'Explosion Skill (Mana: %d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		224 => __( 'Requiem (Mana: %d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		225 => __( 'Pollution skill (Mana: %d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		260 => __( 'Killing Blow (Mana: %d)', 'openmu-wp-connect' ),
		/* translators: %d: mana cost of the skill. */
		261 => __( 'Beast Uppercut (Mana: %d)', 'openmu-wp-connect' ),
	);
	$skill_data = openmu_wp_connect_item_data()['skills'][ (string) $skill ] ?? null;
	if ( ! isset( $formats[ $skill ] ) || ! is_array( $skill_data ) ) {
		// A skill the client has no item text for (e.g. the Phoenix Shot): its name from the skill data.
		return is_array( $skill_data ) ? array(
			array(
				'text' => (string) $skill_data['name'],
				'kind' => 'skill',
			),
		) : array();
	}

	$lines = array(
		array(
			'text' => sprintf( $formats[ $skill ], (int) $skill_data['mana'] ),
			'kind' => 'skill',
		),
	);
	if ( 49 === $skill ) {
		$lines[] = array(
			'text' => __( 'Knight specific skill', 'openmu-wp-connect' ),
			'kind' => 'darkred',
		);
	} elseif ( 62 === $skill ) {
		$lines[] = array(
			'text' => __( 'Dark Lord exclusive skill', 'openmu-wp-connect' ),
			'kind' => 'darkred',
		);
	}

	return $lines;
}

/**
 * The closing lines of the Horn of Fenrir (BuildItemTooltipText in ZzzInventory.cpp).
 *
 * @param array $item Item of the website API.
 * @return array<int, array{text: string, kind: string}> The lines; empty for other items.
 */
function openmu_wp_connect_item_fenrir_lines( array $item ): array {
	if ( 13 !== (int) ( $item['group'] ?? -1 ) || 37 !== (int) ( $item['number'] ?? -1 ) ) {
		return array();
	}

	$lines = array(
		openmu_wp_connect_item_gap(),
		array(
			'text' => __( 'Can summon the Fenrir when equipped.', 'openmu-wp-connect' ),
			'kind' => 'ancient',
		),
	);

	// A Fenrir without its black, blue or gold option can still get one.
	$types = array_map( static fn( $option ) => (string) ( $option['type'] ?? '' ), (array) ( $item['options'] ?? array() ) );
	if ( array() === array_intersect( $types, array( 'Black Fenrir Option', 'Blue Fenrir Option', 'Gold Fenrir Option' ) ) ) {
		$lines[] = array(
			'text' => __( 'Skills will improve through upgrading.', 'openmu-wp-connect' ),
			'kind' => 'ancient',
		);
	}

	return $lines;
}

/**
 * Finds the ancient set of an item by the set name of the API: the client data has two sets
 * per item (the A and B variant) named like "Hyon's"; OpenMU names them "Hyon".
 *
 * @param array $definition Item record of data/items.json.
 * @param array $item       Item of the website API.
 * @return int The set number of item-sets.json, or 0 for none.
 */
function openmu_wp_connect_item_set_number( array $definition, array $item ): int {
	$name = openmu_wp_connect_item_fold_set_name( (string) ( $item['ancientSetName'] ?? '' ) );
	if ( '' === $name ) {
		return 0;
	}

	$sets       = openmu_wp_connect_item_data()['sets'];
	$candidates = array_values( array_filter( array_map( 'intval', (array) ( $definition['sets'] ?? array() ) ) ) );
	foreach ( $candidates as $number ) {
		if ( openmu_wp_connect_item_fold_set_name( (string) ( $sets[ (string) $number ]['name'] ?? '' ) ) === $name ) {
			return $number;
		}
	}

	// A renamed set: an item with only one set can only mean that one.
	return 1 === count( $candidates ) ? $candidates[0] : 0;
}

/**
 * Folds a set name for comparison: lower case, without the possessive "'s" and apostrophes.
 *
 * @param string $name Set name, e.g. "Hyon's" or "Hyon".
 * @return string The folded name, e.g. "hyon".
 */
function openmu_wp_connect_item_fold_set_name( string $name ): string {
	$name = strtolower( trim( $name ) );
	$name = preg_replace( "/(['\x{2019}]s)$/u", '', $name );
	return str_replace( array( "'", "\u{2019}" ), '', (string) $name );
}

/**
 * Builds the ancient set block: "Set Item option info" and the set's bonus list.
 *
 * Like the client (AddSetOptions and RenderSetOptionList in CSItemOption.cpp), the pieces of the set
 * the character wears decide which bonuses are active: the n-th standard bonus needs n+1 pieces, the
 * extra bonuses two, the full bonuses the whole set. Active bonuses are blue (full set: yellow, extra:
 * green), the others grey. With a single piece the client lists nothing; here the list is shown grey,
 * so a visitor still sees what the set gives.
 *
 * @param array $definition Item record of data/items.json.
 * @param array $item       Item of the website API.
 * @param array $equipped   All equipped items of the character (website API).
 * @return array<int, array{text: string, kind: string}> The lines; empty for items of no set.
 */
function openmu_wp_connect_item_set_lines( array $definition, array $item, array $equipped ): array {
	$number = openmu_wp_connect_item_set_number( $definition, $item );
	$set    = openmu_wp_connect_item_data()['sets'][ (string) $number ] ?? null;
	if ( ! is_array( $set ) ) {
		return '' !== (string) ( $item['ancientSetName'] ?? '' ) ? array(
			array(
				/* translators: %s: ancient set name. */
				'text' => sprintf( __( 'Set: %s', 'openmu-wp-connect' ), (string) $item['ancientSetName'] ),
				'kind' => 'ancient',
			),
		) : array();
	}

	// Worn pieces of this set; the item itself is one of them (an armory item is always worn).
	$worn = 0;
	foreach ( $equipped as $other ) {
		$other_definition = is_array( $other ) ? openmu_wp_connect_item_definition( $other ) : null;
		if ( null !== $other_definition && openmu_wp_connect_item_set_number( $other_definition, $other ) === $number ) {
			++$worn;
		}
	}
	$worn     = max( 1, $worn );
	$complete = $worn >= (int) $set['items'];

	$lines = array(
		openmu_wp_connect_item_gap(),
		array(
			'text' => __( 'Set Item option info', 'openmu-wp-connect' ),
			'kind' => 'ancient',
		),
		openmu_wp_connect_item_gap(),
	);

	$add = static function ( int $option, int $value, bool $active, string $active_kind ) use ( &$lines, $set ): void {
		$text = openmu_wp_connect_item_set_option_text( $option, $value, $set );
		if ( null !== $text ) {
			$lines[] = array(
				'text' => $text,
				'kind' => $active ? $active_kind : 'muted',
			);
		}
	};

	$standard_count = min( (int) $set['items'] - 1, 6 );
	for ( $index = 0; $index < $standard_count; $index++ ) {
		foreach ( (array) ( $set['std'][ $index ] ?? array() ) as $option ) {
			$add( (int) $option[0], (int) $option[1], $complete || $index < $worn - 1, 'option' );
		}
	}
	foreach ( (array) $set['ext'] as $option ) {
		$add( (int) $option[0], (int) $option[1], $complete || $worn >= 2, 'note' );
	}
	foreach ( (array) $set['full'] as $option ) {
		$add( (int) $option[0], (int) $option[1], $complete, 'ancient' );
	}

	return $lines;
}

/**
 * Describes one ancient set bonus (kSetOptionFormats in CSItemOption.cpp).
 *
 * The bonuses 24 to 28 are not supported by the client and not shown; the mastery bonuses (29 to 35)
 * only exist for sets bound to a class.
 *
 * @param int   $option Bonus number of the set data.
 * @param int   $value  Its value.
 * @param array $set    The set of item-sets.json.
 * @return string|null The text, or null for a bonus the client doesn't show.
 */
function openmu_wp_connect_item_set_option_text( int $option, int $value, array $set ): ?string {
	if ( 0 === $value || ( $option >= 29 && array( 0, 0, 0, 0, 0, 0, 0 ) === array_map( 'intval', (array) $set['classes'] ) ) ) {
		return null;
	}

	$formats = array(
		/* translators: %d: bonus. */
		0  => __( 'Increase strength +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		1  => __( 'Increase agility +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		2  => __( 'Increase energy +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		3  => __( 'Increase stamina +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		4  => __( 'Increase command +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		5  => __( 'Increase min. damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		6  => __( 'Increase max. damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus in percent. */
		7  => __( 'Increase Wizardry Dmg +%d%%', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		8  => __( 'Increase damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		9  => __( 'Increase damage success rate +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		10 => __( 'Increase defensive skill +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		11 => __( 'Increase max. life +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		12 => __( 'Increase max. mana +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		13 => __( 'Increase max. AG +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		14 => __( 'Increase AG increase rate +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus in percent. */
		15 => __( 'Increase critical damage rate %d%%', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		16 => __( 'Increase critical damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus in percent. */
		17 => __( 'Increase excellent damage rate %d%%', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		18 => __( 'Increase excellent damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		19 => __( 'Increase skill attacking rate +%d', 'openmu-wp-connect' ),
		/* translators: %d: chance in percent. */
		20 => __( 'Double damage rate %d%%', 'openmu-wp-connect' ),
		/* translators: %d: chance in percent. */
		21 => __( 'Ignore enemies defensive skill %d%%', 'openmu-wp-connect' ),
		/* translators: %d: bonus in percent. */
		22 => __( 'Increase defensive skill when using shield weapons %d%%', 'openmu-wp-connect' ),
		/* translators: %d: bonus in percent. */
		23 => __( 'Increase damage when using two handed weapons +%d%%', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		29 => __( 'Ice attribute skill increase damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		30 => __( 'Poison attribute skill increase damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		31 => __( 'Lightning attribute skill increase damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		32 => __( 'Fire attribute skill increase damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		33 => __( 'Earth attribute skill increase damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		34 => __( 'Wind attribute skill increase damage +%d', 'openmu-wp-connect' ),
		/* translators: %d: bonus. */
		35 => __( 'Water attribute skill increase damage +%d', 'openmu-wp-connect' ),
	);

	return isset( $formats[ $option ] ) ? sprintf( $formats[ $option ], $value ) : null;
}
