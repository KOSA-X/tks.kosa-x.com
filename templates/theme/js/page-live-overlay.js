/*
 * TRANSMISJA LIVE — nakładka OBS (page-live-overlay.php).
 * Vanilla JS (bez zależności): jedno żądanie stanu co 1 s (GET state&since=ID),
 * zegar tyka LOKALNIE między odpowiedziami (płynny niezależnie od sieci),
 * popupy zdarzeń w kolejce (jeden naraz), plansze przełączane klasą.
 */
(function () {
    'use strict';

    var cfg = window.liveOverlayConfig;
    if (!cfg) {
        return;
    }

    var EVENT_SHOW_MS = 8000;  // czas wyświetlania popupu zdarzenia
    var EVENT_ANIM_MS = 600;   // czas animacji wyjścia (musi >= transition w CSS)

    var sinceId = null;        // null = pierwszy odczyt (bez odtwarzania historii)
    var timerState = null;     // { seconds, half, running, at }
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
    // POPUPY ZDARZEŃ — kolejka, jeden naraz
    // ------------------------------------------------------------
    function iconFor(action) {
        return el('span', 'obsEvent__icon obsEvent__icon--' + action);
    }

    function showEvent(ev, done) {
        var container = document.getElementById('obs-events');
        var team = cfg.teams[String(ev.team)] || { name: '', logo: '' };

        var popup = el('div', 'obsEvent obsEvent--' + ev.action);

        // blok zdjęcia tylko, gdy jest co pokazać (fotka zawodnika albo herb)
        if (ev.player.photo || team.logo) {
            var media = el('div', 'obsEvent__media');
            var image = el('img', ev.player.photo ? '' : 'is-logo');
            image.src = cfg.filesUrl + (ev.player.photo || team.logo);
            media.appendChild(image);
            popup.appendChild(media);
        }

        var body = el('div', 'obsEvent__body');
        var label = el('div', 'obsEvent__label');
        label.appendChild(iconFor(ev.action));
        label.appendChild(el('span', '', cfg.actions[ev.action] || ev.action));
        if (ev.minute !== '') {
            label.appendChild(el('span', 'obsEvent__minute', ev.minute + "'"));
        }
        body.appendChild(label);

        var name = el('div', 'obsEvent__name');
        if (ev.player.id > 0) {
            if (ev.player.number !== '') {
                name.appendChild(el('span', 'number', ev.player.number));
            }
            name.appendChild(el('span', '', ev.player.name));
        } else {
            name.appendChild(el('span', '', team.name));
        }
        body.appendChild(name);
        body.appendChild(el('div', 'obsEvent__team', team.name));
        popup.appendChild(body);

        container.appendChild(popup);

        // wjazd (dwie klatki, żeby transition na pewno zagrała)
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                popup.classList.add('is-visible');
            });
        });

        // auto-ukrycie + sprzątnięcie z DOM
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

    function processQueue() {
        if (busy || !queue.length) {
            return;
        }
        busy = true;
        showEvent(queue.shift(), function () {
            busy = false;
            processQueue();
        });
    }

    // ------------------------------------------------------------
    // PODSUMOWANIE — pełna lista zdarzeń per drużyna
    // (bez tabelki statystyk — wszystko widać na osi zdarzeń)
    // ------------------------------------------------------------
    function renderSummary(events) {
        // oś zdarzeń pokazuje tylko gole i kartki (zmiany ukryte — są w statystykach)
        var SUMMARY_ACTIONS = ['goal', 'own_goal', 'yellow_card', 'red_card'];
        [cfg.team1, cfg.team2].forEach(function (teamId, index) {
            var list = document.getElementById('obs-summary-' + (index + 1));
            if (!list) {
                return;
            }
            list.textContent = '';
            var teamEvents = events.filter(function (ev) {
                return ev.team === teamId && SUMMARY_ACTIONS.indexOf(ev.action) !== -1;
            });
            if (!teamEvents.length) {
                var empty = el('li', 'obsSummary__empty', cfg.labels.noEvents);
                list.appendChild(empty);
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

    // dolny pasek „aktualny wynik": strzelcy bramek per drużyna
    function renderGoals(events) {
        [cfg.team1, cfg.team2].forEach(function (teamId, index) {
            var list = document.getElementById('obs-goals-' + (index + 1));
            if (!list) {
                return;
            }
            list.textContent = '';
            var otherId = teamId === cfg.team1 ? cfg.team2 : cfg.team1;
            events.forEach(function (ev) {
                // gol drużyny albo samobój przeciwnika (bramka dla tej drużyny)
                var scored = (ev.action === 'goal' && ev.team === teamId)
                    || (ev.action === 'own_goal' && ev.team === otherId);
                if (!scored) {
                    return;
                }
                var item = el('li');
                item.appendChild(iconFor('goal'));
                item.appendChild(el('span', 'minute', ev.minute !== '' ? ev.minute + "'" : ''));
                item.appendChild(el('span', '',
                    (ev.player.id > 0 ? ev.player.name : (cfg.teams[String(ev.team)] || {}).name || '')
                    + (ev.action === 'own_goal' ? ' (sam.)' : '')));
                list.appendChild(item);
            });
        });
    }

    function refreshSummary() {
        // odświeżaj najwyżej co 10 s, tylko gdy któraś plansza zdarzeń widoczna
        if (performance.now() - summaryFetchedAt < 10000) {
            return;
        }
        summaryFetchedAt = performance.now();
        fetch(cfg.api + '?action=events')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.ok) {
                    renderSummary(data.events);
                    renderGoals(data.events);
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

        // kropki kartek pod nazwami drużyn (cards: [gospodarz, gość])
        if (data.cards) {
            [1, 2].forEach(function (side) {
                var box = document.querySelector('.js-cards' + side);
                if (!box) {
                    return;
                }
                var counts = data.cards[side - 1] || { yellow: 0, red: 0 };
                box.textContent = '';
                for (var y = 0; y < counts.yellow; y++) {
                    box.appendChild(el('span', 'obsScorebar__dot obsScorebar__dot--yellow'));
                }
                for (var r = 0; r < counts.red; r++) {
                    box.appendChild(el('span', 'obsScorebar__dot obsScorebar__dot--red'));
                }
            });
        }

        var scorebar = document.querySelector('.obsScorebar');
        if (scorebar) {
            scorebar.classList.toggle('obsScorebar--right', data.scorebar === 1);
        }

        // logo realizatora ucieka na przeciwny róg niż pasek wyniku
        var producerLogo = document.getElementById('obs-producer-logo');
        if (producerLogo) {
            producerLogo.classList.toggle('obsProducerLogo--left', data.scorebar === 1);
        }

        // plansza może mieć kilka elementów (np. pasek wyniku + logo realizatora)
        Object.keys(data.boards).forEach(function (name) {
            all('[data-board="' + name + '"]').forEach(function (board) {
                board.classList.toggle('is-visible', data.boards[name] === 1);
            });
        });

        if (data.boards.podsumowanie === 1 || data.boards.aktualny_wynik === 1) {
            refreshSummary();
        }

        // zdarzenia: pierwszy odczyt tylko ustawia kursor (nie odtwarzamy
        // historii po odświeżeniu źródła w OBS); kolejne — do kolejki popupów
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

    poll();
    setInterval(poll, 1000);
    setInterval(tick, 250);

})();
