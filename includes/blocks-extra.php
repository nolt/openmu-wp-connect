<?php
/**
 * Registers the website-API blocks (account, events, mini game ranking) and
 * the small helpers they share for talking to the OpenMU website API.
 *
 * These blocks only make sense with a real game server: the account block logs
 * players in, the other two show live server data that has no demo files. With
 * the demo source they render a notice that points the administrator to the
 * settings page. They are kept apart from includes/blocks.php so the three
 * original blocks keep working without the website API functions.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the API source when the site is connected to a game server, or null for the demo source.
 */
function openmu_wp_connect_api_source(): ?OpenMU_WP_Connect_Api_Source {
	$source = openmu_wp_connect_source();
	return $source instanceof OpenMU_WP_Connect_Api_Source ? $source : null;
}

/**
 * Sends one request to the website API and decodes the answer.
 *
 * @param string     $method HTTP method.
 * @param string     $path   Path below the server address, with its query.
 * @param array|null $body   JSON body, if any.
 * @return array{code: int, data: mixed, retry_after: int} HTTP status (0 = not connected or no answer),
 *         decoded JSON (null when empty or not JSON) and the Retry-After header in seconds (0 when absent).
 */
function openmu_wp_connect_api_call( string $method, string $path, ?array $body = null ): array {
	$api = openmu_wp_connect_api_source();
	if ( ! $api ) {
		return array(
			'code'        => 0,
			'data'        => null,
			'retry_after' => 0,
		);
	}

	$response = $api->request( $method, $path, $body );
	if ( is_wp_error( $response ) ) {
		return array(
			'code'        => 0,
			'data'        => null,
			'retry_after' => 0,
		);
	}

	// Retry-After is either a number of seconds or an HTTP date; both are allowed by RFC 9110.
	$retry = (string) wp_remote_retrieve_header( $response, 'retry-after' );
	if ( '' === $retry ) {
		$retry_seconds = 0;
	} elseif ( ctype_digit( $retry ) ) {
		$retry_seconds = (int) $retry;
	} else {
		$retry_at      = strtotime( $retry );
		$retry_seconds = false !== $retry_at ? max( 0, $retry_at - time() ) : 0;
	}

	return array(
		'code'        => (int) wp_remote_retrieve_response_code( $response ),
		'data'        => json_decode( (string) wp_remote_retrieve_body( $response ), true ),
		'retry_after' => $retry_seconds,
	);
}

/**
 * Gets a JSON answer of the website API, cached in a transient for a short time.
 *
 * @param string               $path    Path below the server address.
 * @param array<string, mixed> $query   Query parameters.
 * @param int                  $seconds Lifetime of the cache.
 * @return array|null The decoded answer, or null when the server didn't answer with 200 and JSON.
 */
function openmu_wp_connect_api_cached_get( string $path, array $query, int $seconds ): ?array {
	$transient = 'openmu_wp_connect_x_' . md5( $path . '?' . wp_json_encode( $query ) );
	$cached    = get_transient( $transient );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$answer = openmu_wp_connect_api_call( 'GET', add_query_arg( array_map( 'rawurlencode', $query ), $path ) );
	if ( 200 !== $answer['code'] || ! is_array( $answer['data'] ) ) {
		return null;
	}

	set_transient( $transient, $answer['data'], $seconds );
	return $answer['data'];
}

/**
 * Markup of the notice the website-API blocks show while the site uses demo data.
 */
function openmu_wp_connect_not_connected_notice(): string {
	return '<p class="openmu-line openmu-line--requirement">'
		. esc_html__( 'Connect the site to an OpenMU server in Settings > OpenMU WP Connect.', 'openmu-wp-connect' )
		. '</p>';
}

/**
 * Turns an identifier of the server (e.g. BloodCastle, TemporarilyBanned) into words ("Blood Castle").
 *
 * The game's own English names are kept on purpose: players use them in any language.
 *
 * @param string $identifier PascalCase identifier from the API.
 */
function openmu_wp_connect_split_words( string $identifier ): string {
	return trim( (string) preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $identifier ) );
}

/**
 * Registers the editor script and stylesheet of the website-API blocks, then the block folders.
 */
function openmu_wp_connect_register_extra_blocks(): void {
	// Editor-side definitions of the three blocks; plain JS, no build step, like assets/editor.js.
	wp_register_script(
		'openmu-wp-connect-editor-extra',
		plugins_url( 'assets/editor-extra.js', OPENMU_WP_CONNECT_DIR . 'openmu-wp-connect.php' ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
		OPENMU_WP_CONNECT_VERSION,
		true
	);
	wp_set_script_translations( 'openmu-wp-connect-editor-extra', 'openmu-wp-connect', OPENMU_WP_CONNECT_DIR . 'languages' );

	// Layout of the forms, character cards and event lists; colours come from blocks.css.
	wp_register_style(
		'openmu-wp-connect-blocks-extra',
		plugins_url( 'assets/blocks-extra.css', OPENMU_WP_CONNECT_DIR . 'openmu-wp-connect.php' ),
		array( 'openmu-wp-connect-blocks' ),
		OPENMU_WP_CONNECT_VERSION
	);

	foreach ( array( 'account', 'events', 'minigame-ranking' ) as $block ) {
		register_block_type( OPENMU_WP_CONNECT_DIR . 'blocks/' . $block );
	}
}
add_action( 'init', 'openmu_wp_connect_register_extra_blocks' );
