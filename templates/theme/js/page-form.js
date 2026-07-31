(function ($) {
  function buildCalendarUrl(base, href) {
    if (href.indexOf('/plugins/') === 0) {
      return href;
    }
    if (href.indexOf('?') === 0) {
      if (base.indexOf('?') === -1) {
        return base + href;
      } else {
        return base + '&' + href.substring(1);
      }
    }
    return href;
  }

  function loadCalendar(url, mode) {
    $('#availability-calendar').addClass('is-loading');
    $.get(url, function (html) {
      $('#availability-calendar').html(html).removeClass('is-loading');
      if (mode === 'range') {
        initRangeCalendar(mode);
      } else if (mode === 'visit') {
        initVisitCalendar(mode);
      }
    });
  }

  // ============================
  // RANGE – wybór zakresu
  // ============================
  function initRangeCalendar(mode) {
    let selStart = $('#date_start').val() || '';
    let selEnd = $('#date_end').val() || '';
    const busyDays = new Set();

    function showOrHideReset() {
      if (selStart || selEnd) {
        $('#date-reset-wrapper').show();
      } else {
        $('#date-reset-wrapper').hide();
      }
    }

    function addOneDay(dateStr) {
      const d = new Date(dateStr);
      d.setDate(d.getDate() + 1);
      return d.toISOString().slice(0, 10);
    }

    function rangeHasBusy(start, end) {
      let cur = start;
      while (cur <= end) {
        if (busyDays.has(cur)) {
          return true;
        }
        cur = addOneDay(cur);
      }
      return false;
    }

    function applySelectionOnCalendar() {
      const $cal = $('#availability-calendar');
      $cal.find('.day-start').removeClass('day-start');
      $cal.find('.day-end').removeClass('day-end');
      $cal.find('.day-range').removeClass('day-range');

      if (selStart) {
        $cal.find('[data-date="' + selStart + '"]').addClass('day-start');
      }
      if (selEnd) {
        $cal.find('[data-date="' + selEnd + '"]').addClass('day-end');
        let cur = selStart;
        while (cur <= selEnd) {
          $cal.find('[data-date="' + cur + '"]').addClass('day-range');
          cur = addOneDay(cur);
        }
      }
    }

    function updateSelectedBox() {
      $('#pageForm__selectedDateStart').text(selStart || '—');
      $('#pageForm__selectedDateEnd').text(selEnd || '—');

      if (selStart && selEnd) {
        const d1 = new Date(selStart);
        const d2 = new Date(selEnd);
        const diff = (d2 - d1) / (1000 * 60 * 60 * 24);
        const days = diff >= 0 ? diff + 1 : 0;
        $('#pageForm__selectedDateCount').text(days);
        $('#day_count').val(days);
      } else if (selStart) {
        $('#pageForm__selectedDateCount').text(1);
        $('#day_count').val(1);
      } else {
        $('#pageForm__selectedDateCount').text('—');
        $('#day_count').val('');
      }
    }

    function harvestBusyDays() {
      $('#availability-calendar')
        .find('.day-busy')
        .each(function () {
          const d = $(this).data('date');
          if (d) busyDays.add(d);
        });
    }

    $('#availability-calendar')
      .off('click.range')
      .on('click.range', '.day-link', function (e) {
        e.preventDefault();
        const $td = $(this).closest('td');
        if ($td.hasClass('day-busy') || $td.hasClass('day-past')) return;

        const date = $(this).data('date');

        if (!selStart) {
          selStart = date;
          selEnd = '';
        } else if (!selEnd) {
          let start = selStart;
          let end = date;
          if (date < selStart) {
            start = date;
            end = selStart;
          }
          if (rangeHasBusy(start, end)) {
            alert('W wybranym zakresie są już zarezerwowane dni. Wybierz inny zakres.');
            return;
          }
          selStart = start;
          selEnd = end;
        } else {
          selStart = date;
          selEnd = '';
        }

        $('#date_start').val(selStart);
        $('#date_end').val(selEnd);

        applySelectionOnCalendar();
        updateSelectedBox();
        showOrHideReset();
      });

    $('#date-reset-btn')
      .off('click')
      .on('click', function () {
        selStart = '';
        selEnd = '';
        $('#date_start').val('');
        $('#date_end').val('');
        applySelectionOnCalendar();
        updateSelectedBox();
        showOrHideReset();
      });

    harvestBusyDays();
    applySelectionOnCalendar();
    updateSelectedBox();
    showOrHideReset();

    $(document)
      .off('click.calnav')
      .on('click.calnav', '.pageFormCalendar__nav a', function (e) {
        e.preventDefault();
        const base = $('#availability-calendar').data('url');
        const href = $(this).attr('href');
        const finalUrl = buildCalendarUrl(base, href);
        loadCalendar(finalUrl, mode);
      });
  }

  // ============================
  // VISIT – wybór dnia + godziny
  // ============================
  function initVisitCalendar(mode) {
    let selectedDate = $('#date_start').val() || '';
    let selectedTime = $('#time').val() || '';

    $('#availability-calendar')
      .off('click.visit')
      .on('click.visit', '.day-link', function (e) {
        e.preventDefault();
        const $td = $(this).closest('td');
        if ($td.hasClass('day-busy') || $td.hasClass('day-past')) return;

        const date = $(this).data('date');
        selectedDate = date;
        $('#date_start').val(date);
        $('#date_end').val('');
        $('#time').val('');
        $('#visit-selected-time').text('-');
        $('#visit-selected-date').text(date);

        $('#availability-calendar').find('.day-start').removeClass('day-start');
        $td.addClass('day-start');

        loadHoursForDate(date);
      });

    function loadHoursForDate(date) {
      const url = '/plugins/form-calendar-hours.php?date=' + encodeURIComponent(date);
      $('#visit-hours-wrapper').html('<p>Ładuję dostępne godziny...</p>');
      $.getJSON(url, function (resp) {
        if (!resp || !resp.available) {
          $('#visit-hours-wrapper').html('<p>Brak danych o godzinach.</p>');
          return;
        }
        const hours = resp.available;
        if (!hours.length) {
          $('#visit-hours-wrapper').html('<p>Brak wolnych godzin w tym dniu.</p>');
          return;
        }

        let html = '<div class="visit-hours-grid">';
        hours.forEach(function (h) {
          html +=
            '<button type="button" class="visit-hour-btn button-sm mr-1 mb-1 button button-light" data-hour="' +
            h +
            '">' +
            h +
            '</button>';
        });
        html += '</div>';
        $('#visit-hours-wrapper').html(html);

        $('.visit-hour-btn').on('click', function () {
          $('.visit-hour-btn').removeClass('selected');
          $(this).addClass('selected');
          selectedTime = $(this).data('hour');
          $('#time').val(selectedTime);
          $('#visit-selected-time').text(selectedTime);
        });
      });
    }

    $(document)
      .off('click.calnav')
      .on('click.calnav', '.pageFormCalendar__nav a', function (e) {
        e.preventDefault();
        const base = $('#availability-calendar').data('url');
        const href = $(this).attr('href');
        const finalUrl = buildCalendarUrl(base, href);
        loadCalendar(finalUrl, mode);
      });
  }

  $(function () {
    const $pageForm = $('.pageForm').first();
    if (!$pageForm.length) return;

    const mode = $pageForm.data('mode');
    if (!mode) return;

    if (mode === 'range') {
      initRangeCalendar(mode);
    } else if (mode === 'visit') {
      initVisitCalendar(mode);
    }
  });
})(jQuery);
