<?php
/**
 * Event schedule block: the next start of each game event, per game server.
 *
 * Reads GET /api/website/events (cached for a minute), sorts each server's
 * events by their next start and prints the time in the site's time zone, with
 * a short day + time ("Today 00:30", "Tomorrow 02:00", "Fri 18:00"; the full date is in the
 * tooltip) and a relative hint ("in 25 mins"). The event names stay in English, as in game.
 *
 * Each server is drawn as a game window: the section carries the theme's
 * "mu-window" group style (stone + frame textures, gold title), so the theme CSS
 * styles it; blocks-extra.css only adds the grid and a plain fallback without the theme.
 * With exactly one server the wrapper gets "openmu-events--single": the window is full width and
 * its events flow into columns instead of one narrow list.
 *
 * Attributes: limit (events shown per server, 1-50).
 *
 * @package OpenMU_WP_Connect
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$openmu_connected = null !== openmu_wp_connect_api_source();
$openmu_limit     = max( 1, min( 50, (int) ( $attributes['limit'] ?? 10 ) ) );
// Event times move every few minutes; a minute of cache keeps the page cheap and close to the truth.
$openmu_servers = $openmu_connected ? openmu_wp_connect_api_cached_get( '/api/website/events', array(), MINUTE_IN_SECONDS ) : null;

// Build a clean, sorted list per server; the answer is untrusted, so only known fields are used.
$openmu_schedule = array();
foreach ( (array) $openmu_servers as $openmu_server ) {
	if ( ! is_array( $openmu_server ) ) {
		continue;
	}

	$openmu_events = array();
	foreach ( (array) ( $openmu_server['events'] ?? array() ) as $openmu_event ) {
		if ( ! is_array( $openmu_event ) || empty( $openmu_event['name'] ) || empty( $openmu_event['nextStartUtc'] ) ) {
			continue;
		}
		// .NET writes up to 7 fractional digits, more than PHP's parser takes; seconds are precise enough.
		$openmu_time = strtotime( (string) preg_replace( '/\.\d+/', '', (string) $openmu_event['nextStartUtc'] ) );
		if ( false === $openmu_time ) {
			continue;
		}
		$openmu_events[] = array(
			'name' => openmu_wp_connect_event_name( (string) ( $openmu_event['id'] ?? '' ), (string) $openmu_event['name'] ),
			'time' => $openmu_time,
		);
	}
	usort( $openmu_events, fn( $a, $b ) => $a['time'] <=> $b['time'] );

	$openmu_schedule[] = array(
		'name'   => (string) ( $openmu_server['serverName'] ?? '' ),
		'id'     => (int) ( $openmu_server['serverId'] ?? 0 ),
		'events' => array_slice( $openmu_events, 0, $openmu_limit ),
	);
}

$openmu_time_format = get_option( 'time_format' );
$openmu_full_format = get_option( 'date_format' ) . ' ' . $openmu_time_format;
$openmu_now         = time();

/**
 * Short label for an event start in the site's time zone: "Today 00:30", "Tomorrow 02:00",
 * or the short weekday for later days ("Fri 18:00").
 *
 * @param int    $timestamp   Event start (Unix time).
 * @param int    $now         Current time (Unix time).
 * @param string $time_format PHP time format of the site.
 * @return string
 */
$openmu_short_when = static function ( int $timestamp, int $now, string $time_format ): string {
	$time = wp_date( $time_format, $timestamp );
	$day  = wp_date( 'Y-m-d', $timestamp );
	if ( wp_date( 'Y-m-d', $now ) === $day ) {
		/* translators: %s: time of day, e.g. "00:30". */
		return sprintf( __( 'Today %s', 'openmu-wp-connect' ), $time );
	}
	if ( wp_date( 'Y-m-d', $now + DAY_IN_SECONDS ) === $day ) {
		/* translators: %s: time of day, e.g. "02:00". */
		return sprintf( __( 'Tomorrow %s', 'openmu-wp-connect' ), $time );
	}
	/* translators: 1: short weekday name, e.g. "Fri". 2: time of day, e.g. "18:00". */
	return sprintf( __( '%1$s %2$s', 'openmu-wp-connect' ), wp_date( 'D', $timestamp ), $time );
};

// With a single game server the one window spans the whole block and lists its events in columns (see blocks-extra.css).
$openmu_wrapper_class = 'openmu-events alignwide' . ( 1 === count( $openmu_schedule ) ? ' openmu-events--single' : '' );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => $openmu_wrapper_class ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>>
	<?php if ( ! $openmu_connected ) : ?>
		<?php echo openmu_wp_connect_not_connected_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
	<?php elseif ( null === $openmu_servers ) : ?>
		<p class="openmu-line openmu-line--requirement"><?php esc_html_e( 'The event schedule is not available right now.', 'openmu-wp-connect' ); ?></p>
	<?php elseif ( ! $openmu_schedule ) : ?>
		<p><?php esc_html_e( 'No events are planned at the moment.', 'openmu-wp-connect' ); ?></p>
	<?php else : ?>
		<?php foreach ( $openmu_schedule as $openmu_server ) : ?>
			<section class="wp-block-group is-style-mu-window openmu-events__server">
				<?php if ( count( $openmu_schedule ) > 1 || '' !== $openmu_server['name'] ) : ?>
					<h3 class="wp-block-heading mu-window__title openmu-events__server-name">
						<?php
						echo esc_html(
							'' !== $openmu_server['name']
								? $openmu_server['name']
								/* translators: %d: number of the game server. */
								: sprintf( __( 'Server %d', 'openmu-wp-connect' ), $openmu_server['id'] )
						);
						?>
					</h3>
				<?php endif; ?>
				<?php if ( ! $openmu_server['events'] ) : ?>
					<p class="openmu-line openmu-line--muted"><?php esc_html_e( 'No events are planned on this server.', 'openmu-wp-connect' ); ?></p>
				<?php else : ?>
					<ul class="openmu-events__list">
						<?php foreach ( $openmu_server['events'] as $openmu_event ) : ?>
							<li class="openmu-events__event">
								<span class="openmu-events__name openmu-line--option"><?php echo esc_html( $openmu_event['name'] ); ?></span>
								<time class="openmu-events__time" datetime="<?php echo esc_attr( gmdate( 'c', $openmu_event['time'] ) ); ?>" title="<?php echo esc_attr( wp_date( $openmu_full_format, $openmu_event['time'] ) ); ?>">
									<?php echo esc_html( $openmu_short_when( $openmu_event['time'], $openmu_now, $openmu_time_format ) ); ?>
								</time>
								<span class="openmu-events__in openmu-line--muted">
									<?php
									echo esc_html(
										$openmu_event['time'] > $openmu_now
											/* translators: %s: time until the event starts, e.g. "25 mins". */
											? sprintf( __( 'in %s', 'openmu-wp-connect' ), human_time_diff( $openmu_now, $openmu_event['time'] ) )
											: __( 'starting now', 'openmu-wp-connect' )
									);
									?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
