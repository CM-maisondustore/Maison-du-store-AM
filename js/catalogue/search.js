/* ==========================================================================
   catalogue/search.js
   Responsabilité unique : rechercher les produits.
   Critères : nom, référence, catégorie, type. La recherche ignore la casse
   et les accents ; chaque mot saisi doit se retrouver dans au moins un critère.

   Événements :
     émet    search:change   { query }
     écoute  catalogue:reset
   ========================================================================== */
(function (MDS) {
  'use strict';

  const DEBOUNCE_MS = 200;

  let input = null;
  let clearButton = null;
  let query = '';
  let timer = null;

  const norm = function (text) { return MDS.validation.normalizeText(text); };

  /** Texte de recherche d'un produit : nom, référence, catégorie et type (singulier et pluriel). */
  function haystack(product) {
    const type = (MDS.data.types || []).filter(function (t) { return t.id === product.type; })[0];
    return norm([
      product.name,
      product.reference,
      product.category,
      product.type,
      type ? type.label + ' ' + type.plural : ''
    ].join(' '));
  }

  /** Vrai si tous les mots de la requête se retrouvent dans le produit. */
  function matches(product, text) {
    const words = norm(text).split(' ').filter(Boolean);
    if (!words.length) return true;
    const target = haystack(product);
    return words.every(function (word) { return target.indexOf(word) !== -1; });
  }

  function searchProducts(products, text) {
    const q = text === undefined ? query : text;
    if (!norm(q)) return products.slice();
    return products.filter(function (p) { return matches(p, q); });
  }

  function getQuery() {
    return query;
  }

  function updateClear() {
    if (clearButton) clearButton.hidden = query === '';
  }

  function commit(value) {
    if (value === query) return;
    query = value;
    updateClear();
    document.dispatchEvent(new CustomEvent('search:change', { detail: { query: query } }));
  }

  function setQuery(value) {
    if (input) input.value = value;
    commit(value);
  }

  function clear() {
    setQuery('');
    if (input) input.focus();
  }

  /** Remise à zéro silencieuse (utilisée par catalogue:reset). */
  function reset() {
    query = '';
    if (input) input.value = '';
    updateClear();
  }

  function init() {
    input = document.getElementById('catalogue-search');
    clearButton = document.querySelector('[data-search-clear]');
    if (!input) return;

    input.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(function () { commit(input.value); }, DEBOUNCE_MS);
    });

    const form = input.closest('form');
    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        window.clearTimeout(timer);
        commit(input.value);
      });
    }
    if (clearButton) clearButton.addEventListener('click', clear);
    document.addEventListener('catalogue:reset', reset);
  }

  MDS.search = { init, getQuery, setQuery, clear, reset, matches, searchProducts };
})((window.MDS = window.MDS || {}));
