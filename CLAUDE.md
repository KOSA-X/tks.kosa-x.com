# CLAUDE.md — KOSA X CMS

Ten plik jest automatycznie wczytywany przez Claude Code na starcie każdej sesji.
Jest to **kontrakt pracy** z tym repozytorium. Czytaj go w całości, zanim dotkniesz jakiegokolwiek pliku.

---

## 0. CZYM JEST TEN PROJEKT

**KOSA X CMS** — system zarządzania treścią oparty na **Quick.CMS** (OpenSolution.org), zmodyfikowany w ~70%.

> ⚠️ To **nie jest** autorski CMS pisany od zera. To Quick.CMS z bardzo głęboką customizacją. Nie opisuj go jako „proprietary" ani „autorski".

Baza tego repo jest **kopiowana do każdego nowego projektu klienckiego**. Zmiana w plikach rdzenia = zmiana w każdym przyszłym projekcie. Traktuj to poważnie.

**Stack:** PHP (proceduralny + kilka klas singleton), SQLite (PDO), SCSS → CSS, jQuery + zewnętrzne biblioteki JS.

---

## 0.5 FILOZOFIA: STRUKTURA ZAMKNIĘTA, DESIGN OTWARTY

Ten projekt ma **dwie warstwy** i traktujesz je **zupełnie inaczej**.

### 🔒 Warstwa STRUKTURALNA — święta, dyscyplina obowiązuje
Silnik CMS, treść z bazy, nazwy klas sprzężone z PHP, routing, logika, wzorzec `page.php`.
Tutaj obowiązują wszystkie żelazne reguły. Tu nie improwizujesz.

### 🎨 Warstwa WIZUALNA — otwarta, oczekiwana jest ambicja
Wygląd, layout, animacje, mikrointerakcje, typografia, przestrzeń, głębia, ruch.
**Tutaj masz pełną swobodę i pełne pozwolenie. Więcej — masz OBOWIĄZEK dostarczyć design z polotem.**

> **Kontekst biznesowy:** robimy strony dla **klientów premium**, którzy płacą za **produkt premium**.
> Strona ma się **wyróżniać**. Ma być elegancka, nowoczesna, dopracowana. Ma robić „wow".
> **Bazowy wygląd CMS-a to punkt startu, a nie cel.** Jeśli efekt końcowy wygląda jak lekko przemalowany default — to porażka, nawet jeśli kod jest czysty.

### Co to konkretnie znaczy — MASZ PRAWO:
- ✅ **Pisać własne nowe klasy CSS** i całe nowe komponenty wizualne
- ✅ **Nadpisywać istniejące style**, żeby podnieść poziom wizualny (dopóki nie zmieniasz *nazwy* klasy sprzężonej z PHP — patrz niżej)
- ✅ **Edytować pliki szablonu PHP**, żeby dołożyć wrappery, warstwy, struktury pod bogatszy design
- ✅ **Wgrywać nowe biblioteki JS** (GSAP, Lenis, Splitting, Swiper, Lottie itp.), jeśli podnoszą jakość
- ✅ **Tworzyć nowe animacje, przejścia, efekty, mikrointerakcje**
- ✅ **Proponować i wdrażać nietypowe layouty** — asymetria, nakładanie warstw, sticky, scroll-driven
- ✅ **Rozbudowywać paletę, typografię, system odstępów** o nowe wartości

### Jedyne granice warstwy wizualnej (i tylko te):
1. **Nazwy klas sprzężonych z PHP zostają** (patrz 10.2). Chcesz je ostylować inaczej — śmiało. Chcesz zmienić nazwę — NIE. Możesz je *opakować* w nowe wrappery i nadać własne klasy obok.
2. **Treść nadal leci z bazy** (R5). Design owija treść, nie zastępuje jej hardkodem.
3. **Nie psujesz działania** — koszyk, formularze, menu, filtry mają dalej działać. Restyling TAK, rozłączenie od JS/PHP NIE.
4. **Wydajność i dostępność** — animacje płynne (GPU), obrazy lazy, kontrast czytelny, `prefers-reduced-motion` uszanowany.

Poza tymi czterema punktami: **twórz odważnie.** Jeśli wahasz się „czy mogę dodać tę animację / ten layout / tę bibliotekę" — odpowiedź brzmi TAK.

---

## 1. KONWENCJA TAGÓW W KODZIE

Tagi w komentarzach (PHP `//`, SCSS `//`, JS `//`) sterują Twoim zachowaniem.
**Tag w kodzie ma pierwszeństwo nad tym dokumentem** — jest bardziej aktualny i bardziej szczegółowy.

| Tag | Znaczenie |
|---|---|
| `@claude-lock` … `@claude-unlock` | Kod między tagami jest **ZABLOKOWANY**. Nie edytuj, nie refaktoryzuj, nie „poprawiaj". Nawet jeśli widzisz błąd — zgłoś go, nie naprawiaj sam. |
| `@claude-note: [tekst]` | Kontekst / ostrzeżenie od właściciela. Uszanuj to. Przeczytaj, zanim coś zmienisz w okolicy. |
| `@claude-extend: [tekst]` | To jest **wyznaczone miejsce** na dopisanie nowych elementów danego typu. Tutaj dodawaj, nie gdzie indziej. |

---

## 2. ŻELAZNE REGUŁY

Te reguły są ważniejsze niż elegancja kodu, ważniejsze niż Twoje preferencje, ważniejsze niż „lepszy pattern".

### R1. SZUKAJ, ZANIM STWORZYSZ
Zanim napiszesz nową funkcję, klasę CSS, zmienną czy plik — **przeszukaj repo** (`grep`, `find`, `rg`).
Prawdopodobieństwo, że to, czego potrzebujesz, już istnieje, jest bardzo wysokie.
Duplikacja jest największym grzechem w tym projekcie.

```bash
rg "nazwaFunkcji" --type php
rg "\.nazwaKlasy" --type scss
```

### R2. NIE PSUJ DZIAŁAJĄCEJ LOGIKI (ale design rozbudowuj śmiało)
Ta reguła dotyczy **logiki i struktury**, NIE wyglądu.

**Warstwa strukturalna/logiczna** (PHP, routing, obsługa formularzy, JS koszyka/menu, nazwy klas sprzężone z PHP):
- Rozbudowa — TAK. Edycja w miejscu — TAK.
- Przepisanie logiki od zera / zmiana nazw klas sprzężonych z PHP — NIE bez zgody.
- Uważasz, że logika wymaga refaktoryzacji → **powiedz, nie rób sam**.

**Warstwa wizualna** (SCSS, style, animacje, wrappery HTML pod design):
- Nadpisywanie istniejących stylów — TAK, śmiało.
- Nowe klasy, nowe komponenty, nowe efekty — TAK, oczekiwane.
- Jedyny warunek: nie rozłączasz elementu od jego JS/PHP i nie zmieniasz *nazwy* klasy, po której PHP/JS go znajduje (opakuj, dołóż własną klasę obok).

### R3. PLAN PRZED KODEM
Zanim napiszesz kod, przedstaw **krótki plan (2–4 punkty)** i poczekaj na akceptację.
Wyjątek: gdy właściciel wyraźnie mówi „pisz kod od razu".
Pracuj **etapami**, krok po kroku. Nie wywalaj 10 etapów wdrożenia naraz.

### R4. ANALIZA CAŁEGO PLIKU PRZED EDYCJĄ
Nigdy nie edytuj fragmentu bez przeczytania całego pliku.
Musisz wiedzieć, jak dana zmienna/funkcja/klasa jest już używana gdzie indziej w tym pliku.

### R5. ⚠️ TREŚĆ ZAWSZE Z BAZY, NIGDY HARDCODED
**To jest najważniejsza reguła w tym dokumencie.**

Zero tekstu wpisanego na sztywno w HTML/PHP. Nigdy.

| Rodzaj treści | Skąd brać |
|---|---|
| Treść sekcji / opis / tytuł strony | `getData($config['x_page'], 'sName' \| 'sDesc' \| 'sDescriptionShort' \| 'sDescriptionFull')` |
| Statyczne teksty UI (przyciski, etykiety) | `$lang['klucz']` z `database/lang_pl.php` |
| Dane firmy (telefon, email, adres) | `$config['phone']`, `$config['email']`, `$config['street']`… |
| Linki do podstron | `getUrl($config['x_page'])` lub `getLink()` |

**Zła praktyka (występuje w istniejącym kodzie — NIE powielaj jej):**
```php
echo getData(13, 'sDescriptionFull');  // ❌ magiczne ID
```
**Dobra praktyka:**
```php
echo getData($config['transfer_page'], 'sDescriptionFull');  // ✅
```

### R6. KORZYSTAJ Z ISTNIEJĄCEGO SYSTEMU I ROZBUDOWUJ GO
Breakpointy, grid, mixiny, funkcje pomocnicze — wszystko już jest. **Używaj tego jako fundamentu**, żeby nie tworzyć równoległych mechanizmów (media queries, siatka, gettery treści).

Ale fundament to nie sufit:
- **Nowe zmienne** (kolory, cienie, gradienty, odstępy, fonty) — dopisuj do **`_brand.scss`** (kolory/paleta/`:root`) śmiało. Zero surowych kolorów poza tym plikiem (§10.0).
- **Nowe mixiny/animacje** — twórz, gdy podnoszą jakość.
- **Nowe komponenty wizualne** — twórz w `_content.scss`.
- Zmiana samego **rdzenia gridu / zestawu breakpointów** (rzecz, na której opierają się WSZYSTKIE projekty bazowe) — to zaproponuj przed wdrożeniem. Reszta — działaj.

---

## 3. GDZIE CZEGO SZUKAĆ — MAPA

```
database/          → konfiguracja, baza SQLite, tłumaczenia
core/              → klasy CMS (Pages, Files, Sliders, Shortcode) + admin
plugins/           → funkcje pomocnicze (HUB: settings.php), sklep, formularze
files/             → TREŚĆ wgrana przez CMS (zdjęcia, PDF-y klienta)
templates/admin/   → panel administracyjny
templates/theme/   → SZABLON STRONY (PHP + SCSS + JS + grafiki designu)
```

**Szukasz funkcji pomocniczej?** → `plugins/settings.php` (95% szans, że tam jest).
**Szukasz klasy CSS?** → `templates/theme/_source/` (patrz sekcja 10).
**Szukasz generatora HTML dla listy?** → `templates/theme/_lists.php`.
**Szukasz ID zakładki?** → `database/config_pl.php` (`$config['*_page']`).

---

## 4. `database/`

| Plik | Rola |
|---|---|
| `database.db` | SQLite. Tabele: `pages`, `files`, `sliders`, `orders`, `form`, `users`, `bin` |
| `config.php` | Główna konfiguracja + słowniki. Zawiera `@claude-lock` wokół rdzenia Quick.CMS |
| `config_pl.php` | Dane zależne od języka + **`$config['*_page']` = ID zakładek** |
| `lang_pl.php` | `$lang['...']` — statyczne teksty UI |

### Słowniki w `config.php` (używaj `getElement()` / `getElementArray()`)
`themes`, `pages_menus`, `order_status`, `order_delivery`, `order_payment`, `user_status`, `form_categories`, `calendar_hours`, `images_locations`, `images_thumbnails`

### `$config['*_page']` — ID zakładek (`config_pl.php`)
`start_page`, `shop_page`, `contact_page`, `about_page`, `blog_page`, `offer_page`, `projects_page`, `faq_page`, `form_page`, `user_page`, `order_page`, `payment_page`, `search_page`, `sitemap_page`, `private_policy`, `terms_page`, `slider_page`, `video_page`

> Zawsze odwołuj się przez te stałe. **Nigdy przez surowe ID.**

### NIE WOLNO
- Nie ruszaj bloków `@claude-lock` (getPageId, wykrywanie języka).
- Nie edytuj `database.db` ręcznie — tylko przez PDO / panel admina.

---

## 5. `core/` — klasy CMS

Wszystkie klasy to **singletony**: `Pages::getInstance()`, `Files::getInstance()`, `Sliders::getInstance()`.
W szablonach dostępne jako `$oPage`, `$oFile`, `$oSlider`, `$oSql`.

### `core/pages.php` — klasa `Pages`
| Metoda | Do czego |
|---|---|
| `listPages($iPage, $params)` | Lista podstron / produktów |
| `listPagesMenu()` / `listPagesSubMenu()` | Menu nawigacji |
| `listPagesPopup($iPage)` | Podstrony jako popupy (fancybox) |
| `listFaq()` | Akordeon FAQ |
| `getPagesTree()` | Breadcrumbs |
| `getClassName()` | Klasa CSS body/strony |
| `generateCache()` / `generateLinks()` | **Cache routingu — NIE DOTYKAJ** |

**Wzorzec rozpoznawania typu strony:**
```php
if (!empty($config['blog_page']) && (int)$aData['iPageParent'] === (int)$config['blog_page']) { ... }
```
Ten sam wzorzec jest w `_lists.php`. Nowy typ = kolejny warunek w tym stylu, **nie nowa funkcja**.

### `core/files.php` — klasa `Files`
`getDefaultImage()`, `getDefaultImageUrl()`, `getDefaultImageBackground()`, `listImages()`, `listFiles()`, `metaFacebook()`

`iSize > 0` = obrazek, `iSize = 0` = plik do pobrania.

**Typy obrazków (`iType`)** — patrz `$config['images_locations']`:
- `1` → galeria główna strony
- `2` → ikona (menu, kafelek oferty)
- `3` → galeria dodatkowa (grid)
- `4` → tło nagłówka strony

### `core/sliders.php` — klasa `Sliders`
`listSliders()` — główny slider HERO z tabeli `sliders`. Cache statyczny. OwlCarousel.

### `core/shortcode.php` — klasa `Shortcode`
Shortcode'y w stylu WordPressa, parsowane w treści z TinyMCE przez `parseShortcodes()`.

Zarejestrowane: `listPages`, `listMenu`, `listFaq`, `listPagesPopup`, `gallery`, `slider`, `socialMedia`, `contacts`, `sitemap`, `hoursOpen`, `listMenuWidget`, `page`, `link`, `url`, `image`, `darkMode`, `searchIcon`, `userIcon`, `language`

Nowy shortcode → `registerShortcode()`, nie nowy mechanizm parsowania.

### `core/*-admin.php`
`PagesAdmin extends Pages`, `FilesAdmin extends Files`, `common-admin.php`.

**NIE DOTYKAJ:** `loginActions`, `checkLogin`, `saveVariables` (fizycznie edytuje pliki `.php` configu), `uploadFile` (sanityzacja).

**Wzorce do naśladowania przy nowym module admina:** `listOrdersAdmin`, `listFormsAdmin`, `listUsersAdmin`.

---

## 6. `plugins/`

### `plugins/settings.php` — **HUB. Najważniejszy plik pomocniczy.**
Zanim napiszesz jakąkolwiek funkcję pomocniczą — **sprawdź tutaj**.

**Stałe:**
`BASE_URL`, `CURRENT_URL`, `IMAGES`, `FILES`, `ICONS`, `THEME`, `SHOP_PAGE`, `LOGO_URL`, `LOGO`, `FAVICON`, `LOGGED`
**Zmienne:** `$css_file`, `$js_file`, `$theme`, `$page_desc`, `$image_for_facebook`

**Funkcje bazowe:**
`getUrl()`, `getLink()`, `getData()` (getter z cache), `getElement()`, `getElementArray()`, `listMenu()`, `listPagesQuery()`, `renderPaginationForQuery()`, `renderSiteMap()`, `update_views()`, `image_count()`, `parametrs()`

**Filtry (sklep + portfolio):**
`getFiltersConfig()`, `parsePageFilters()`, `pageMatchesFiltersArray()`, `renderFilterSelect()`, `renderFilterBar()`, `renderFiltersWidget()`, `renderActiveFilters()`, `getFilters()`, `renderProductConfigFilters()`, `packProductConfigFilters()`, `formatSelectedFilters()`

**UI:**
`contactIcon()`, `userIcon()`, `searchIcon()`, `cartIcon()`, `contacts()`, `contactsButtons()`, `socialMedia()`, `language()`, `darkMode()`, `contrastMode()`, `fontSizeToggle()`, `location()`, `acceptLabel()`, `hoursOpen()`

**Moduły:**
`feature('shop'|'blog'|'portfolio'|'reservations'|'users'|'search'|'payments')` — czy moduł włączony. **Jedyne źródło: przypisana strona w panelu** (Konfiguracja → „Dopasowanie stron" → `$config['*_page']`), która REALNIE ISTNIEJE i jest aktywna. Off = strona „-" (id 0), strona usunięta (config wskazuje na skasowane id) lub ukryta (`iStatus=0`) — sprawdzane przez `isset($oPage->aPages[$id])`. Dzięki temu usunięcie zakładki wyłącza moduł wszędzie i nie zostawia sierot w UI. Używaj do warunkowego UI (ikony w `_header.php`, sekcje w `page-index.php`, modale w `_footer.php`) — nie dubluj `&& $config['*_page']`.

**Inne:**
`isLoggedIn()`, `trunc()`, **`html()`** ← używaj tego zamiast `htmlspecialchars()`, `getMonthReservations()`, `sendEmail()` (PHPMailer + reCAPTCHA v3)

Na końcu pliku: `require_once plugins/shop-functions.php`.

### Reszta `plugins/`
`cart.php` (`cartList()`), `shop-functions.php`, `shop_filters.php`, `shop-payu-notify.php`, `form-calendar.php` (interaktywny kalendarz rezerwacji), `form/` (PHPMailer + `form-handler.php`), `chosen/`, `products/`, `valums-file-uploader/`

To **najswobodniejszy katalog PHP** — tu możesz dodawać nowe pliki pomocnicze.

### `plugins/instagram/` — moduł InstaFeed (widget Instagram, zastępuje LightWidget)

Modułowy feed Instagram, **multi-klient**: nowe konto = wpis w **`database/config_pl.php`** (`$config['instagram_accounts']`), zero zmian w kodzie modułu. Cała komunikacja z Graph API jest server-side; media (w tym mp4 reeli) pobierane i hostowane lokalnie (URL-e IG wygasają); token nigdy nie trafia do frontu.

> **Sterowanie w `database/config_pl.php`:** `$config['instagram_accounts']` (mapa kont: `ig_user_id`, `access_token`, `token_updated_at`, `limit`, `handle`, `cover_from_permalink`), `$config['instagram_cron_secret']`, `$config['instagram_account']` (klucz konta dla sekcji na stronie głównej). Moduł nie ma już własnego `config.php`. Sekrety (token, cron_secret) → nadpisuj w `database/config.secrets.php` (§15.1).

| Plik | Rola |
|---|---|
| `InstaFeed.class.php` | Silnik: czyta `$config['instagram_accounts']`; `fetchFeed` (Graph API), `normalize`, `downloadMedia` (jpg + mp4 reeli + og:image okładki), `cleanupMedia` (usuwa pliki poza limitem), `saveFeed`→`feed.json`, `refreshTokenIfNeeded` (>45 dni), `getFeed`. Fail-safe: błąd API / pusty feed nie rusza starego `feed.json`. |
| `cron.php` | `cron.php?key={instagram_cron_secret}` (bez/zły klucz → 403). Bootstrapuje CMS config, iteruje konta, loguje do `cache/cron.log` (rotacja >500 KB). **Cron hostingu: co 6 h.** |
| `widget.php` | `renderInstaFeed($account, $options)` — `limit`/`columns`(2-4)/`class`/`show_reels`/`header`. Reels grają w Fancyboxie (html5video z lokalnego mp4), fallback → permalink. Require w `settings.php`. |
| `cache/{account}/` | `feed.json`, `token.json`, `media/*.jpg` + `*.mp4` — runtime, w `.gitignore`. |

**Jak dodać konto:** wpis w `$config['instagram_accounts']` (`database/config_pl.php`). **Cron:** ustaw `$config['instagram_cron_secret']`, w panelu hostingu `curl "…/plugins/instagram/cron.php?key=SEKRET"` co 6 h. **Szablon:** `echo renderInstaFeed('main', ['limit' => 9]);` (strona główna bierze klucz z `$config['instagram_account']`). Styl (`.instaFeed`/`.fb-instafeed`) w `_components.scss`; grid w konwencji `.galleryGrid`; caption Fancyboxa scope `.fb-instafeed`. Reels: mp4 hostowane lokalnie + okładka z permalinku (`cover_from_permalink`, bo API zwraca klatkę zamiast customowej okładki).

---

## 7. `files/` vs `templates/theme/images/`

| | `files/` | `templates/theme/images/` |
|---|---|---|
| Co | **TREŚĆ** klienta (zdjęcia, PDF-y) | **DESIGN** (ikony, logo, favicon) |
| Kto wgrywa | CMS / panel admina | developer, ręcznie |
| Miniatury | `files/{rozmiar}/` (ta sama nazwa pliku) | brak |
| Powiązanie | tabela `files` w DB | brak |

**Nigdy nie myl tych dwóch.** Ikona interfejsu → `ICONS`. Zdjęcie z galerii klienta → `FILES`.

---

## 8. `templates/admin/`

Panel administracyjny. Pary `{moduł}.php` + `{moduł}-form.php`. Logika siedzi w `core/*-admin.php`.

> **STATUS SPECJALNY:** to jedyne miejsce, gdzie **wolno Ci aktywnie ulepszać, naprawiać bugi i poprawiać bezpieczeństwo** bez pytania o każdą drobiazgową zmianę.
> Wyjątek: `loginActions`, `checkLogin`, `saveVariables`, `uploadFile` — nie dotykasz.

---

## 9. `templates/theme/` — PHP szablonu

### 9.1 Struktura HTML — kto co otwiera i zamyka

```
_meta.php    → <html> <head> ... <body>
_header.php  → require _meta.php; <header>; [video LUB slider]; <main class="mainBody"> <div class="mainPage">
  page-*.php → treść
_footer.php  → </div>.mainPage </footer> </main> [modale] [JSON-LD] </body></html>
```

`_header.php` woła `require_once _meta.php` i `update_views()` **na samym początku**. `_meta.php` nie jest includowany nigdzie indziej.

Jeśli edytujesz otwarcie — sprawdź zamknięcie. Liczba divów musi się zgadzać.

### 9.2 Pliki RDZENIA (nazwy nienegocjowalne)

| Plik | Rola |
|---|---|
| `_meta.php` | `<head>`, nagłówki bezpieczeństwa, OG/Twitter, CSS/JS, GTM/Analytics, otwarcie `<body>` |
| `_header.php` | Nawigacja, menu mobilne, sekcja HERO (video LUB slider), otwarcie `<main>` |
| `_title.php` | `.mainPage__header` — tytuł, podtytuł, breadcrumbs (`$aData['sPagesTree']`), tło (`iType=4`). Dla `projects_page` dokleja `renderFilterBar()` |
| `_column.php` | Sidebar. Dwie ścieżki: **sklep** (`iMenu==2` → `listMenu()` + `renderFiltersWidget()`) / **standard** (`listMenu()` z linkiem do rodzica) |
| `_lists.php` | **Warstwa widoku (HTML)** dla `core/pages.php`, `core/files.php`, `core/sliders.php` |
| `_shop_cart.php` | Karta produktu (galeria, cena, konfigurator, tabsy) |
| `_footer.php` | Stopka + modale + JSON-LD + zamknięcie HTML |
| `_404.php` | Strona błędu |

### 9.3 `_lists.php` — funkcje widoku

`listPagesMenuView()`, `listMenuView()`, `listPagesView()`, `listImagesView()`, `listFilesView()`, `listSlidersView()`

**`listPagesView()`** jest najbardziej złożona: kategoria, cena + `add-to-cart`, popup, filtry (`getFilters()`), tryb poziomy dla bloga (`$horizontal`), lazy loading.

> Nowy specjalny wygląd karty → **kolejny warunek `if (!empty($config['x_page']) && ...)` wewnątrz istniejącej funkcji.** Nie twórz `listMojaListaView()`.

### 9.4 `_footer.php` — szczegóły

- `$showFooterTop = !in_array($theme, [3, 4, 5, 10], true)` — górna część stopki znika na: kontakt (3), user (4), order (5), payment (10).
- Modale: `#modal-contact`, `#cart-widget`, `#search-widget` → wszystkie w tym samym wzorcu: `.modal` + `display:none` + `.card` w środku.
- `listPagesPopup($config['footer_popups_page'])` — popupy podstron rodzica (ID w `config_pl.php`, dawniej zahardkodowane `32`).
- JSON-LD: `WebPage` + `Organization`.

Nowy modal → dopisz tu, w tym samym wzorcu.

### 9.5 Pliki DEDYKOWANE (`page-*.php`)

Mapowanie na `$config['themes']` w `database/config.php`:

| ID | Plik | Zakładka |
|---|---|---|
| 1 | `page.php` | **WZORZEC BAZOWY** — domyślny szablon |
| 2 | `page-index.php` | Strona główna |
| 3 | `page-contact.php` | Kontakt (formularz + mapa) |
| 4 | `page-user.php` | Panel użytkownika |
| 5 | `page-order.php` | Zamówienie |
| 6 | `page-shop.php` | Sklep |
| 7 | `page-search.php` | Wyszukiwarka |
| 8 | `page-examples.php` | Przykłady / realizacje |
| 9 | `page-form.php` | Formularz rezerwacyjny |
| 10 | `page-payment.php` | Płatności online |

> **Nowy dedykowany szablon = DWA kroki, zawsze razem:**
> 1. Nowy plik `page-nazwa.php` (skopiowany z `page.php`)
> 2. Wpis w `$config['themes']` w `database/config.php`
>
> Jeden krok bez drugiego = szablon nie zadziała.

### 9.6 WZORZEC `page.php` — kopiuj to przy nowym szablonie

```php
<?php
if (!defined('CUSTOMER_PAGE')) { exit; }

require_once $theme.'_header.php';

if (isset($aData['sName'])) {

    require_once $theme.'_title.php';

    echo '<div class="container">';
    echo '<div class="mainPage__wrapper">';

        require_once $theme.'_column.php';

        echo '<div class="mainPage__content">';

            // treść — ZAWSZE przez parseShortcodes() dla opisów z TinyMCE
            $content  = '<div class="mainPage__descShort">'.parseShortcodes($aData['sDescriptionShort']).'</div>';
            $content .= $oFile->listImages($aData['iPage'], ['iType' => 1, 'slider' => true]);
            $content .= '<div class="mainPage__descFull">'.parseShortcodes($aData['sDescriptionFull']).'</div>';
            $content .= $oFile->listFiles($aData['iPage']);

            if ($content !== '') {
                echo '<article class="mainPage__article negativeMargin">'.$content.'</article>';
            }

            echo $oFile->listImages($aData['iPage'], ['iType' => 3, 'class' => 'galleryGrid']);
            echo $oPage->listPages($aData['iPage'], ['footer' => true, 'per_page' => 10]);

        echo '</div>'; // mainPage__content
    echo '</div>';     // mainPage__wrapper
    echo '</div>';     // container

} else {
    require_once $theme.'_404.php';
}

require_once $theme.'_footer.php';
```

**Kluczowe elementy wzorca:**
1. Guard `CUSTOMER_PAGE` na górze — zawsze
2. `require_once $theme.'_header.php'` — zawsze
3. `if (isset($aData['sName']))` … `else require _404.php` — zawsze
4. `require_once $theme.'_footer.php'` na końcu — zawsze
5. Produkt vs strona: `$aData['sType'] == 2` → `ob_start()` + `_shop_cart.php`

### 9.7 Wzorce z pozostałych `page-*.php`

**Formularze (contact / form / order / user):**
- **CSRF:** `orderInitCsrfToken()` / `userInitCsrfToken()` **przed jakimkolwiek HTML** + walidacja przez `hash_equals()`
- **reCAPTCHA v3:** ukryte pole `g-recaptcha-response` + `action` + skrypt na dole
- **Prefill:** `old('nazwa', $default)`
- **Redirect po POST:** `header('Location: ...'); exit;` — żeby F5 nie duplikował
- **Mail:** `sendEmail()` z gotowym HTML-owym `$body` (tabelki inline-CSS)
- **Słowniki:** `getElement()` / `getElementArray()` — nigdy `if ($status === 0) 'Nowe'`

**`page-index.php` — wzorzec sekcji strony głównej:**
```php
<?php if ($config['about_page']): ?>
<section class="section aboutUs">
    <div class="section__scroll" id="section-aboutUs"></div>
    <div class="container">
        <header class="section__header showUp">
            <div class="section__subtitle">
                <a href="<?= getUrl($config['about_page']) ?>"><?= getData($config['about_page'], 'sName') ?></a>
            </div>
            <h2 class="section__title text-glow"><?= getData($config['about_page'], 'sDesc') ?></h2>
        </header>
        <!-- treść -->
    </div>
</section>
<?php endif; ?>
```
Każda sekcja: **guard** (moduł: `feature('shop'|'blog'|'portfolio'…)`; zwykła zakładka: `if ($config['x_page'])`) + `.section` + `.section__scroll` + treść z `getData()` / etykiety z `$lang[]`.
Klasa `showUp` = animacja scrollReveal.

> ⭐ **Sekcje w `page-index.php` to PRZYKŁADY (wzory do replikacji), nie sztywny układ.**
> Istnieją po to, żeby pokazać przyjęty styl: moduł włączany przez `feature()`/`$config['*_page']`, treść z bazy (`getData()`), etykiety z `$lang[]`, linki z `getUrl()`, struktura `.section` + `.section__scroll` pod animacje. Część jest naturalnie powtarzalna (karuzela produktów, karuzela newsów, CTA) — możesz je przenosić między projektami.
>
> **Ale NIE ograniczaj się do nich.** Przy nowym projekcie **twórz nowe sekcje od zera**, dobrane do branży i wymagań klienta — inne układy, inne komponenty, inne animacje. Różne branże = różne struktury strony głównej. To wzorce, z których korzystasz i które rozwijasz, a **nie lista dozwolonych sekcji.** Powielenie tylko gotowych modułów „bo są" = za mało. Oczekiwana jest kreatywność i sprawczość (patrz §0.5).
>
> **Jedyne twarde granice:** treść leci z `getData()`/`$lang[]` (nie hardcode), guard modułu zostaje, kolory ze zmiennych (§10.0).

### 9.8 ⚠️ ZNANE ODCHYLENIA W ISTNIEJĄCYM KODZIE

Te rzeczy **istnieją i działają — nie naprawiaj ich bez pytania**, ale **nie powielaj ich w nowym kodzie**:

| Gdzie | Odchylenie | W nowym kodzie |
|---|---|---|
| `page-order.php`, `page-user.php` | `$skinPath = 'templates/'.$config['skin'].'/'` | Używaj `$theme` |
| `_header.php` | `'templates/'.$config['skin'].'/'` | Używaj `$theme` (`page-index.php` już naprawione) |
| `page-search.php` | `require_once $theme.'/_column.php'` (podwójny slash) | `$theme.'_column.php'` |
| `page-shop.php` | Zahardkodowany `"sku": "PIZ-ANANAS-001"` w JSON-LD | Bierz z `$aData['sSKU']` |
| ~~`page-payment.php`, `page-order.php` — `getData(13/15/35, …)`~~ | **NAPRAWIONE** → `getData($config['transfer_page'\|'payu_page'\|'payment_info_page'], …)` (ID w `config_pl.php`) | używaj tych kluczy |

---

## 10. `templates/theme/_source/` — SCSS

```
global/
  _brand.scss        ← ★ SKIN KLIENTA: marka, fonty, radius, odstępy, paleta (:root/.theme-*). Importowany PIERWSZY. Re-skin = edytuj TYLKO ten plik.
  _cms.scss          ← FUNDAMENT strukturalny: breakpointy, skala typografii, wagi, mixiny (media-up, flexbox, fade…), grid
  _reset.scss        ← reset przeglądarki
  _form.scss         ← .form-control, .formCheckBox, floating labels
  _slider.scss       ← .mainSlider + OwlCarousel (HERO)
  _components.scss   ← .card, .button, .tabs, .table, .accordion…
layout/
  _header.scss       ← ściśle sprzężony z _header.php (nawigacja + HERO)
  _footer.scss       ← ściśle sprzężony z _footer.php
  _content.scss      ← ★ NAJSWOBODNIEJSZY — .section, efekty, nowe sekcje projektu
  _page.scss         ← NAJWIĘKSZY: .mainPage, .pageItem, galerie, listy
  _shop.scss         ← koszyk, produkty, zamówienie
style.scss           ← lista @import (kolejność: brand, cms, reset, form, slider, components, header, footer, content, page, shop)
_source/style.css    ← build rozwinięty (referencja) — NIE edytuj ręcznie
css/style.css        ← ★ BUILD SERWOWANY (compressed) — to jego ładuje _meta.php. NIE edytuj; przebuduj z SCSS
```

> **Build:** serwowany jest `templates/theme/css/style.css` (compressed). Po zmianie SCSS przebuduj OBA:
> `sass _source/style.scss css/style.css --style=compressed` oraz `sass _source/style.scss _source/style.css --style=expanded`.
> Nie ma plików `_helpers.scss` / `_pageList.scss` / `_gallery.scss` (usunięte) — nie odtwarzaj ich.

### 10.0 ⚠️ ZARZĄDZANIE KOLORAMI — WSZYSTKIE KOLORY W `_brand.scss`

**Reguła twarda (warstwa strukturalna, nie łam jej):**

1. **Zero surowych kolorów poza `_brand.scss`.** W żadnym innym pliku SCSS nie może pojawić się `#hex`, `rgb()` ani `rgba()` z liczbami. Grep `#[0-9a-fA-F]` i `rgba?\(\s*\d` w `global/` + `layout/` (bez `_brand.scss`) musi zwracać **zero**.
2. **Używaj zmiennych i tokenów:** SCSS `$brand`, `$brand2`, `$gray`, `$white`, `$black`, `$success`, `$danger`, `$warning`, `$info`, `$social-*` — albo tokeny `var(--brand)`, `var(--white)`… Alpha/cień/scrim: `rgba($black, .5)` / `rgba($white, .1)` (NIE `rgba(0,0,0,.5)`).
3. **Nie tworzymy kolorów jednorazowego użycia.** Potrzebujesz nowego koloru → **dopisz zmienną do `_brand.scss`** i użyj jej. Nigdy koloru wklejonego „na miejscu".
4. **Maks. 3 kolory marki.** `$brand` (+ `-light` / `-dark`) obowiązkowo; `$brand2` (+ odcienie) opcjonalnie; `$brand3` tylko w wyjątkach (odkomentuj w `_brand.scss`). Poza tym: szarości, biel, czerń, kolory semantyczne (status) i social.
5. **Kolory z tokenów vs. SCSS:** token `var(--white)`/`var(--black)` **zmienia się z motywem** (dark/contrast — to „powierzchnia/tekst"). Element, który ma być ZAWSZE biały/czarny niezależnie od motywu (tekst na ciemnym hero, scrim na zdjęciu), używaj SCSS `$white`/`$black`.
6. **Nowa paleta klienta:** przy nowym projekcie dostaniesz paletę → wpisz ją do `_brand.scss` (i tylko tam), reszta plików korzysta ze zmiennych bez zmian.

### 10.1 `_cms.scss` — fundament strukturalny

> Marka, fonty, radius, odstępy i cała paleta (`$brand`, `$gutter`, `$font-h`, `:root`, `.theme-dark`, `.theme-contrast`) → **`_brand.scss`** (patrz §10.0). W `_cms.scss` zostają rzeczy strukturalne:

**Breakpointy:** `$breakpoints`: `xs, sm, md, lg, xl, xxl, xxxl`
**Skala typografii:** `$font-size`, `$font-size-sm/md/l/xl/xxl/xxxl`
**Wagi:** `$font-light/regular/medium/bold/xbold`
**Wysokości layoutu:** `$header_top/mobile/desktop/scroll`, `$footer`

**Mixiny:** `font-*`, `border-*`, `shadow*` (używają `$black`), `flexbox`, `fade` / `fade-slow` / `fade2`, `motion-safe`, `opacity`, `invert`, `media-up/down/between`

**Media queries — JEDYNY poprawny sposób:**
```scss
@include media-up(xl)   { ... }
@include media-down(md) { ... }
@include media-between(sm, lg) { ... }
```
❌ Nigdy `@media (min-width: 1200px)` ręcznie.

**Grid:** `.container`, `.row`, `.col-2` … `.col-12`, `.col-{bp}-{n}`

### 10.2 Klasy sprzężone z PHP — **ZERO ZMIANY NAZW**

Te nazwy są zahardkodowane w PHP. Zmiana nazwy = zerwane renderowanie.

| Plik SCSS | Sprzężony z |
|---|---|
| `_header.scss` | `_header.php` + `core/pages.php` (`menu_item`, `menu_link`, `submenu`, `submenu_link`, `menu_arrow`, `headerMenu__list`) |
| `_footer.scss` | `_footer.php` + te same klasy menu |
| `_page.scss` | `_lists.php` (`.pageItem`, `.galleryItem`), `page*.php` (`.mainPage__*`) |
| `_components.scss` | `settings.php` (`.contactsList`, `.socialMediaIcons`, `.hoursOpen`, `.card`, `.button`) |
| `_form.scss` | `.form-control`, `.formCheckBox` — generowane w PHP |
| `_shop.scss` | `page-shop.js` (`.cartItem`, `.cartList`), `renderProductConfigFilters()` (`.productConfig`) |

### 10.3 Warianty list — wzorzec

Bazowy `.pageItem` + warianty: `.offerList`, `.blogList`, `.quotesList`, `.searchList`, `.projectsList`, `.productsList`

Nowy typ listy →
```scss
.mojaLista {
    .pageItem {
        // TYLKO to, co różni się od bazowego .pageItem
    }
}
```
Nie zaczynaj od zera. Nadpisuj.

### 10.4 `.formCheckBox` — reużywalny wzorzec

Radio/checkbox w formie klikalnych kart. Używany w:
- `.productConfig` (warianty produktu)
- `.pageOrder` (wybór dostawy i płatności)

Nowy wybór opcji (kolor, rozmiar, pakiet) → **użyj `.formCheckBox`**, nie nowego komponentu.

### 10.5 `.theme-N` — nadpisania per szablon

`.theme-6` (sklep), `.theme-10` (zamówienie) itd. — numer = ID z `$config['themes']`.
Customizacja tylko dla jednej zakładki → użyj `.theme-{numer}`, nie generycznego selektora.

### 10.6 `_content.scss` — ★ TWÓJ PLAC BUDOWY DESIGNU

**To jest miejsce, gdzie tworzysz design premium.** Tu masz pełną swobodę i tu oczekuję najwyższego poziomu wizualnego.

Zawiera już (realnie zdefiniowane): `.section` (reużywalny szkielet), `.theme-dark` / `.theme-contrast` (globalne nadpisania), `.blurEffect`, `.text-glow` (poświata nagłówka, token `--glow-brand`), sekcja `prefers-reduced-motion`, `#cookies-message`. `.glass` jest w `_cms.scss`. Sprawdzaj `rg` zanim założysz, że klasa istnieje.

**Cel: strona ma się WYRÓŻNIAĆ.** Klient premium ma poczuć, że dostał coś zrobionego z myślą o nim, a nie szablon. Bazowy `.pageItem` / `.section` to szkielet — Twoim zadaniem jest zbudować na nim coś, co robi wrażenie.

#### Arsenał — z czego korzystaj, żeby podnieść poziom:

**Głębia i warstwy**
- Nakładanie warstw (`z-index`, nachodzące na siebie bloki, obrazy wychodzące poza kontener)
- Cienie wielowarstwowe, subtelne gradienty, glassmorphism (masz już `.glass`)
- Tło z ruchem: parallax (jest `.imageParallax`), gradient mesh, delikatny noise/grain

**Ruch i mikrointerakcje**
- Reveal przy scrollu (jest scrollReveal — klasa `showUp`), staggered animations
- Hover z charakterem: skala, przesunięcie, zmiana cienia, podkreślenia, magnetyczne przyciski
- Scroll-driven animation, sticky sections, pinned content
- Płynne przejścia stanów (`@include fade` i pochodne, własne `@keyframes`)
- Animowana typografia (Splitting.js, słowo/litera po literze)

**Typografia z charakterem**
- Duże, odważne nagłówki (masz `$font-h` = Zalando Sans Expanded — wykorzystaj jego charakter)
- Kontrast rozmiarów, świadomy tracking/leading, mieszanie wag
- `.text-glow` i podobne akcenty — buduj więcej takich

**Layout z polotem**
- Asymetria zamiast wiecznie wyśrodkowanych bloków
- Nietypowe siatki (broken grid, overlap grid), naprzemienne układy (buduj wariant sam — `.articleRow-invert` NIE istnieje)
- Świadome wykorzystanie pustej przestrzeni (whitespace to luksus)
- Bento-grid, split-screen, horizontal scroll (jest `.horizontalGallery`)

**Biblioteki JS — wolno wgrywać**, jeśli podnoszą jakość:
- **GSAP** (+ ScrollTrigger) — złożone animacje i scroll
- **Lenis** — smooth scroll (już jest w `_meta.php`)
- **Splitting.js** — animacje tekstu
- **Swiper** — bogatsze slidery (gdy Owl nie wystarcza)
- **Lottie** — animacje wektorowe
- Dodajesz w `_meta.php` (CDN lub lokalnie, po jQuery), inicjujesz w `scripts.js` lub dedykowanym `page-*.js`

#### Zasady jakości (obowiązują niezależnie od swobody):

1. **Nagłówek-komentarz** przy każdej sekcji:
```scss
// ----------------------------------------------------------
// ----- NAZWA SEKCJI
// ----------------------------------------------------------
```
2. **Nazwy opisowe:** `.pricingTable`, `.teamGrid`, `.heroReveal` — nie `.section2`, `.block1`
3. **BEM zagnieżdżony:** `&__element`, `&--modifier`
4. **Bazuj na `.section`** dla struktury sekcji (spacing, nagłówki), ale wygląd buduj śmiało ponad nią
5. **Kod czysty i czytelny** — to plik, po którym łatwo nawigować
6. **Wydajność:** animuj `transform`/`opacity` (GPU), `will-change` z głową, nie animuj `width`/`top` w pętlach
7. **Dostępność:** globalny blok `prefers-reduced-motion: reduce` jest już w `_content.scss` (skraca animacje, zdejmuje parallax/Ken-Burns). Każdą NOWĄ animację owijaj dodatkowo w `@include motion-safe { ... }` (mixin w `_cms.scss`).
8. **Responsywność:** design premium działa tak samo dobrze na mobile — nie projektuj tylko pod desktop

Nowe nadpisanie dark/contrast → **dopisz do istniejącego bloku `.theme-dark, .theme-contrast`**, nie twórz osobnego selektora.

> Gdzie tworzyć: pierwsza iteracja / rzecz specyficzna dla projektu → `_content.scss`. Komponent złożony i reużywalny w wielu projektach → osobny plik zaimportowany w `style.scss`.

---

## 11. `templates/theme/js/`

| Typ | Pliki | Wolno edytować? |
|---|---|---|
| **Custom** | `scripts.js`, `page-form.js`, `page-shop.js`, `files-sortable.js` | ✅ TAK |
| **Build** | `scripts.min.js` | ❌ generowany |
| **Biblioteki** | `owl.carousel.min.js`, `_owl.carousel2.thumbs.min.js`, `scrollReveal.min.js`, `_jquery.malihu.PageScroll2id.js`, `mmenu-light.js`, `jquery.mobile-events.min.js` | ❌ NIGDY |

**Zasady:**
- Nowa funkcjonalność → nazwana funkcja w `scripts.js` (wzorem `initMenu()`, `initFancyboxBinds()`)
- Duży osobny moduł dla jednej zakładki → nowy `page-*.js`
- **Zanim dodasz bibliotekę** — sprawdź, czy owl.carousel / scrollReveal / mmenu-light już tego nie robią
- Pliki z podkreślnikiem na początku (`_owl…`) = biblioteki, nie ruszaj

**W `_meta.php` kolejność ładowania ma znaczenie: jQuery zawsze pierwsze.**

---

## 12. CHECKLISTA PRZED ODDANIEM ZMIAN

- [ ] Przeszukałem repo (`rg`), żeby nie zduplikować istniejącej funkcji/klasy
- [ ] Przeczytałem **cały** plik przed edycją, nie tylko fragment
- [ ] Nie zmieniłem żadnej nazwy klasy CSS sprzężonej z PHP
- [ ] Nie usunąłem ani nie przepisałem działającego kodu
- [ ] **Zero hardcoded tekstu** — wszystko z `getData()` / `$lang[]` / `$config[]`
- [ ] Odwołania do zakładek przez `$config['x_page']`, nie surowe ID
- [ ] Media queries przez `@include media-up()` / `media-down()`
- [ ] **Zero surowych kolorów poza `_brand.scss`** — żadnego `#hex` ani `rgb()/rgba()` z liczbami; wszystko przez zmienne/tokeny (§10.0)
- [ ] Nowy kolor → dopisany do `_brand.scss` (nie „na miejscu"); marka ≤ 3 kolory
- [ ] Nowe pole formularza ma `.form-item` + `.form-control`
- [ ] Nowa sekcja w `_content.scss` ma nagłówek-komentarz i opisową nazwę
- [ ] Escapowanie przez `html()`, nie `htmlspecialchars()`
- [ ] `getUrl()`/`getData()` mogą zwrócić `null`/`''` dla **usuniętej strony** — string-operacje na wyniku (`rtrim`, `strpos`, `htmlspecialchars`, `.` konkatenacja do typu `string`) owijaj `?? ''` / `(string)`. Nie przekazuj `getUrl()` do parametru typu `string` bez zabezpieczenia (PHP 8.4)
- [ ] Nowy szablon `page-*.php`: plik **ORAZ** wpis w `$config['themes']`
- [ ] Nowy szablon zaczyna się od `if (!defined('CUSTOMER_PAGE')) exit;`
- [ ] Nie ruszyłem bloków `@claude-lock`
- [ ] Nie edytowałem `.min.js` ani `style.css`
- [ ] Nie tknąłem: `getPageId`, `saveVariables`, `uploadFile`, `checkLogin`, `generateCache`

### Checklista DESIGN (warstwa wizualna — tu podnoś poprzeczkę)
- [ ] Efekt końcowy **wyróżnia się** — nie wygląda jak przemalowany default CMS-a
- [ ] Jest ruch/interakcja tam, gdzie dodaje klasy (reveal, hover, scroll) — nie martwa statyka
- [ ] Typografia ma charakter (hierarchia, kontrast, świadomy `$font-h`)
- [ ] Layout ma polot (asymetria / warstwy / przestrzeń) — nie same wyśrodkowane pudełka
- [ ] Animacje płynne (`transform`/`opacity`, GPU), `prefers-reduced-motion` uszanowany
- [ ] Wygląda premium też na mobile, nie tylko desktop
- [ ] Nowe biblioteki JS (jeśli dodane) załadowane w `_meta.php` po jQuery, zainicjowane czysto
- [ ] Restyling nie rozłączył elementu od jego JS/PHP (koszyk/menu/formularz dalej działają)

---

## 13. STYL KOMUNIKACJI

- **Zwięźle i konkretnie.** Bez wstępów, bez podsumowywania mojego pytania, bez gratulacji.
- **Bez lania wody.** Konkret od pierwszego zdania.
- **Gdy się mylę — powiedz to wprost** i naprowadź na właściwy kierunek.
- **Etapami.** Nie 10 kroków wdrożenia naraz. Krok, weryfikacja, kolejny krok.
- **Plan przed kodem** (2–4 punkty), chyba że proszę o kod od razu.
- Rozróżniaj to, co sprawdziłeś w kodzie, od tego, co zgadujesz. Jeśli zgadujesz — powiedz to.

---

## 14. GDY UTKNIESZ

1. Nie zgaduj struktury — **przeczytaj plik**.
2. Nie twórz obejścia — **zapytaj**.
3. Nie refaktoryzuj „przy okazji" — **zaproponuj osobno**.
4. Jeśli reguła z tego pliku kłóci się z tagiem w kodzie → **wygrywa tag w kodzie**.
5. Jeśli reguła z tego pliku kłóci się z tym, o co proszę w rozmowie → **wygra to, o co proszę** (ale powiedz mi, że łamiesz regułę).
6. **Wahasz się przy DESIGNIE** („czy mogę dodać tę animację / bibliotekę / nietypowy layout / nadpisać ten styl")? → domyślna odpowiedź to **TAK, działaj**. Warstwa wizualna jest otwarta (patrz 0.5). Ostrożność rezerwujesz dla warstwy strukturalnej/logicznej.

---

## 15. BEZPIECZEŃSTWO — INWARIANTY (nie łam ich)

Te zasady wynikają z audytu bezpieczeństwa. Traktuj jak warstwę strukturalną.

### 15.1 Sekrety poza repozytorium
- Prawdziwe klucze (Google / PayU / InPost / reCAPTCHA / SMTP) i `login_pass` trzymaj w
  **`database/config.secrets.php`** — plik jest w `.gitignore` i blokowany przez `.htaccess`.
  Ładuje się **na końcu `config.php`** i nadpisuje wartości z `config.php` / `config_pl.php`.
- Wzorzec do skopiowania: **`database/config.secrets.dist.php`**.
- **Nigdy nie commituj prawdziwych kluczy** do `config.php` / `config_pl.php`. Placeholdery TAK.
- Domyślne `login_pass = "haslo"` w bazie to tylko placeholder — **zmień na mocne hasło** przy każdym wdrożeniu.

### 15.2 SQL — dane od użytkownika
- **Żadnego surowego `$_GET` / `$_POST` / `$_COOKIE` w zapytaniu.** Zawsze prepared statement
  (`prepare` + `execute([':x'=>...])`) albo `(int)`-cast, albo `$oSql->quote(...)`.
- Wzorzec bezpieczny (filtry sklepu): `listPagesQuery()` w `plugins/settings.php` — wartość
  filtra idzie przez `$oSql->quote()`. Nie wklejaj wartości do `LIKE '%...%'` przez konkatenację.
- Filtrowanie po stronie PHP (bez SQL): `pageMatchesFilters()` w `core/pages.php` — też bezpieczne.

### 15.3 Błędy / tryb dev
- `index.php` i `adm.php` **domyślnie wyłączają `display_errors`** (produkcja). `DEVELOPER_MODE`
  w `config.php` włącza je z powrotem. **Na produkcji zakomentuj `define('DEVELOPER_MODE', true)`.**
- Nie używaj `E_STRICT` (deprecated w PHP 8.4) — jest już w `E_ALL`.

### 15.4 Upload / pliki treści
- `files/.htaccess` **blokuje wykonanie skryptów** w katalogu treści i **neutralizuje SVG**
  (Content-Disposition + CSP). Nie usuwaj tego pliku.
- `checkCorrectFile()` (`core/libraries/file-jobs.php`) ma **zakotwiczony** regex rozszerzenia (`^(...)$`).

### 15.5 Panel / sesje / CSRF (już wdrożone — nie psuj)
- CSRF admina: token wymagany na każdym POST/usuwaniu — `checkCsrfToken()` (`core/common-admin.php`),
  brama w `adm.php`. Hasło admina: bcrypt + migracja (`adminPasswordVerify`/`adminPasswordMigrate`).
- Logowanie Google (panel): `plugins/google-auth.php` + biała lista `$config['admin_google_emails']`.
- `session_regenerate_id()` przy każdym logowaniu (admin, Google, panel użytkownika).

### 15.6 Czego NIE ma w repo (i ma nie wracać)
- **`plugins/admin.php` (phpLiteAdmin)** — konsola SQLite dostępna po URL ze słabym hasłem.
  Usunięta i w `.gitignore`. To narzędzie **tylko lokalnie**, nigdy na produkcji / w repo.
- `.htaccess` (root) blokuje `.git`/dotfiles, listing katalogów i wrażliwe pliki (`.db`, `.log`, `.dist`…).

---

*Ostatnia aktualizacja: lipiec 2026 — v3 (sekcja 15 BEZPIECZEŃSTWO po audycie; poluzowana warstwa wizualna: pełna swoboda designu przy zachowaniu dyscypliny strukturalnej)*