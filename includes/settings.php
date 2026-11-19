<?php
/**
 * Settings page (Settings > OpenMU WP Connect): address of the OpenMU server and the API key of the site.
 *
 * Both can also be set in wp-config.php with the constants OPENMU_WP_CONNECT_API_URL
 * and OPENMU_WP_CONNECT_API_KEY; the constants win and keep the key out of the
 * database. Without an address and a key, the blocks keep showing the demo data.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gets the configured server address, or an empty string.
 */
function openmu_wp_connect_api_url(): string {
	return defined( 'OPENMU_WP_CONNECT_API_URL' ) ? (string) OPENMU_WP_CONNECT_API_URL : (string) get_option( 'openmu_wp_connect_api_url', '' );
}

/**
 * Gets the configured API key, or an empty string.
 */
function openmu_wp_connect_api_key(): string {
	return defined( 'OPENMU_WP_CONNECT_API_KEY' ) ? (string) OPENMU_WP_CONNECT_API_KEY : (string) get_option( 'openmu_wp_connect_api_key', '' );
}

/**
 * Uses the API source when the server address and the key are configured.
 *
 * @param OpenMU_WP_Connect_Data_Source $source The source so far (the demo source).
 */
function openmu_wp_connect_use_api_source( OpenMU_WP_Connect_Data_Source $source ): OpenMU_WP_Connect_Data_Source {
	$url = openmu_wp_connect_api_url();
	$key = openmu_wp_connect_api_key();
	return '' !== $url && '' !== $key ? new OpenMU_WP_Connect_Api_Source( $url, $key ) : $source;
}
add_filter( 'openmu_wp_connect_data_source', 'openmu_wp_connect_use_api_source' );

/**
 * Registers the two options and the settings page.
 */
function openmu_wp_connect_register_settings(): void {
	register_setting(
		'openmu_wp_connect',
		'openmu_wp_connect_api_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		)
	);
	register_setting(
		'openmu_wp_connect',
		'openmu_wp_connect_api_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', 'openmu_wp_connect_register_settings' );

/**
 * Adds the page below Settings.
 */
function openmu_wp_connect_settings_menu(): void {
	add_options_page( __( 'OpenMU WP Connect', 'openmu-wp-connect' ), __( 'OpenMU WP Connect', 'openmu-wp-connect' ), 'manage_options', 'openmu-wp-connect', 'openmu_wp_connect_settings_page' );
}
add_action( 'admin_menu', 'openmu_wp_connect_settings_menu' );

/**
 * Renders the settings page, with the result of a connection test when the settings are complete.
 */
function openmu_wp_connect_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$source = openmu_wp_connect_source();
	$test   = $source instanceof OpenMU_WP_Connect_Api_Source ? $source->test_connection() : null;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'OpenMU WP Connect', 'openmu-wp-connect' ); ?></h1>
		<p><?php esc_html_e( 'Create an API key with the role "Website (selected functions only)" in the OpenMU admin panel (API keys), select the functions this site uses, and enter it here. Without an address and a key, the blocks show demo data.', 'openmu-wp-connect' ); ?></p>
		<?php if ( $test ) : ?>
			<div class="notice <?php echo $test['ok'] ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $test['message'] ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'openmu_wp_connect' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="openmu_wp_connect_api_url"><?php esc_html_e( 'Server address', 'openmu-wp-connect' ); ?></label></th>
					<td>
						<input name="openmu_wp_connect_api_url" id="openmu_wp_connect_api_url" type="url" class="regular-text" value="<?php echo esc_attr( openmu_wp_connect_api_url() ); ?>" placeholder="http://192.168.1.10:8080" <?php disabled( defined( 'OPENMU_WP_CONNECT_API_URL' ) ); ?> />
						<p class="description"><?php esc_html_e( 'Address of the OpenMU admin panel, as this server reaches it. Keep the admin panel out of the internet; only this site needs to reach it.', 'openmu-wp-connect' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="openmu_wp_connect_api_key"><?php esc_html_e( 'API key', 'openmu-wp-connect' ); ?></label></th>
					<td>
						<input name="openmu_wp_connect_api_key" id="openmu_wp_connect_api_key" type="password" class="regular-text" value="<?php echo esc_attr( openmu_wp_connect_api_key() ); ?>" autocomplete="off" <?php disabled( defined( 'OPENMU_WP_CONNECT_API_KEY' ) ); ?> />
						<p class="description"><?php esc_html_e( 'Set by OPENMU_WP_CONNECT_API_KEY in wp-config.php instead, if you prefer to keep the key out of the database.', 'openmu-wp-connect' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php openmu_wp_connect_settings_client_assets(); ?>
	</div>
	<?php
}

/**
 * Prints the read-only state of the optional game client assets (client-assets.php):
 * the folder and which of its parts the armory found.
 */
function openmu_wp_connect_settings_client_assets(): void {
	$folder = openmu_wp_connect_client_assets()['path'];
	$status = openmu_wp_connect_client_assets_status();
	$parts  = array(
		'textures' => array( 'character-view/', __( 'Inventory textures: the armory is drawn like the game window.', 'openmu-wp-connect' ) ),
		'classes'  => array( 'classes/', __( 'Class pictures: the armory shows the class render instead of an emblem.', 'openmu-wp-connect' ) ),
		'data'     => array( 'data/', __( 'Item data: item tooltips add base stats, requirements and descriptions.', 'openmu-wp-connect' ) ),
	);
	?>
	<h2><?php esc_html_e( 'Game client look (optional)', 'openmu-wp-connect' ); ?></h2>
	<p><?php esc_html_e( 'The plugin ships no files of the game client. Copy the textures, class pictures and item data from your own game client into this folder to give the armory the look of the game; the README lists the files. Without them the armory uses a neutral style.', 'openmu-wp-connect' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Folder', 'openmu-wp-connect' ); ?></th>
			<td>
				<code><?php echo esc_html( '' !== $folder ? $folder : __( '(none)', 'openmu-wp-connect' ) ); ?></code>
				<p class="description"><?php esc_html_e( 'Change it with the openmu_wp_connect_client_assets filter.', 'openmu-wp-connect' ); ?></p>
			</td>
		</tr>
		<?php foreach ( $parts as $openmu_key => [ $openmu_dir, $openmu_effect ] ) : ?>
			<tr>
				<th scope="row"><code><?php echo esc_html( $openmu_dir ); ?></code></th>
				<td>
					<?php if ( $status[ $openmu_key ] ) : ?>
						<strong><?php esc_html_e( 'Found', 'openmu-wp-connect' ); ?></strong>
					<?php else : ?>
						<?php esc_html_e( 'Not found', 'openmu-wp-connect' ); ?>
					<?php endif; ?>
					<p class="description"><?php echo esc_html( $openmu_effect ); ?></p>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
}

/**
 * Carries the settings over from the plugin's former name, OpenMU Connect (folder
 * openmu-connect, options openmu_connect_*).
 *
 * Runs on activation. A renamed plugin lives in a new folder, so WordPress treats it as
 * a new plugin that has to be activated anyway; that is the one moment the move is needed.
 *
 * @remarks For each of the server address and the API key: when the new option is empty
 * and the old one is set, the value is copied and the old option is deleted. Cached API
 * answers and visitor sessions of the old name (transients openmu_connect_*) are dropped
 * rather than moved; they expire within minutes anyway, and logged-in visitors simply log
 * in again because the session cookie has a new name too. The wp-config.php constants
 * OPENMU_CONNECT_API_URL / OPENMU_CONNECT_API_KEY are not read any more; rename them to
 * OPENMU_WP_CONNECT_API_URL / OPENMU_WP_CONNECT_API_KEY.
 */
function openmu_wp_connect_migrate_legacy_options(): void {
	foreach ( array( 'api_url', 'api_key' ) as $name ) {
		$old = get_option( 'openmu_connect_' . $name, '' );
		if ( '' === (string) get_option( 'openmu_wp_connect_' . $name, '' ) && '' !== (string) $old ) {
			update_option( 'openmu_wp_connect_' . $name, $old );
		}
		delete_option( 'openmu_connect_' . $name );
	}

	global $wpdb;
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time cleanup, there is no API to list transients.
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_openmu_connect_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_openmu_connect_' ) . '%'
		)
	);
}
