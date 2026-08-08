# Powtórki z OBS na telebimie (Etap 5) + kiosk mode (Etap 6)

## Jak to działa

1. OBS (na komputerze przy boisku) nagrywa **Bufor powtórek** do folderu.
2. `replay-server.py` (ten sam komputer) serwuje **najnowszy** klip z tego
   folderu pod `http://localhost:8766/replay.mp4`.
3. Operator klika w panelu meczowym **„▶ Powtórka na telebimie"** →
   API inkrementuje `live_state.iReplayCount`.
4. Telebim (otwarty w kiosku na drugim ekranie tego samego komputera)
   widzi wzrost licznika w stanie i odtwarza klip z localhosta na pełnym
   ekranie. Mixed-content nie blokuje — Chrome traktuje `http://localhost`
   jako zaufane źródło nawet na stronie HTTPS.

## Konfiguracja OBS (raz)

- Ustawienia → Wyjście → **Bufor powtórek**: włącz, długość np. 30 s.
- Ustawienia → Wyjście → Nagrywanie → **Format**: `mp4` lub `fMP4`
  (domyślny `mkv` też zagra w Chrome, ale mp4 jest pewniejszy).
- Ustawienia → Skróty klawiszowe → **Zapisz powtórkę** — przypisz klawisz.

## Uruchomienie serwera (przy każdej transmisji)

```bat
python replay-server.py "C:\Users\obs\Videos"
:: inny port:  python replay-server.py "C:\Users\obs\Videos" --port 9000
```

Wymagany tylko Python 3 (stdlib, zero instalacji pakietów). Serwer wiąże
się z 127.0.0.1 — nic nie wystaje na zewnątrz.

Inny port/adres → nadpisz `$config['live_replay_url']`
(np. w `database/config.secrets.php`).

## Obieg meczowy

1. Gol / sytuacja → operator OBS wciska skrót **Zapisz powtórkę**.
2. W panelu meczowym: **„▶ Powtórka na telebimie"**.
3. Telebim gra najnowszy klip; po końcu wraca do plansz.

Docelowe rozszerzenie (poza zakresem): automatyczny trigger przez
`obs-websocket` (event `ReplayBufferSaved`) zamiast ręcznego klikania.

## Kiosk mode telebimu (Etap 6 — konfiguracja, nie kod)

Skrót/autostart na komputerze przy telebimie (telebim = rozszerzony ekran):

```bat
chrome.exe --kiosk --app=https://tks.kosa-x.com/telebim/ ^
  --autoplay-policy=no-user-gesture-required ^
  --noerrdialogs --disable-infobars --window-position=1920,0
```

- `--autoplay-policy=no-user-gesture-required` — cieszynki/powtórki
  startują z dźwiękiem bez kliknięcia (bez tej flagi JS i tak spróbuje
  zagrać wyciszone).
- `--window-position=1920,0` — otwarcie na drugim ekranie (podaj offset
  swojego głównego monitora).
- Wyjście z kiosku: `Alt+F4`.

## Rozmiar okna / rozdzielczość

Layout telebimu jest ELASTYCZNY: skala liczy się z `min(szerokość/48,
wysokość/27)`, więc dopasowuje się do KAŻDEGO okna — także przyciętego
paskiem systemowym macOS (żadnego scrollbara; przy pełnym 16:9 wygląd
jest 1:1 taki sam jak projekt).

Rekomendacja: okno/ekran **1280×720**. Fizyczna matryca LED ma mniej
pikseli niż Full HD, więc 1080p nie dodaje detalu, a kosztuje komputer
więcej (kompozycja + dekodowanie wideo). Zdjęcia plansz lecą z miniatur
500 px — w 720p wyglądają identycznie. Przełączenie na 1920×1080 nie
wymaga żadnych zmian w kodzie.
