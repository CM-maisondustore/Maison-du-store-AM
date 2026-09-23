/* ==========================================================================
   mobile-menu.js
   Uniquement le menu mobile : bouton hamburger, ouverture, fermeture,
   fermeture après le choix d'un lien, état aria-expanded.

   Balisage attendu :
     <button data-menu-toggle aria-controls="site-nav" aria-expanded="false">
     <nav id="site-nav"> ... </nav>
   Les styles du menu replié se trouvent dans css/responsive.css.
   ========================================================================== */
(function (MDS) {
  'use strict';

  const DESKTOP_QUERY = '(min-width: 1025px)';

  let toggle = null;
  let panel = null;

  function isOpen() {
    return !!toggle && toggle.getAttribute('aria-expanded') === 'true';
  }

  function setLabel(open) {
    const label = toggle.querySelector('.visually-hidden');
    if (label) label.textContent = open ? 'Fermer le menu' : 'Ouvrir le menu';
  }

  function open() {
    if (!toggle || !panel) return;
    toggle.setAttribute('aria-expanded', 'true');
    panel.classList.add('is-open');
    setLabel(true);
  }

  function close(options) {
    if (!toggle || !panel) return;
    toggle.setAttribute('aria-expanded', 'false');
    panel.classList.remove('is-open');
    setLabel(false);
    if (options && options.returnFocus) toggle.focus();
  }

  function onToggleClick() {
    if (isOpen()) close();
    else open();
  }

  function onPanelClick(event) {
    // Fermeture après sélection d'un lien ou d'un bouton d'action
    if (event.target.closest('a, button')) close();
  }

  function onKeydown(event) {
    if (event.key === 'Escape' && isOpen()) close({ returnFocus: true });
  }

  function onDocumentClick(event) {
    if (isOpen() && !event.target.closest('[data-site-header]')) close();
  }

  function init() {
    toggle = document.querySelector('[data-menu-toggle]');
    if (!toggle) return;
    panel = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!panel) return;

    toggle.addEventListener('click', onToggleClick);
    panel.addEventListener('click', onPanelClick);
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('click', onDocumentClick);

    // Repasser en affichage bureau referme le menu
    if (window.matchMedia) {
      const query = window.matchMedia(DESKTOP_QUERY);
      const onChange = function (e) { if (e.matches) close(); };
      if (query.addEventListener) query.addEventListener('change', onChange);
      else if (query.addListener) query.addListener(onChange);
    }
  }

  MDS.mobileMenu = { init, open, close, isOpen };
})((window.MDS = window.MDS || {}));
