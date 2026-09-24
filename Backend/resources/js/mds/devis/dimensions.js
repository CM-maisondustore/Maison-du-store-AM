/* ==========================================================================
   devis/dimensions.js
   Gère uniquement les DIMENSIONS. Utilisé aussi bien par le devis Store que
   par le devis Rideau (quote-store.js et quote-curtain.js s'appuient dessus).

   Structure d'une fenêtre : { id, label, width, height, quantity }
   Fonctions : ajouter, supprimer, modifier largeur/hauteur/quantité,
   vérifier la validité (nombre positif, dans une plage raisonnable).
   ========================================================================== */
(function (MDS) {
  'use strict';

  const LIMITS = { min: 20, max: 400 }; // centimètres, plage plausible pour une fenêtre
  const MAX_WINDOWS = 8;

  const esc = function (text) { return MDS.validation.escapeHtml(text); };
  let counter = 0;

  function makeWindow(partial) {
    counter += 1;
    return Object.assign({ id: 'w' + counter, label: '', width: '', height: '', quantity: 1 }, partial);
  }

  /** Crée un gestionnaire de dimensions dans `container`. */
  function create(container, options) {
    const opts = Object.assign({ suggestion: '', initial: null }, options);
    let windows = opts.initial && opts.initial.length
      ? opts.initial.map(function (w) { return makeWindow(w); })
      : [makeWindow({})];

    function emit() {
      container.dispatchEvent(new CustomEvent('dimensions:change', { bubbles: true, detail: { windows: getWindows() } }));
    }

    function getWindows() {
      return windows.map(function (w) { return Object.assign({}, w); });
    }

    function row(win, index) {
      return (
        '<div class="window-row" data-window="' + win.id + '">' +
          '<div class="window-row__head">' +
            '<p class="window-row__title">Fenêtre n\u00B0 ' + (index + 1) + (win.label ? ' \u2014 ' + esc(win.label) : '') + '</p>' +
            (windows.length > 1
              ? '<button type="button" class="window-row__remove" data-window-remove="' + win.id + '">' +
                '<svg class="icon icon--sm" aria-hidden="true"><use href="#i-trash"/></svg> Supprimer</button>'
              : '') +
          '</div>' +
          '<div class="window-row__fields">' +
            '<div class="field"><label class="field__label" for="w-label-' + win.id + '">Emplacement <small>(facultatif)</small></label>' +
              '<input class="input" id="w-label-' + win.id + '" data-window-field="label" value="' + esc(win.label) + '" placeholder="Ex. Salon gauche"></div>' +
            '<div class="field"><label class="field__label" for="w-width-' + win.id + '">Largeur (cm)</label>' +
              '<input class="input" id="w-width-' + win.id + '" data-window-field="width" inputmode="decimal" value="' + esc(win.width) + '" placeholder="Ex. 120"></div>' +
            '<div class="field"><label class="field__label" for="w-height-' + win.id + '">Hauteur (cm)</label>' +
              '<input class="input" id="w-height-' + win.id + '" data-window-field="height" inputmode="decimal" value="' + esc(win.height) + '" placeholder="Ex. 160"></div>' +
            '<div class="field"><label class="field__label" for="w-qty-' + win.id + '">Quantité</label>' +
              '<div class="stepper"><button type="button" data-window-qty="-1" aria-label="Retirer une unité">\u2212</button>' +
              '<input class="input stepper__value" id="w-qty-' + win.id + '" data-window-field="quantity" inputmode="numeric" value="' + win.quantity + '" aria-live="polite">' +
              '<button type="button" data-window-qty="1" aria-label="Ajouter une unité">+</button></div></div>' +
          '</div>' +
        '</div>'
      );
    }

    function render() {
      container.innerHTML =
        '<div class="window-list">' + windows.map(row).join('') + '</div>' +
        (windows.length < MAX_WINDOWS
          ? '<button type="button" class="add-window" data-window-add>' +
            '<svg class="icon" aria-hidden="true"><use href="#i-plus"/></svg> Ajouter une fenêtre</button>'
          : '<p class="field__hint">Nombre maximal de fenêtres atteint pour une même demande.</p>') +
        (opts.suggestion
          ? '<p class="window-suggestion"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-info"/></svg> ' + esc(opts.suggestion) + '</p>'
          : '');
    }

    function updateField(id, key, value) {
      const win = windows.filter(function (w) { return w.id === id; })[0];
      if (!win) return;
      win[key] = value;
      emit();
    }

    container.addEventListener('input', function (event) {
      const field = event.target.closest('[data-window-field]');
      if (!field) return;
      const row = event.target.closest('[data-window]');
      updateField(row.getAttribute('data-window'), field.getAttribute('data-window-field'), field.value);
    });

    container.addEventListener('click', function (event) {
      const add = event.target.closest('[data-window-add]');
      if (add) {
        windows.push(makeWindow({}));
        render();
        emit();
        const inputs = container.querySelectorAll('.window-row');
        const last = inputs[inputs.length - 1];
        if (last) { const first = last.querySelector('input'); if (first) first.focus(); }
        return;
      }
      const remove = event.target.closest('[data-window-remove]');
      if (remove) {
        const id = remove.getAttribute('data-window-remove');
        windows = windows.filter(function (w) { return w.id !== id; });
        render();
        emit();
        return;
      }
      const qty = event.target.closest('[data-window-qty]');
      if (qty) {
        const row = event.target.closest('[data-window]');
        const win = windows.filter(function (w) { return w.id === row.getAttribute('data-window'); })[0];
        if (win) {
          const delta = Number(qty.getAttribute('data-window-qty'));
          win.quantity = Math.min(20, Math.max(1, (Number(win.quantity) || 1) + delta));
          render();
          emit();
        }
      }
    });

    render();

    return {
      getWindows,
      setWindows: function (list) {
        windows = (list && list.length ? list : [makeWindow({})]).map(function (w) { return makeWindow(w); });
        render();
      },
      reset: function () {
        windows = [makeWindow({})];
        render();
      }
    };
  }

  /** Vérifie une liste de fenêtres. Renvoie { valid, errors: [{id, field, message}] }. */
  function validateWindows(windows) {
    const v = MDS.validation;
    const errors = [];
    if (!windows || !windows.length) {
      errors.push({ id: null, field: 'general', message: 'Ajoutez au moins une fenêtre à mesurer.' });
      return { valid: false, errors: errors };
    }
    windows.forEach(function (win, index) {
      const label = 'Fenêtre n\u00B0 ' + (index + 1);
      if (!v.isPositiveNumber(win.width, { min: LIMITS.min, max: LIMITS.max })) {
        errors.push({ id: win.id, field: 'width', message: label + ' : indiquez une largeur entre ' + LIMITS.min + ' et ' + LIMITS.max + ' cm.' });
      }
      if (!v.isPositiveNumber(win.height, { min: LIMITS.min, max: LIMITS.max })) {
        errors.push({ id: win.id, field: 'height', message: label + ' : indiquez une hauteur entre ' + LIMITS.min + ' et ' + LIMITS.max + ' cm.' });
      }
      if (!v.isPositiveNumber(win.quantity, { integer: true, min: 1, max: 20 })) {
        errors.push({ id: win.id, field: 'quantity', message: label + ' : indiquez une quantité entre 1 et 20.' });
      }
    });
    return { valid: errors.length === 0, errors: errors };
  }

  /** Résumé compact "120 x 160 cm (x2)" utilisé par la carte de configuration et le récapitulatif. */
  function formatWindow(win) {
    const qty = Number(win.quantity) > 1 ? ' (\u00D7' + win.quantity + ')' : '';
    return (win.width || '?') + ' \u00D7 ' + (win.height || '?') + ' cm' + qty + (win.label ? ' \u2014 ' + win.label : '');
  }

  function formatWindows(windows) {
    return (windows || []).map(formatWindow).join(' · ');
  }

  MDS.dimensions = { create, validateWindows, formatWindow, formatWindows, LIMITS };
})((window.MDS = window.MDS || {}));
