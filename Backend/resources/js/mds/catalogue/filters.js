/* ==========================================================================
   catalogue/filters.js
   Filtres latéraux du catalogue : type, catégorie, caractéristique, couleur,
   disponibilité, réinitialisation. Au sein d'un groupe les choix s'ajoutent
   (OU) ; entre groupes ils se combinent (ET).

   Événements :
     émet    filters:change
     écoute  catalogue:reset
   ========================================================================== */
(function (MDS) {
  'use strict';

  const esc = function (text) { return MDS.validation.escapeHtml(text); };

  let body = null;
  let toggle = null;
  let panel = null;
  let products = [];

  const state = emptyState();

  function emptyState() {
    return { types: [], categories: [], features: [], colors: [], availability: 'all' };
  }

  function getState() {
    return {
      types: state.types.slice(),
      categories: state.categories.slice(),
      features: state.features.slice(),
      colors: state.colors.slice(),
      availability: state.availability
    };
  }

  function hasActive(s) {
    const current = s || state;
    return !!(current.types.length || current.categories.length || current.features.length ||
      current.colors.length || current.availability !== 'all');
  }

  /** Applique un état de filtres à une liste de produits (fonction pure). */
  function apply(list, s) {
    const f = s || state;
    return list.filter(function (p) {
      if (f.types.length && f.types.indexOf(p.type) === -1) return false;
      if (f.categories.length && f.categories.indexOf(p.category) === -1) return false;
      if (f.features.length && !f.features.some(function (x) { return (p.features || []).indexOf(x) !== -1; })) return false;
      if (f.colors.length && !f.colors.some(function (x) { return (p.colors || []).indexOf(x) !== -1; })) return false;
      if (f.availability === 'available' && !p.available) return false;
      if (f.availability === 'order' && p.available) return false;
      return true;
    });
  }

  /* --- Rendu --------------------------------------------------------------- */

  function checkbox(group, value, label, checked) {
    return (
      '<label class="check filter-option">' +
      '<input type="checkbox" data-filter-group="' + group + '" value="' + esc(value) + '"' + (checked ? ' checked' : '') + '>' +
      '<span>' + esc(label) + '</span></label>'
    );
  }

  function group(title, content, extraClass) {
    return (
      '<fieldset class="filter-group' + (extraClass ? ' ' + extraClass : '') + '">' +
      '<legend class="filter-group__title">' + esc(title) + '</legend>' + content + '</fieldset>'
    );
  }

  function uniqueCategories() {
    const seen = [];
    products.forEach(function (p) { if (p.category && seen.indexOf(p.category) === -1) seen.push(p.category); });
    return seen;
  }

  function render() {
    if (!body) return;
    const types = (MDS.data.types || []).map(function (t) {
      return checkbox('types', t.id, t.plural, state.types.indexOf(t.id) !== -1);
    }).join('');
    const categories = uniqueCategories().map(function (c) {
      return checkbox('categories', c, c, state.categories.indexOf(c) !== -1);
    }).join('');
    const features = (MDS.data.features || []).map(function (f) {
      return checkbox('features', f.id, f.label, state.features.indexOf(f.id) !== -1);
    }).join('');
    const colors = (MDS.data.colors || []).map(function (c) {
      const on = state.colors.indexOf(c.id) !== -1;
      return (
        '<button type="button" class="swatch-btn' + (on ? ' is-active' : '') + '" data-filter-color="' + c.id +
        '" data-hex="' + c.hex + '" aria-pressed="' + on + '" aria-label="' + esc(c.label) + '" title="' + esc(c.label) + '"></button>'
      );
    }).join('');
    const availability = [
      ['all', 'Tous'],
      ['available', 'Disponible'],
      ['order', 'Sur commande']
    ].map(function (o) {
      return (
        '<label class="check filter-option"><input type="radio" name="filter-availability" data-filter-availability value="' +
        o[0] + '"' + (state.availability === o[0] ? ' checked' : '') + '><span>' + o[1] + '</span></label>'
      );
    }).join('');

    body.innerHTML =
      group('Type', '<div class="filter-list">' + types + '</div>') +
      group('Catégorie', '<div class="filter-list">' + categories + '</div>') +
      group('Caractéristiques', '<div class="filter-list">' + features + '</div>') +
      group('Couleur', '<div class="swatch-row">' + colors + '</div>') +
      group('Disponibilité', '<div class="filter-list">' + availability + '</div>');

    body.querySelectorAll('[data-hex]').forEach(function (el) {
      el.style.setProperty('--swatch', el.getAttribute('data-hex'));
    });
  }

  function emit() {
    document.dispatchEvent(new CustomEvent('filters:change'));
  }

  function toggleValue(list, value, on) {
    const i = list.indexOf(value);
    if (on && i === -1) list.push(value);
    if (!on && i !== -1) list.splice(i, 1);
  }

  /* --- Actions ------------------------------------------------------------- */

  function reset(options) {
    const fresh = emptyState();
    Object.keys(fresh).forEach(function (key) { state[key] = fresh[key]; });
    render();
    if (!options || !options.silent) emit();
  }

  /** Met à jour la liste des produits (catégories affichées) après un chargement. */
  function setProducts(list) {
    products = list || [];
    render();
  }

  function setPanelOpen(open) {
    if (!panel || !toggle) return;
    panel.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
  }

  function init() {
    body = document.querySelector('[data-filters-body]');
    toggle = document.querySelector('[data-filters-toggle]');
    panel = document.getElementById('filters');
    if (!body) return;

    products = (MDS.data && MDS.data.products) || [];
    render();

    body.addEventListener('change', function (event) {
      const input = event.target;
      if (input.matches('[data-filter-group]')) {
        toggleValue(state[input.getAttribute('data-filter-group')], input.value, input.checked);
        emit();
      } else if (input.matches('[data-filter-availability]')) {
        state.availability = input.value;
        emit();
      }
    });

    body.addEventListener('click', function (event) {
      const swatch = event.target.closest('[data-filter-color]');
      if (!swatch) return;
      const on = swatch.getAttribute('aria-pressed') !== 'true';
      swatch.setAttribute('aria-pressed', String(on));
      swatch.classList.toggle('is-active', on);
      toggleValue(state.colors, swatch.getAttribute('data-filter-color'), on);
      emit();
    });

    const resetButton = document.querySelector('[data-filters-reset]');
    if (resetButton) resetButton.addEventListener('click', function () { reset(); });
    if (toggle) toggle.addEventListener('click', function () { setPanelOpen(toggle.getAttribute('aria-expanded') !== 'true'); });

    document.addEventListener('catalogue:reset', function () { reset({ silent: true }); });
  }

  MDS.filters = { init, getState, hasActive, apply, reset, setProducts };
})((window.MDS = window.MDS || {}));
