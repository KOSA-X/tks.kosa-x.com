/*
 * TRANSMISJA LIVE — TELEBIM (page-telebim.php).
 * Vanilla JS: jedno żądanie stanu co 1 s (GET state&since=ID), zegar tyka
 * lokalnie, plansze przełączane klasą (te same live_boards co nakładka OBS).
 *
 * Warstwa WIDEO (różnica vs nakładka):
 *  - cieszynki po golu: klip mp4/webm zawodnika (cfg.clips, preload na
 *    starcie) odtwarzany na pełnym ekranie z podpisem; brak klipu / błąd
 *    odtwarzania → zwykły popup zdarzenia (fallback);
 *  - powtórki z OBS: wzrost licznika state.replay → odtworzenie klipu
 *    z lokalnego serwera replay buffera (cfg.replayUrl); powtórka ma
 *    priorytet — przerywa aktualnie grające wideo.
 * Autoplay: próba z dźwiękiem, przy blokadzie przeglądarki retry mute
 * (kiosk: chrome --autoplay-policy=no-user-gesture-required).
 */
(function () {
    'use strict';

    var cfg = window.telebimConfig;
    if (!cfg) {
        return;
    }

    var EVENT_SHOW_MS = 8000;   // czas wyświetlania popupu zdarzenia
    var EVENT_ANIM_MS = 600;    // czas animacji wyjścia (>= transition w CSS)
    var VIDEO_MAX_MS  = 45000;  // twardy limit klipu (zabezpieczenie przed zwisem)
    var VIDEO_LOAD_MS = 8000;   // limit na start odtwarzania (błędny plik / brak serwera powtórek)

    var sinceId = null;         // null = pierwszy odczyt (bez odtwarzania historii)
    var replaySeen = null;      // null = pierwszy odczyt licznika powtórek
    var timerState = null;      // { seconds, half, running, at }
    var queue = [];
    var busy = false;
    var summaryFetchedAt = 0;

    // ------------------------------------------------------------
    // POMOCNICZE
    // ------------------------------------------------------------
    function all(selector) {
        return Array.prototype.slice.call(document.querySelectorAll(selector));
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== '') {
            node.textContent = text;
        }
        return node;
    }

    function formatTime(totalSeconds, half) {
        // limity meczowe: 45:00 / 90:00, powyżej — oznaczenie doliczonego czasu
        var capped = totalSeconds;
        var extra = false;
        if (half === 1 && capped > 2700) { capped = 2700; extra = true; }
        if (half === 2 && capped > 5400) { capped = 5400; extra = true; }
        var minutes = Math.floor(capped / 60);
        var seconds = capped % 60;
        return {
            text: (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds,
            extra: extra
        };
    }

    // ------------------------------------------------------------
    // ZEGAR — lokalny tick między odpowiedziami serwera
    // ------------------------------------------------------------
    function tick() {
        if (!timerState) {
            return;
        }
        var seconds = timerState.seconds;
        if (timerState.running) {
            seconds += Math.floor((performance.now() - timerState.at) / 1000);
        }
        var time = formatTime(seconds, timerState.half);
        all('.js-clock').forEach(function (node) { node.textContent = time.text; });
        all('.js-half').forEach(function (node) { node.textContent = timerState.half + ' ' + cfg.labels.halfShort; });
        all('.js-clock-box').forEach(function (node) { node.classList.toggle('is-extra', time.extra); });
    }

    // ------------------------------------------------------------
    // WARSTWA WIDEO — cieszynki + powtórki
    // ------------------------------------------------------------
    var videoLayer   = document.getElementById('tb-video');
    var videoPlayer  = document.getElementById('tb-video-player');
    var videoCaption = document.getElementById('tb-video-caption');
    var videoDone    = null;   // callback aktualnego odtwarzania
    var videoTimers  = [];

    // preload cieszynek obu drużyn na starcie meczu (brief, Etap 4) —
    // ukryte elementy <video preload="auto"> buforują pliki z wyprzedzeniem
    function preloadClips() {
        Object.keys(cfg.clips).forEach(function (playerId) {
            var buffer = document.createElement('video');
            buffer.preload = 'auto';
            buffer.muted = true;
            buffer.src = cfg.clips[playerId];
            buffer.setAttribute('aria-hidden', 'true');
            buffer.style.display = 'none';
            document.body.appendChild(buffer);
        });
    }

    function clearVideoTimers() {
        videoTimers.forEach(clearTimeout);
        videoTimers = [];
    }

    function stopVideo(runDone, failed) {
        clearVideoTimers();
        videoPlayer.pause();
        videoPlayer.removeAttribute('src');
        videoPlayer.load();
        videoLayer.hidden = true;
        videoLayer.classList.remove('is-visible');
        var done = videoDone;
        videoDone = null;
        if (runDone && done) {
            done(!!failed);
        }
    }

    /**
     * Odtwarza klip na pełnym ekranie. caption = {label, name} albo null.
     * done(failed) woła się dokładnie raz — po końcu (failed=false) albo
     * błędzie/limicie startu (failed=true, cieszynka robi fallback na popup).
     */
    function playVideo(src, caption, done) {
        // powtórka może przerwać grającą cieszynkę — stary callback jest
        // PORZUCANY (nie wołany), kontynuację kolejki przejmuje nowy done,
        // który ma identyczne działanie (release) — kolejka nie stanie
        if (videoDone) {
            stopVideo(false);
        }

        var finished = false;
        videoDone = function (failed) {
            finished = true;
            done(failed);
        };

        var label = document.getElementById('tb-video-label');
        var name  = document.getElementById('tb-video-name');
        if (caption) {
            label.textContent = caption.label || '';
            name.textContent = caption.name || '';
            videoCaption.hidden = false;
        } else {
            videoCaption.hidden = true;
        }

        videoLayer.hidden = false;
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                videoLayer.classList.add('is-visible');
            });
        });

        videoPlayer.muted = false;
        videoPlayer.src = src;

        videoPlayer.onended = function () { stopVideo(true, false); };
        videoPlayer.onerror = function () { stopVideo(true, true); };

        // limit na start: błędny plik / brak lokalnego serwera powtórek
        videoTimers.push(setTimeout(function () {
            if (!finished && videoPlayer.readyState < 3) {
                stopVideo(true, true);
            }
        }, VIDEO_LOAD_MS));

        // twardy limit długości klipu (zagrał — nie traktuj jako błąd)
        videoTimers.push(setTimeout(function () {
            if (!finished) {
                stopVideo(true, false);
            }
        }, VIDEO_MAX_MS));

        var attempt = videoPlayer.play();
        if (attempt && attempt.catch) {
            attempt.catch(function () {
                // polityka autoplay — retry bez dźwięku, potem poddaj się
                videoPlayer.muted = true;
                var muted = videoPlayer.play();
                if (muted && muted.catch) {
                    muted.catch(function () { stopVideo(true, true); });
                }
            });
        }
    }

    // ------------------------------------------------------------
    // POPUPY ZDARZEŃ — kolejka, jeden naraz; gol z klipem = cieszynka
    // ------------------------------------------------------------
    function iconFor(action) {
        return el('span', 'tbEvent__icon tbEvent__icon--' + action);
    }

    function showPopup(ev, done) {
        var container = document.getElementById('tb-events');
        var team = cfg.teams[String(ev.team)] || { name: '', logo: '' };

        var popup = el('div', 'tbEvent tbEvent--' + ev.action);

        if (ev.player.photo || team.logo) {
            var media = el('div', 'tbEvent__media');
            var image = el('img', ev.player.photo ? '' : 'is-logo');
            image.src = cfg.filesUrl + (ev.player.photo || team.logo);
            media.appendChild(image);
            popup.appendChild(media);
        }

        var body = el('div', 'tbEvent__body');
        var label = el('div', 'tbEvent__label');
        label.appendChild(iconFor(ev.action));
        label.appendChild(el('span', '', cfg.actions[ev.action] || ev.action));
        if (ev.minute !== '') {
            label.appendChild(el('span', 'tbEvent__minute', ev.minute + "'"));
        }
        body.appendChild(label);

        var name = el('div', 'tbEvent__name');
        if (ev.player.id > 0) {
            if (ev.player.number !== '') {
                name.appendChild(el('span', 'number', ev.player.number));
            }
            name.appendChild(el('span', '', ev.player.name));
        } else {
            name.appendChild(el('span', '', team.name));
        }
        body.appendChild(name);
        body.appendChild(el('div', 'tbEvent__team', team.name));
        popup.appendChild(body);

        container.appendChild(popup);

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                popup.classList.add('is-visible');
            });
        });

        setTimeout(function () {
            popup.classList.remove('is-visible');
            setTimeout(function () {
                if (popup.parentNode) {
                    popup.parentNode.removeChild(popup);
                }
                done();
            }, EVENT_ANIM_MS);
        }, EVENT_SHOW_MS);
    }

    function showEvent(ev, done) {
        // cieszynka: gol z przypisanym klipem → wideo; fallback = popup.
        // Klip z payloadu eventu (player.video) albo z preloadowanej mapy.
        var clip = '';
        if (ev.action === 'goal' && ev.player.id > 0) {
            // mapa z preloadu (klipy znane na starcie) albo payload eventu
            // (klip wgrany już w trakcie meczu)
            clip = cfg.clips[String(ev.player.id)] || (ev.player.video ? cfg.clipsUrl + ev.player.video : '');
        }
        if (clip !== '') {
            playVideo(clip, {
                label: (cfg.actions.goal || '') + (ev.minute !== '' ? ' • ' + ev.minute + "'" : ''),
                name: (ev.player.number !== '' ? ev.player.number + ' ' : '') + ev.player.name
            }, function (failed) {
                // klip się nie odtworzył (uszkodzony plik itp.) — zdarzenie
                // nie może przepaść: fallback na zwykły popup
                if (failed) {
                    showPopup(ev, done);
                } else {
                    done();
                }
            });
            return;
        }
        showPopup(ev, done);
    }

    /** Zwolnienie ekranu — wspólna kontynuacja popupów, cieszynek i powtórek. */
    function release() {
        busy = false;
        processQueue();
    }

    function processQueue() {
        // videoDone = gra wideo (cieszynka/powtórka) — kolejka czeka
        if (busy || videoDone || !queue.length) {
            return;
        }
        busy = true;
        showEvent(queue.shift(), release);
    }

    // ------------------------------------------------------------
    // POWTÓRKI Z OBS — wzrost licznika state.replay
    // ------------------------------------------------------------
    function playReplay(count) {
        if (!cfg.replayUrl) {
            return;
        }
        // ?t=licznik — omija cache przeglądarki, serwer lokalny zwraca najnowszy plik;
        // powtórka gra od razu (playVideo przerywa ewentualną cieszynkę)
        playVideo(cfg.replayUrl + (cfg.replayUrl.indexOf('?') === -1 ? '?' : '&') + 't=' + count,
            { label: cfg.labels.replay, name: '' }, release);
    }

    // ------------------------------------------------------------
    // PODSUMOWANIE — statystyki + pełna lista zdarzeń per drużyna
    // ------------------------------------------------------------
    function renderStats(events) {
        var container = document.getElementById('tb-stats');
        if (!container) {
            return;
        }
        var stats = {};
        stats[cfg.team1] = { goals: 0, yellow: 0, red: 0, subs: 0 };
        stats[cfg.team2] = { goals: 0, yellow: 0, red: 0, subs: 0 };

        events.forEach(function (ev) {
            var own = stats[ev.team];
            var other = stats[ev.team === cfg.team1 ? cfg.team2 : cfg.team1];
            if (!own) {
                return;
            }
            if (ev.action === 'goal') { own.goals++; }
            if (ev.action === 'own_goal') { other.goals++; } // samobój = gol dla przeciwnika
            if (ev.action === 'yellow_card') { own.yellow++; }
            if (ev.action === 'red_card') { own.red++; }
            if (ev.action === 'in') { own.subs++; }
        });

        container.textContent = '';
        [['goals', cfg.labels.stats.goals], ['yellow', cfg.labels.stats.yellow],
         ['red', cfg.labels.stats.red], ['subs', cfg.labels.stats.subs]].forEach(function (pair) {
            var row = el('div', 'tbStats__row');
            row.appendChild(el('span', 'tbStats__value', String(stats[cfg.team1][pair[0]])));
            row.appendChild(el('span', 'tbStats__label', pair[1]));
            row.appendChild(el('span', 'tbStats__value', String(stats[cfg.team2][pair[0]])));
            container.appendChild(row);
        });
    }

    function renderSummary(events) {
        // oś zdarzeń pokazuje tylko gole i kartki (zmiany ukryte — są w statystykach)
        var SUMMARY_ACTIONS = ['goal', 'own_goal', 'yellow_card', 'red_card'];
        [cfg.team1, cfg.team2].forEach(function (teamId, index) {
            var list = document.getElementById('tb-summary-' + (index + 1));
            if (!list) {
                return;
            }
            list.textContent = '';
            var teamEvents = events.filter(function (ev) {
                return ev.team === teamId && SUMMARY_ACTIONS.indexOf(ev.action) !== -1;
            });
            if (!teamEvents.length) {
                list.appendChild(el('li', 'tbSummary__empty', cfg.labels.noEvents));
                return;
            }
            teamEvents.forEach(function (ev) {
                var item = el('li');
                item.appendChild(el('span', 'minute', ev.minute !== '' ? ev.minute + "'" : ''));
                item.appendChild(iconFor(ev.action));
                item.appendChild(el('span', '', ev.player.id > 0 ? ev.player.name : (cfg.actions[ev.action] || ev.action)));
                list.appendChild(item);
            });
        });
    }

    function refreshSummary() {
        // odświeżaj najwyżej co 10 s, tylko gdy plansza widoczna
        if (performance.now() - summaryFetchedAt < 10000) {
            return;
        }
        summaryFetchedAt = performance.now();
        fetch(cfg.api + '?action=events')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.ok) {
                    renderStats(data.events);
                    renderSummary(data.events);
                }
            })
            .catch(function () {});
    }

    // ------------------------------------------------------------
    // STAN — jedno żądanie co 1 s
    // ------------------------------------------------------------
    function applyState(data) {
        if (!data.ok) {
            return;
        }

        timerState = {
            seconds: data.timer.seconds,
            half: data.timer.half,
            running: data.timer.running,
            at: performance.now()
        };
        tick();

        all('.js-score1').forEach(function (node) { node.textContent = data.score[0]; });
        all('.js-score2').forEach(function (node) { node.textContent = data.score[1]; });

        Object.keys(data.boards).forEach(function (name) {
            var board = document.querySelector('[data-board="' + name + '"]');
            if (board) {
                board.classList.toggle('is-visible', data.boards[name] === 1);
            }
        });

        if (data.boards.podsumowanie === 1) {
            refreshSummary();
        }

        // powtórka: pierwszy odczyt tylko zapamiętuje licznik (odświeżenie
        // strony nie odtwarza starej powtórki); wzrost licznika = graj
        if (replaySeen === null) {
            replaySeen = data.replay;
        } else if (data.replay > replaySeen) {
            replaySeen = data.replay;
            playReplay(data.replay);
        }

        // zdarzenia: pierwszy odczyt tylko ustawia kursor (bez historii),
        // kolejne — do kolejki (gol z klipem = cieszynka wideo)
        if (sinceId === null) {
            sinceId = data.last_event_id;
        } else {
            data.events.forEach(function (ev) { queue.push(ev); });
            sinceId = data.last_event_id;
            processQueue();
        }
    }

    function poll() {
        fetch(cfg.api + '?action=state' + (sinceId !== null ? '&since=' + sinceId : ''))
            .then(function (response) { return response.json(); })
            .then(applyState)
            .catch(function () {}); // chwilowy brak sieci — zostaje ostatni stan
    }

    preloadClips();
    poll();
    setInterval(poll, 1000);
    setInterval(tick, 250);

})();
