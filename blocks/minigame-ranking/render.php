<?php
/**
 * Mini game ranking block: leaderboard of Blood Castle, Devil Square, Chaos Castle and others.
 *
 * Reads GET /api/website/minigames/ranking (cached for two minutes). The block's
 * miniGameType attribute picks the mini game shown first (empty = the first one
 * the server has a leaderboard for); visitors switch with the selector, which
 * lists the server's availableTypes and sets the "openmu_minigame" query parameter.
 *
 * The leaderboard is drawn as one game window (the theme's "mu-window" group style, like the
 * events block): gold title = mini game name, the selector at the top, then the table.
 *
 * Attributes: miniGameType (OpenMU type name, e.g. BloodCastle, or empty),
 * days (period in days, 0 = the server's default).
 *
 * @package OpenMU_WP_Connect
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$openmu_connected = null !== openmu_wp_connect_api_source();

// Type names of OpenMU are plain identifiers; anything else can't be a type and isn't sent.
$openmu_type_pattern = '/^[A-Za-z][A-Za-z0-9]{0,39}$/';
$openmu_block_type   = (string) ( $attributes['miniGameType'] ?? '' );
$openmu_block_type   = preg_match( $openmu_type_pattern, $openmu_block_type ) ? $openmu_block_type : '';
$openmu_asked_type   = isset( $_GET['openmu_minigame'] ) ? sanitize_text_field( wp_unslash( $_GET['openmu_minigame'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
$openmu_asked_type   = preg_match( $openmu_type_pattern, $openmu_asked_type ) ? $openmu_asked_type : '';
$openmu_days         = max( 0, min( 365, (int) ( $attributes['days'] ?? 0 ) ) );

/**
 * Builds the query of the ranking request.
 *
 * @param string $type Mini game type, '' for the server's choice.
 * @param int    $days Period in days, 0 for the server's default.
 */
$openmu_query = static function ( string $type, int $days ): array {
	$query = array();
	if ( '' !== $type ) {
		$query['type'] = $type;
	}
	if ( $days > 0 ) {
		$query['days'] = $days;
	}
	return $query;
};

$openmu_ranking = null;
if ( $openmu_connected ) {
	$openmu_ranking = openmu_wp_connect_api_cached_get( '/api/website/minigames/ranking', $openmu_query( '' !== $openmu_asked_type ? $openmu_asked_type : $openmu_block_type, $openmu_days ), 2 * MINUTE_IN_SECONDS );
	// A type from the address bar may be unknown to the server; then show the block's own choice.
	if ( null === $openmu_ranking && '' !== $openmu_asked_type && $openmu_asked_type !== $openmu_block_type ) {
		$openmu_ranking = openmu_wp_connect_api_cached_get( '/api/website/minigames/ranking', $openmu_query( $openmu_block_type, $openmu_days ), 2 * MINUTE_IN_SECONDS );
	}
}

$openmu_types   = array_values( array_filter( array_map( 'strval', (array) ( $openmu_ranking['availableTypes'] ?? array() ) ), fn( $t ) => (bool) preg_match( $openmu_type_pattern, $t ) ) );
$openmu_current = (string) ( $openmu_ranking['miniGameType'] ?? '' );
$openmu_entries = array_filter( (array) ( $openmu_ranking['entries'] ?? array() ), 'is_array' );
$openmu_period  = (int) ( $openmu_ranking['days'] ?? 0 );

// Other query parameters of the page (e.g. page_id without pretty links) must survive the GET form.
$openmu_keep = array();
foreach ( $_GET as $openmu_key => $openmu_value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- values are only echoed back, escaped.
	if ( 'openmu_minigame' !== $openmu_key && is_string( $openmu_value ) ) {
		$openmu_keep[ sanitize_key( $openmu_key ) ] = sanitize_text_field( wp_unslash( $openmu_value ) );
	}
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'openmu-ranking openmu-minigame-ranking alignwide' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>>
	<?php if ( ! $openmu_connected ) : ?>
		<?php echo openmu_wp_connect_not_connected_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
	<?php elseif ( null === $openmu_ranking ) : ?>
		<p class="openmu-line openmu-line--requirement"><?php esc_html_e( 'The mini game ranking is not available right now.', 'openmu-wp-connect' ); ?></p>
	<?php else : ?>
		<section class="wp-block-group is-style-mu-window openmu-minigame-ranking__window">
		<?php if ( '' !== $openmu_current ) : ?>
			<h3 class="wp-block-heading mu-window__title openmu-minigame-ranking__title">
				<?php echo esc_html( openmu_wp_connect_split_words( $openmu_current ) ); ?>
				<?php if ( $openmu_period > 0 ) : ?>
					<span class="openmu-line--muted">
						<?php
						/* translators: %d: number of days the ranking covers. */
						echo esc_html( sprintf( _n( 'last %d day', 'last %d days', $openmu_period, 'openmu-wp-connect' ), $openmu_period ) );
						?>
					</span>
				<?php endif; ?>
			</h3>
		<?php endif; ?>

		<?php if ( count( $openmu_types ) > 1 ) : ?>
			<form class="openmu-minigame-ranking__filter" method="get">
				<?php foreach ( $openmu_keep as $openmu_key => $openmu_value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $openmu_key ); ?>" value="<?php echo esc_attr( $openmu_value ); ?>" />
				<?php endforeach; ?>
				<label for="openmu-minigame-type"><?php esc_html_e( 'Mini game', 'openmu-wp-connect' ); ?></label>
				<select id="openmu-minigame-type" name="openmu_minigame">
					<?php foreach ( $openmu_types as $openmu_type ) : ?>
						<option value="<?php echo esc_attr( $openmu_type ); ?>" <?php selected( $openmu_type, $openmu_current ); ?>><?php echo esc_html( openmu_wp_connect_split_words( $openmu_type ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="wp-element-button"><?php esc_html_e( 'Show', 'openmu-wp-connect' ); ?></button>
			</form>
		<?php endif; ?>

		<?php if ( ! $openmu_entries ) : ?>
			<p class="openmu-minigame-ranking__empty"><?php esc_html_e( 'Nobody has finished this mini game in this period yet.', 'openmu-wp-connect' ); ?></p>
		<?php else : ?>
			<div class="openmu-minigame-ranking__table">
			<table>
				<thead>
					<tr>
						<th scope="col" class="openmu-ranking__place"><?php esc_html_e( 'Place', 'openmu-wp-connect' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Character', 'openmu-wp-connect' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Class', 'openmu-wp-connect' ); ?></th>
						<th scope="col" class="openmu-ranking__number"><?php esc_html_e( 'Total score', 'openmu-wp-connect' ); ?></th>
						<th scope="col" class="openmu-ranking__number"><?php esc_html_e( 'Best score', 'openmu-wp-connect' ); ?></th>
						<th scope="col" class="openmu-ranking__number"><?php esc_html_e( 'Games', 'openmu-wp-connect' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $openmu_entries as $openmu_entry ) : ?>
						<?php $openmu_name = (string) ( $openmu_entry['characterName'] ?? '' ); ?>
						<tr>
							<td class="openmu-ranking__place"><?php echo esc_html( number_format_i18n( (int) ( $openmu_entry['rank'] ?? 0 ) ) ); ?></td>
							<td>
								<?php if ( '' !== $openmu_name ) : ?>
									<a href="<?php echo esc_url( openmu_wp_connect_armory_url( $openmu_name ) ); ?>"><?php echo esc_html( $openmu_name ); ?></a>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) ( $openmu_entry['className'] ?? '' ) ); ?></td>
							<td class="openmu-ranking__number"><?php echo esc_html( number_format_i18n( (int) ( $openmu_entry['totalScore'] ?? 0 ) ) ); ?></td>
							<td class="openmu-ranking__number"><?php echo esc_html( number_format_i18n( (int) ( $openmu_entry['bestScore'] ?? 0 ) ) ); ?></td>
							<td class="openmu-ranking__number"><?php echo esc_html( number_format_i18n( (int) ( $openmu_entry['gameCount'] ?? 0 ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
