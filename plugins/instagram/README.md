# KOSA X InstaFeed — konfiguracja krok po kroku

Widget Instagram dla KOSA X CMS. Pobiera posty przez **Instagram API (graph.instagram.com)**,
hostuje media lokalnie i renderuje premium grid. Poniżej pełna instrukcja od zera.

> Nazwy menu w panelu Meta bywają zmieniane — jeśli któryś przycisk nazywa się
> inaczej, szukaj hasła z nawiasu. Endpointy API (URL-e) są stałe.

---

## 0. Czego potrzebujesz

- Konto **Instagram** przełączone na **Firmowe (Business)** lub **Twórca (Creator)**.
- Konto **Facebook** (do założenia aplikacji Meta).
- ~15 minut.

---

## 1. Przełącz Instagram na konto profesjonalne

Aplikacja Instagram → **Ustawienia i prywatność → Typ konta i narzędzia →
Przełącz na konto profesjonalne** → wybierz **Firma** (zalecane; daje `like_count`
i komentarze) lub **Twórca**.

> Konto prywatne NIE zadziała — API nie zwróci mediów.

---

## 2. Utwórz aplikację w Meta for Developers

1. Wejdź na **https://developers.facebook.com/apps** i zaloguj się kontem Facebook.
2. **Utwórz aplikację** (Create App).
3. Typ aplikacji: **Firma (Business)**. Nazwa dowolna (np. „KOSA X InstaFeed”).
4. Utwórz i wejdź do panelu aplikacji.

---

## 3. Dodaj produkt „Instagram”

1. W panelu aplikacji: **Dodaj produkt** → znajdź **Instagram** → **Skonfiguruj (Set up)**.
2. Wybierz ścieżkę **„Instagram API setup with Instagram business login”**
   (logowanie firmowe przez Instagram — endpoint `graph.instagram.com`).

---

## 4. Wygeneruj token i pobierz ID konta

1. W sekcji **„Generuj tokeny dostępu” (Generate access tokens)** kliknij
   **Dodaj konto (Add account)** i zaloguj się TYM kontem Instagram, które ma
   być na stronie. Zaakceptuj uprawnienia (m.in. `instagram_business_basic`,
   a dla komentarzy `instagram_business_manage_comments`).
2. Po połączeniu zobaczysz **krótkotrwały token (short-lived)** oraz
   **Instagram user ID** (długi numer) — zapisz oba.

   ID konta możesz też sprawdzić w dowolnej chwili:
   ```
   GET https://graph.instagram.com/me?fields=user_id,username&access_token={TOKEN}
   ```

---

## 5. Zamień token krótkotrwały na długotrwały (60 dni)

Potrzebujesz **App secret** (Panel aplikacji → **Ustawienia → Podstawowe →
Klucz aplikacji / App secret**).

Wywołaj w przeglądarce / curlem:
```
GET https://graph.instagram.com/access_token
    ?grant_type=ig_exchange_token
    &client_secret={APP_SECRET}
    &access_token={SHORT_LIVED_TOKEN}
```
Odpowiedź zawiera `access_token` — to jest **long-lived token** (ważny 60 dni).
To jego wpisujesz do configu.

> Widget **sam odświeża** token, gdy zbliża się do wygaśnięcia (>45 dni) — patrz
> `refreshTokenIfNeeded()`. Wystarczy, że raz wpiszesz świeży long-lived token.

---

## 6. Wpisz dane w `database/config_pl.php`

Całe sterowanie feedem jest w głównej konfiguracji CMS (nie w module):

```php
$config['instagram_cron_secret'] = 'a9F3xQ...';   // losowy ciąg ~32 znaki
$config['instagram_accounts'] = array(
    'main' => array(
        'ig_user_id'           => '17841400000000000', // ID z kroku 4
        'access_token'         => 'IGQVJ...long-lived...', // token z kroku 5
        'token_updated_at'     => '2026-02-14',        // DZISIEJSZA data (Y-m-d)
        'limit'                => 15,                  // ile postów trzymamy
        'handle'               => '@twoje_konto',
        'cover_from_permalink' => true,                // reels: okładka z permalinku (patrz niżej)
    ),
);
```

> Klucz `'main'` jest dowolny. Strona główna bierze go z
> `$config['instagram_account']`. Więcej kont = więcej wpisów; w szablonie
> `renderInstaFeed('drugie')`.
>
> **Sekrety bezpiecznie:** `access_token` i `instagram_cron_secret` możesz
> nadpisać w `database/config.secrets.php` (poza gitem — patrz
> `config.secrets.dist.php`), zostawiając w `config_pl.php` placeholdery.

---

## 7. Pierwsze pobranie + cron hostingu

**Ręczne pierwsze uruchomienie** (podmień SEKRET na swój `cron_secret`):
```
https://twojadomena.pl/plugins/instagram/cron.php?key=SEKRET
```
Powinno pojawić się `OK konto=main postów=N`, a w `cache/main/` — `feed.json`
i `media/*.jpg`.

**Cron w panelu hostingu (co 6 h):**
```
0 */6 * * * curl -s "https://twojadomena.pl/plugins/instagram/cron.php?key=SEKRET" >/dev/null
```
(lub CLI: `php /sciezka/do/plugins/instagram/cron.php SEKRET`)

---

## 8. Wyświetlenie na stronie

- **Strona główna** pokazuje sekcję automatycznie, gdy istnieje feed dla konta
  z `$config['instagram_account']`.
- **Ręcznie w dowolnym szablonie:**
  ```php
  echo renderInstaFeed('main', ['limit' => 9, 'columns' => 4, 'header' => true]);
  ```

---

## 9. Uprawnienia katalogów (na serwerze)

`plugins/instagram/cache/` musi być **zapisywalny** dla PHP (cron tam zapisuje):
```
chmod -R 755 plugins/instagram/cache
```

---

## Wideo / reels

- Reelsy i posty wideo są **pobierane jako lokalny plik `.mp4`** (`cache/{account}/media/{id}.mp4`)
  i **odtwarzane w Fancyboxie** (nie przekierowują na Instagram).
- Okładka w gridzie to **`thumbnail_url` z API** (nie generujemy klatki z wideo).
- Fail-safe: jeśli mp4 się nie pobierze, wpis zostaje z samą miniaturką, a kafel
  reela **linkuje do Instagrama** (permalink). Błąd trafia do `cron.log`
  (`BLAD mp4 ...`). Cykl nie jest przerywany.

## Miejsce na dysku

- Trzymamy ostatnie **`limit`** postów (domyślnie **15**). Posty, które wypadną
  poza limit, są **usuwane z dysku** (i `.jpg`, i `.mp4`) przy każdym cronie —
  log `USUNIETO konto=... plik=...`.
- mp4 bywają ciężkie (reelsy) — pliki są zapisywane 1:1 z API (bez re-enkodowania).
  Jeśli chcesz mniej zajętości, zmniejsz `limit`.
- Bezpiecznik: gdy API zwróci **pusty feed** (0 postów — prawdopodobny błąd), cache
  **nie jest czyszczony**, `feed.json` nie jest nadpisywany (log `OSTRZEZENIE`).

## Weryfikacja po wgraniu tokenu

1. Odpal `cron.php?key=...` ręcznie → w `cron.log` `OK konto=main postow=N`.
2. W `cache/main/media/` są pliki **`.mp4`** dla reelsów (obok `.jpg`).
3. W `feed.json` wpisy wideo mają pole **`local_video`**.
4. Na stronie kliknij reel → **odtwarza się w Fancyboxie** (nie przekierowuje).
5. (Opcjonalnie) zmień `limit` na `5`, odpal cron → nadmiarowe pliki znikają
   z dysku (`USUNIETO` w logu); wróć do `15`.

---

## Rozwiązywanie problemów

| Objaw | Przyczyna / rozwiązanie |
|---|---|
| `cron.php` → 403 | Zły `key` lub `cron_secret` = placeholder. Ustaw prawdziwy sekret. |
| `BLAD konto=main: API niedostępne` | Zły/wygasły token lub złe `ig_user_id`. Wygeneruj token od nowa (krok 4–5). |
| Grid pusty, `feed.json` nie powstaje | Konto nie jest Firmowe/Twórca; albo brak uprawnień do mediów. |
| Brak `like_count`/komentarzy | Konto Twórca ma ograniczenia — użyj konta **Firmowego** + zgoda na `instagram_business_manage_comments`. |
| Obrazy 403 w przeglądarce | `.htaccess` blokuje tylko config/token/feed/log — `media/*.jpg` musi być serwowane. Sprawdź uprawnienia plików. |
| Token wygasł mimo cron | Cron musi chodzić regularnie (odświeża >45 dni). Sprawdź `cache/cron.log`. |

---

## Bezpieczeństwo

- **Token nigdy nie trafia do HTML/JS** — cała komunikacja z API jest server-side.
- `config.php`, `token.json`, `feed.json`, logi — blokowane przez `.htaccess`.
- Docelowo przenieś `access_token` i `cron_secret` do `database/config.secrets.php`
  (poza repo) — patrz CLAUDE.md §15.1.
