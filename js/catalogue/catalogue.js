/* ==========================================================================
   catalogue/catalogue.js   (fichier ajouté à l'arborescence de base)
   Assemble le catalogue : charge les produits, combine recherche, catégorie
   et filtres, affiche la grille, le compteur, l'état vide et la pagination,
   ainsi que les sélections mises en avant de l'accueil.

   Il ne contient ni données (products.js) ni logique de recherche ou de
   filtre (search.js, categories.js, filters.js) : il les compose.
   ========================================================================== */
(function (MDS) {
  'use strict';

  const PAGE_SIZE = 6;
  const FEATURED_COUNT = 3;

  const esc = function (text) { return MDS.validation.escapeHtml(text); };

  let all = [];
  let page = 1;
  let els = {};

  /* --- Carte produit --------------------------------------------------------- */

  function featureLabel(id) {
    const f = (MDS.data.features || []).filter(function (x) { return x.id === id; })[0];
    return f ? f.label : id;
  }

  /** Lien vers le parcours devis adapté au type du produit (Store ou Rideau). */
  function quoteHref(product) {
    const routes = (MDS.config && MDS.config.routes) || {};
    const page = routes.quote || 'devis.html';
    return page + '?type=' + encodeURIComponent(product.type) + '&product=' + encodeURIComponent(product.id);
  }

  function renderCard(product) {
    const tags = (product.features || []).map(function (f) {
      return '<li class="tag">' + esc(featureLabel(f)) + '</li>';
    }).join('');
    const badge = product.available ? '' : '<span class="badge badge--order">Sur commande</span>';

    return (
      '<article class="product-card" data-product-id="' + esc(product.id) + '">' +
        '<div class="product-card__media">' +
          '<div class="image-placeholder image-placeholder--card" aria-hidden="true"></div>' + badge +
        '</div>' +
        '<div class="product-card__body">' +
          '<p class="product-card__eyebrow">' + esc(product.category) + '</p>' +
          '<h3 class="product-card__title">' +
            '<button type="button" class="product-card__link" data-product-open="' + esc(product.id) + '" aria-haspopup="dialog">' +
              esc(product.name) + '</button>' +
          '</h3>' +
          '<p class="product-card__text">' + esc(product.description) + '</p>' +
          '<ul class="tag-list" aria-label="Caractéristiques">' + tags + '</ul>' +
          '<div class="product-card__footer">' +
            '<span class="product-card__price">' + esc(product.price) + '</span>' +
            '<a class="btn btn--gold btn--sm" href="' + quoteHref(product) + '" data-action="quote" data-type="' +
              esc(product.type) + '" data-product-id="' + esc(product.id) + '">Demander un devis</a>' +
          '</div>' +
        '</div>' +
      '</article>'
    );
  }

  /* --- Calcul de la liste affichée ------------------------------------------ */

  function compute() {
    let list = MDS.search.searchProducts(all, MDS.search.getQuery());
    const category = MDS.categories.getActive();
    list = list.filter(function (p) { return MDS.categories.matches(p, category); });
    return MDS.filters.apply(list, MDS.filters.getState());
  }

  /* --- Rendu ------------------------------------------------------------------ */

  function countLabel(n) {
    if (n === 0) return 'Aucune création';
    return n + (n > 1 ? ' créations' : ' création');
  }

  function pageList(current, total) {
    if (total <= 7) return Array.from({ length: total }, function (_, i) { return i + 1; });
    const pages = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    if (start > 2) pages.push('…');
    for (let i = start; i <= end; i++) pages.push(i);
    if (end < total - 1) pages.push('…');
    pages.push(total);
    return pages;
  }

  function renderPagination(total) {
    if (!els.pagination) return;
    if (total <= 1) {
      els.pagination.innerHTML = '';
      return;
    }
    const items = pageList(page, total).map(function (p) {
      if (p === '…') return '<li class="pagination__gap" aria-hidden="true">…</li>';
      const current = p === page;
      return (
        '<li><button type="button" class="pagination__btn' + (current ? ' is-current' : '') + '" data-page="' + p + '"' +
        (current ? ' aria-current="page"' : '') + ' aria-label="Page ' + p + '">' + p + '</button></li>'
      );
    }).join('');
    const arrow = function (dir, label, disabled, icon) {
      return (
        '<li><button type="button" class="pagination__btn pagination__btn--arrow" data-page="' + dir + '" aria-label="' + label + '"' +
        (disabled ? ' disabled' : '') + '><svg class="icon icon--sm" aria-hidden="true"><use href="#' + icon + '"/></svg></button></li>'
      );
    };
    els.pagination.innerHTML =
      '<ul class="pagination__list">' +
      arrow('prev', 'Page précédente', page === 1, 'i-chevron-left') + items +
      arrow('next', 'Page suivante', page === total, 'i-chevron-right') + '</ul>';
  }

  function render() {
    if (!els.grid) return;
    const list = compute();
    const totalPages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
    if (page > totalPages) page = totalPages;

    const slice = list.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
    els.grid.innerHTML = slice.map(renderCard).join('');
    els.grid.hidden = slice.length === 0;
    if (els.empty) els.empty.hidden = slice.length !== 0;
    if (els.count) els.count.textContent = countLabel(list.length);
    renderPagination(totalPages);
  }

  function renderFeatured() {
    document.querySelectorAll('[data-featured]').forEach(function (container) {
      const type = container.getAttribute('data-featured');
      const items = all.filter(function (p) { return p.type === type && p.featured; }).slice(0, FEATURED_COUNT);
      container.innerHTML = items.map(renderCard).join('');
    });
  }

  function goToPage(next, options) {
    const total = Math.max(1, Math.ceil(compute().length / PAGE_SIZE));
    page = Math.min(Math.max(1, next), total);
    render();
    if (options && options.scroll && els.toolbar) {
      els.toolbar.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }
  }

  /* --- Initialisation -------------------------------------------------------- */

  function loadProducts() {
    // MDS.api.getProducts() lit products.js aujourd'hui, GET /api/products/ demain.
    return MDS.api.getProducts();
  }

  function init() {
    els = {
      grid: document.getElementById('catalogue-grid'),
      count: document.getElementById('catalogue-count'),
      empty: document.getElementById('catalogue-empty'),
      pagination: document.getElementById('catalogue-pagination'),
      toolbar: document.querySelector('[data-catalogue-toolbar]')
    };

    const refresh = function () { page = 1; render(); };
    document.addEventListener('search:change', refresh);
    document.addEventListener('category:change', refresh);
    document.addEventListener('filters:change', refresh);

    if (els.pagination) {
      els.pagination.addEventListener('click', function (event) {
        const button = event.target.closest('[data-page]');
        if (!button || button.disabled) return;
        const value = button.getAttribute('data-page');
        goToPage(value === 'prev' ? page - 1 : value === 'next' ? page + 1 : Number(value), { scroll: true });
      });
    }

    document.addEventListener('click', function (event) {
      if (!event.target.closest('[data-catalogue-reset]')) return;
      document.dispatchEvent(new CustomEvent('catalogue:reset'));
      page = 1;
      render();
    });

    loadProducts()
      .then(function (list) {
        all = list;
        MDS.filters.setProducts(all);
        renderFeatured();
        render();
      })
      .catch(function () {
        if (els.count) els.count.textContent = 'Le catalogue est momentanément indisponible.';
        if (MDS.notifications) MDS.notifications.error('Impossible de charger le catalogue. Réessayez dans un instant.');
      });
  }

  MDS.catalogue = { init, render, renderCard, goToPage };
})((window.MDS = window.MDS || {}));
