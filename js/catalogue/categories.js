/* ==========================================================================
   catalogue/categories.js
   Catégories du catalogue : définition, affichage des pastilles, sélection
   d'une catégorie et état actif.

   Événements :
     émet    category:change   { id }
     écoute  catalogue:show    { category }  (depuis navigation.js)
             catalogue:reset
   ========================================================================== */
(function (MDS) {
  'use strict';

  const hasFeature = function (product, feature) {
    return (product.features || []).indexOf(feature) !== -1;
  };

  /** Catégories affichées, dans l'ordre. `matches` décide si un produit en fait partie. */
  const CATEGORIES = [
    { id: 'all', label: 'Tous', matches: function () { return true; } },
    { id: 'stores', label: 'Stores', matches: function (p) { return p.type === 'store'; } },
    { id: 'rideaux', label: 'Rideaux', matches: function (p) { return p.type === 'rideau'; } },
    { id: 'occultants', label: 'Occultants', matches: function (p) { return hasFeature(p, 'occultant'); } },
    { id: 'tamisants', label: 'Tamisants', matches: function (p) { return hasFeature(p, 'tamisant'); } },
    { id: 'motorises', label: 'Motorisés', matches: function (p) { return hasFeature(p, 'motorise'); } }
  ];

  let container = null;
  let active = 'all';

  function find(id) {
    return CATEGORIES.filter(function (c) { return c.id === id; })[0] || null;
  }

  function getActive() {
    return active;
  }

  function matches(product, id) {
    const category = find(id || active);
    return category ? category.matches(product) : true;
  }

  function render() {
    if (!container) return;
    container.innerHTML = CATEGORIES.map(function (c) {
      const pressed = c.id === active;
      return (
        '<button type="button" class="chip' + (pressed ? ' is-active' : '') + '" data-category="' + c.id +
        '" aria-pressed="' + pressed + '">' + c.label + '</button>'
      );
    }).join('');
  }

  /** Sélectionne une catégorie. Renvoie false si l'identifiant est inconnu. */
  function select(id, options) {
    if (!find(id)) return false;
    const silent = options && options.silent;
    const changed = active !== id;
    active = id;
    render();
    if (changed && !silent) {
      document.dispatchEvent(new CustomEvent('category:change', { detail: { id: id } }));
    }
    return true;
  }

  function reset() {
    active = 'all';
    render();
  }

  function init() {
    container = document.querySelector('[data-category-chips]');
    if (!container) return;
    render();

    container.addEventListener('click', function (event) {
      const chip = event.target.closest('[data-category]');
      if (chip) select(chip.getAttribute('data-category'));
    });

    document.addEventListener('catalogue:show', function (event) {
      const id = event.detail && event.detail.category;
      if (id) select(id);
    });
    document.addEventListener('catalogue:reset', reset);
  }

  MDS.categories = { init, list: CATEGORIES, getActive, select, reset, matches };
})((window.MDS = window.MDS || {}));
