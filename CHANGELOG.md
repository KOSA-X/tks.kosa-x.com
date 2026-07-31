# Changelog

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
