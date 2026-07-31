# Changelog

## 2026-07-31 — Transmisja live (TKS): nakładki OBS, krok 2 — panel operatora
- `page-live-panel.php` + `js/page-live-panel.js` + sekcja `.livePanel` w `_content.scss`: pełny panel sterowania meczem (tylko zalogowany admin)
- Sticky pasek wyniku i zegara (+/- per drużyna, MM:SS z limitem 45/90 i oznaczeniem doliczonego), sterowanie zegarem (1./2. połowa, pauza/wznów wg stanu, ±1 min, reset), przyciski plansz ze stanem aktywności, konfiguracja meczu (wybór drużyn + nowy mecz)
- Kafelki zawodników obu drużyn (numer+nazwisko, grupy wg `sSquad`, znacznik na boisku/zszedł wyliczany z historii zmian) → arkusz akcji (minuta z zegara, 6 zdarzeń ze słownika `live_actions`) — wygodne na telefonie
- Lista zdarzeń: edycja minuty, kasowanie, czyszczenie historii; odświeżanie pomija cykl podczas edycji (nie gubi wpisywanej wartości)
- Fixy z testów w przeglądarce (Playwright, 8 scenariuszy E2E): `[hidden]` vs `display:flex` na arkuszu, sticky poniżej nagłówka strony, arkusz/toast przenoszone do `<body>` (transform na `.mainBody` łamie `position:fixed`), neutralne przyciski panelu (brandowy `.button` maskował stany), API root-relative zamiast BASE_URL

## 2026-07-31 — Transmisja live (TKS): nakładki OBS, krok 1 — fundament (tabele + API)
- Baza (migracja `database/migrations/2026-07-31-live-tables.php`): `live_state` (1 wiersz: drużyny, wynik, zegar z realną pauzą, połowa), `live_events` (zdarzenia pobierane po ID — koniec gubienia/dublowania z okna czasowego), `live_boards` (plansze definiowane w bazie, 8 startowych) — zastępują 4 tabele starego systemu
- Zakładki: „Mecz" (31, `match_page`), „Panel meczowy" (32, theme 11), „Nakładka OBS" (33, theme 12) + placeholdery szablonów (panel z guardem sesji admina, overlay standalone z przezroczystym tłem)
- `plugins/live/api.php` — JSON API: GET `state` (zbiorczo: zegar+wynik+plansze+eventy `?since=ID` w jednym żądaniu) i `events`; POST (tylko zalogowany admin + CSRF, prepared statements — stary `ajax.php` nie miał ŻADNEJ autoryzacji): eventy (add/update/delete/clear z walidacją typu, drużyny i przynależności zawodnika), wynik (nie schodzi poniżej 0), zegar (start połowy / pauza / wznowienie / ±1 min / reset), plansze (jedna naraz, „wynik" niezależny), `teams_set`, `match_reset`
- Config: słownik `$config['live_actions']` (gol, samobój, kartki, zmiany), themes 11/12, klucze `match_page`/`live_panel_page`/`live_overlay_page`

## 2026-07-31 — Transmisja live (TKS), Etap 1 / krok 3 (Etap 1 KOMPLETNY)
- Zapis składu z ekranu korekty do bazy: zawodnik = podstrona drużyny przez `PagesAdmin::savePage()` (`sNumber`, `sSquad`, `iPosition` = numer, dziedziczone menu po drużynie)
- Dopasowanie po nazwisku (normalizacja: spacje/encje/wielkość liter) — ponowny import AKTUALIZUJE zawodnika zamiast dublować; opisy (`sDescription*`) istniejących zawodników zachowywane
- Zawodnicy drużyny nieobecni w protokole → `sSquad = ''` (poza kadrą meczową)
- Sztab szkoleniowy → blok `<div class="teamStaff">` w `sDescriptionShort` drużyny (podmieniany przy kolejnym imporcie, nie dublowany)
- Po zapisie: widok „Aktualna kadra" (podstawowi → rezerwowi → poza kadrą) + sprzątanie plików roboczych (zdjęcie protokołu i JSON usuwane z serwera)

## 2026-07-31 — Transmisja live (TKS), Etap 1 / krok 2
- OCR protokołu: `plugins/live/ocr.php` — wysyłka zdjęcia do Anthropic API (vision, model `$config['anthropic_ocr_model']`, domyślnie claude-opus-5) przez cURL; structured outputs (JSON schema) gwarantują poprawny JSON; obsługa `refusal`/`max_tokens`/błędów API; server-side fallback; obrazy pomniejszane do 2576 px (GD) przed wysyłką
- Panel: przycisk „Rozpoznaj skład (OCR)" po uploadzie → wynik do `{token}.json` obok zdjęcia → **ekran korekty** (edytowalna tabela zawodników: numer/nazwisko/skład ze słownika `squad_types`; sztab: funkcja/nazwisko; dodawanie i usuwanie wierszy; podgląd oryginału)
- Zapis do bazy = następny krok (przycisk działa, pokazuje status „save_pending")

## 2026-07-31 — Transmisja live (TKS), Etap 1 / krok 1
- Baza: nowe kolumny `pages.sNumber` (numer na koszulce) i `pages.sSquad` (skład meczowy, słownik `$config['squad_types']`) — migracja `database/migrations/2026-07-31-live-schema.php` (idempotentna, CLI)
- Baza: zakładka „Drużyny" (iPage=28, Systemowe) + podstrona „TKS Tomasovia" (iPage=29); nowy klucz `$config['teams_page']` w `config_pl.php`
- Panel: nowy moduł **Transmisja → Import składu** (`templates/admin/squad-import.php`) — upload zdjęcia protokołu meczowego (JPG/PNG/WEBP, walidacja `getimagesize`, max 12 MB) + wybór drużyny lub utworzenie nowej (`PagesAdmin::savePage`); podgląd protokołu tylko dla zalogowanego admina; pliki w `plugins/live/cache/protocols/` (poza gitem, HTTP zablokowane przez `.htaccess`)
- Config: placeholdery `$config['anthropic_api_key']` / `$config['elevenlabs_api_key']` (prawdziwe klucze → `config.secrets.php`)
- Nowy `.gitignore` (sekrety, cache Instagram/live, phpLiteAdmin)

## 2026-01-16
- jQuery 3.6.0 → 3.7.1
- PHPMailer 6.x → 7.0.2
