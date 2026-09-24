/* ==========================================================================
   app.js
   Point d'entrée général de l'application.
   Initialise les modules nécessaires à la page courante et lance les
   fonctionnalités générales (icônes, notifications, modales, navigation).
   Ne contient aucune logique détaillée du catalogue ou des formulaires :
   celle-ci vit dans js/catalogue/, js/rendez-vous/ et js/devis/.
   ========================================================================== */
(function (MDS) {
  'use strict';

  /** Initialise un module seulement s'il est chargé et présent sur la page. */
  function boot(mod, condition) {
    if (mod && typeof mod.init === 'function' && (condition === undefined || condition)) mod.init();
  }

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    // Toujours présents (en-tête et pied de page communs à toutes les pages)
    boot(MDS.mobileMenu);
    boot(MDS.navigation);
    boot(MDS.modal);

    // Accueil : catalogue résumé, sections mises en avant, produits vedettes
    boot(MDS.categories, document.querySelector('[data-category-chips]'));
    boot(MDS.search, document.getElementById('catalogue-search'));
    boot(MDS.filters, document.querySelector('[data-filters-body]'));
    boot(MDS.catalogue, document.getElementById('catalogue-grid') || document.querySelector('[data-featured]'));
    boot(MDS.productDetails, document.getElementById('product-modal'));

    // Prise de rendez-vous (accueil ou page dédiée)
    boot(MDS.appointment, document.getElementById('appointment-form'));

    // Parcours de devis (devis.html)
    boot(MDS.quote, document.getElementById('quote-wizard'));

    document.dispatchEvent(new CustomEvent('app:ready'));
  });
})((window.MDS = window.MDS || {}));
