/* ==========================================================================
   utils/modal.js
   Modales génériques : ouverture, fermeture, clic à l'extérieur, touche
   Échap, piégeage du focus et retour du focus à l'élément d'origine.

   Structure attendue :
   <div class="modal" id="..." hidden>
     <div class="modal__backdrop" data-modal-close></div>
     <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="...">
       <button class="modal__close" data-modal-close>...</button>
       ...
     </div>
   </div>
   ========================================================================== */
(function (MDS) {
  'use strict';

  const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  const stack = []; // modales ouvertes, la dernière est au premier plan
  const state = new WeakMap(); // modale -> { opener, onClose }
  let bound = false;

  function resolve(target) {
    return typeof target === 'string' ? document.getElementById(target) : target;
  }

  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function getDialog(modal) {
    return modal.querySelector('[role="dialog"]') || modal;
  }

  function isOpen(target) {
    const modal = resolve(target);
    return !!modal && stack.indexOf(modal) !== -1;
  }

  /**
   * Ouvre une modale.
   * @param {string|HTMLElement} target
   * @param {{onClose?: Function}} [options]
   */
  function open(target, options) {
    const modal = resolve(target);
    if (!modal || isOpen(modal)) return;

    state.set(modal, {
      opener: document.activeElement,
      onClose: options && options.onClose
    });

    modal.hidden = false;
    void modal.offsetWidth; // force le rendu pour lancer la transition
    modal.classList.add('is-open');
    stack.push(modal);
    document.documentElement.classList.add('has-modal');

    const dialog = getDialog(modal);
    if (!dialog.hasAttribute('tabindex')) dialog.setAttribute('tabindex', '-1');
    dialog.focus({ preventScroll: true });
    dialog.scrollTop = 0;

    modal.dispatchEvent(new CustomEvent('modal:open', { bubbles: true }));
  }

  /** Ferme une modale (la plus au premier plan si aucune n'est précisée). */
  function close(target) {
    const modal = target ? resolve(target) : stack[stack.length - 1];
    if (!modal || !isOpen(modal)) return;

    stack.splice(stack.indexOf(modal), 1);
    modal.classList.remove('is-open');

    const finish = function () {
      if (!modal.classList.contains('is-open')) modal.hidden = true;
    };
    if (prefersReducedMotion()) finish();
    else window.setTimeout(finish, 240);

    if (!stack.length) document.documentElement.classList.remove('has-modal');

    const info = state.get(modal) || {};
    if (info.opener && typeof info.opener.focus === 'function' && document.contains(info.opener)) {
      info.opener.focus({ preventScroll: true });
    }
    if (typeof info.onClose === 'function') info.onClose(modal);
    modal.dispatchEvent(new CustomEvent('modal:close', { bubbles: true }));
  }

  function closeAll() {
    while (stack.length) close(stack[stack.length - 1]);
  }

  function trapFocus(event, modal) {
    const items = Array.prototype.filter.call(modal.querySelectorAll(FOCUSABLE), function (el) {
      return el.offsetParent !== null || el === document.activeElement;
    });
    if (!items.length) {
      event.preventDefault();
      return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;
    const dialog = getDialog(modal);

    if (event.shiftKey && (active === first || active === dialog)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function onKeydown(event) {
    if (!stack.length) return;
    const top = stack[stack.length - 1];
    if (event.key === 'Escape') {
      event.preventDefault();
      close(top);
    } else if (event.key === 'Tab') {
      trapFocus(event, top);
    }
  }

  function onClick(event) {
    const closer = event.target.closest('[data-modal-close]');
    if (closer) {
      const modal = closer.closest('.modal');
      if (modal) close(modal);
      return;
    }
    // Clic sur la zone autour du dialogue
    if (event.target.classList && event.target.classList.contains('modal')) close(event.target);
  }

  function init() {
    if (bound) return;
    bound = true;
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('click', onClick);
  }

  MDS.modal = { init, open, close, closeAll, isOpen };
})((window.MDS = window.MDS || {}));
