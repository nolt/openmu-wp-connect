<?php
/**
 * Menu label of the player account page: "Sign in" or "Account".
 *
 * The header's navigation block lists the site pages. The account page is one
 * page for both states: a visitor without a player session sees the login form
 * there, a logged-in player sees the account. This filter only swaps the
 * visible label of that one menu item, so the menu needs no second item and
 * no theme code knows about player sessions (they are not WordPress users).
 *
 * A render_block filter was chosen over a dynamic block because it works with
 * both kinds of menu: the automatic page list and explicit navigation links.
 *
 * Caching: the label depends on the session cookie, so a full-page cache would
 * show it wrongly on other pages. Pages are not cached for visitors with the
 * session cookie (see openmu_wp_connect_account_no_cache(), which marks them
 * DONOTCACHEPAGE). The dev site has no page cache; on a site with one, keep
 * the cookie openmu_wp_connect_session in the cache's "do not cache" list.
 *
 * @package OpenMU_WP_Connect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replaces the label of the account page's menu item with "Sign in" for visitors without a player session.
 *
 * Works on the HTML of core/page-list (automatic menu) and core/navigation-link
 * (explicit menu items): finds the anchor pointing at the account page and
 * rewrites its label text. A logged-in player keeps the page title ("Account").
 *
 * @param string $content Rendered block HTML.
 * @return string Block HTML, with the label changed when needed.
 */
function openmu_wp_connect_account_menu_label( string $content ): string {
	if ( '' === $content || openmu_wp_connect_account_current() ) {
		return $content;
	}
	$account_page = get_page_by_path( 'account' );
	if ( ! $account_page ) {
		return $content;
	}
	$url    = preg_quote( untrailingslashit( get_permalink( $account_page ) ), '#' );
	$log_in = esc_html__( 'Sign in', 'openmu-wp-connect' );

	// Anchor whose href is the account page (trailing slash or query string allowed); the label is either
	// plain text or wrapped in <span class="wp-block-navigation-item__label">.
	return (string) preg_replace_callback(
		'#(<a\b[^>]*\bhref="' . $url . '/?(?:[?\#][^"]*)?"[^>]*>)(.*?)(</a>)#s',
		static function ( array $m ) use ( $log_in ): string {
			$inner = preg_replace( '#(<span class="wp-block-navigation-item__label">).*?(</span>)#s', '${1}' . $log_in . '${2}', $m[2], 1, $count );
			return $m[1] . ( $count ? $inner : $log_in ) . $m[3];
		},
		$content
	);
}
add_filter( 'render_block_core/page-list', 'openmu_wp_connect_account_menu_label' );
add_filter( 'render_block_core/navigation-link', 'openmu_wp_connect_account_menu_label' );
