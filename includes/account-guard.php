<?php
/**
 * Protection of the account forms against bots and password guessing.
 *
 * The game server limits failed logins per account, but it sees every request
 * coming from the web server's address, so it can't tell visitors apart. This
 * file adds what only the site can do:
 *
 * - limits per visitor address: failed logins (many accounts tried from one
 *   address) and registrations (mass account creation);
 * - a honeypot field in the registration form, which people never see and
 *   simple bots fill in;
 * - hooks for a captcha plugin: an action that prints into the registration form
 *   and a filter that can refuse the registration.
 *
 * The counters live in transients keyed by a hash of the address, so the
 * addresses themselves aren't stored in readable form.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/** Name of the honeypot field of the registration form. */
const OPENMU_WP_CONNECT_HONEYPOT_FIELD = 'openmu_website';

/**
 * Returns the address of the visitor the limits count for.
 *
 * Only REMOTE_ADDR is used by default: forwarding headers can be forged by anyone.
 * A site behind a reverse proxy or CDN should return the real client address with
 * the "openmu_wp_connect_client_ip" filter, otherwise all visitors share one address.
 */
function openmu_wp_connect_guard_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	/**
	 * Filters the visitor address used by the login and registration limits.
	 *
	 * @param string $ip Address from REMOTE_ADDR.
	 */
	return (string) apply_filters( 'openmu_wp_connect_client_ip', $ip );
}

/**
 * Returns the limits per visitor address: attempts allowed and the window in seconds.
 *
 * - login: failed logins; a successful login doesn't count.
 * - register: registrations sent to the server, successful or not.
 *
 * @return array<string, array{0: int, 1: int}>
 */
function openmu_wp_connect_guard_limits(): array {
	/**
	 * Filters the limits of the account forms per visitor address.
	 *
	 * Each entry is [ allowed attempts, window in seconds ]; 0 attempts turns the limit off.
	 *
	 * @param array<string, array{0: int, 1: int}> $limits Defaults: 10 failed logins per
	 *        15 minutes, 5 registrations per hour.
	 */
	$limits = apply_filters(
		'openmu_wp_connect_rate_limits',
		array(
			'login'    => array( 10, 15 * MINUTE_IN_SECONDS ),
			'register' => array( 5, HOUR_IN_SECONDS ),
		)
	);
	return is_array( $limits ) ? $limits : array();
}

/**
 * Name of the transient that counts the attempts of one address for one form.
 *
 * @param string $bucket Form: "login" or "register".
 * @param string $ip     Visitor address.
 */
function openmu_wp_connect_guard_transient( string $bucket, string $ip ): string {
	return 'openmu_wp_connect_guard_' . hash( 'sha256', $bucket . '|' . $ip );
}

/**
 * Tells how long the visitor has to wait before the next attempt.
 *
 * @param string $bucket Form: "login" or "register".
 * @return int Seconds to wait; 0 when the attempt is allowed.
 */
function openmu_wp_connect_guard_wait( string $bucket ): int {
	[ $allowed ] = openmu_wp_connect_guard_limits()[ $bucket ] ?? array( 0, 0 );
	$ip          = openmu_wp_connect_guard_ip();
	if ( $allowed <= 0 || '' === $ip ) {
		return 0;
	}

	$entry = get_transient( openmu_wp_connect_guard_transient( $bucket, $ip ) );
	if ( ! is_array( $entry ) || (int) ( $entry['count'] ?? 0 ) < $allowed ) {
		return 0;
	}
	return max( 0, (int) ( $entry['until'] ?? 0 ) - time() );
}

/**
 * Counts one attempt of the visitor.
 *
 * The window starts with the first attempt and isn't extended by later ones, so a
 * blocked visitor can try again when it ends.
 *
 * @param string $bucket Form: "login" or "register".
 */
function openmu_wp_connect_guard_hit( string $bucket ): void {
	[ $allowed, $window ] = openmu_wp_connect_guard_limits()[ $bucket ] ?? array( 0, 0 );
	$ip                   = openmu_wp_connect_guard_ip();
	if ( $allowed <= 0 || $window <= 0 || '' === $ip ) {
		return;
	}

	$name  = openmu_wp_connect_guard_transient( $bucket, $ip );
	$entry = get_transient( $name );
	if ( ! is_array( $entry ) || (int) ( $entry['until'] ?? 0 ) <= time() ) {
		$entry = array(
			'count' => 0,
			'until' => time() + $window,
		);
	}
	++$entry['count'];
	set_transient( $name, $entry, max( 1, (int) $entry['until'] - time() ) );
}

/**
 * Checks the registration form against bots before anything goes to the server.
 *
 * @param string $login_name Login name as typed.
 * @param string $email      E-mail address, may be empty.
 * @return string Empty when the registration may go on, otherwise a message code
 *                (see openmu_wp_connect_account_message()).
 */
function openmu_wp_connect_guard_register( string $login_name, string $email ): string {
	// People never see the honeypot field; anything in it was typed by a bot.
	if ( '' !== openmu_wp_connect_account_posted( OPENMU_WP_CONNECT_HONEYPOT_FIELD ) ) {
		return 'register_invalid';
	}

	/**
	 * Filters whether a registration may go on, e.g. to verify a captcha.
	 *
	 * Return true to allow it, or a message code to refuse it ("captcha_failed" shows
	 * "Please confirm that you are not a robot."; any unknown code shows nothing).
	 * Print the captcha into the form with the "openmu_wp_connect_register_form" action.
	 *
	 * @param true|string $allowed    True so far.
	 * @param string      $login_name Login name as typed.
	 * @param string      $email      E-mail address, may be empty.
	 */
	$allowed = apply_filters( 'openmu_wp_connect_register_allowed', true, $login_name, $email );
	return true === $allowed ? '' : (string) $allowed;
}

/**
 * Prints the honeypot field and the extension point for a captcha into the registration form.
 */
function openmu_wp_connect_guard_register_fields(): void {
	// Off-screen rather than display:none, which some bots skip; hidden from screen readers and the keyboard.
	printf(
		'<div class="openmu-account__trap" aria-hidden="true"><label for="openmu-register-website">%1$s</label><input id="openmu-register-website" type="text" name="%2$s" value="" tabindex="-1" autocomplete="off" /></div>',
		esc_html__( 'Leave this field empty', 'openmu-wp-connect' ),
		esc_attr( OPENMU_WP_CONNECT_HONEYPOT_FIELD )
	);

	/**
	 * Fires inside the registration form of the account block, before the submit button.
	 *
	 * Use it to print a captcha; verify it with the "openmu_wp_connect_register_allowed" filter.
	 */
	do_action( 'openmu_wp_connect_register_form' );
}
