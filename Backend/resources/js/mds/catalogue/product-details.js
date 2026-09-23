/* ==========================================================================
   catalogue/product-details.js
   Détail d'un produit dans une modale : ouverture, affichage des
   informations, fermeture, bouton « Demander un devis ».

   Parcours :
     produit Store  -> devis.html?type=store&product=ID   (Devis Store)
     produit Rideau -> devis.html?type=rideau&product=ID  (Devis Rideau)
   La redirection est assurée par navigation.js (data-action="quote").

   Ouverture : clic sur un élément [data-product-open="ID"] ou événement
   document « product:open » { id }.
   ========================================================================== */
(function (MDS) {
  'use strict';

  const MODAL_ID = 'product-modal';
  const SUGGESTION_COUNT = 3;

  const esc = function (text) { return MDS.validation.escapeHtml(text); };

  let modal = null;
  let content = null;
  let products = [];
  let currentId = null;

  function byId(id) {
    return products.filter(function (p) { return String(p.id) === String(id); })[0] || null;
  }

  function typeInfo(product) {
    const type = (MDS.data.types || []).filter(function (t) { return t.id === product.type; })[0];
    return type || { id: product.type, label: product.type, plural: product.type };
  }

  function colorsOf(product) {
    return (product.colors || []).map(function (id) {
      return (MDS.data.colors || []).filter(function (c) { return c.id === id; })[0];
    }).filter(Boolean);
  }

  function featureLabel(id) {
    const f = (MDS.data.features || []).filter(function (x) { return x.id === id; })[0];
    return f ? f.label : id;
  }

  /** Autres produits du même type, ceux de la même catégorie en premier. */
  function suggestions(product) {
    const others = products.filter(function (p) { return p.id !== product.id && p.type === product.type; });
    others.sort(function (a, b) {
      return (b.category === product.category ? 1 : 0) - (a.category === product.category ? 1 : 0);
    });
    return others.slice(0, SUGGESTION_COUNT);
  }

  function renderSuggestion(p) {
    return (
      '<li><button type="button" class="suggestion" data-product-open="' + esc(p.id) + '">' +
        '<span class="image-placeholder image-placeholder--thumb" aria-hidden="true"></span>' +
        '<span class="suggestion__text">' +
          '<span class="suggestion__category">' + esc(p.category) + '</span>' +
          '<span class="suggestion__name">' + esc(p.name) + '</span>' +
        '</span>' +
        '<span class="suggestion__price">' + esc(p.price) + '</span>' +
      '</button></li>'
    );
  }

  function render(product) {
    const type = typeInfo(product);
    const quoteLabel = 'Devis ' + type.label;
    const routes = (MDS.config && MDS.config.routes) || {};
    const href = (routes.quote || 'devis.html') + '?type=' + encodeURIComponent(product.type) + '&product=' + encodeURIComponent(product.id);

    const specs = (product.specs || []).map(function (s) {
      return '<div class="spec-list__row"><dt>' + esc(s.label) + '</dt><dd>' + esc(s.value) + '</dd></div>';
    }).join('');

    const colors = colorsOf(product).map(function (c) {
      return '<li class="swatch-item"><span class="swatch-dot" data-hex="' + c.hex + '"></span>' + esc(c.label) + '</li>';
    }).join('');

    const tags = (product.features || []).map(function (f) {
      return '<li class="tag">' + esc(featureLabel(f)) + '</li>';
    }).join('');

    const availability = product.available
      ? '<span class="badge badge--ok">Disponible</span>'
      : '<span class="badge badge--order">Sur commande</span>';

    const more = suggestions(product).map(renderSuggestion).join('');

    content.innerHTML =
      '<div class="product-detail">' +
        '<div class="product-detail__gallery">' +
          '<div class="image-placeholder image-placeholder--gallery" aria-hidden="true"></div>' +
          '<div class="product-detail__thumbs" aria-hidden="true">' +
            '<div class="image-placeholder image-placeholder--tiny"></div>'.repeat(4) +
          '</div>' +
        '</div>' +
        '<div class="product-detail__info">' +
          '<p class="eyebrow">' + esc(type.label) + ' · ' + esc(product.category) + '</p>' +
          '<h2 class="product-detail__title" id="product-modal-title">' + esc(product.name) + '</h2>' +
          '<p class="product-detail__ref">Réf. ' + esc(product.reference) + ' ' + availability + '</p>' +
          '<p class="product-detail__text">' + esc(product.description) + '</p>' +
          (tags ? '<ul class="tag-list">' + tags + '</ul>' : '') +
          '<h3 class="product-detail__heading">Caractéristiques techniques</h3>' +
          '<dl class="spec-list">' + specs + '</dl>' +
          (colors ? '<h3 class="product-detail__heading">Couleurs disponibles</h3><ul class="swatch-list">' + colors + '</ul>' : '') +
          '<div class="product-detail__buy">' +
            '<div class="product-detail__price"><span class="product-detail__price-label">Tarif</span><strong>' + esc(product.price) + '</strong></div>' +
            '<div class="product-detail__cta">' +
              '<a class="btn btn--gold" href="' + href + '" data-action="quote" data-type="' + esc(product.type) +
                '" data-product-id="' + esc(product.id) + '">Demander un devis</a>' +
              '<span class="product-detail__cta-note">Vous serez redirigé vers le ' + esc(quoteLabel) + '.</span>' +
            '</div>' +
          '</div>' +
          '<button type="button" class="link-btn" data-modal-close>Retour au catalogue</button>' +
        '</div>' +
      '</div>' +
      (more
        ? '<section class="product-more" aria-labelledby="product-more-title">' +
            '<h3 id="product-more-title" class="product-more__title">Créations complémentaires suggérées</h3>' +
            '<ul class="product-more__list">' + more + '</ul>' +
          '</section>'
        : '');

    content.querySelectorAll('[data-hex]').forEach(function (el) {
      el.style.setProperty('--swatch', el.getAttribute('data-hex'));
    });
  }

  /** Ouvre (ou met à jour) le détail d'un produit. */
  function open(id) {
    const product = byId(id);
    if (!product || !modal) return;
    currentId = product.id;
    render(product);
    if (MDS.modal.isOpen(modal)) {
      const dialog = modal.querySelector('[role="dialog"]');
      if (dialog) dialog.scrollTop = 0;
    } else {
      MDS.modal.open(modal);
    }
  }

  function close() {
    if (modal) MDS.modal.close(modal);
  }

  function init() {
    modal = document.getElementById(MODAL_ID);
    content = modal && modal.querySelector('[data-product-detail]');
    if (!modal || !content) return;

    MDS.api.getProducts().then(function (list) { products = list; });

    document.addEventListener('click', function (event) {
      const trigger = event.target.closest('[data-product-open]');
      if (!trigger) return;
      event.preventDefault();
      open(trigger.getAttribute('data-product-open'));
    });
    document.addEventListener('product:open', function (event) {
      if (event.detail) open(event.detail.id);
    });
    modal.addEventListener('modal:close', function () { currentId = null; });
  }

  MDS.productDetails = { init, open, close, current: function () { return currentId; } };
})((window.MDS = window.MDS || {}));
