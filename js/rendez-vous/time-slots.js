/* ==========================================================================
   rendez-vous/time-slots.js
   Responsabilité : les HEURES.
   Affiche les créneaux d'une date et gère leurs trois états :
     available | selected | disabled
   Aujourd'hui, les créneaux déjà passés (ou à moins d'une heure) sont
   désactivés. Les créneaux complets peuvent être fournis par le backend via
   setUnavailable(date, ["09:00", ...]).

   Événement (bubbles) émis sur le conteneur : slots:select { time: "HH:MM" | null }
   ========================================================================== */
(function (MDS) {
  'use strict';

  const SLOTS = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
  const LEAD_MINUTES = 60;

  function create(container) {
    const unavailable = {}; // date ISO -> Set d'heures
    let date = null;
    let selected = null;

    function isDisabled(time) {
      if (unavailable[date] && unavailable[date].has(time)) return true;
      if (date === MDS.calendar.toISO(new Date())) {
        const parts = time.split(':');
        const slot = new Date();
        slot.setHours(Number(parts[0]), Number(parts[1]), 0, 0);
        if (slot.getTime() < Date.now() + LEAD_MINUTES * 60000) return true;
      }
      return false;
    }

    function stateOf(time) {
      if (isDisabled(time)) return 'disabled';
      return time === selected ? 'selected' : 'available';
    }

    function emit() {
      container.dispatchEvent(new CustomEvent('slots:select', { bubbles: true, detail: { time: selected } }));
    }

    function render() {
      if (!date) {
        container.innerHTML = '<p class="slots__hint">Choisissez d\u2019abord une date pour voir les créneaux disponibles.</p>';
        return;
      }
      if (SLOTS.every(isDisabled)) {
        container.innerHTML = '<p class="slots__hint">Aucun créneau disponible ce jour-là. Choisissez une autre date.</p>';
        return;
      }
      container.innerHTML =
        '<p class="slots__title">Créneaux du ' + MDS.calendar.formatLong(date) + '</p>' +
        '<div class="slots__grid" role="group" aria-label="Créneaux horaires">' +
        SLOTS.map(function (time) {
          const state = stateOf(time);
          return (
            '<button type="button" class="slot" data-time="' + time + '" data-state="' + state + '" aria-pressed="' +
            (state === 'selected') + '"' + (state === 'disabled' ? ' disabled' : '') + '>' + time +
            (state === 'disabled' ? '<span class="visually-hidden"> (indisponible)</span>' : '') + '</button>'
          );
        }).join('') + '</div>';
    }

    container.addEventListener('click', function (event) {
      const button = event.target.closest('.slot');
      if (!button || button.disabled) return;
      selected = button.getAttribute('data-time');
      container.querySelectorAll('.slot').forEach(function (b) {
        const on = b === button;
        b.setAttribute('data-state', on ? 'selected' : b.disabled ? 'disabled' : 'available');
        b.setAttribute('aria-pressed', String(on));
      });
      emit();
    });

    render();

    return {
      /** Change de date ; l'heure choisie est conservée si elle reste disponible. */
      setDate: function (iso) {
        date = iso || null;
        const hadSelection = selected !== null;
        if (selected && (!date || isDisabled(selected))) selected = null;
        render();
        if (hadSelection && selected === null) emit();
      },
      getSelected: function () { return selected; },
      setUnavailable: function (iso, times) {
        unavailable[iso] = new Set(times || []);
        if (iso === date) render();
      },
      reset: function () {
        date = null;
        selected = null;
        render();
      }
    };
  }

  MDS.timeSlots = { create, SLOTS };
})((window.MDS = window.MDS || {}));
