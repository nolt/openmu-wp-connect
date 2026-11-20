# OpenMU WP Connect

*[English version](README.md)*

Wtyczka WordPressa dla strony serwera gry [OpenMU](https://github.com/MUnique/OpenMU).
Jej bloki pokazują dane na żywo z serwera — status, rankingi, harmonogram eventów,
zbrojownię — i pozwalają graczom logować się kontem z gry. Strona nigdy nie łączy
się z bazą gry: wszystko idzie przez **API dla stron** serwera, z kluczem API,
który może tylko to, na co pozwolisz.

Działa z każdym motywem blokowym. Nie ma własnego motywu; zbrojownia ma neutralny
wygląd albo wygląd okna postaci z gry, gdy dodasz pliki z własnego klienta gry
(zobacz [Wygląd z klienta gry](#wygląd-z-klienta-gry-opcjonalnie)).

Kompletną stronę od razu po instalacji dostaniesz z motywem towarzyszącym
**[OpenMU WP Theme](https://github.com/nolt/openmu-wp-theme)**: włączenie motywu tworzy
strony z tymi blokami (ranking, minigry, zbrojownia, wydarzenia, konto), menu i stronę
główną. Każde z nich działa też bez drugiego.

## Wymagania

- WordPress 6.6 lub nowszy, PHP 8.1 lub nowszy.
- Serwer OpenMU z API dla stron (`/api/website`) i klucz API z rolą
  **Website (selected functions only)**. API dla stron jest w gałęzi `master` OpenMU
  od listopada 2026; dopóki nie trafi do wydania, potrzebny jest build z aktualnego
  `master`. OpenMU opisuje to API i jego klucze w
  [The API of a website](https://github.com/MUnique/OpenMU/blob/master/docs-website/docs/admin-panel/authentication.md#the-api-of-a-website) (po angielsku).

Bez połączenia bloki statusu serwera, rankingu i zbrojowni pokazują dane
demonstracyjne (`demo/`), a kokpit informuje o tym administratorów — nic zmyślonego nie
zostanie opublikowane przez przypadek. Harmonogram wydarzeń, rankingi minigier i konto
gracza potrzebują połączenia; do tego czasu o tym informują.

## Instalacja

1. Skopiuj ten katalog do `wp-content/plugins/openmu-wp-connect` (albo wgraj go jako
   zip w **Wtyczki > Dodaj nową > Wyślij wtyczkę na serwer**) i włącz
   **OpenMU WP Connect**.
2. W admin panelu OpenMU otwórz **API keys** (obok *Users*; potrzebna rola
   administratora), kliknij **Create API key**, wybierz rolę
   **Website (selected functions only)** i zaznacz funkcje, których używa strona
   (tabela niżej). Klucz jest pokazany raz: od razu go skopiuj. Klucz da się utworzyć
   dopiero, gdy panel ma pierwszego użytkownika. Szczegóły:
   [API keys for external applications](https://github.com/MUnique/OpenMU/blob/master/docs-website/docs/admin-panel/authentication.md#api-keys-for-external-applications).
3. W WordPressie otwórz **Ustawienia > OpenMU WP Connect**, wpisz adres admin
   panelu OpenMU oraz klucz i użyj testu połączenia na tej stronie. Albo wpisz je w
   `wp-config.php`, wtedy klucz nie leży w bazie; stałe mają pierwszeństwo przed
   ustawieniami:

   ```php
   define( 'OPENMU_WP_CONNECT_API_URL', 'http://192.168.1.10:8080' );
   define( 'OPENMU_WP_CONNECT_API_KEY', '...' );
   ```

   **Adres to admin panel widziany z serwera WordPressa**, a nie z Twojej
   przeglądarki: API wywołuje sam WordPress. Typowe przypadki:

   | Układ | Adres |
   | --- | --- |
   | WordPress i OpenMU w Dockerze, w jednej sieci Dockera | nazwa kontenera OpenMU i port panelu wewnątrz kontenera, np. `http://openmu-server:8080` |
   | Oba na jednej maszynie, port panelu OpenMU wystawiony | `http://<adres maszyny>:<wystawiony port>` (z kontenera WordPressa `localhost` to sam kontener, nie maszyna) |
   | Na różnych maszynach | adres maszyny z OpenMU i port panelu; port ma być otwarty tylko dla serwera WordPressa, nie dla internetu |

4. Utwórz strony i wstaw na nie bloki. Z motywem towarzyszącym
   [OpenMU WP Theme](https://github.com/nolt/openmu-wp-theme) dzieje się to samo: włączenie motywu tworzy strony z tymi
   blokami i menu. Z innym motywem utwórz je sam; oczekiwane są dwa adresy: zbrojownia
   pod `/armory/` (wiersze rankingu tam linkują) i konto pod `/account/` (jego pozycja
   w menu brzmi „Sign in” dla gości).

API wywołuj tylko z serwera WordPressa, nigdy z przeglądarek odwiedzających;
wtyczka robi dokładnie tak.

## Bloki

Wszystkie bloki są w wstawiaczu w grupie **OpenMU** i renderują się na serwerze,
więc zawsze pokazują aktualne dane.

| Blok | Pokazuje | Ustawienia | Funkcje klucza API |
| --- | --- | --- | --- |
| **Status serwera** | Online lub offline z lampką statusu i liczbą graczy. | brak | `website_status` |
| **Ranking postaci** | Najlepsze postacie wg resetów, master levelu i levelu, z klasą i gildią; nazwy linkują do zbrojowni. | liczba postaci, jedna klasa lub wszystkie | `website_ranking` |
| **Zbrojownia** | Postać w dwóch oknach ułożonych jak w grze: klasa, poziomy, resety, gildia i założone przedmioty z tooltipami. Z opcjonalnymi plikami klienta: tekstury, render klasy i pełne tooltipy jak w grze. | stała postać albo puste, żeby szukać (`?character=Nazwa`) | `website_character` |
| **Konto** | Logowanie kontem z gry, rejestracja, zmiana hasła; postacie konta z zenem i położeniem. | zezwól na rejestrację | `website_login`, `website_change_password`, `website_register` (tylko z rejestracją) |
| **Eventy** | Najbliższe starty eventów serwera (mini gry, inwazje, happy hour, ...). | liczba pozycji | `website_events` |
| **Ranking mini gier** | Tabele wyników Blood Castle, Devil Square, Chaos Castle i innych mini gier. | mini gra, liczba dni | `website_minigame_ranking` |

## Gracze i sesje

Gracze nie są użytkownikami WordPressa i nie mają żadnych uprawnień na stronie. Po
udanym logowaniu wtyczka trzyma odpowiedź serwera w transiencie pod losowym
tokenem; przeglądarka dostaje tylko ten token w ciasteczku HttpOnly
(`openmu_wp_connect_session`, 30 minut). Hasło nigdy nie jest zapisywane. Serwer
ogranicza nieudane logowania na konto. Jeśli strona używa cache stron, wyłącz
z niego odwiedzających z tym ciasteczkiem.

### Ochrona przed botami i zgadywaniem haseł

Serwer gry widzi każde zapytanie jako przychodzące z serwera WWW, więc wtyczka sama
dokłada limity na adres odwiedzającego:

- **Logowanie:** 10 nieudanych logowań z jednego adresu w 15 minut, łącznie na
  wszystkie konta; potem formularz prosi o odczekanie.
- **Rejestracja:** 5 rejestracji z jednego adresu w godzinę.
- **Pole-pułapka (honeypot):** formularz rejestracji ma pole, którego ludzie nie
  widzą; wypełnione oznacza bota i rejestracja jest odrzucana.

Adres odwiedzającego to `REMOTE_ADDR`. Za reverse proxy lub CDN wszyscy odwiedzający
mają adres proxy: prawdziwy zwróć filtrem `openmu_wp_connect_client_ip`. Wtyczka z
captchą może dopisać się do formularza rejestracji akcją
`openmu_wp_connect_register_form` i odrzucić rejestrację filtrem
`openmu_wp_connect_register_allowed` (zob. Dla programistów).

## Odinstalowanie

Usunięcie wtyczki w **Wtyczki** kasuje jej ustawienia, zapamiętane odpowiedzi,
sesje graczy i obrazki przedmiotów zapisane w `uploads/openmu-wp-connect/`.
Opcjonalne pliki klienta gry są Twoje i zostają. Stałe z `wp-config.php` usuń ręcznie.

## Cache

Wtyczka zapamiętuje odpowiedzi serwera na krótko: status na 30 sekund, rankingi
na 5 minut, profile postaci na 1 minutę. Nieistniejące nazwy postaci nie są
zapamiętywane.

## Języki

Angielski, z tłumaczeniem polskim (`languages/`). Nazwy klas, eventów, map,
przedmiotów i umiejętności zostają po angielsku, tak jak gracze znają je z gry.

## Dla programistów

- `includes/interface-data-source.php` to kontrakt, z którego czytają bloki;
  `includes/class-openmu-wp-connect-api-source.php` realizuje go przez API dla stron,
  `includes/class-openmu-wp-connect-demo-source.php` przez `demo/*.json`.
- Filtry:
  - `openmu_wp_connect_data_source`: podmiana źródła danych;
  - `openmu_wp_connect_armory_url`: adres strony zbrojowni;
  - `openmu_wp_connect_class_picture`: obrazek klasy postaci;
  - `openmu_wp_connect_client_assets`: folder opcjonalnych plików klienta gry;
  - `openmu_wp_connect_client_ip`: adres odwiedzającego, dla którego liczą się limity formularzy;
  - `openmu_wp_connect_rate_limits`: limity, `[ próby, sekundy ]` dla `login` i
    `register`; 0 prób wyłącza limit;
  - `openmu_wp_connect_register_allowed`: zwróć `true` albo kod komunikatu, np.
    `captcha_failed`, żeby odrzucić rejestrację.
- Akcja `openmu_wp_connect_register_form`: dopisuje do formularza rejestracji, np. captchę.
- Każda wartość z serwera jest escapowana przy wypisaniu.

## Wygląd z klienta gry (opcjonalnie)

Wtyczka nie zawiera żadnych plików klienta gry MU Online. Bez nich zbrojownia
(i widok postaci w bloku konta) ma neutralny wygląd, pasujący do każdego motywu:
proste okna z obramowanymi slotami ekwipunku, obrazki przedmiotów z serwera OpenMU,
emblemat zamiast obrazka klasy i tooltipy przedmiotów zbudowane z tego, co przysyła
API dla stron (nazwa, poziom, excellent/ancient, opcje, zestaw ancient, sockety).

Właściciel strony, który ma klienta gry, może nadać zbrojowni wygląd z gry,
kopiując pliki z własnego klienta do `wp-content/uploads/openmu-wp-connect-client/`.
Wtyczka sprawdza każdą część osobno i używa tego, co znajdzie; **Ustawienia >
OpenMU WP Connect** pokazuje, które części znalazła.

| Folder | Pliki | Efekt |
| --- | --- | --- |
| `character-view/` | `frame-top.webp`, `frame-bottom.webp`, `frame-left.webp`, `frame-right.webp` (ramka ekwipunku, w kliencie `Data/Interface/newui_item_back04/02/03`), `zen.webp` (pasek zen, `newui_item_money`), `slot-pet.webp`, `slot-helm.webp`, `slot-wings.webp`, `slot-weapon.webp`, `slot-armor.webp`, `slot-shield.webp`, `slot-gloves.webp`, `slot-pants.webp`, `slot-boots.webp`, `slot-pendant.webp`, `slot-ring.webp` (puste sloty, `newui_item_*`). WebP, w dwukrotnym rozmiarze klienta. | Teksturowane okna jak ekwipunek w grze. Używane tylko, gdy jest wszystkich 16 plików. |
| `classes/` | Po jednym obrazku całej postaci na klasę, przezroczysty WebP nazwany jak klasa małymi literami z łącznikami: `dark-wizard.webp`, `soul-master.webp`, `grand-master.webp`, `dark-knight.webp`, `blade-knight.webp`, `blade-master.webp`, `fairy-elf.webp`, `muse-elf.webp`, `high-elf.webp`, `magic-gladiator.webp`, `duel-master.webp`, `dark-lord.webp`, `lord-emperor.webp`, `summoner.webp`, `bloody-summoner.webp`, `dimension-master.webp`, `rage-fighter.webp`, `fist-master.webp`. | Obrazek klasy zamiast emblematu. Klasa bez własnego obrazka używa klasy bazowej swojej linii (np. `dark-knight.webp`). |
| `data/` | `items.json` (wymagany), `item-sets.json`, `skills.json`: dane przedmiotów klienta (`Data/Items`, `Data/Local/Eng`) w formacie JSON wtyczki. Skrypt, który je generuje, nie jest częścią tego repozytorium. | Pełne tooltipy jak w grze: statystyki bazowe, wytrzymałość, wymagania, klasy, opisy, mana umiejętności i blok zestawu ancient. |

Żeby trzymać pliki gdzie indziej, np. w motywie, użyj filtra
`openmu_wp_connect_client_assets`; zwraca on ścieżkę i adres folderu (puste
napisy wyłączają wygląd z gry):

```php
add_filter( 'openmu_wp_connect_client_assets', function () {
	return array(
		'path' => get_stylesheet_directory() . '/openmu-client/',
		'url'  => get_stylesheet_directory_uri() . '/openmu-client/',
	);
} );
```

Pliki należą do klienta gry; używaj tylko takich, które wolno ci publikować.
MU Online jest znakiem towarowym Webzen Inc.; ten projekt nie jest powiązany z Webzen.

## Licencja

GPL-2.0-or-later, zobacz [LICENSE](LICENSE).

Autor oryginalny: [Nolt](https://github.com/nolt).
