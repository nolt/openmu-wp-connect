<?php
/**
 * Contract between the blocks and wherever the game data comes from.
 *
 * The blocks only talk to this interface. Two implementations ship with the plugin:
 * the OpenMU website API (class-api-source.php) and the demo source
 * with bundled JSON files (class-demo-source.php), used until the API
 * is configured. All methods return plain arrays in the shapes the blocks use, or null
 * when the data isn't available.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * A provider of public game data for the site.
 */
interface OpenMU_WP_Connect_Data_Source {

	/**
	 * Whether the data is real (false for the demo source).
	 */
	public function is_live(): bool;

	/**
	 * Server state.
	 *
	 * @return array{online: bool, players: int}|null Null when the server can't be reached.
	 */
	public function get_status(): ?array;

	/**
	 * Characters ordered by resets, then level.
	 *
	 * @param int         $limit Maximum number of rows (1-100).
	 * @param string|null $character_class Exact class name to filter by, or null for all.
	 * @return array<int, array{name: string, characterClass: string, level: int, masterLevel: int, resets: int, guild: ?string}>|null
	 */
	public function get_ranking( int $limit, ?string $character_class ): ?array;

	/**
	 * Public view of one character: what other players could see in game.
	 *
	 * @param string $name Exact character name.
	 * @return array|null Character with its equipped items, or null when it doesn't exist.
	 */
	public function get_character( string $name ): ?array;
}
