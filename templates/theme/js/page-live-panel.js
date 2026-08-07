/*
 * TRANSMISJA LIVE — panel operatora (page-live-panel.php).
 * Komunikacja z plugins/live/api.php: odczyt stanu co 1 s (GET state),
 * komendy POST z tokenem CSRF. Kafelek zawodnika → arkusz akcji → event_add.
 */
(function ($) {
    'use strict';

    var cfg = window.livePanelConfig;
    if (!cfg || !$('#livePanel').length) {
        return;
    }

    var sheetPlayer = null; // { id, team, name, number }
    var lastTimerSeconds = 0;

    // .mainBody ma transform (smooth scroll) i łamie position:fixed potomków —
    // arkusz i toast muszą żyć bezpośrednio w <body>
    $('#lp-sheet, #lp-toast').appendTo('body');

    // ------------------------------------------------------------
    // POMOCNICZE
    // ------------------------------------------------------------
    function toast(message, isError) {
        var $toast = $('#lp-toast');
        $toast.text(message)
            .toggleClass('is-error', !!isError)
            .prop('hidden', false)
            .addClass('is-visible');
        clearTimeout(toast.timer);
        toast.timer = setTimeout(function () {
            $toast.removeClass('is-visible');
        }, 2500);
    }

    function api(action, data, onDone) {
        var payload = $.extend({ action: action, sTokenCsrf: cfg.csrf }, data || {});
        $.post(cfg.api, payload, null, 'json')
            .done(function (response) {
                if (!response.ok) {
                    toast(response.error || 'Błąd', true);
                    return;
                }
                if (onDone) {
                    onDone(response);
                }
            })
            .fail(function (xhr) {
                var response = xhr.responseJSON || {};
                toast(response.error || 'Błąd połączenia', true);
            });
    }

    function formatTime(totalSeconds, half) {
        // limity jak w meczu: 45:00 / 90:00 + oznaczenie czasu doliczonego
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

    function currentMinute() {
        if (lastTimerSeconds <= 0) {
            return '';
        }
        return String(Math.max(1, Math.ceil(lastTimerSeconds / 60)));
    }

    // ------------------------------------------------------------
    // ZNACZNIKI ZAWODNIKÓW — badge akcji + kropka statusu komunikatu
    // (statusy wyświetlania liczy serwer — symulacja kolejki nakładki)
    // ------------------------------------------------------------
    var BADGE_ORDER = ['goal', 'own_goal', 'yellow_card', 'red_card', 'in', 'out'];

    function updatePlayerMarkers(events) {
        var perPlayer = {};
        $.each(events, function (i, ev) {
            if (ev.player.id <= 0) {
                return;
            }
            var entry = perPlayer[ev.player.id] = perPlayer[ev.player.id] || { counts: {}, showing: false, queued: false };
            entry.counts[ev.action] = (entry.counts[ev.action] || 0) + 1;
            if (ev.display === 'showing') { entry.showing = true; }
            if (ev.display === 'queued') { entry.queued = true; }
        });

        $('.livePanel__player').each(function () {
            var $player = $(this);
            var entry = perPlayer[$player.data('player')];
            var $badges = $player.find('.livePanel__playerBadges').empty();
            var $dot = $player.find('.livePanel__playerDot');

            if (!entry) {
                $dot.attr('data-state', '');
                return;
            }
            $.each(BADGE_ORDER, function (i, action) {
                var count = entry.counts[action];
                if (!count) {
                    return;
                }
                $badges.append(
                    $('<span class="lp-badge lp-badge--' + action + '">')
                        .text((cfg.badges[action] || action) + (count > 1 ? ' ×' + count : ''))
                );
            });
            $dot.attr('data-state', entry.showing ? 'showing' : (entry.queued ? 'queued' : ''));
        });
    }

    // ------------------------------------------------------------
    // ODCZYT STANU (co 1 s)
    // ------------------------------------------------------------
    function refreshState() {
        $.getJSON(cfg.api, { action: 'state' })
            .done(function (state) {
                if (!state.ok) {
                    return;
                }

                lastTimerSeconds = state.timer.seconds;
                var time = formatTime(state.timer.seconds, state.timer.half);
                $('#lp-timer').text(time.text).toggleClass('is-extra', time.extra);
                $('#lp-half').text(state.timer.half + ' ' + cfg.labels.halfShort);
                $('#lp-pause').prop('hidden', !state.timer.running);
                $('#lp-resume').prop('hidden', state.timer.running);

                $('#lp-score1').text(state.score[0]);
                $('#lp-score2').text(state.score[1]);

                $.each(state.boards, function (name, visible) {
                    $('.livePanel__board[data-board="' + name + '"]').toggleClass('is-active', visible === 1);
                });

                $('#lp-scorebar-pos').attr('data-pos', state.scorebar);
                $('#lp-scorebar-label').text(state.scorebar === 1 ? cfg.labels.posRight : cfg.labels.posLeft);

                updatePlayerMarkers(state.events);
            });
    }

    // ------------------------------------------------------------
    // LISTA ZDARZEŃ + stan „na boisku" z historii zmian (in/out)
    // ------------------------------------------------------------
    function renderEvents(events) {
        var $list = $('#lp-events').empty();
        if (!events.length) {
            $list.append($('<p class="livePanel__hint">').text(cfg.labels.noEvents));
            return;
        }

        $.each(events, function (i, ev) {
            var actionLabel = cfg.actions[ev.action] || ev.action;
            var teamName = cfg.teamNames[String(ev.team)] || '';

            var $row = $('<div class="livePanel__event" data-event-action="' + ev.action + '">');
            var $minute = $('<input type="text" class="form-control livePanel__eventMinute" inputmode="numeric" maxlength="3">').val(ev.minute);
            $row.append($minute);
            $row.append($('<span class="livePanel__eventAction">').html(actionLabel));

            // zawodnik edytowalny — select z kadry drużyny zdarzenia
            // (pomyłkowo wybrany zawodnik do poprawki bez kasowania wpisu)
            var $player = $('<select class="form-control livePanel__eventPlayerSelect">');
            $player.append($('<option value="0">').text('—'));
            $.each(cfg.players[String(ev.team)] || [], function (j, player) {
                $player.append(
                    $('<option>').val(player.id)
                        .text((player.number !== '' ? player.number + '. ' : '') + player.name)
                );
            });
            $player.val(String(ev.player.id));
            if ($player.val() === null) {
                $player.val('0'); // zawodnik spoza aktualnej kadry (np. skasowany)
            }
            $row.append($player);
            $row.append($('<span class="livePanel__eventTeam">').text(teamName));

            var $save = $('<button type="button" class="button livePanel__eventBtn">').text(cfg.labels.save);
            $save.on('click', function () {
                api('event_update', { id: ev.id, iPlayer: $player.val(), sMinute: $minute.val() }, function () {
                    loadEvents(true);
                    refreshState();
                });
            });
            var $remove = $('<button type="button" class="button livePanel__eventBtn livePanel__danger">').text(cfg.labels.delete);
            $remove.on('click', function () {
                if (!window.confirm(cfg.labels.confirmDelete)) {
                    return;
                }
                api('event_delete', { id: ev.id }, function () {
                    loadEvents(true);
                });
            });
            $row.append($save).append($remove);
            $list.append($row);
        });
    }

    // przeniesienie kafelka zawodnika do innej grupy składu (bez zapisu do
    // bazy — sSquad zostaje protokołowy; to tylko widok operatora)
    function movePlayerTile(playerId, targetSquad) {
        var $tile = $('.livePanel__player[data-player="' + playerId + '"]');
        if (!$tile.length) {
            return;
        }
        var $team = $tile.closest('.livePanel__team');
        var $group = $team.find('.livePanel__group[data-squad="' + targetSquad + '"]');
        if (!$group.length || $group.find($tile).length) {
            return; // brak celu albo kafelek już w tej grupie
        }
        $group.prop('hidden', false).find('.livePanel__players').append($tile);
        $tile.toggleClass('is-bench', targetSquad === '2');
        // grupy opróżnione przez przenosiny chowamy
        $team.find('.livePanel__group').each(function () {
            $(this).prop('hidden', $(this).find('.livePanel__player').length === 0);
        });
    }

    function applyOnPitch(events) {
        // ostatnie zdarzenie in/out zawodnika decyduje o oznaczeniu kafelka
        // ORAZ o grupie: wszedł → „Wyjściowa 11", zszedł → „Rezerwa"
        $('.livePanel__player').removeClass('is-on is-out');
        var lastState = {};
        $.each(events, function (i, ev) {
            if (ev.action === 'in' || ev.action === 'out') {
                lastState[ev.player.id] = ev.action;
            }
        });
        $.each(lastState, function (playerId, action) {
            $('.livePanel__player[data-player="' + playerId + '"]')
                .addClass(action === 'in' ? 'is-on' : 'is-out');
            movePlayerTile(playerId, action === 'in' ? '1' : '2');
        });
    }

    function loadEvents(force) {
        // nie podmieniaj listy, gdy operator właśnie ją edytuje (fokus w środku) —
        // cykliczne odświeżanie kasowałoby wpisywaną wartość
        if (!force && $('#lp-events').find(':focus').length) {
            return;
        }
        $.getJSON(cfg.api, { action: 'events' })
            .done(function (response) {
                if (!response.ok) {
                    return;
                }
                if (!force && $('#lp-events').find(':focus').length) {
                    return;
                }
                renderEvents(response.events);
                applyOnPitch(response.events);
            });
    }

    // ------------------------------------------------------------
    // WYNIK
    // ------------------------------------------------------------
    $('[data-score-team]').on('click', function () {
        api('score_adjust', {
            team: $(this).data('score-team'),
            delta: $(this).data('delta')
        }, function (response) {
            $('#lp-score1').text(response.score[0]);
            $('#lp-score2').text(response.score[1]);
        });
    });

    // ------------------------------------------------------------
    // ZEGAR
    // ------------------------------------------------------------
    var timerCommands = {
        start1: { action: 'timer_start', data: { half: 1 } },
        start2: { action: 'timer_start', data: { half: 2 } },
        pause:  { action: 'timer_pause' },
        resume: { action: 'timer_resume' },
        plus:   { action: 'timer_adjust', data: { delta: 1 } },
        minus:  { action: 'timer_adjust', data: { delta: -1 } },
        reset:  { action: 'timer_reset' }
    };
    $('[data-timer]').on('click', function () {
        var command = timerCommands[$(this).data('timer')];
        if (command) {
            api(command.action, command.data || {}, refreshState);
        }
    });

    // ------------------------------------------------------------
    // PLANSZE — przycisk pokazuje stan, klik przełącza
    // ------------------------------------------------------------
    $('.livePanel__board').on('click', function () {
        var $board = $(this);
        api('board_toggle', {
            sName: $board.data('board'),
            iVisible: $board.hasClass('is-active') ? 0 : 1
        }, function (response) {
            $.each(response.boards, function (name, visible) {
                $('.livePanel__board[data-board="' + name + '"]').toggleClass('is-active', visible === 1);
            });
        });
    });

    // ------------------------------------------------------------
    // POZYCJA PASKA WYNIKU NA NAKŁADCE
    // ------------------------------------------------------------
    $('#lp-scorebar-pos').on('click', function () {
        var next = (parseInt($(this).attr('data-pos'), 10) === 1) ? 0 : 1;
        api('scorebar_pos', { pos: next }, function (response) {
            $('#lp-scorebar-pos').attr('data-pos', response.scorebar);
            $('#lp-scorebar-label').text(response.scorebar === 1 ? cfg.labels.posRight : cfg.labels.posLeft);
        });
    });

    // ------------------------------------------------------------
    // POWTÓRKA NA TELEBIMIE (inkrementacja licznika w live_state)
    // ------------------------------------------------------------
    $('#lp-replay').on('click', function () {
        api('replay_show', {}, function () {
            toast(cfg.labels.replaySent);
        });
    });

    // ------------------------------------------------------------
    // NOWY MECZ (wybór drużyn: panel admina → Transmisja → Konfiguracja)
    // ------------------------------------------------------------
    $('#lp-new-match').on('click', function () {
        if (!window.confirm(cfg.labels.confirmNewMatch)) {
            return;
        }
        api('match_reset', {}, function () {
            window.location.reload();
        });
    });

    // ------------------------------------------------------------
    // FILTR ZAWODNIKÓW (per drużyna) — wpisujesz frazę, lista się zawęża
    // ------------------------------------------------------------
    function normalizeName(text) {
        // bez wielkości liter i polskich znaków (ł nie rozkłada się w NFD)
        return String(text).toLowerCase()
            .replace(/ł/g, 'l')
            .normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function filterPlayers($team, phrase) {
        var query = normalizeName($.trim(phrase));
        $team.find('.livePanel__player').each(function () {
            var $player = $(this);
            var haystack = normalizeName($player.data('name') + ' ' + $player.data('number'));
            $player.toggle(query === '' || haystack.indexOf(query) !== -1);
        });
        // nagłówki grup bez widocznych zawodników chowamy razem z nimi
        $team.find('.livePanel__groupLabel').each(function () {
            var $label = $(this);
            $label.toggle($label.next('.livePanel__players').find('.livePanel__player:visible').length > 0);
        });
    }

    $('.lp-player-search').on('input', function () {
        filterPlayers($(this).closest('.livePanel__team'), $(this).val());
    });

    $('.lp-player-search-clear').on('click', function () {
        var $team = $(this).closest('.livePanel__team');
        $team.find('.lp-player-search').val('');
        filterPlayers($team, '');
    });

    // ------------------------------------------------------------
    // KAFELEK ZAWODNIKA → ARKUSZ AKCJI
    // ------------------------------------------------------------
    $('.livePanel__player').on('click', function () {
        var $player = $(this);
        sheetPlayer = {
            id: $player.data('player'),
            team: $player.data('team'),
            name: $player.data('name'),
            number: $player.data('number')
        };
        $('#lp-sheet-title').text(
            (sheetPlayer.number !== '' ? sheetPlayer.number + '. ' : '') + sheetPlayer.name
        );
        $('#lp-sheet-minute').val(currentMinute());
        $('#lp-sheet').prop('hidden', false);
    });

    function closeSheet() {
        $('#lp-sheet').prop('hidden', true);
        sheetPlayer = null;
    }

    $('#lp-sheet-cancel').on('click', closeSheet);
    $('#lp-sheet').on('click', function (event) {
        if (event.target === this) {
            closeSheet();
        }
    });

    $('.livePanel__sheetAction').on('click', function () {
        if (!sheetPlayer) {
            return;
        }
        var action = $(this).data('action');
        api('event_add', {
            iPlayer: sheetPlayer.id,
            iTeam: sheetPlayer.team,
            sAction: action,
            sMinute: $('#lp-sheet-minute').val()
        }, function () {
            // etykiety akcji zawierają HTML (ikony) — do toastu sam tekst
            toast($('<div>').html(cfg.actions[action] || action).text() + ' — OK');
            closeSheet();
            loadEvents(true);
        });
    });

    // ------------------------------------------------------------
    // ZDARZENIA — czyszczenie
    // ------------------------------------------------------------
    $('#lp-clear-events').on('click', function () {
        if (!window.confirm(cfg.labels.confirmClear)) {
            return;
        }
        api('events_clear', {}, function () {
            loadEvents(true);
        });
    });

    // ------------------------------------------------------------
    // START
    // ------------------------------------------------------------
    refreshState();
    loadEvents();
    setInterval(refreshState, 1000);
    setInterval(loadEvents, 10000);

})(jQuery);
