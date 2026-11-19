<?php
/**
 * Player accounts on the site: website login, registration, password change and logout.
 *
 * The site never keeps a player's password. After a successful
 * POST /api/website/login the answer (account state and characters) is stored
 * in a transient under a random token; the browser only gets the token, in an
 * HttpOnly, SameSite=Lax cookie that lives 30 minutes. The transient name is a
 * hash of the token, so a copy of the database doesn't give working cookies.
 * Because the password isn't kept, the stored data is a snapshot from the
 * moment of the login; "refresh" means logging in again with the password.
 *
 * Forms of the account block post to the page they are on; the handler runs on
 * template_redirect, checks the nonce, calls the API and redirects back with a
 * message code (POST-redirect-GET), so reloading the page never resends a password.
 *
 * Players' sessions are not WordPress users: a game account gets no rights on the site.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/** Name of the cookie with the session token. */
const OPENMU_WP_CONNECT_ACCOUNT_COOKIE = 'openmu_wp_connect_session';

/** Lifetime of a session in seconds (30 minutes, not extended by activity). */
const OPENMU_WP_CONNECT_ACCOUNT_LIFETIME = 1800;

/** Query parameter that carries the message code after a redirect. */
const OPENMU_WP_CONNECT_ACCOUNT_MESSAGE_ARG = 'openmu_account';

/** Query parameter that carries the lockout wait in minutes after a redirect. */
const OPENMU_WP_CONNECT_ACCOUNT_RETRY_ARG = 'openmu_retry';

/**
 * Checks a login name the way the server does: 3-10 printable ASCII characters, no spaces.
 *
 * @param string $login_name Login name as typed.
 */
function openmu_wp_connect_account_valid_login_name( string $login_name ): bool {
	return 1 === preg_match( '/^[\x21-\x7E]{3,10}$/', $login_name );
}

/**
 * Checks a password the way the server does: 3-20 printable ASCII characters.
 *
 * @param string $password Password as typed.
 */
function openmu_wp_connect_account_valid_password( string $password ): bool {
	return 1 === preg_match( '/^[\x20-\x7E]{3,20}$/', $password );
}

/**
 * Returns the session token from the cookie, or null when there is none or it is malformed.
 */
function openmu_wp_connect_account_token(): ?string {
	$token = isset( $_COOKIE[ OPENMU_WP_CONNECT_ACCOUNT_COOKIE ] ) ? (string) wp_unslash( $_COOKIE[ OPENMU_WP_CONNECT_ACCOUNT_COOKIE ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by the pattern below.
	return 1 === preg_match( '/^[A-Za-z0-9]{48}$/', $token ) ? $token : null;
}

/**
 * Name of the transient that holds the session of a token.
 *
 * @param string $token Session token from the cookie.
 */
function openmu_wp_connect_account_transient( string $token ): string {
	return 'openmu_wp_connect_acct_' . hash( 'sha256', $token );
}

/**
 * Sends the session cookie, or deletes it when the token is empty.
 *
 * @param string $token   Session token, '' to delete the cookie.
 * @param int    $expires Unix time when the cookie expires.
 */
function openmu_wp_connect_account_set_cookie( string $token, int $expires ): void {
	if ( headers_sent() ) {
		return;
	}

	setcookie(
		OPENMU_WP_CONNECT_ACCOUNT_COOKIE,
		$token,
		array(
			'expires'  => '' === $token ? time() - YEAR_IN_SECONDS : $expires,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
	// Later code in this request (the block) must see the new state, not the old cookie.
	if ( '' === $token ) {
		unset( $_COOKIE[ OPENMU_WP_CONNECT_ACCOUNT_COOKIE ] );
	} else {
		$_COOKIE[ OPENMU_WP_CONNECT_ACCOUNT_COOKIE ] = $token;
	}
}

/**
 * Keeps only the known fields of a login answer, with fixed types.
 *
 * The answer comes from another system, so nothing unexpected is stored;
 * escaping still happens when the block prints the values.
 *
 * @param array $answer Decoded answer of POST /api/website/login.
 * @return array<string, mixed>
 */
function openmu_wp_connect_account_normalize( array $answer ): array {
	$characters = array();
	foreach ( (array) ( $answer['characters'] ?? array() ) as $character ) {
		if ( ! is_array( $character ) ) {
			continue;
		}
		$characters[] = array(
			'name'        => (string) ( $character['name'] ?? '' ),
			'className'   => (string) ( $character['className'] ?? '' ),
			'level'       => (int) ( $character['level'] ?? 0 ),
			'masterLevel' => (int) ( $character['masterLevel'] ?? 0 ),
			'resets'      => (int) ( $character['resets'] ?? 0 ),
			'money'       => (int) ( $character['money'] ?? 0 ),
			'mapNumber'   => (int) ( $character['mapNumber'] ?? 0 ),
			'mapName'     => (string) ( $character['mapName'] ?? '' ),
			'positionX'   => (int) ( $character['positionX'] ?? 0 ),
			'positionY'   => (int) ( $character['positionY'] ?? 0 ),
		);
	}

	return array(
		'loginName'         => (string) ( $answer['loginName'] ?? '' ),
		'state'             => (string) ( $answer['state'] ?? '' ),
		'isOnline'          => ! empty( $answer['isOnline'] ),
		'isOfflineLeveling' => ! empty( $answer['isOfflineLeveling'] ),
		'characters'        => $characters,
	);
}

/**
 * Starts a new session for a login answer: new token, new transient, new cookie.
 *
 * A new token on every login (also on refresh) avoids session fixation; the
 * previous session of this browser is removed.
 *
 * @param array $answer Decoded answer of POST /api/website/login.
 */
function openmu_wp_connect_account_start( array $answer ): void {
	// Only the stored data is dropped: the new cookie below replaces the old one.
	$old = openmu_wp_connect_account_token();
	if ( $old ) {
		delete_transient( openmu_wp_connect_account_transient( $old ) );
	}

	$token   = wp_generate_password( 48, false, false );
	$expires = time() + OPENMU_WP_CONNECT_ACCOUNT_LIFETIME;
	set_transient(
		openmu_wp_connect_account_transient( $token ),
		array(
			'account'    => openmu_wp_connect_account_normalize( $answer ),
			'loggedInAt' => time(),
			'expires'    => $expires,
		),
		OPENMU_WP_CONNECT_ACCOUNT_LIFETIME
	);
	openmu_wp_connect_account_set_cookie( $token, $expires );
}

/**
 * Returns the session of the current visitor.
 *
 * @return array{account: array, loggedInAt: int, expires: int}|null The account data
 *         (as normalised from the login answer), the time of the login and the end
 *         of the session; null when the visitor isn't logged in.
 */
function openmu_wp_connect_account_session(): ?array {
	$token = openmu_wp_connect_account_token();
	if ( ! $token ) {
		return null;
	}

	$session = get_transient( openmu_wp_connect_account_transient( $token ) );
	// The expiry is checked too, because a persistent object cache may keep entries a little longer.
	if ( ! is_array( $session ) || (int) ( $session['expires'] ?? 0 ) < time() || empty( $session['account']['loginName'] ) ) {
		return null;
	}

	return $session;
}

/**
 * Returns the account data of the logged-in visitor, or null.
 *
 * @return array<string, mixed>|null Fields: loginName, state, isOnline, isOfflineLeveling, characters.
 */
function openmu_wp_connect_account_current(): ?array {
	$session = openmu_wp_connect_account_session();
	return $session ? $session['account'] : null;
}

/**
 * Logs the visitor out: removes the stored session and the cookie.
 */
function openmu_wp_connect_account_logout(): void {
	$token = openmu_wp_connect_account_token();
	if ( $token ) {
		delete_transient( openmu_wp_connect_account_transient( $token ) );
	}
	if ( isset( $_COOKIE[ OPENMU_WP_CONNECT_ACCOUNT_COOKIE ] ) ) {
		openmu_wp_connect_account_set_cookie( '', 0 );
	}
}

/**
 * Logs in through the API and starts a session on success.
 *
 * Also used to refresh the stored data of a running session, since the data can
 * only be read again with the password.
 *
 * @param string $login_name Login name.
 * @param string $password   Password; only sent to the API, never stored.
 * @return array{0: string, 1: int} Message code and the lockout wait in seconds.
 */
function openmu_wp_connect_account_login( string $login_name, string $password ): array {
	// The server answers wrong formats like wrong passwords; checking here saves a round trip.
	if ( ! openmu_wp_connect_account_valid_login_name( $login_name ) || ! openmu_wp_connect_account_valid_password( $password ) ) {
		return array( 'login_failed', 0 );
	}

	$answer = openmu_wp_connect_api_call(
		'POST',
		'/api/website/login',
		array(
			'loginName' => $login_name,
			'password'  => $password,
		)
	);

	switch ( $answer['code'] ) {
		case 200:
			if ( ! is_array( $answer['data'] ) || empty( $answer['data']['loginName'] ) ) {
				return array( 'unavailable', 0 );
			}
			openmu_wp_connect_account_start( $answer['data'] );
			return array( 'logged_in', 0 );
		case 400:
		case 401:
			return array( 'login_failed', 0 );
		case 403:
			return array( 'not_allowed', 0 );
		case 429:
			return array( 'locked_out', $answer['retry_after'] );
		default:
			return array( 'unavailable', 0 );
	}
}

/**
 * Refreshes the data of the running session by logging in again.
 *
 * @param string $password Password of the logged-in account; never stored.
 * @return array{0: string, 1: int} Message code and the lockout wait in seconds.
 */
function openmu_wp_connect_account_refresh( string $password ): array {
	$account = openmu_wp_connect_account_current();
	if ( ! $account ) {
		return array( 'session_expired', 0 );
	}

	[ $code, $retry ] = openmu_wp_connect_account_login( $account['loginName'], $password );
	return array( 'logged_in' === $code ? 'refreshed' : $code, $retry );
}

/**
 * Creates a game account through the API.
 *
 * @param string $login_name Login name.
 * @param string $password   Password; only sent to the API, never stored.
 * @param string $repeat     The password typed a second time.
 * @param string $email      E-mail address, may be empty.
 * @return string Message code.
 */
function openmu_wp_connect_account_register( string $login_name, string $password, string $repeat, string $email ): string {
	if ( ! openmu_wp_connect_account_valid_login_name( $login_name ) ) {
		return 'invalid_login_name';
	}
	if ( ! openmu_wp_connect_account_valid_password( $password ) ) {
		return 'invalid_password';
	}
	if ( $password !== $repeat ) {
		return 'password_mismatch';
	}
	if ( '' !== $email && ! is_email( $email ) ) {
		return 'invalid_email';
	}

	$body = array(
		'loginName' => $login_name,
		'password'  => $password,
	);
	if ( '' !== $email ) {
		$body['eMail'] = $email;
	}

	$answer = openmu_wp_connect_api_call( 'POST', '/api/website/accounts', $body );
	return match ( $answer['code'] ) {
		200, 201 => 'registered',
		400      => 'register_invalid',
		403      => 'not_allowed',
		409      => 'login_taken',
		default  => 'unavailable',
	};
}

/**
 * Changes the password of the logged-in account through the API.
 *
 * @param string $current  Current password.
 * @param string $new      New password.
 * @param string $repeat   The new password typed a second time.
 * @return array{0: string, 1: int} Message code and the lockout wait in seconds.
 */
function openmu_wp_connect_account_change_password( string $current, string $new, string $repeat ): array {
	$account = openmu_wp_connect_account_current();
	if ( ! $account ) {
		return array( 'session_expired', 0 );
	}
	if ( ! openmu_wp_connect_account_valid_password( $new ) ) {
		return array( 'invalid_new_password', 0 );
	}
	if ( $new !== $repeat ) {
		return array( 'password_mismatch', 0 );
	}
	if ( '' === $current ) {
		return array( 'wrong_password', 0 );
	}

	$answer = openmu_wp_connect_api_call(
		'PUT',
		'/api/website/accounts/' . rawurlencode( $account['loginName'] ) . '/password',
		array(
			'currentPassword' => $current,
			'newPassword'     => $new,
		)
	);

	switch ( $answer['code'] ) {
		case 200:
		case 204:
			return array( 'password_changed', 0 );
		case 400:
			return array( 'invalid_new_password', 0 );
		case 401:
			return array( 'wrong_password', 0 );
		case 403:
			return array( 'not_allowed', 0 );
		case 409:
			// 409 carries a status: OfflineLeveling = a character levels offline, AccountOnline = in the game.
			$status = is_array( $answer['data'] ) ? (string) ( $answer['data']['status'] ?? '' ) : '';
			return array( 'OfflineLeveling' === $status ? 'account_offline_leveling' : 'account_online', 0 );
		case 429:
			return array( 'locked_out', $answer['retry_after'] );
		default:
			return array( 'unavailable', 0 );
	}
}

/**
 * Returns the type and the text of a message code shown by the account block.
 *
 * Only these codes are accepted from the address bar, so the address can't inject text.
 *
 * @param string $code          Message code from the redirect.
 * @param int    $retry_minutes Lockout wait in minutes, for the "locked_out" code.
 * @return array{0: string, 1: string}|null CSS modifier (excellent = success, requirement = error) and text.
 */
function openmu_wp_connect_account_message( string $code, int $retry_minutes = 0 ): ?array {
	$locked = $retry_minutes > 0
		? sprintf(
			/* translators: %d: minutes until the next try is allowed. */
			_n( 'Too many failed attempts. Try again in %d minute.', 'Too many failed attempts. Try again in %d minutes.', $retry_minutes, 'openmu-wp-connect' ),
			$retry_minutes
		)
		: __( 'Too many failed attempts. Try again later.', 'openmu-wp-connect' );

	$messages = array(
		'logged_in'            => array( 'excellent', __( 'You are logged in.', 'openmu-wp-connect' ) ),
		'refreshed'            => array( 'excellent', __( 'Your account data is up to date.', 'openmu-wp-connect' ) ),
		'logged_out'           => array( 'excellent', __( 'You are logged out.', 'openmu-wp-connect' ) ),
		'registered'           => array( 'excellent', __( 'Your account has been created. You can log in now, on the site and in the game.', 'openmu-wp-connect' ) ),
		'password_changed'     => array( 'excellent', __( 'Your password has been changed. Use the new one in the game too.', 'openmu-wp-connect' ) ),
		'login_failed'         => array( 'requirement', __( 'Wrong login name or password.', 'openmu-wp-connect' ) ),
		'locked_out'           => array( 'requirement', $locked ),
		'wrong_password'       => array( 'requirement', __( 'The current password is wrong.', 'openmu-wp-connect' ) ),
		'account_offline_leveling' => array( 'requirement', __( 'A character of this account is offline leveling. Stop the offline leveling in the game first, then change the password.', 'openmu-wp-connect' ) ),
		'account_online'       => array( 'requirement', __( 'Your account is in the game now. Leave the game, then change the password.', 'openmu-wp-connect' ) ),
		'invalid_new_password' => array( 'requirement', __( 'The new password must have 3 to 20 characters: letters, digits and common symbols (no national characters).', 'openmu-wp-connect' ) ),
		'invalid_password'     => array( 'requirement', __( 'The password must have 3 to 20 characters: letters, digits and common symbols (no national characters).', 'openmu-wp-connect' ) ),
		'invalid_login_name'   => array( 'requirement', __( 'The login name must have 3 to 10 characters: letters, digits and common symbols, no spaces.', 'openmu-wp-connect' ) ),
		'password_mismatch'    => array( 'requirement', __( 'The two passwords are not the same.', 'openmu-wp-connect' ) ),
		'invalid_email'        => array( 'requirement', __( 'The e-mail address is not valid.', 'openmu-wp-connect' ) ),
		'register_invalid'     => array( 'requirement', __( 'The server did not accept these account details.', 'openmu-wp-connect' ) ),
		'login_taken'          => array( 'requirement', __( 'This login name is already taken.', 'openmu-wp-connect' ) ),
		'not_allowed'          => array( 'requirement', __( 'This function is not enabled for the site on the game server.', 'openmu-wp-connect' ) ),
		'not_connected'        => array( 'requirement', __( 'Connect the site to an OpenMU server in Settings > OpenMU WP Connect.', 'openmu-wp-connect' ) ),
		'unavailable'          => array( 'requirement', __( 'The game server is not available right now. Try again later.', 'openmu-wp-connect' ) ),
		'session_expired'      => array( 'requirement', __( 'Your session has ended. Log in again.', 'openmu-wp-connect' ) ),
		'expired_form'         => array( 'requirement', __( 'The form has expired. Please try again.', 'openmu-wp-connect' ) ),
	);

	return $messages[ $code ] ?? null;
}

/**
 * Redirects back to the page of the form with a message code (POST-redirect-GET) and stops.
 *
 * The address carries no fragment on purpose: the page loads at the top instead of
 * scrolling to the block, which would hide half of the site menu.
 *
 * @param string $code          Message code.
 * @param int    $retry_seconds Lockout wait in seconds, 0 when none.
 */
function openmu_wp_connect_account_redirect( string $code, int $retry_seconds = 0 ): never {
	// The page the form was posted to; wp_validate_redirect() rejects foreign hosts from a forged Host header.
	$host    = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$uri     = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$current = wp_validate_redirect( ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri, home_url( '/' ) );

	$target = remove_query_arg( array( OPENMU_WP_CONNECT_ACCOUNT_MESSAGE_ARG, OPENMU_WP_CONNECT_ACCOUNT_RETRY_ARG ), $current );
	$args   = array( OPENMU_WP_CONNECT_ACCOUNT_MESSAGE_ARG => $code );
	if ( $retry_seconds > 0 ) {
		$args[ OPENMU_WP_CONNECT_ACCOUNT_RETRY_ARG ] = (int) ceil( $retry_seconds / MINUTE_IN_SECONDS );
	}
	$target = add_query_arg( $args, $target );

	// 303 tells the browser to load the page with GET, so a reload doesn't resend the form.
	wp_safe_redirect( $target, 303 );
	exit;
}

/**
 * Reads a posted text field without changing it (passwords must stay byte for byte).
 *
 * @param string $name Field name.
 */
function openmu_wp_connect_account_posted( string $name ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the nonce is checked by the caller; values are validated against strict patterns and only sent to the API.
	return isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? (string) wp_unslash( $_POST[ $name ] ) : '';
}

/**
 * Handles the forms of the account block: login, refresh, registration, password change, logout.
 *
 * Runs before the page is rendered, so it can set the cookie and redirect.
 */
function openmu_wp_connect_account_handle_forms(): void {
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['openmu_wp_connect_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is checked below, per action.
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['openmu_wp_connect_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is checked below, per action.
	if ( ! in_array( $action, array( 'login', 'refresh', 'register', 'password', 'logout' ), true ) ) {
		return;
	}

	$nonce = isset( $_POST['openmu_wp_connect_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['openmu_wp_connect_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'openmu_wp_connect_' . $action ) ) {
		openmu_wp_connect_account_redirect( 'expired_form' );
	}

	// Logging out works even without a server connection.
	if ( 'logout' === $action ) {
		openmu_wp_connect_account_logout();
		openmu_wp_connect_account_redirect( 'logged_out' );
	}

	if ( ! openmu_wp_connect_api_source() ) {
		openmu_wp_connect_account_redirect( 'not_connected' );
	}

	switch ( $action ) {
		case 'login':
			[ $code, $retry ] = openmu_wp_connect_account_login( trim( openmu_wp_connect_account_posted( 'openmu_login_name' ) ), openmu_wp_connect_account_posted( 'openmu_password' ) );
			openmu_wp_connect_account_redirect( $code, $retry );
			break;
		case 'refresh':
			[ $code, $retry ] = openmu_wp_connect_account_refresh( openmu_wp_connect_account_posted( 'openmu_password' ) );
			openmu_wp_connect_account_redirect( $code, $retry );
			break;
		case 'register':
			openmu_wp_connect_account_redirect(
				openmu_wp_connect_account_register(
					trim( openmu_wp_connect_account_posted( 'openmu_login_name' ) ),
					openmu_wp_connect_account_posted( 'openmu_password' ),
					openmu_wp_connect_account_posted( 'openmu_password_repeat' ),
					sanitize_email( openmu_wp_connect_account_posted( 'openmu_email' ) )
				)
			);
			break;
		case 'password':
			[ $code, $retry ] = openmu_wp_connect_account_change_password(
				openmu_wp_connect_account_posted( 'openmu_current_password' ),
				openmu_wp_connect_account_posted( 'openmu_new_password' ),
				openmu_wp_connect_account_posted( 'openmu_new_password_repeat' )
			);
			openmu_wp_connect_account_redirect( $code, $retry );
			break;
	}
}
add_action( 'template_redirect', 'openmu_wp_connect_account_handle_forms', 5 );

/**
 * Keeps pages with player data or account forms out of page caches.
 *
 * A cached copy would show one player's characters to others, or carry
 * outdated nonces. The account block repeats this while rendering, for
 * templates where the block isn't part of the post content.
 */
function openmu_wp_connect_account_no_cache(): void {
	$has_block = is_singular() && has_block( 'openmu/account', get_queried_object() );
	if ( $has_block || openmu_wp_connect_account_token() || isset( $_GET[ OPENMU_WP_CONNECT_ACCOUNT_MESSAGE_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only.
		openmu_wp_connect_account_send_no_cache();
	}
}
add_action( 'template_redirect', 'openmu_wp_connect_account_no_cache', 6 );

/**
 * Sends the no-cache headers and asks caching plugins to skip the page.
 */
function openmu_wp_connect_account_send_no_cache(): void {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // Convention honoured by most page-cache plugins.
	}
	if ( ! headers_sent() ) {
		nocache_headers();
	}
}

/**
 * Prints the hidden fields every account form needs: the action and its nonce.
 *
 * The nonce input is written by hand instead of wp_nonce_field(), because the
 * block holds several forms and wp_nonce_field() gives each input the same id.
 *
 * @param string $action One of login, refresh, register, password, logout.
 */
function openmu_wp_connect_account_form_fields( string $action ): void {
	printf(
		'<input type="hidden" name="openmu_wp_connect_action" value="%1$s" /><input type="hidden" name="openmu_wp_connect_nonce" value="%2$s" />',
		esc_attr( $action ),
		esc_attr( wp_create_nonce( 'openmu_wp_connect_' . $action ) )
	);
}
