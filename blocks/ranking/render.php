<?php
/**
 * Character ranking block: a table of the top characters.
 *
 * Attributes: limit (1-100 rows), characterClass (exact class name or empty).
 *
 * @package OpenMU_WP_Connect
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$openmu_class = (string) ( $attributes['characterClass'] ?? '' );
$openmu_rows  = openmu_wp_connect_source()->get_ranking( (int) ( $attributes['limit'] ?? 10 ), '' !== $openmu_class ? $openmu_class : null );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'openmu-ranking' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>>
	<?php if ( null === $openmu_rows ) : ?>
		<p class="openmu-line openmu-line--requirement"><?php esc_html_e( 'The ranking is not available right now.', 'openmu-wp-connect' ); ?></p>
	<?php elseif ( ! $openmu_rows ) : ?>
		<p><?php esc_html_e( 'No characters in this ranking yet.', 'openmu-wp-connect' ); ?></p>
	<?php else : ?>
		<table>
			<thead>
				<tr>
					<th scope="col" class="openmu-ranking__place"><?php esc_html_e( 'Place', 'openmu-wp-connect' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Character', 'openmu-wp-connect' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Class', 'openmu-wp-connect' ); ?></th>
					<th scope="col" class="openmu-ranking__number"><?php esc_html_e( 'Resets', 'openmu-wp-connect' ); ?></th>
					<th scope="col" class="openmu-ranking__number"><?php esc_html_e( 'Level', 'openmu-wp-connect' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Guild', 'openmu-wp-connect' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $openmu_rows as $openmu_i => $openmu_row ) : ?>
					<tr>
						<td class="openmu-ranking__place"><?php echo esc_html( (string) ( $openmu_i + 1 ) ); ?></td>
						<td><a href="<?php echo esc_url( openmu_wp_connect_armory_url( $openmu_row['name'] ) ); ?>"><?php echo esc_html( $openmu_row['name'] ); ?></a></td>
						<td><?php echo esc_html( $openmu_row['characterClass'] ); ?></td>
						<td class="openmu-ranking__number"><?php echo esc_html( (string) $openmu_row['resets'] ); ?></td>
						<td class="openmu-ranking__number">
							<?php
							echo esc_html( (string) $openmu_row['level'] );
							if ( ! empty( $openmu_row['masterLevel'] ) ) {
								/* translators: %d: master level, shown after the normal level. */
								echo ' <span class="openmu-line--option">' . esc_html( sprintf( __( '+%d ML', 'openmu-wp-connect' ), $openmu_row['masterLevel'] ) ) . '</span>';
							}
							?>
						</td>
						<td><?php echo esc_html( $openmu_row['guild'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
