<?php
/**
 * Friendly English names for scheduled events shown on the Events page.
 *
 * The API returns the plugin display name (e.g. "DevilSquareStartPlugIn"),
 * which is a technical type name. This file maps the stable plugin GUID
 * (the event `id`) to the in-game name. Unknown ids keep the API name.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the display name of an event.
 *
 * GUIDs come from the `[Guid]` attributes of the OpenMU
 * `GameLogic/PlugIns/PeriodicTasks/*StartPlugIn.cs` classes.
 * Ids are compared case-insensitively.
 *
 * @param string $id       Event id (plugin GUID) from the API.
 * @param string $fallback Name returned by the API, used for unknown ids.
 * @return string Friendly name, or the fallback.
 */
function openmu_wp_connect_event_name( $id, $fallback ) {
	$names = array(
		'61c61a58-211e-4d6a-9ea1-d25e0c4a47c5' => __( 'Devil Square', 'openmu-wp-connect' ),
		'95e68c14-ad87-4b3c-af46-45b8f1c3bc2a' => __( 'Blood Castle', 'openmu-wp-connect' ),
		'3ad96a70-ed24-4979-80b8-169e461e548f' => __( 'Chaos Castle', 'openmu-wp-connect' ),
		'3ad96a70-ed24-4979-80b8-169e464e545f' => __( 'Illusion Temple', 'openmu-wp-connect' ),
		'a8f3c2d1-9e74-4ecb-8963-08a3697278c4' => __( 'Kanturu', 'openmu-wp-connect' ),
	);

	$key = strtolower( trim( (string) $id, "{} \t\n\r" ) );

	return isset( $names[ $key ] ) ? $names[ $key ] : (string) $fallback;
}
