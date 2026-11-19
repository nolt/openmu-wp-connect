<?php
/**
 * Armory block: a search form and one character's public view.
 *
 * The character comes from the block's characterName attribute, or else from
 * the "character" query parameter (the search form and ranking links set it).
 * The form is hidden when the name is fixed in the block. The character itself
 * (header, equipment, item tooltips) is drawn by the shared view in
 * includes/character-view.php, openmu_wp_connect_render_character_view().
 *
 * @package OpenMU_WP_Connect
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

// MU character names are at most 10 characters; anything longer can't exist.
$openmu_fixed = (string) ( $attributes['characterName'] ?? '' );
$openmu_name  = '' !== $openmu_fixed ? $openmu_fixed : sanitize_text_field( wp_unslash( $_GET['character'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup.
$openmu_name  = mb_substr( trim( $openmu_name ), 0, 10 );
$openmu_char  = '' !== $openmu_name ? openmu_wp_connect_source()->get_character( $openmu_name ) : null;
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'openmu-armory' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>>
	<?php if ( '' === $openmu_fixed ) : ?>
		<form class="openmu-armory__search" method="get" role="search">
			<label for="openmu-armory-name"><?php esc_html_e( 'Character name', 'openmu-wp-connect' ); ?></label>
			<input id="openmu-armory-name" type="search" name="character" maxlength="10" value="<?php echo esc_attr( $openmu_name ); ?>" autocomplete="off" />
			<button type="submit" class="wp-element-button"><?php esc_html_e( 'Show character', 'openmu-wp-connect' ); ?></button>
		</form>
	<?php endif; ?>

	<?php if ( '' !== $openmu_name && ! $openmu_char ) : ?>
		<p class="openmu-line openmu-line--requirement">
			<?php
			/* translators: %s: character name the visitor searched for. */
			echo esc_html( sprintf( __( 'No character named "%s".', 'openmu-wp-connect' ), $openmu_name ) );
			?>
		</p>
	<?php elseif ( $openmu_char ) : ?>
		<?php echo openmu_wp_connect_render_character_view( $openmu_char ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while built. ?>
	<?php endif; ?>
</div>
