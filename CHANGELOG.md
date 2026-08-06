# Changelog

## 2026-08-06 — Nakładka OBS: wspólna ul.teamList, kaskadowy wjazd wierszy, jedna stopka
- **Wspólna lista `ul.teamList`** — jedna klasa i style dla wszystkich list
  „kwadracik + nazwisko" na planszach: składy drużyn (kwadracik = numer)
  i oś zdarzeń w podsumowaniu (kwadracik = minuta + ikona akcji); formatowanie
  przeniesione 1:1 z ostylowanych składów właściciela; wariant `--dark`
  (rezerwa / kolumna gościa); stare `.obsSquad__list`/`.obsSummary__list`
  zastąpione — zmiana stylu w jednym miejscu wchodzi wszędzie
- **Kaskadowy wjazd wierszy**: po pokazaniu planszy wiersze `.teamList`
  wsuwają się z lewej jeden po drugim (opóźnienie wg `--i` ustawianego
  w PHP/JS; tylko transform+opacity — płynne w OBS; reset przy ukryciu
  planszy; wyłączane przy `prefers-reduced-motion`); odświeżanie
  podsumowania co 10 s pomija render przy niezmienionej treści, żeby nie
  restartować animacji (cache klucza zdarzeń)
- **Jedna stopka plansz** (`$sBoardFooter`): zdefiniowana raz na górze
  page-live-overlay.php, echo we wszystkich planszach; uchwyty social
  wyciągane z Konfiguracji → Social media (instagram/facebook/youtube) —
  zmiana w panelu aktualizuje wszystkie plansze; ikony root-relative
  (bez zahardkodowanej domeny)
- Fix: brakujący `</div>` w planszy składu wciągał kolejne plansze do
  wnętrza niewidocznej planszy (podsumowanie znikało z ekranu)
- E2E: opóźnienia 0.25/0.32/0.39 s, wiersze kończą na opacity 1, listy
  podsumowania w teamList z kwadracikami minut, stopka z configu na
  9 planszach — zielone, zero błędów JS

## 2026-08-06 — Moduł „Drużyny" w panelu admina + filtr zawodników w panelu meczowym
- **Nowy moduł panelu: Drużyny** (`?p=teams`, w menu w miejscu Sklepu; submenu
  Lista drużyn / Nowa drużyna):
  - lista drużyn (zakładki typu menu „Drużyny") z licznikami kadry meczowej
    i wszystkich zawodników + skróty Kadra / Edytuj stronę,
  - **widok kadry**: tabela zawodników z polem numeru na koszulce (szybka
    zmiana przy wymianie koszulek) i przełącznikiem składu JEDNYM kliknięciem
    (11 / Rezerwa / Poza kadrą — stan od razu widoczny w tabeli), zapis
    ZBIORCZY jak pozycje w Liście stron; celowany UPDATE (sNumber/sSquad/
    iPosition) nie rusza opisów zawodnika; walidacja przynależności do drużyny,
  - szybki formularz „Nowa drużyna" (top-level + typ menu „Drużyny")
- **Panel meczowy: filtr zawodników** — pole szukania nad listą każdej
  z dwóch drużyn: wpisywanie zawęża kafelki na żywo (bez wielkości liter
  i polskich znaków — „swi" znajdzie Świątka), przycisk WYCZYŚĆ / skasowanie
  frazy przywraca wszystkich; nagłówki pustych grup chowają się razem
  z zawodnikami
- E2E na realnej bazie: lista drużyn, zapis kadry (numer+skład+pozycja),
  filtr „kon"→1 / „xyz"→0 / wyczyść→wszyscy — zielone, zero błędów JS

## 2026-08-06 — Fix zapisu Konfiguracji transmisji + plakat + plansza „Sponsorzy — slider"
- **Naprawiony zapis w Transmisja → Konfiguracja**: `saveVariables()` z rdzenia
  dopasowuje linię wzorcem `] = ` (dokładnie jedna spacja) — klucze plansz
  w `config_pl.php` miały WYRÓWNANE `=` wieloma spacjami, więc zapis po cichu
  je pomijał i formularz „resetował się" do starych wartości; przypisania
  znormalizowane do pojedynczej spacji (+ komentarz ostrzegawczy w pliku)
- **Naprawiona plansza „Plakat meczowy"**: `.obsPoster` neutralizuje teraz CAŁE
  pozycjonowanie bazowej `.obsBoard` (left/top 50%, width 1180px i transform
  translate -50% nakładany przez `.is-visible`) — plakat uciekał w lewy górny
  róg, teraz jest pełnoekranowy i wyśrodkowany
- **Nowa plansza „Sponsorzy — slider"** (`sponsorzy_slider`, wiersz w bazie):
  biała banda reklamowa przyklejona do dolnej krawędzi — jeden wiersz logotypów
  z zakładki SPONSORZY (te same zdjęcia co grid) płynie powoli w lewo w pętli
  bez szwu (track = 2× ta sama grupa, animacja tylko `transform` na GPU, czas
  = liczba log × 4 s, wyłączana przy `prefers-reduced-motion`)
- E2E: roundtrip zapisu konfiguracji (41→43→41 w pliku), plakat [0,0,1920,1080]
  wyśrodkowany, slider płynie w lewo ~18 px/s — zielone, zero błędów JS

## 2026-08-06 — 3 nowe plansze + uproszczony panel meczowy + edycja zawodnika w zdarzeniu
- **Nowe plansze** (wiersze w `live_boards` — są w bazie repo, przyciski w panelu
  pojawiają się automatycznie):
  - **Partnerzy główni** — grid logotypów jak sponsorzy (`live_partners_page`),
  - **Sponsor meczu** — 1 duże logo (pierwszy obrazek zakładki) + opis pełny
    (`live_match_sponsor_page`, ustawione na zakładkę „Sponsor meczu"),
  - **Aktualny wynik** — dolny pasek na czas meczu: pełne nazwy drużyn, wynik
    i strzelcy bramek per drużyna (gole + samobóje przeciwnika z „(sam.)";
    zdarzenie bez zawodnika pokazuje nazwę drużyny); nakładka odświeża
    strzelców razem z podsumowaniem
  - nowe selecty w Transmisja → Konfiguracja; partnerzy/sponsor meczu także
    na telebimie (pasek aktualnego wyniku tylko na nakładce)
- **Panel meczowy uproszczony**: sekcja „Konfiguracja meczu" (wybór drużyn)
  usunięta — drużyny ustawia się w Transmisja → Konfiguracja; przycisk
  „Nowy mecz (wyzeruj)" przeniesiony na dół obok „Wyczyść historię zdarzeń"
- **Edycja zawodnika w zdarzeniu**: w historii zdarzeń zamiast tekstu jest
  select z kadrą drużyny zdarzenia (+ „—") — pomyłkowo wybranego zawodnika
  poprawisz bez kasowania wpisu; API `event_update` waliduje przynależność
  zawodnika do drużyny zdarzenia
- E2E na realnej bazie: 12 przycisków plansz, pasek ze strzelcami, sponsor
  meczu (logo+opis), zmiana zawodnika w zdarzeniu, walidacja złej drużyny —
  zielone, zero błędów JS

## 2026-08-06 — Transmisja → Konfiguracja (nowy moduł) + szlif paska wyniku i podsumowania
- **Nowy moduł panelu: Transmisja → Konfiguracja** (`?p=live-config`, pod
  Importem składu) — konfiguracja transmisji ODDZIELONA od konfiguracji CMS:
  gospodarz + gość (zapis do `live_state`, walidacja jak `teams_set`),
  **data meczu** i **kolejka** (pola `sDate`/`sPrice` strony `match_page` —
  celowany UPDATE nie rusza opisów) oraz dopasowanie zakładek-źródeł plansz
  (Dzień meczowy / Sędziowie / Sponsorzy / Realizacja) przeniesione
  z modułu Konfiguracja (saveVariables → config_pl.php)
- **Kolejka + data w belkach plansz**: Dzień meczowy i Podsumowanie pokazują
  w belce „kolejka — data" (np. „30. kolejka 2025/26 — 31.08.2026 18:00")
- **Doliczony czas na CZERWONO**: po przekroczeniu 45:00 / 90:00 zegar paska
  wyniku (nakładka i telebim) dostaje tło `$danger` — licznik stoi na
  45:00/90:00 i świeci na czerwono
- **Logo realizatora transmisji**: `images/kosax-live.png` w prawym górnym
  rogu nakładki, włączane razem z planszą „wynik i czas"; gdy pasek wyniku
  przełączony na prawy róg, logo przeskakuje na lewy; brak pliku = brak
  elementu (guard `is_file`) — **wgraj plik do images/**
- **Podsumowanie bez tabelki statystyk** (nakładka + telebim) — wszystko
  widać na osi zdarzeń; JS toggluje wiele elementów jednej planszy
  (querySelectorAll — pasek + logo współdzielą `data-board="wynik"`)
- E2E: zapis modułu (drużyny/data/kolejka/plansze), czerwony zegar 45:00,
  logo z paskiem i po przeciwnej stronie, podsumowanie bez statystyk —
  zielone, zero błędów JS

## 2026-08-06 — Panel admina: pola zawodnika + plansze w Konfiguracji; kropki kartek na pasku
- **Formularz strony (Opcje)**: sekcja „Transmisja live — zawodnik" — numer
  zawodnika (`sNumber`) i skład meczowy (`sSquad`: Wyjściowa 11 / Rezerwa /
  Poza kadrą); etykiety `$config['squad_types']` ujednolicone z frontem
- **Konfiguracja → Dopasowanie stron**: wycięte nieużywane selecty
  (sklep/blog/oferta/FAQ…), w ich miejsce sekcja „Transmisja live — treści
  plansz": Dzień meczowy (`match_page`), Sędziowie (`live_referees_page`),
  Sponsorzy (`live_sponsors_page`), Realizacja (`live_production_page`) —
  wybór zakładki z listy, zapis standardowo przez saveVariables
- **Pasek wyniku (nakładka)**: żółte/czerwone kropki pod nazwą drużyny =
  kartki (liczniki `cards` w API state — liczone z całej historii, odporne
  na odświeżenie); usunięty wskaźnik połowy („1 poł./2 poł.") z zegara
- **Podsumowanie (nakładka + telebim)**: oś zdarzeń pokazuje tylko gole
  i kartki — zmiany ukryte (nadal widoczne w wierszu statystyk)
- E2E na realnej bazie: kropki YY/R, pasek bez połowy, lista bez zmian,
  zapis numeru+składu z formularza strony, selecty plansz w Konfiguracji

## 2026-08-06 — Drużyny wg typu menu + plansze z zakładek (nowa struktura właściciela)
- **Nowa konwencja drużyn**: drużyna = zakładka NAJWYŻSZEGO poziomu z typem menu
  „Drużyny" (`$config['teams_menu']`, domyślnie 4 z `pages_menus`); zawodnicy =
  jej podstrony. Klucz `teams_page` usunięty. Filtry wszędzie łączą
  `iMenu = teams_menu` z `iPageParent = 0`, bo `savePage()` dziedziczy iMenu
  dziecka po rodzicu (zawodnicy po imporcie też mają iMenu drużyny)
- Naprawione pod nową strukturę: wybór i zapis drużyn w panelu meczowym,
  walidacja `teams_set` w API, lista drużyn + nowa drużyna w imporcie składu
  (tworzona jako top-level z iMenu drużyn)
- **Plansze z zakładek panelu** (typ menu „Plansze"): sędziowie =
  `live_referees_page` (opis pełny + GRID zdjęć strony; fallback: opis skrócony
  strony meczu), sponsorzy = `live_sponsors_page`, realizacja =
  `live_production_page` — ustawione na zakładki 38/41/39; grid logotypów
  przełączony na `auto-fill` (24 sponsorów mieści się tak samo jak 4)
- Usunięte ponownie stare skrypty `database/migrations/*` (wróciły z nieaktualnej
  kopii roboczej; z nową strukturą tworzyłyby zdublowane zakładki)
- Testy E2E na realnej bazie właściciela: 16 drużyn w selekcie (bez zawodników),
  zapis drużyn, plansze sędziów/sponsorów (24 loga)/realizacji na nakładce
  i telebimie — zielone, zero błędów JS

## 2026-08-03 — Baza danych KOMPLETNA w repo — koniec migracji
- `database/database.db` zawiera już CAŁY schemat i dane systemowe modułu live:
  kolumny `pages.sNumber`/`pages.sSquad`, tabele `live_state` (z `iScorebarPos`
  i `iReplayCount`), `live_events`, `live_boards` (9 plansz, w tym „Sędziowie"),
  zakładki 28 „Drużyny" / 29 „TKS Tomasovia" / 31 „Mecz" / 32 „Panel meczowy" /
  33 „Nakładka OBS" / 34 „Telebim" + przebudowany cache routingu
  (`database/cache/links*`)
- **Wgranie całego repozytorium = działająca strona.** Zero kroków CLI po
  wdrożeniu; instrukcje „uruchom migrację na serwerze" z wcześniejszych wpisów
  są NIEAKTUALNE
- Skrypty `database/migrations/*` usunięte (ich efekty siedzą w bazie);
  komentarze w `config_pl.php` i komunikat w imporcie składu zaktualizowane
- Zweryfikowane na czystej kopii repo (git archive → PHP): front `/`,
  `/telebim/`, `/panel-meczowy/`, `/nakladka-obs/`, `/druzyny/`, `/mecz/`,
  API state (czysty stan, 9 plansz), logowanie do panelu i moduł
  Transmisja → Import składu — wszystko działa od ręki
- ⚠️ Wdrożenie NADPISUJE bazę na serwerze — treści dodane przez panel
  (zaimportowani zawodnicy, opisy) wracają do stanu bazowego; sekrety jak
  dotąd w `database/config.secrets.php` (poza repo)

## 2026-07-31 — Telebim: przebudowa layoutu pod czytelność z trybun (wzór: _old)
- **Scena wyniku** (plansza „wynik") = pełny ekran zamiast paska: zegar 4rem w białej pigule u góry, wynik **11rem**, herby 11rem + skróty drużyn 3.2rem — proporcje jak w starym telebimie (zegar 150px / wynik 300px / herby 500px @1080p)
- **Zdarzenia przejmują CAŁY ekran** (jak w _old): gruba kolorowa ramka per typ (gol=brand, żółta=warning, czerwona/zejście/samobój=danger, wejście=success), etykieta akcji 3.6rem, nazwisko 4rem z numerem w brandowej pigule, zdjęcie zawodnika w medalionie 13rem
- **Gol = mrugające tło**: warstwa radialnego brandowego glow pulsująca opacity (`tbGoalFlash` .9s infinite, GPU) + pulsowanie etykiety „GOL" (`tbGoalPulse`); wyłączane przy `prefers-reduced-motion`
- **Plansze uproszczone i powiększone**: skład = SAMA lista (bez sztabu i logo — nieczytelne z daleka), zawodnik 1.9rem; belka tytułu 2.4rem; sędziowie 2.4rem; nagłówek meczowy 8rem wyniku / herby 8.5rem (na podsumowaniu skalowany w dół, bo wynik powtarza się w statystykach); wszystkie plansze pełnoekranowe z własnym tłem
- Warstwy: scena wyniku (1) → plansze (2) → zdarzenia (5) → wideo (10)
- Retest E2E: 8/8 zielone, zero błędów JS; dodatkowe kadry mrugnięcia tła gola

## 2026-07-31 — Transmisja live (TKS): TELEBIM (Etapy 4-6) — cieszynki wideo + powtórki z OBS
- **Widok telebimu** — `page-telebim.php` (theme 13, zakładka „Telebim" iPage=34, `telebim_page`), standalone jak nakładka OBS, ale z CIEMNYM tłem (fizyczny ekran LED) i layoutem skalowanym do dowolnej rozdzielczości (`1rem = 1/48 szerokości`, projekt bazowy 768×512); pasek wyniku na całą szerokość, plansze niemal pełnoekranowe — sterowane TYMI SAMYMI `live_boards` co nakładka (operator przełącza raz, oba ekrany reagują); arkusz `_source/telebim.scss` → `css/telebim.css` (10 KB), vanilla `js/page-telebim.js`
- **Cieszynki wideo po golu (Etap 4)** — klip mp4/webm wgrany STANDARDOWYM uploadem plików na podstronie zawodnika (rozszerzenia dopisane do `allowed_not_image_extensions` poza blokiem `@claude-lock`); preload wszystkich klipów obu drużyn na starcie (ukryte `<video preload>`); gol strzelca z klipem = pełnoekranowe wideo z podpisem (numer+nazwisko+minuta), brak klipu → zwykły popup, USZKODZONY klip → fallback na popup (zdarzenie nie przepada); kolejkowanie zdarzeń w trakcie odtwarzania
- **Powtórki z OBS (Etap 5)** — `plugins/live/replay/replay-server.py` (Python stdlib, zero zależności): lokalny mini-serwer na komputerze z OBS serwujący NAJNOWSZY plik replay buffera pod `http://localhost:8766/replay.mp4` (CORS, Range/206, bind 127.0.0.1); przycisk „▶ Powtórka na telebimie" w panelu → POST `replay_show` → `live_state.iReplayCount++` → telebim wykrywa wzrost licznika i gra klip (powtórka przerywa cieszynkę; localhost zwolniony z mixed-content w Chrome); adres konfigurowalny `$config['live_replay_url']`
- **Kiosk mode (Etap 6)** — instrukcja w `plugins/live/replay/README.md` (chrome `--kiosk --autoplay-policy=no-user-gesture-required --window-position=…`, konfiguracja OBS Replay Buffer + format mp4)
- `plugins/live/view-helpers.php` — wspólne gettery danych widoków live (`liveTeamData`, `liveSquad`, `liveStaffBlock`, `livePageImage(s)`, `liveTeamClips`) — ta sama logika co closury nakładki, wyniesiona do funkcji (nakładkę można zmigrować w osobnym kroku)
- API: `player.video` w payloadzie zdarzeń stanu, `replay` (licznik) w state, POST `replay_show`
- Migracja `database/migrations/2026-07-31-live-telebim.php` (idempotentna): zakładka Telebim + `live_state.iReplayCount` — **uruchom na serwerze po wdrożeniu**
- Testy: 8 scenariuszy E2E w Chromium (klip testowy webm generowany MediaRecorderem) — scorebar/plansze, popup bez klipu, cieszynka gra i się chowa, fallback po uszkodzonym klipie, toast panelu, powtórka z realnego lokalnego serwera (curl: 200/206/CORS), statystyki podsumowania; wyłapały brakujący `</div>` w planszy składu (wszystkie kolejne plansze zagnieżdżały się w niewidocznej)

## 2026-07-31 — Transmisja live (TKS): poprawki po teście na produkcji + szlif
- OCR: nazwiska zapisywane jako „Imię Nazwisko" zamiast DRUKOWANYCH — instrukcja w prompcie + `liveOcrTitleCase()` (mb, polskie znaki; konwertuje TYLKO stringi w całości wielkimi literami, „Piotr van der Berg" zostaje bez zmian)
- Panel: zawodnicy w osobnych wierszach (1 kolumna); przy nazwisku badge zapisanych akcji (`$config['live_action_badges']`: GOL/SAM/ŻÓŁTA/CZERW/▲/▼, z licznikiem ×N) + kropka stanu komunikatu na nakładce: żółta = czeka w kolejce, zielona (pulsująca) = właśnie wyświetlany, brak = już zniknął
- API: symulacja harmonogramu wyświetlania popupów po stronie serwera (`queued`/`showing`/`done` per zdarzenie w `state` — nakładka nie ma dostępu zapisu, więc stan liczony z `iClock` + czasy animacji nakładki) + `iScorebarPos` w stanie + komenda POST `scorebar_pos`
- Nakładka: plansza „Sędziowie" (treść = opis SKRÓCONY strony „Mecz", renderowana tylko gdy niepusta), statystyki na planszy podsumowania (gole z uwzgl. samobójów, żółte/czerwone kartki, zmiany — per drużyna), pasek wyniku przełączany lewy/prawy górny róg (przycisk w panelu, klasa `.obsScorebar--right`)
- Migracja `database/migrations/2026-07-31-live-extras.php` (idempotentna): kolumna `live_state.iScorebarPos` + plansza „sedziowie" (iPosition=9) — **uruchom na serwerze po wdrożeniu**
- Testy Playwright (9 asercji E2E): wiersze, badge, pełny cykl kropki queued→showing→zniknięcie, przełącznik paska, plansza sędziów, statystyki — wszystko zielone, zero błędów JS

## 2026-07-31 — Transmisja live (TKS): nakładki OBS, krok 3 — nakładka (KOMPLET sterowania live)
- `page-live-overlay.php` — standalone nakładka OBS (Browser Source 1920×1080, `background: transparent`): pasek wyniku+zegar (skróty z `sDesc` drużyny, herby `iType=2`), plansze: dzień meczowy (treść z `match_page`), składy obu drużyn (`sNumber`/`sSquad` z importera + sztab z bloku `.teamStaff`), podsumowanie (oś zdarzeń per drużyna), sponsorzy/realizacja (`live_sponsors_page`/`live_production_page`, 0 = plansza wyłączona), plakat meczowy
- Własny lekki arkusz `css/live-overlay.css` (źródło `_source/live-overlay.scss`, 8 KB zamiast 120 KB motywu) — animacje tylko `transform`/`opacity` (GPU), broadcast look na tokenach `_brand`
- `js/page-live-overlay.js` (vanilla, bez jQuery): jedno żądanie stanu co 1 s (`state?since=ID`), zegar tyka lokalnie między odpowiedziami (płynny przy lagu), popupy zdarzeń w kolejce (jeden naraz, auto-ukrycie 8 s), po odświeżeniu źródła w OBS historia NIE jest odtwarzana (kursor od bieżącego ID)
- API: zdjęcie zawodnika w payloadzie zdarzeń (popup pokazuje fotkę albo herb; bez obu — czysty layout)
- Testy Playwright (przezroczystość, zegar/wynik, kolejka popupów, przełączanie plansz, podsumowanie) — wyłapały m.in. rzutowanie kluczy `'2'`→int w PHP, przez które nie renderował się duży wynik

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
