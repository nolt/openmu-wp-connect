<?php
/**
 * Player account block: website login of a game account and the account page.
 *
 * Logged out: login form and, when allowRegistration is on, a form to create an
 * account. Logged in: account state, whether the account is in the game, the
 * characters (level, master level, resets, zen, location), password change,
 * refresh of the data and logout. The forms are handled in includes/account.php
 * before the page is rendered; this file only shows the result.
 *
 * A character name in the list opens that character on the same page
 * (?openmu_character=<name>, includes/character-view.php), with its equipment, zen
 * and location; only characters of the logged-in account are accepted. With a
 * character opened the block shows only the "All characters" link, the character
 * switch and the character view; the account header, the character list, password
 * change, refresh and logout belong to the normal account view (no character opened).
 *
 * Attributes: allowRegistration (bool).
 *
 * @package OpenMU_WP_Connect
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

// The block shows personal data and nonces: never from a page cache.
openmu_wp_connect_account_send_no_cache();

$openmu_connected = null !== openmu_wp_connect_api_source();
$openmu_session   = $openmu_connected ? openmu_wp_connect_account_session() : null;
$openmu_account   = $openmu_session ? $openmu_session['account'] : null;
$openmu_register  = ! empty( $attributes['allowRegistration'] );

// Message of the last form, passed through the redirect as a fixed code (see openmu_wp_connect_account_message()).
$openmu_message_code = isset( $_GET[ OPENMU_WP_CONNECT_ACCOUNT_MESSAGE_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ OPENMU_WP_CONNECT_ACCOUNT_MESSAGE_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$openmu_retry        = isset( $_GET[ OPENMU_WP_CONNECT_ACCOUNT_RETRY_ARG ] ) ? absint( $_GET[ OPENMU_WP_CONNECT_ACCOUNT_RETRY_ARG ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$openmu_message      = '' !== $openmu_message_code ? openmu_wp_connect_account_message( $openmu_message_code, min( $openmu_retry, 60 ) ) : null;
// "Logged in" after the session has already ended would be confusing.
if ( $openmu_message && ! $openmu_account && in_array( $openmu_message_code, array( 'logged_in', 'refreshed' ), true ) ) {
	$openmu_message = openmu_wp_connect_account_message( 'session_expired' );
}

$openmu_time_format = (string) get_option( 'time_format' );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'openmu-account', 'id' => 'openmu-account' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>>
	<?php if ( ! $openmu_connected ) : ?>
		<?php echo openmu_wp_connect_not_connected_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
	<?php else : ?>
		<?php if ( $openmu_message ) : ?>
			<p class="openmu-account__message openmu-line openmu-line--<?php echo esc_attr( $openmu_message[0] ); ?>" role="<?php echo 'requirement' === $openmu_message[0] ? 'alert' : 'status'; ?>"><?php echo esc_html( $openmu_message[1] ); ?></p>
		<?php endif; ?>

		<?php if ( ! $openmu_account ) : ?>
			<div class="openmu-account__forms">
				<form class="openmu-account__form" method="post">
					<h2 class="openmu-account__title"><?php esc_html_e( 'Log in', 'openmu-wp-connect' ); ?></h2>
					<?php openmu_wp_connect_account_form_fields( 'login' ); ?>
					<p>
						<label for="openmu-login-name"><?php esc_html_e( 'Login name', 'openmu-wp-connect' ); ?></label>
						<input id="openmu-login-name" type="text" name="openmu_login_name" minlength="3" maxlength="10" required autocomplete="username" autocapitalize="none" spellcheck="false" />
					</p>
					<p>
						<label for="openmu-password"><?php esc_html_e( 'Password', 'openmu-wp-connect' ); ?></label>
						<input id="openmu-password" type="password" name="openmu_password" minlength="3" maxlength="20" required autocomplete="current-password" />
					</p>
					<p><button type="submit" class="wp-element-button"><?php esc_html_e( 'Log in', 'openmu-wp-connect' ); ?></button></p>
					<p class="openmu-line openmu-line--muted"><?php esc_html_e( 'Use the login name and password of your game account.', 'openmu-wp-connect' ); ?></p>
				</form>

				<?php if ( $openmu_register ) : ?>
					<form class="openmu-account__form" method="post">
						<h2 class="openmu-account__title"><?php esc_html_e( 'Create an account', 'openmu-wp-connect' ); ?></h2>
						<?php openmu_wp_connect_account_form_fields( 'register' ); ?>
						<p>
							<label for="openmu-register-name"><?php esc_html_e( 'Login name', 'openmu-wp-connect' ); ?></label>
							<input id="openmu-register-name" type="text" name="openmu_login_name" minlength="3" maxlength="10" pattern="[!-~]{3,10}" required autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="openmu-register-name-help" />
							<span id="openmu-register-name-help" class="openmu-line openmu-line--muted"><?php esc_html_e( '3 to 10 characters, no spaces.', 'openmu-wp-connect' ); ?></span>
						</p>
						<p>
							<label for="openmu-register-password"><?php esc_html_e( 'Password', 'openmu-wp-connect' ); ?></label>
							<input id="openmu-register-password" type="password" name="openmu_password" minlength="3" maxlength="20" required autocomplete="new-password" aria-describedby="openmu-register-password-help" />
							<span id="openmu-register-password-help" class="openmu-line openmu-line--muted"><?php esc_html_e( '3 to 20 characters, without national characters.', 'openmu-wp-connect' ); ?></span>
						</p>
						<p>
							<label for="openmu-register-repeat"><?php esc_html_e( 'Repeat the password', 'openmu-wp-connect' ); ?></label>
							<input id="openmu-register-repeat" type="password" name="openmu_password_repeat" minlength="3" maxlength="20" required autocomplete="new-password" />
						</p>
						<p>
							<label for="openmu-register-email"><?php esc_html_e( 'E-mail (optional)', 'openmu-wp-connect' ); ?></label>
							<input id="openmu-register-email" type="email" name="openmu_email" maxlength="254" autocomplete="email" />
						</p>
						<?php openmu_wp_connect_guard_register_fields(); ?>
						<p><button type="submit" class="wp-element-button"><?php esc_html_e( 'Create account', 'openmu-wp-connect' ); ?></button></p>
					</form>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php [ $openmu_requested, $openmu_chosen ] = openmu_wp_connect_account_requested_character( $openmu_account ); ?>
			<?php if ( $openmu_chosen ) : ?>
				<?php // Character opened: only the character view, nothing of the account below it. ?>
				<?php openmu_wp_connect_account_render_character( $openmu_account, $openmu_chosen ); ?>
			<?php else : ?>
			<header class="openmu-account__header">
				<h2 class="openmu-account__title">
					<?php
					/* translators: %s: login name of the game account. */
					echo esc_html( sprintf( __( 'Account %s', 'openmu-wp-connect' ), $openmu_account['loginName'] ) );
					?>
				</h2>
				<?php if ( '' !== $openmu_account['state'] ) : ?>
					<p class="openmu-line <?php echo esc_attr( str_contains( $openmu_account['state'], 'Banned' ) ? 'openmu-line--requirement' : 'openmu-line--option' ); ?>">
						<?php
						/* translators: %s: account state from the game server, e.g. Normal or Banned. */
						echo esc_html( sprintf( __( 'Account state: %s', 'openmu-wp-connect' ), openmu_wp_connect_split_words( $openmu_account['state'] ) ) );
						?>
					</p>
				<?php endif; ?>
				<?php if ( $openmu_account['isOfflineLeveling'] ) : ?>
					<p class="openmu-line openmu-line--excellent"><?php esc_html_e( 'A character of this account is leveling offline. End offline leveling before you change the password.', 'openmu-wp-connect' ); ?></p>
				<?php elseif ( $openmu_account['isOnline'] ) : ?>
					<p class="openmu-line openmu-line--excellent"><?php esc_html_e( 'You are in the game now. Leave the game to change the password.', 'openmu-wp-connect' ); ?></p>
				<?php else : ?>
					<p class="openmu-line openmu-line--muted"><?php esc_html_e( 'You are not in the game now.', 'openmu-wp-connect' ); ?></p>
				<?php endif; ?>
				<p class="openmu-line openmu-line--muted">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: time the data was read from the game server, 2: time the website session ends. */
							__( 'Data as of %1$s. Your session on the site ends at %2$s.', 'openmu-wp-connect' ),
							wp_date( $openmu_time_format, (int) $openmu_session['loggedInAt'] ),
							wp_date( $openmu_time_format, (int) $openmu_session['expires'] )
						)
					);
					?>
				</p>
			</header>

			<section class="openmu-account__characters" aria-labelledby="openmu-account-characters">
				<h3 id="openmu-account-characters"><?php esc_html_e( 'Characters', 'openmu-wp-connect' ); ?></h3>
					<?php if ( '' !== $openmu_requested ) : ?>
						<p class="openmu-line openmu-line--requirement" role="alert">
							<?php
							/* translators: %s: character name from the address. */
							echo esc_html( sprintf( __( 'There is no character named "%s" on your account.', 'openmu-wp-connect' ), $openmu_requested ) );
							?>
						</p>
					<?php endif; ?>
					<?php if ( ! $openmu_account['characters'] ) : ?>
						<p class="openmu-line openmu-line--muted"><?php esc_html_e( 'No characters yet. Create one in the game.', 'openmu-wp-connect' ); ?></p>
					<?php else : ?>
						<div class="openmu-account__table">
							<table>
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Character', 'openmu-wp-connect' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Class', 'openmu-wp-connect' ); ?></th>
										<th scope="col" class="openmu-account__number"><?php esc_html_e( 'Level', 'openmu-wp-connect' ); ?></th>
										<th scope="col" class="openmu-account__number"><?php esc_html_e( 'Resets', 'openmu-wp-connect' ); ?></th>
										<th scope="col" class="openmu-account__number"><?php esc_html_e( 'Zen', 'openmu-wp-connect' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Location', 'openmu-wp-connect' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $openmu_account['characters'] as $openmu_char ) : ?>
										<tr>
											<td><a href="<?php echo esc_url( openmu_wp_connect_account_character_url( $openmu_char['name'] ) ); ?>"><?php echo esc_html( $openmu_char['name'] ); ?></a></td>
											<td><?php echo esc_html( $openmu_char['className'] ); ?></td>
											<td class="openmu-account__number">
												<?php
												echo esc_html( number_format_i18n( $openmu_char['level'] ) );
												if ( $openmu_char['masterLevel'] > 0 ) {
													/* translators: %d: master level, shown after the normal level. */
													echo ' <span class="openmu-line--option">' . esc_html( sprintf( __( '+%d ML', 'openmu-wp-connect' ), $openmu_char['masterLevel'] ) ) . '</span>';
												}
												?>
											</td>
											<td class="openmu-account__number"><?php echo esc_html( number_format_i18n( $openmu_char['resets'] ) ); ?></td>
											<td class="openmu-account__number"><?php echo esc_html( number_format_i18n( $openmu_char['money'] ) ); ?></td>
											<td>
												<?php
												// The map name comes from the server; fall back to its number for maps without a name.
												$openmu_map = '' !== $openmu_char['mapName']
													? $openmu_char['mapName']
													/* translators: %d: map number of the game. */
													: sprintf( __( 'Map %d', 'openmu-wp-connect' ), $openmu_char['mapNumber'] );
												echo esc_html(
													sprintf(
														/* translators: 1: map name, 2: X coordinate, 3: Y coordinate. */
														__( '%1$s (%2$d, %3$d)', 'openmu-wp-connect' ),
														$openmu_map,
														$openmu_char['positionX'],
														$openmu_char['positionY']
													)
												);
												?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
			</section>

			<div class="openmu-account__forms">
				<form class="openmu-account__form" method="post">
					<h3 class="openmu-account__title"><?php esc_html_e( 'Change password', 'openmu-wp-connect' ); ?></h3>
					<?php openmu_wp_connect_account_form_fields( 'password' ); ?>
					<?php // Browsers' password managers need the login name next to a new password. ?>
					<input type="text" name="openmu_username_hint" value="<?php echo esc_attr( $openmu_account['loginName'] ); ?>" autocomplete="username" hidden />
					<p>
						<label for="openmu-current-password"><?php esc_html_e( 'Current password', 'openmu-wp-connect' ); ?></label>
						<input id="openmu-current-password" type="password" name="openmu_current_password" maxlength="20" required autocomplete="current-password" />
					</p>
					<p>
						<label for="openmu-new-password"><?php esc_html_e( 'New password', 'openmu-wp-connect' ); ?></label>
						<input id="openmu-new-password" type="password" name="openmu_new_password" minlength="3" maxlength="20" required autocomplete="new-password" aria-describedby="openmu-new-password-help" />
						<span id="openmu-new-password-help" class="openmu-line openmu-line--muted"><?php esc_html_e( '3 to 20 characters, without national characters.', 'openmu-wp-connect' ); ?></span>
					</p>
					<p>
						<label for="openmu-new-password-repeat"><?php esc_html_e( 'Repeat the new password', 'openmu-wp-connect' ); ?></label>
						<input id="openmu-new-password-repeat" type="password" name="openmu_new_password_repeat" minlength="3" maxlength="20" required autocomplete="new-password" />
					</p>
					<p><button type="submit" class="wp-element-button"><?php esc_html_e( 'Change password', 'openmu-wp-connect' ); ?></button></p>
				</form>

				<div class="openmu-account__form">
					<form method="post">
						<h3 class="openmu-account__title"><?php esc_html_e( 'Refresh data', 'openmu-wp-connect' ); ?></h3>
						<?php openmu_wp_connect_account_form_fields( 'refresh' ); ?>
						<p class="openmu-line openmu-line--muted"><?php esc_html_e( 'The site does not keep your password, so it needs it again to read the current data.', 'openmu-wp-connect' ); ?></p>
						<p>
							<label for="openmu-refresh-password"><?php esc_html_e( 'Password', 'openmu-wp-connect' ); ?></label>
							<input id="openmu-refresh-password" type="password" name="openmu_password" maxlength="20" required autocomplete="current-password" />
						</p>
						<p><button type="submit" class="wp-element-button"><?php esc_html_e( 'Refresh', 'openmu-wp-connect' ); ?></button></p>
					</form>

					<form class="openmu-account__logout" method="post">
						<?php openmu_wp_connect_account_form_fields( 'logout' ); ?>
						<button type="submit" class="wp-element-button"><?php esc_html_e( 'Log out', 'openmu-wp-connect' ); ?></button>
					</form>
				</div>
			</div>
			<?php endif; // Character opened or not. ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
