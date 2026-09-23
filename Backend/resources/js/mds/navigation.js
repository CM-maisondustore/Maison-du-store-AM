/* ==========================================================================
   navigation.js
   Navigation du site :
   - liens internes et défilement entre les sections ;
   - boutons « Demander un devis » et « Prendre rendez-vous » ;
   - retour au catalogue ;
   - repère de la section visible dans le menu.

   Attributs pris en charge (délégation d'événements sur document) :
     data-action="quote"               [data-type="store|rideau"] [data-product-id]
     data-action="appointment"
     data-action="catalogue"           [data-category="stores|rideaux|..."]
     data-action="back-to-catalogue"
   ========================================================================== */
(function (MDS) {
  'use strict';

  // Les vues Blade fournissent ces URLs nommées ; les pages HTML conservent
  // leurs chemins historiques pour le mode maquette autonome.
  const routes = (MDS.config && MDS.config.routes) || {};
  const PAGE_QUOTE = routes.quote || 'devis.html';
  const PAGE_HOME = routes.home || 'index.html';

  // Sections sans lien propre dans le menu : on repère le lien le plus proche.
  const SECTION_ALIAS = { 'rendez-vous': 'contact' };

  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  /** Fait défiler jusqu'à une section de la page courante. Renvoie false si elle n'existe pas. */
  function scrollToSection(id, options) {
    const target = document.getElementById(id);
    if (!target) return false;
    const opts = Object.assign({ updateHash: true, focus: true }, options);

    target.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });

    if (opts.updateHash && window.history && history.replaceState) {
      history.replaceState(null, '', '#' + id);
    }
    if (opts.focus) {
      // Permet aux lecteurs d'écran de suivre le déplacement sans piéger le clavier.
      if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
      target.focus({ preventScroll: true });
    }
    return true;
  }

  /** Ouvre le parcours devis. type : "store" | "rideau" ; productId pré-remplit le modèle. */
  function goToQuote(options) {
    const opts = options || {};
    const params = new URLSearchParams();
    if (opts.type) params.set('type', opts.type);
    if (opts.productId !== undefined && opts.productId !== null) params.set('product', String(opts.productId));
    const query = params.toString();
    window.location.href = PAGE_QUOTE + (query ? '?' + query : '');
  }

  function goToAppointment() {
    if (!scrollToSection('rendez-vous')) window.location.href = PAGE_HOME + '#rendez-vous';
  }

  /** Retourne au catalogue ; category ("stores", "rideaux"...) pré-sélectionne un onglet. */
  function goToCatalogue(options) {
    const category = options && options.category;
    if (category) document.dispatchEvent(new CustomEvent('catalogue:show', { detail: { category: category } }));
    if (!scrollToSection('catalogue')) window.location.href = PAGE_HOME + '#catalogue';
  }

  /* --- Clics ------------------------------------------------------------------ */

  function isPlainLeftClick(event) {
    return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
  }

  function onClick(event) {
    if (!isPlainLeftClick(event) || event.defaultPrevented) return;

    const trigger = event.target.closest('[data-action]');
    if (trigger) {
      const action = trigger.getAttribute('data-action');

      if (action === 'quote') {
        // Un simple lien vers devis.html reste un lien normal ; seul un produit ou un type déclenche le script.
        if (trigger.dataset.type || trigger.dataset.productId) {
          event.preventDefault();
          goToQuote({ type: trigger.dataset.type, productId: trigger.dataset.productId });
        }
        return;
      }
      if (action === 'appointment') {
        event.preventDefault();
        goToAppointment();
        return;
      }
      if (action === 'catalogue') {
        event.preventDefault();
        goToCatalogue({ category: trigger.dataset.category });
        return;
      }
      if (action === 'back-to-catalogue') {
        event.preventDefault();
        if (MDS.modal) MDS.modal.closeAll();
        goToCatalogue();
        return;
      }
    }

    // Ancres internes (#section) : défilement doux
    const link = event.target.closest('a[href^="#"]');
    if (link) {
      const id = link.getAttribute('href').slice(1);
      if (id && scrollToSection(id)) event.preventDefault();
    }
  }

  /* --- Section active dans le menu ----------------------------------------- */

  function initActiveLinks() {
    if (!('IntersectionObserver' in window)) return;
    const links = Array.prototype.slice.call(document.querySelectorAll('[data-nav-link]'));
    const byId = {};
    links.forEach(function (link) {
      const href = link.getAttribute('href') || '';
      if (href.charAt(0) === '#') byId[href.slice(1)] = link;
    });
    const sections = Object.keys(byId)
      .concat(Object.keys(SECTION_ALIAS))
      .map(function (id) { return document.getElementById(id); })
      .filter(Boolean);
    if (!sections.length) return;

    function setActive(id) {
      const linkId = byId[id] ? id : SECTION_ALIAS[id];
      links.forEach(function (link) {
        if (byId[linkId] === link) link.setAttribute('aria-current', 'true');
        else link.removeAttribute('aria-current');
      });
    }

    const observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) setActive(entry.target.id);
        });
      },
      { rootMargin: '-45% 0px -50% 0px', threshold: 0 }
    );
    sections.forEach(function (section) { observer.observe(section); });
  }

  function init() {
    document.addEventListener('click', onClick);
    initActiveLinks();
  }

  MDS.navigation = { init, scrollToSection, goToQuote, goToAppointment, goToCatalogue };
})((window.MDS = window.MDS || {}));
