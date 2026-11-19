<?php
/**
 * Live data source: the website API of an OpenMU server (api/website/*).
 *
 * Every request carries the API key of the site (a key with the role
 * "Website (selected functions only)" in the OpenMU admin panel). Answers are
 * mapped to the shapes the blocks already use, so the blocks don't care whether
 * the data is live or from the demo files. Short-lived answers (status, ranking)
 * are cached in transients, so a busy page doesn't hit the game server on every view.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads the game data from the OpenMU website API.
 */
class OpenMU_WP_Connect_Api_Source implements OpenMU_WP_Connect_Data_Source {

	/**
	 * Seconds the server status is cached.
	 */
	private const STATUS_CACHE = 30;

	/**
	 * Seconds a ranking is cached; the ranking query reads all characters.
	 */
	private const RANKING_CACHE = 300;

	/**
	 * Seconds a character profile is cached: repeated views of the same character
	 * (armory, account view) don't each ask the server, and new equipment shows
	 * up within a minute. Unknown names aren't cached.
	 */
	private const CHARACTER_CACHE = 60;

	/**
	 * Inventory slots of OpenMU, mapped to the slot keys of the armory block.
	 */
	private const SLOTS = array(
		0  => 'weapon',
		1  => 'shield',
		2  => 'helm',
		3  => 'armor',
		4  => 'pants',
		5  => 'gloves',
		6  => 'boots',
		7  => 'wings',
		8  => 'pet',
		9  => 'pendant',
		10 => 'ring1',
		11 => 'ring2',
	);

	/**
	 * Numbers of the Season 6 character classes in OpenMU; the ranking filters by
	 * the number, and the server ranks the whole line of the class (e.g. Dark
	 * Knight, Blade Knight and Blade Master).
	 */
	private const CLASS_NUMBERS = array(
		'Dark Wizard'      => 0,
		'Soul Master'      => 2,
		'Grand Master'     => 3,
		'Dark Knight'      => 4,
		'Blade Knight'     => 6,
		'Blade Master'     => 7,
		'Fairy Elf'        => 8,
		'Muse Elf'         => 10,
		'High Elf'         => 11,
		'Magic Gladiator'  => 12,
		'Duel Master'      => 13,
		'Dark Lord'        => 16,
		'Lord Emperor'     => 17,
		'Summoner'         => 20,
		'Bloody Summoner'  => 22,
		'Dimension Master' => 23,
		'Rage Fighter'     => 24,
		'Fist Master'      => 25,
	);

	/**
	 * Creates the source.
	 *
	 * @param string $base_url Address of the OpenMU admin panel, e.g. http://192.168.1.10:8080.
	 * @param string $api_key  The API key of the site.
	 */
	public function __construct( private string $base_url, private string $api_key ) {
		$this->base_url = untrailingslashit( $base_url );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_live(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_status(): ?array {
		$status = $this->cached( 'status', self::STATUS_CACHE, fn() => $this->get( '/api/website/status' ) );
		if ( ! is_array( $status ) ) {
			return null;
		}

		$online = false;
		foreach ( (array) ( $status['servers'] ?? array() ) as $server ) {
			$online = $online || ! empty( $server['isOnline'] );
		}

		return array(
			'online'  => $online,
			'players' => (int) ( $status['players'] ?? 0 ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ranking( int $limit, ?string $character_class ): ?array {
		$query = array( 'count' => max( 1, min( 100, $limit ) ) );
		if ( $character_class && isset( self::CLASS_NUMBERS[ $character_class ] ) ) {
			$query['class'] = self::CLASS_NUMBERS[ $character_class ];
		}

		$rows = $this->cached( 'ranking_' . md5( wp_json_encode( $query ) ), self::RANKING_CACHE, fn() => $this->get( '/api/website/ranking', $query ) );
		if ( ! is_array( $rows ) ) {
			return null;
		}

		return array_map(
			fn( $row ) => array(
				'name'           => (string) ( $row['name'] ?? '' ),
				'characterClass' => (string) ( $row['className'] ?? '' ),
				'level'          => (int) ( $row['level'] ?? 0 ),
				'masterLevel'    => (int) ( $row['masterLevel'] ?? 0 ),
				'resets'         => (int) ( $row['resets'] ?? 0 ),
				'guild'          => isset( $row['guildName'] ) ? (string) $row['guildName'] : null,
			),
			$rows
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_character( string $name ): ?array {
		$character = $this->cached( 'character_' . md5( $name ), self::CHARACTER_CACHE, fn() => $this->get( '/api/website/characters/' . rawurlencode( $name ) ) );
		if ( ! is_array( $character ) ) {
			return null;
		}

		$equipment = array();
		foreach ( (array) ( $character['equippedItems'] ?? array() ) as $item ) {
			$slot = self::SLOTS[ (int) ( $item['slot'] ?? -1 ) ] ?? null;
			if ( ! $slot ) {
				continue;
			}

			if ( isset( $item['options'] ) && is_array( $item['options'] ) ) {
				// Structured options: the full tooltip of the game (stats from the client item data,
				// options, set block), see item-data.php and item-options.php. The title colour
				// depends on the options only, like the client's.
				$title      = openmu_wp_connect_item_title( $item );
				$lines      = openmu_wp_connect_item_tooltip_lines( $item, $character );
				$title_kind = openmu_wp_connect_item_title_kind( $item, openmu_wp_connect_item_option_parts( $item, (int) ( $character['level'] ?? 0 ) )['options'] );
			} else {
				// An older server only sends the technical description: its first line is the title
				// (name, set, level), the others are the options.
				$lines = explode( "\n", (string) ( $item['description'] ?? '' ) );
				$title = (string) array_shift( $lines );
				$lines = array_values( array_filter( $lines ) );
				// The block picks the title colour itself from the excellent/ancient flags and the level.
				$title_kind = '';
			}

			$equipment[ $slot ] = array(
				'name'      => (string) ( $item['name'] ?? '' ),
				'level'     => (int) ( $item['level'] ?? 0 ),
				'excellent' => ! empty( $item['isExcellent'] ),
				'ancient'   => ! empty( $item['isAncient'] ),
				'title'     => $title,
				'titleKind' => $title_kind,
				'lines'     => $lines,
				'image'     => $this->item_image_url( (string) ( $item['image'] ?? '' ) ),
			);
		}

		return array(
			'name'           => (string) ( $character['name'] ?? $name ),
			'characterClass' => (string) ( $character['className'] ?? '' ),
			'level'          => (int) ( $character['level'] ?? 0 ),
			'masterLevel'    => (int) ( $character['masterLevel'] ?? 0 ),
			'resets'         => (int) ( $character['resets'] ?? 0 ),
			'guild'          => $character['guildName'] ?? null,
			'equipment'      => $equipment,
		);
	}

	/**
	 * Checks the connection and the key, for the settings page.
	 *
	 * @return array{ok: bool, message: string}
	 */
	public function test_connection(): array {
		$response = $this->request( 'GET', '/api/website/status' );
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		return match ( $code ) {
			200     => array( 'ok' => true, 'message' => __( 'Connected. The server status can be read.', 'openmu-wp-connect' ) ),
			401     => array( 'ok' => false, 'message' => __( 'The API key is unknown or disabled.', 'openmu-wp-connect' ) ),
			403     => array( 'ok' => false, 'message' => __( 'The key works, but it is not a website key or "Server status" is not selected for it.', 'openmu-wp-connect' ) ),
			default => array( 'ok' => false, 'message' => sprintf( /* translators: %d: HTTP status code. */ __( 'Unexpected answer of the server: HTTP %d.', 'openmu-wp-connect' ), $code ) ),
		};
	}

	/**
	 * Returns the address of an item picture, downloaded once from the server into the uploads folder.
	 *
	 * The admin panel of OpenMU shouldn't be reachable from the internet, so the
	 * visitors get the pictures from the site, not from the game server.
	 *
	 * @param string $file File name from the API, e.g. item_0_16_13_e.png.
	 * @return string|null Address of the picture, or null when it isn't available.
	 */
	private function item_image_url( string $file ): ?string {
		if ( ! preg_match( '/^item_\d+_\d+_\d+(_[ae])?\.png$/', $file ) ) {
			return null;
		}

		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'openmu-wp-connect/items/';
		$url     = trailingslashit( $uploads['baseurl'] ) . 'openmu-wp-connect/items/' . $file;
		if ( file_exists( $dir . $file ) ) {
			return $url;
		}

		$response = wp_remote_get( $this->base_url . '/_content/MUnique.OpenMU.Web.ItemEditor/img/items/' . $file, array( 'timeout' => 5 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		wp_mkdir_p( $dir );
		file_put_contents( $dir . $file, wp_remote_retrieve_body( $response ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- cache file in uploads.
		return $url;
	}

	/**
	 * Returns a cached answer, or gets and caches it.
	 *
	 * @param string   $key     Cache key within the plugin.
	 * @param int      $seconds Lifetime of the cache.
	 * @param callable $fetch   Gets the answer; null answers aren't cached.
	 * @return mixed
	 */
	private function cached( string $key, int $seconds, callable $fetch ) {
		$transient = 'openmu_wp_connect_' . $key;
		$value     = get_transient( $transient );
		if ( false === $value ) {
			$value = $fetch();
			if ( null !== $value ) {
				set_transient( $transient, $value, $seconds );
			}
		}

		return $value;
	}

	/**
	 * Sends a GET request and decodes its JSON answer.
	 *
	 * @param string               $path  Path below the server address.
	 * @param array<string, mixed> $query Query parameters.
	 * @return mixed Decoded answer, or null for any error (also 404).
	 */
	private function get( string $path, array $query = array() ) {
		$response = $this->request( 'GET', add_query_arg( $query, $path ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Sends a request with the API key.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   Path below the server address, with its query.
	 * @param array|null $body   JSON body, if any.
	 * @return array|WP_Error The response.
	 */
	public function request( string $method, string $path, ?array $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 5,
			'headers' => array(
				'X-Api-Key' => $this->api_key,
				'Accept'    => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		return wp_remote_request( $this->base_url . $path, $args );
	}
}
