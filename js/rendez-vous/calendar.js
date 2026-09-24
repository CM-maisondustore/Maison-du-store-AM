/* ==========================================================================
   rendez-vous/calendar.js
   Responsabilité : la DATE.
   Affichage d'un mois, dates disponibles / indisponibles, date sélectionnée.

   Règles de disponibilité (à adapter dans CONFIG, ou à remplacer par les
   données du backend via setUnavailable) :
     - jours fermés : dimanche ;
     - aucune date passée ; réservation possible jusqu'à 3 mois à l'avance.

   Événement (bubbles) émis sur le conteneur : calendar:select { date: "AAAA-MM-JJ" }
   ========================================================================== */
(function (MDS) {
  'use strict';

  const CONFIG = {
    closedWeekdays: [0],  // 0 = dimanche
    minLeadDays: 0,       // 0 = réservation possible dès aujourd'hui
    maxMonthsAhead: 3
  };

  const WEEKDAYS = [
    ['Lu', 'lundi'], ['Ma', 'mardi'], ['Me', 'mercredi'], ['Je', 'jeudi'],
    ['Ve', 'vendredi'], ['Sa', 'samedi'], ['Di', 'dimanche']
  ];

  const pad = function (n) { return String(n).padStart(2, '0'); };

  function toISO(date) {
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
  }

  function parseISO(text) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text || '');
    if (!m) return null;
    const date = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return date.getMonth() === Number(m[2]) - 1 ? date : null;
  }

  function formatLong(iso) {
    const date = parseISO(iso);
    return date
      ? date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
      : '';
  }

  function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function addDays(date, n) {
    const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    d.setDate(d.getDate() + n);
    return d;
  }

  /** Crée un calendrier dans `container` et renvoie son interface. */
  function create(container, options) {
    const cfg = Object.assign({}, CONFIG, options);
    const today = startOfDay(new Date());
    const min = addDays(today, cfg.minLeadDays);
    const max = new Date(today.getFullYear(), today.getMonth() + cfg.maxMonthsAhead + 1, 0);
    const unavailable = new Set();
    let selected = null;

    function isAvailable(date) {
      if (date < min || date > max) return false;
      if (cfg.closedWeekdays.indexOf(date.getDay()) !== -1) return false;
      return !unavailable.has(toISO(date));
    }

    function firstAvailableMonth() {
      for (let d = new Date(min); d <= max; d = addDays(d, 1)) {
        if (isAvailable(d)) return new Date(d.getFullYear(), d.getMonth(), 1);
      }
      return new Date(min.getFullYear(), min.getMonth(), 1);
    }

    let view = firstAvailableMonth();

    function render() {
      const year = view.getFullYear();
      const month = view.getMonth();
      const offset = (new Date(year, month, 1).getDay() + 6) % 7;
      const days = new Date(year, month + 1, 0).getDate();
      const todayISO = toISO(today);

      const cells = [];
      for (let i = 0; i < offset; i++) cells.push(null);
      for (let d = 1; d <= days; d++) cells.push(new Date(year, month, d));
      while (cells.length % 7) cells.push(null);

      let rows = '';
      for (let i = 0; i < cells.length; i += 7) {
        rows += '<tr>' + cells.slice(i, i + 7).map(function (date) {
          if (!date) return '<td></td>';
          const iso = toISO(date);
          const ok = isAvailable(date);
          const isSel = iso === selected;
          return (
            '<td><button type="button" class="calendar__day' + (isSel ? ' is-selected' : '') + (iso === todayISO ? ' is-today' : '') +
            (ok ? '' : ' is-unavailable') + '" data-date="' + iso + '" aria-pressed="' + isSel + '" aria-label="' +
            formatLong(iso) + (ok ? '' : ' (indisponible)') + '"' + (ok ? '' : ' disabled') + '>' + date.getDate() + '</button></td>'
          );
        }).join('') + '</tr>';
      }

      const title = view.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
      const atStart = year === min.getFullYear() && month === min.getMonth();
      const atEnd = year === max.getFullYear() && month === max.getMonth();
      const chevron = function (icon) {
        return '<svg class="icon icon--sm" aria-hidden="true"><use href="#' + icon + '"/></svg>';
      };

      container.innerHTML =
        '<div class="calendar">' +
          '<div class="calendar__head">' +
            '<button type="button" class="calendar__nav" data-cal-prev aria-label="Mois précédent"' + (atStart ? ' disabled' : '') + '>' + chevron('i-chevron-left') + '</button>' +
            '<p class="calendar__title" aria-live="polite">' + title + '</p>' +
            '<button type="button" class="calendar__nav" data-cal-next aria-label="Mois suivant"' + (atEnd ? ' disabled' : '') + '>' + chevron('i-chevron-right') + '</button>' +
          '</div>' +
          '<table class="calendar__grid"><caption class="visually-hidden">Choisissez une date de rendez-vous</caption>' +
            '<thead><tr>' + WEEKDAYS.map(function (w) { return '<th scope="col" abbr="' + w[1] + '">' + w[0] + '</th>'; }).join('') + '</tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
          '</table>' +
        '</div>';
    }

    function shiftMonth(delta) {
      view = new Date(view.getFullYear(), view.getMonth() + delta, 1);
      render();
      const nav = container.querySelector(delta < 0 ? '[data-cal-prev]' : '[data-cal-next]');
      if (nav && !nav.disabled) nav.focus();
    }

    function select(iso, emit) {
      const date = parseISO(iso);
      if (!date || !isAvailable(date)) return;
      selected = iso;
      container.querySelectorAll('.calendar__day').forEach(function (btn) {
        const on = btn.getAttribute('data-date') === iso;
        btn.classList.toggle('is-selected', on);
        btn.setAttribute('aria-pressed', String(on));
      });
      if (emit) container.dispatchEvent(new CustomEvent('calendar:select', { bubbles: true, detail: { date: iso } }));
    }

    container.addEventListener('click', function (event) {
      if (event.target.closest('[data-cal-prev]')) return shiftMonth(-1);
      if (event.target.closest('[data-cal-next]')) return shiftMonth(1);
      const day = event.target.closest('.calendar__day');
      if (day && !day.disabled) select(day.getAttribute('data-date'), true);
    });

    // Flèches du clavier : déplacement de jour en jour dans le mois affiché
    container.addEventListener('keydown', function (event) {
      const day = event.target.closest && event.target.closest('.calendar__day');
      const steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
      if (!day || !(event.key in steps)) return;
      const target = addDays(parseISO(day.getAttribute('data-date')), steps[event.key]);
      const next = container.querySelector('[data-date="' + toISO(target) + '"]');
      event.preventDefault();
      if (next && !next.disabled) next.focus();
    });

    render();

    return {
      getSelected: function () { return selected; },
      setSelected: function (iso) {
        const date = parseISO(iso);
        if (!date) return;
        view = new Date(date.getFullYear(), date.getMonth(), 1);
        render();
        select(iso, false);
      },
      /** Marque des dates comme complètes (à alimenter depuis l'API). */
      setUnavailable: function (list) {
        unavailable.clear();
        (list || []).forEach(function (iso) { unavailable.add(iso); });
        render();
      },
      reset: function () {
        selected = null;
        view = firstAvailableMonth();
        render();
      }
    };
  }

  MDS.calendar = { create, toISO, parseISO, formatLong, config: CONFIG };
})((window.MDS = window.MDS || {}));
