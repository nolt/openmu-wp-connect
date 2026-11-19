# OpenMU WP Connect

*[Wersja polska](README.pl.md)*

A WordPress plugin for the website of an [OpenMU](https://github.com/MUnique/OpenMU)
game server. Its blocks show live data from the server — status, rankings, the event
schedule, an armory — and let players sign in with their game account. The site
never connects to the game database: everything goes through the server's
**website API** with an API key that can only do what you allow.

It works with any block theme. It brings no theme of its own; the armory has a
neutral look, or the look of the game's character window when you add files from
your own game client (see [Game client look](#game-client-look-optional)).

## Requirements

- WordPress 6.6 or later, PHP 8.1 or later.
- An OpenMU server with the website API (`/api/website`) and an API key of the
  role **Website (selected functions only)**. The website API is being added to
  OpenMU; until it's part of a release, use a build which contains it.

Without a connection the blocks show demo data (`demo/`), and the dashboard tells
administrators so — nothing fake gets published by accident.

## Installation

1. Copy this folder to `wp-content/plugins/openmu-wp-connect` (or upload it as a
   zip under **Plugins > Add New > Upload Plugin**) and activate **OpenMU WP Connect**.
2. In the OpenMU admin panel, create an API key with the role
   **Website (selected functions only)** and tick the functions the site uses
   (see the table below).
3. In WordPress, open **Settings > OpenMU WP Connect** and enter the address of the
   OpenMU admin panel and the key. Or put them in `wp-config.php`, which keeps the
   key out of the database; the constants win over the settings:

   ```php
   define( 'OPENMU_WP_CONNECT_API_URL', 'http://192.168.1.10:8080' );
   define( 'OPENMU_WP_CONNECT_API_KEY', '...' );
   ```

4. Create the pages and add the blocks (the plugin doesn't create pages). Two
   addresses are expected: the armory at `/armory/` (ranking rows link there) and
   the account at `/account/` (its menu item reads "Sign in" for visitors).

Call the API from the WordPress server only, never from the visitors' browsers;
the plugin does exactly that.

## Blocks

All blocks are in the inserter under **Widgets** and render on the server, so they
always show current data.

| Block | Shows | Settings | API key functions |
| --- | --- | --- | --- |
| **Server status** | Online or offline with a status lamp and the number of players. | none | `website_status` |
| **Character ranking** | The best characters by resets, master level and level, with class and guild; names link to the armory. | number of characters, one class or all | `website_ranking` |
| **Armory** | A character in two windows laid out like the game's: class, levels, resets, guild and the equipped items with tooltips. With the optional client files: textures, class render and full tooltips like in the game. | a fixed character, or empty to search (`?character=Name`) | `website_character` |
| **Account** | Sign in with the game account, registration, password change; the account's characters with zen and location. | allow registration | `website_login`, `website_change_password`, `website_register` (only with registration) |
| **Events** | The next starts of the server's events (mini games, invasions, happy hour, ...). | number of entries | `website_events` |
| **Mini game ranking** | The leaderboards of Blood Castle, Devil Square, Chaos Castle and the other mini games. | mini game, number of days | `website_minigame_ranking` |

## Players and sessions

Players are not WordPress users and get no rights on the site. After a successful
login the plugin keeps the server's answer in a transient under a random token;
the browser only gets that token in an HttpOnly cookie
(`openmu_wp_connect_session`, 30 minutes). The password is never stored. The
server limits failed logins per account. If the site uses a page cache, exclude
visitors with that cookie from it.

### Protection against bots and password guessing

The game server sees every request coming from the web server, so the plugin adds
limits per visitor address on its own:

- **Logins:** 10 failed logins per address in 15 minutes, across all accounts; then
  the form asks the visitor to wait.
- **Registrations:** 5 registrations per address in an hour.
- **Honeypot:** the registration form has a field people never see; a filled-in
  field means a bot, and the registration is refused.

The visitor address is `REMOTE_ADDR`. Behind a reverse proxy or CDN every visitor
shares the proxy's address: return the real one with the `openmu_wp_connect_client_ip`
filter. A captcha plugin can print into the registration form with the
`openmu_wp_connect_register_form` action and refuse a registration with the
`openmu_wp_connect_register_allowed` filter (see For developers).

## Caching

The plugin caches answers of the server for a short time: the status for
30 seconds, rankings for 5 minutes, character profiles for 1 minute. Unknown
character names are not cached.

## Languages

English, with a Polish translation (`languages/`). Class, event, map, item and
skill names stay in English, as players know them from the game.

## For developers

- `includes/interface-data-source.php` is the contract the blocks read from;
  `includes/class-api-source.php` implements it with the website API,
  `includes/class-demo-source.php` with `demo/*.json`.
- Filters:
  - `openmu_wp_connect_data_source`: replace the data source;
  - `openmu_wp_connect_armory_url`: the address of the armory page;
  - `openmu_wp_connect_class_picture`: the picture of a character class;
  - `openmu_wp_connect_client_assets`: the folder of the optional game client files;
  - `openmu_wp_connect_client_ip`: the visitor address the form limits count for;
  - `openmu_wp_connect_rate_limits`: the limits, `[ attempts, seconds ]` for `login`
    and `register`; 0 attempts turns a limit off;
  - `openmu_wp_connect_register_allowed`: return `true`, or a message code such as
    `captcha_failed`, to refuse a registration.
- Action `openmu_wp_connect_register_form`: prints into the registration form, e.g. a captcha.
- Every value from the server is escaped when printed.

## Game client look (optional)

The plugin ships no files of the MU Online game client. Without them the armory
(and the character view of the account block) uses a neutral style that fits any
theme: plain windows with bordered equipment slots, the item pictures from the
OpenMU server, an emblem instead of the class picture, and item tooltips built from
what the website API sends (name, level, excellent/ancient, options, ancient set,
sockets).

A site owner who has the game client can give the armory the look of the game by
copying files from their own client into
`wp-content/uploads/openmu-wp-connect-client/`. The plugin checks each part on its
own and uses what it finds; **Settings > OpenMU WP Connect** shows which parts were
found.

| Folder | Files | Effect |
| --- | --- | --- |
| `character-view/` | `frame-top.webp`, `frame-bottom.webp`, `frame-left.webp`, `frame-right.webp` (inventory frame, client `Data/Interface/newui_item_back04/02/03`), `zen.webp` (zen bar, `newui_item_money`), `slot-pet.webp`, `slot-helm.webp`, `slot-wings.webp`, `slot-weapon.webp`, `slot-armor.webp`, `slot-shield.webp`, `slot-gloves.webp`, `slot-pants.webp`, `slot-boots.webp`, `slot-pendant.webp`, `slot-ring.webp` (empty sockets, `newui_item_*`). WebP, at twice the client's size. | Textured windows like the game's inventory. Used only when all 16 files are there. |
| `classes/` | One full-body picture per class, transparent WebP named after the class in lower case with hyphens: `dark-wizard.webp`, `soul-master.webp`, `grand-master.webp`, `dark-knight.webp`, `blade-knight.webp`, `blade-master.webp`, `fairy-elf.webp`, `muse-elf.webp`, `high-elf.webp`, `magic-gladiator.webp`, `duel-master.webp`, `dark-lord.webp`, `lord-emperor.webp`, `summoner.webp`, `bloody-summoner.webp`, `dimension-master.webp`, `rage-fighter.webp`, `fist-master.webp`. | The class picture instead of the emblem. A class without its own picture uses the base class of its line (e.g. `dark-knight.webp`). |
| `data/` | `items.json` (required), `item-sets.json`, `skills.json`: the client's item data (`Data/Items`, `Data/Local/Eng`) in the plugin's JSON format. The script that generates them is not part of this repository. | Full tooltips like in the game: base stats, durability, requirements, classes, descriptions, skill mana and the ancient set block. |

To keep the files somewhere else, e.g. in a theme, use the
`openmu_wp_connect_client_assets` filter; it returns the folder's path and address
(return empty strings to turn the game look off):

```php
add_filter( 'openmu_wp_connect_client_assets', function () {
	return array(
		'path' => get_stylesheet_directory() . '/openmu-client/',
		'url'  => get_stylesheet_directory_uri() . '/openmu-client/',
	);
} );
```

The files belong to the game client; use only files you are allowed to publish.
MU Online is a trademark of Webzen Inc.; this project is not affiliated with Webzen.

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
