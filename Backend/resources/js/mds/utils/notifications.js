/* ==========================================================================
   utils/notifications.js
   Notifications de l'interface :
   - toasts : succès, erreur, information, avertissement ;
   - erreurs de champ affichées sous le champ concerné (fieldError).
   ========================================================================== */
(function (MDS) {
  'use strict';

  const ICONS = {
    success: 'i-check-circle',
    error: 'i-alert',
    info: 'i-info',
    warning: 'i-warning'
  };

  const DURATIONS = { success: 5000, info: 5000, warning: 7000, error: 8000 };
  const MAX_VISIBLE = 3;

  let region = null;
  let counter = 0;

  function getRegion() {
    if (region && document.body.contains(region)) return region;
    region = document.createElement('div');
    region.className = 'toast-region';
    region.setAttribute('role', 'region');
    region.setAttribute('aria-label', 'Notifications');
    region.setAttribute('aria-live', 'polite');
    document.body.appendChild(region);
    return region;
  }

  function dismiss(toast) {
    if (!toast || toast.classList.contains('is-leaving')) return;
    toast.classList.add('is-leaving');
    window.setTimeout(function () {
      toast.remove();
    }, 240);
  }

  /**
   * Affiche une notification.
   * @param {'success'|'error'|'info'|'warning'} type
   * @param {string} message
   * @param {{duration?: number}} [options] duration = 0 pour une notification persistante
   */
  function notify(type, message, options) {
    const kind = ICONS[type] ? type : 'info';
    const opts = options || {};
    const container = getRegion();

    while (container.children.length >= MAX_VISIBLE) {
      container.firstElementChild.remove();
    }

    const toast = document.createElement('div');
    toast.className = 'toast toast--' + kind;
    if (kind === 'error') toast.setAttribute('role', 'alert');

    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('class', 'icon');
    icon.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', '#' + ICONS[kind]);
    icon.appendChild(use);

    const text = document.createElement('p');
    text.className = 'toast__text';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast__close';
    close.setAttribute('aria-label', 'Fermer la notification');
    close.innerHTML = '<svg class="icon icon--sm" aria-hidden="true"><use href="#i-close"/></svg>';
    close.addEventListener('click', function () {
      dismiss(toast);
    });

    toast.append(icon, text, close);
    container.appendChild(toast);

    const duration = typeof opts.duration === 'number' ? opts.duration : DURATIONS[kind];
    if (duration > 0) window.setTimeout(function () { dismiss(toast); }, duration);
    return toast;
  }

  const success = function (message, options) { return notify('success', message, options); };
  const error = function (message, options) { return notify('error', message, options); };
  const info = function (message, options) { return notify('info', message, options); };
  const warning = function (message, options) { return notify('warning', message, options); };

  /* --- Erreurs de champ --------------------------------------------------- */

  function findField(target) {
    if (!target) return null;
    if (target.classList && target.classList.contains('field')) return target;
    return target.closest ? target.closest('.field') : null;
  }

  /** Renvoie le contrôle à marquer aria-invalid (un seul input/select/textarea dans le champ). */
  function findControl(target, field) {
    if (target.matches && target.matches('input, select, textarea')) return target;
    const controls = field.querySelectorAll('input, select, textarea');
    return controls.length === 1 ? controls[0] : null;
  }

  function addDescribedBy(el, id) {
    const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    if (ids.indexOf(id) === -1) ids.push(id);
    el.setAttribute('aria-describedby', ids.join(' '));
  }

  function removeDescribedBy(el, id) {
    const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (x) {
      return x && x !== id;
    });
    if (ids.length) el.setAttribute('aria-describedby', ids.join(' '));
    else el.removeAttribute('aria-describedby');
  }

  const fieldError = {
    /** Affiche un message d'erreur sous le champ (élément .field, ou un contrôle qu'il contient). */
    show: function (target, message) {
      const field = findField(target);
      if (!field) return;
      let err = field.querySelector(':scope > .field__error');
      if (!err) {
        err = document.createElement('p');
        err.className = 'field__error';
        err.id = 'field-error-' + (++counter);
        field.appendChild(err);
      }
      err.textContent = message;
      err.hidden = false;
      field.classList.add('is-invalid');

      const control = findControl(target, field);
      if (control) {
        control.setAttribute('aria-invalid', 'true');
        addDescribedBy(control, err.id);
      } else {
        addDescribedBy(field, err.id);
      }
    },

    /** Retire l'erreur d'un champ. */
    clear: function (target) {
      const field = findField(target);
      if (!field) return;
      const err = field.querySelector(':scope > .field__error');
      field.classList.remove('is-invalid');
      field.querySelectorAll('[aria-invalid]').forEach(function (el) {
        el.removeAttribute('aria-invalid');
        if (err) removeDescribedBy(el, err.id);
      });
      if (err) {
        removeDescribedBy(field, err.id);
        err.remove();
      }
    },

    /** Retire toutes les erreurs d'un conteneur (formulaire, étape...). */
    clearAll: function (container) {
      if (!container) return;
      container.querySelectorAll('.field.is-invalid').forEach(function (field) {
        fieldError.clear(field);
      });
    }
  };

  MDS.notifications = { notify, success, error, info, warning, dismiss, fieldError };
})((window.MDS = window.MDS || {}));
