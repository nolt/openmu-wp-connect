<?php
/**
 * Demo data source: serves the JSON files in demo/.
 *
 * Used until a server address and an API key are set, so the blocks can be placed and
 * styled before the site is connected. The files have the shapes the API source
 * returns, so switching to the live source doesn't change the blocks.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads demo/status.json, demo/ranking.json and demo/characters.json.
 */
class OpenMU_WP_Connect_Demo_Source implements OpenMU_WP_Connect_Data_Source {

	/**
	 * Decoded demo files, keyed by file name; filled on first use.
	 *
	 * @var array<string, mixed>
	 */
	private array $cache = array();

	/**
	 * {@inheritDoc}
	 */
	public function is_live(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_status(): ?array {
		$status = $this->read( 'status' );
		return is_array( $status ) ? array(
			'online'  => 'Online' === ( $status['state'] ?? '' ),
			'players' => (int) ( $status['players'] ?? 0 ),
		) : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ranking( int $limit, ?string $character_class ): ?array {
		$rows = $this->read( 'ranking' );
		if ( ! is_array( $rows ) ) {
			return null;
		}
		if ( $character_class ) {
			$rows = array_values( array_filter( $rows, fn( $row ) => ( $row['characterClass'] ?? '' ) === $character_class ) );
		}
		return array_slice( $rows, 0, max( 1, min( 100, $limit ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_character( string $name ): ?array {
		foreach ( (array) $this->read( 'characters' ) as $character ) {
			// Character names are case-insensitive in game.
			if ( 0 === strcasecmp( $character['name'] ?? '', $name ) ) {
				return $character;
			}
		}
		return null;
	}

	/**
	 * Decodes one demo file once per request.
	 *
	 * @param string $file File name in demo/ without the .json extension.
	 * @return mixed Decoded JSON, or null when the file is missing or invalid.
	 */
	private function read( string $file ) {
		if ( ! array_key_exists( $file, $this->cache ) ) {
			$path                 = OPENMU_WP_CONNECT_DIR . 'demo/' . $file . '.json';
			$this->cache[ $file ] = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		}
		return $this->cache[ $file ];
	}
}
