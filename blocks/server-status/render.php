<?php
/**
 * Server status block: two lines, state and player count.
 *
 * Green "online" and red "offline" follow the item-tooltip colour code
 * (excellent green / unmet requirement red). A small lamp dot in the same colour
 * precedes the state text; it is decorative, the text is the accessible status.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

$openmu_status = openmu_wp_connect_source()->get_status();
$openmu_online = $openmu_status && $openmu_status['online'];
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'openmu-status ' . ( $openmu_online ? 'is-online' : 'is-offline' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>>
	<?php if ( $openmu_online ) : ?>
		<p class="openmu-line openmu-line--excellent"><span class="openmu-status__dot" aria-hidden="true"></span><?php esc_html_e( 'Server online', 'openmu-wp-connect' ); ?></p>
		<p class="openmu-line">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of players currently in game. */
					_n( '%d player in game', '%d players in game', $openmu_status['players'], 'openmu-wp-connect' ),
					$openmu_status['players']
				)
			);
			?>
		</p>
	<?php else : ?>
		<p class="openmu-line openmu-line--requirement"><span class="openmu-status__dot" aria-hidden="true"></span><?php esc_html_e( 'Server offline', 'openmu-wp-connect' ); ?></p>
	<?php endif; ?>
</div>
