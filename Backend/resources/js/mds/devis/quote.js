/* ==========================================================================
   devis/quote.js
   Fonctionnement général des devis : type de devis sélectionné,
   initialisation, navigation entre les étapes, récupération des données,
   envoi vers le récapitulatif puis vers MDS.api.

   Ne contient PAS les champs spécifiques Store (quote-store.js) ou Rideau
   (quote-curtain.js) : ce fichier compose les étapes que ces modules
   fournissent avec l'étape commune « coordonnées » et l'étape « dimensions »
   (dimensions.js), puis délègue la validation à quote-validation.js et le
   récapitulatif à quote-summary.js.
   ========================================================================== */
(function (MDS) {
  'use strict';

  const esc = function (text) { return MDS.validation.escapeHtml(text); };

  /** État complet d'une demande de devis en cours de saisie. */
  function emptyState() {
    return {
      type: null, // "store" | "rideau"
      contact: { civility: '', lastName: '', firstName: '', email: '', phone: '', address: '', postalCode: '', city: '', notes: '' },
      store: { model: '', orderType: '', mount: '', control: '', powerSource: '', color: '', finish: '' },
      curtain: { model: '', fabric: '', color: '', pattern: '', lining: '', finish: '', hanging: '' },
      dimensions: [],
      acceptTerms: false
    };
  }

  let state = emptyState();
  let steps = [];
  let index = 0;
  let dimensionsManager = null;
  let submitting = false;
  let els = {};

  /* --- Étape « Vos informations personnelles » (commune aux deux parcours) ------- */

  function renderContact(container, s) {
    const c = s.contact;
    container.innerHTML =
      '<h2 class="step__heading">Vos informations personnelles</h2>' +
      '<p class="step__intro">Renseignez vos coordonnées pour recevoir votre projet de rideaux sur mesure d\u2019Atelier.</p>' +
      '<div class="field form-grid__full" data-field="civility"><span class="field__label">Civilité</span>' +
        '<div class="pill-group">' +
          ['m', 'mme'].map(function (id) {
            const label = id === 'm' ? 'M.' : 'Mme';
            return '<label class="pill"><input type="radio" name="civility" value="' + id + '"' + (c.civility === id ? ' checked' : '') +
              '><span>' + label + '</span></label>';
          }).join('') +
        '</div></div>' +
      '<div class="form-grid">' +
        field('lastName', 'Nom', c.lastName, 'text', 'Dupont') +
        field('firstName', 'Prénom', c.firstName, 'text', 'Jean') +
        field('email', 'Adresse e-mail (facultatif)', c.email, 'email', 'jean.dupont@email.com') +
        field('phone', 'Téléphone', c.phone, 'tel', '07 12 34 56 78') +
        field('address', 'Adresse (rue et numéro)', c.address, 'text', '14 Rue de la Paix', true) +
        field('postalCode', 'Code postal', c.postalCode, 'text', 'Facultatif') +
        field('city', 'Ville', c.city, 'text', 'Abidjan') +
      '</div>' +
      '<div class="field form-grid__full" data-field="notes"><label class="field__label" for="quote-notes">Précisions sur votre projet <small>(facultatif)</small></label>' +
      '<textarea class="textarea" id="quote-notes" name="notes" placeholder="Ex. Je souhaite équiper 3 fenêtres de mon salon avec des rideaux occultants...">' + esc(c.notes) + '</textarea></div>';
  }

  function field(name, label, value, type, placeholder, full) {
    return (
      '<div class="field' + (full ? ' form-grid__full' : '') + '" data-field="' + name + '">' +
      '<label class="field__label" for="quote-' + name + '">' + esc(label) + '</label>' +
      '<input class="input" id="quote-' + name + '" name="' + name + '" type="' + type + '" value="' + esc(value) + '" placeholder="' + esc(placeholder) + '"></div>'
    );
  }

  function collectContact(container) {
    const civility = container.querySelector('input[name="civility"]:checked');
    const read = function (name) {
      const el = container.querySelector('[name="' + name + '"]');
      return el ? el.value.trim() : '';
    };
    return {
      civility: civility ? civility.value : '',
      lastName: read('lastName'), firstName: read('firstName'), email: read('email'), phone: read('phone'),
      address: read('address'), postalCode: read('postalCode'), city: read('city'), notes: read('notes')
    };
  }

  /* --- Étape « Dimensions » (commune, déléguée à dimensions.js) -------------------- */

  function renderDimensions(container, s) {
    container.innerHTML =
      '<h2 class="step__heading">Saisissez les dimensions de vos fenêtres</h2>' +
      '<p class="step__intro">Prenez les mesures intérieures au mm près pour une confection sur mesure au cordeau.</p>' +
      '<div data-dimensions-root></div>';
    dimensionsManager = MDS.dimensions.create(container.querySelector('[data-dimensions-root]'), {
      initial: s.dimensions,
      suggestion: 'Nos conseillers vérifient systématiquement vos mesures lors de la prise de rendez-vous à domicile.'
    });
  }

  function collectDimensions() {
    return dimensionsManager ? dimensionsManager.getWindows() : state.dimensions;
  }

  /* --- Étape « Récapitulatif » ------------------------------------------------------ */

  function renderRecap(container, s) {
    MDS.quoteSummary.renderRecap(container, s);
  }

  function collectRecap(container) {
    const checkbox = container.querySelector('#quote-accept');
    return { acceptTerms: checkbox ? checkbox.checked : false };
  }

  /* --- Construction de la liste d'étapes selon le type ------------------------------ */

  function buildSteps(type) {
    const module = type === 'store' ? MDS.quoteStore : MDS.quoteCurtain;
    const list = [
      { id: 'contact', title: 'Vos coordonnées', render: renderContact, collect: collectContact,
        validate: function (data) { return MDS.quoteValidation.validateContact(data); }, target: 'contact' }
    ];
    module.STEPS.slice(0, 1).forEach(function (step) { list.push(moduleStep(step, type)); }); // modèle
    list.push({ id: 'dimensions', title: 'Dimensions', render: renderDimensions, collect: collectDimensions,
      validate: function (data) { return MDS.quoteValidation.validateDimensions(data); }, target: 'dimensions' });
    module.STEPS.slice(1).forEach(function (step) { list.push(moduleStep(step, type)); }); // reste des étapes
    list.push({ id: 'recap', title: 'Récapitulatif', render: renderRecap, collect: collectRecap,
      validate: function (data) { return MDS.quoteValidation.validateSubmission(Object.assign({}, state, data)); }, target: 'meta', isRecap: true });
    return list;
  }

  function moduleStep(step, type) {
    return {
      id: step.id, title: step.title,
      render: function (container, s) { step.render(container, s); },
      collect: step.collect,
      validate: function (data) {
        return type === 'store' ? MDS.quoteValidation.validateStoreStep(step.id, data) : MDS.quoteValidation.validateCurtainStep(step.id, data);
      },
      target: type
    };
  }

  /* --- Recherche d'un modèle à partir du produit choisi dans le catalogue ------------ */

  function guessModelId(type, quoteModelLabel) {
    if (!quoteModelLabel) return '';
    const target = MDS.validation.normalizeText(quoteModelLabel);
    const list = (type === 'store' ? MDS.quoteStore.MODELS : MDS.quoteCurtain.MODELS);
    const found = list.filter(function (m) { return target.indexOf(MDS.validation.normalizeText(m.name)) !== -1; })[0];
    return found ? found.id : '';
  }

  /* --- Rendu de la mise en page (progression, étape, sidebar) ------------------------ */

  function renderProgress() {
    if (!els.progress) return;
    const total = steps.length;
    const current = index + 1;
    els.progressText.textContent = 'Étape ' + current + ' sur ' + total;
    els.progressBar.innerHTML = steps.map(function (step, i) {
      const state2 = i < index ? 'done' : i === index ? 'current' : 'upcoming';
      return '<li class="progress-bar__item progress-bar__item--' + state2 + '"><span></span></li>';
    }).join('');
  }

  function renderStep() {
    const step = steps[index];
    if (!step || !els.step) return;
    step.render(els.step, state);
    MDS.notifications.fieldError.clearAll(els.step);
    renderProgress();
    els.back.hidden = false;
    els.next.textContent = step.isRecap ? 'Confirmer la demande de devis' : 'Continuer';
    els.step.scrollIntoView({ block: 'start', behavior: 'smooth' });
    if (typeof els.step.focus === 'function') els.step.setAttribute('tabindex', '-1');
    MDS.quoteSummary.renderSidebar(els.sidebar, state);
  }

  function mergeInto(target, patch) {
    if (target === 'contact') Object.assign(state.contact, patch);
    else if (target === 'store') Object.assign(state.store, patch);
    else if (target === 'rideau') Object.assign(state.curtain, patch);
    else if (target === 'dimensions') state.dimensions = patch;
    else if (target === 'meta') Object.assign(state, patch);
  }

  function dataForValidation(step, patch) {
    if (step.target === 'contact') return Object.assign({}, state.contact, patch);
    if (step.target === 'store') return Object.assign({}, state.store, patch);
    if (step.target === 'rideau') return Object.assign({}, state.curtain, patch);
    if (step.target === 'dimensions') return patch;
    return patch;
  }

  function showStepErrors(container, errors) {
    MDS.notifications.fieldError.clearAll(container);
    let firstTarget = null;
    Object.keys(errors).forEach(function (key) {
      const message = errors[key];
      let target = null;
      if (key.indexOf(':') !== -1) {
        const parts = key.split(':');
        target = container.querySelector('[data-window="' + parts[1] + '"] [data-window-field="' + parts[0] + '"]');
      } else {
        target = container.querySelector('[data-field="' + key + '"]') || container.querySelector('[name="' + key + '"]');
      }
      if (target) {
        MDS.notifications.fieldError.show(target, message);
        if (!firstTarget) firstTarget = target;
      }
    });
    if (firstTarget) {
      const focusable = firstTarget.matches('input, select, textarea') ? firstTarget : firstTarget.querySelector('input, select, textarea, button');
      if (focusable) focusable.focus();
      else firstTarget.scrollIntoView({ block: 'center' });
    }
    MDS.notifications.error('Vérifiez les champs signalés avant de continuer.');
  }

  function goNext() {
    const step = steps[index];
    const patch = step.collect(els.step);
    const data = dataForValidation(step, patch);
    const result = step.validate(data);

    if (!result.valid) {
      showStepErrors(els.step, result.errors);
      return;
    }
    mergeInto(step.target, patch);

    if (step.isRecap) {
      submit();
      return;
    }
    index += 1;
    renderStep();
  }

  function goBack() {
    const step = steps[index];
    // Conserve la saisie en cours même si elle est incomplète, pour ne rien perdre en reculant.
    mergeInto(step.target, step.collect(els.step));
    if (index === 0) {
      showIntro();
      return;
    }
    index -= 1;
    renderStep();
  }

  function jumpTo(stepId) {
    const targetIndex = steps.findIndex(function (s) { return s.id === stepId; });
    if (targetIndex === -1) return;
    const step = steps[index];
    mergeInto(step.target, step.collect(els.step));
    index = targetIndex;
    renderStep();
  }

  /* --- Bascule intro / assistant / confirmation --------------------------------------- */

  function showIntro() {
    state.type = null;
    if (els.intro) els.intro.hidden = false;
    if (els.wizard) els.wizard.hidden = true;
    if (els.confirmation) els.confirmation.hidden = true;
    updateQuery();
  }

  function showWizard(type, options) {
    const opts = options || {};
    state.type = type;
    steps = buildSteps(type);
    index = 0;
    if (opts.model) state.store.model = state.curtain.model = '';
    if (type === 'store' && opts.model) state.store.model = opts.model;
    if (type === 'rideau' && opts.model) state.curtain.model = opts.model;

    if (els.intro) els.intro.hidden = true;
    if (els.confirmation) els.confirmation.hidden = true;
    if (els.wizard) els.wizard.hidden = false;
    renderStep();
    updateQuery();
  }

  function updateQuery() {
    if (!window.history || !history.replaceState) return;
    const params = new URLSearchParams(window.location.search);
    if (state.type) params.set('type', state.type); else params.delete('type');
    params.delete('product');
    const query = params.toString();
    history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
  }

  /* --- Envoi ---------------------------------------------------------------------------- */

  function submit() {
    if (submitting) return;
    submitting = true;
    els.next.disabled = true;
    els.next.textContent = 'Envoi en cours…';

    const payload = {
      contact: state.contact,
      product: state.type === 'store' ? state.store : state.curtain,
      dimensions: state.dimensions,
      acceptTerms: state.acceptTerms,
      notes: state.contact.notes,
      submittedAt: new Date().toISOString()
    };
    const send = state.type === 'store' ? MDS.api.submitStoreQuote : MDS.api.submitCurtainQuote;

    send(payload)
      .then(function (result) {
        if (els.wizard) els.wizard.hidden = true;
        if (els.confirmation) {
          MDS.quoteSummary.renderConfirmation(els.confirmation, state, result);
          els.confirmation.hidden = false;
          els.confirmation.setAttribute('tabindex', '-1');
          els.confirmation.focus();
          els.confirmation.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
        if (result.demo) MDS.notifications.info('Demande enregistrée en mode démonstration : elle n\u2019a pas été transmise.');
        else MDS.notifications.success('Votre demande de devis a été envoyée.');
        state = emptyState();
      })
      .catch(function (error) {
        if (error.status === 422 && error.data && error.data.errors) {
          const messages = Object.values(error.data.errors).flat();
          MDS.notifications.error(messages.join(' '));
          return;
        }
        MDS.notifications.error('L\u2019envoi a échoué. Réessayez ou contactez-nous directement.');
      })
      .finally(function () {
        submitting = false;
        if (els.next) { els.next.disabled = false; }
      });
  }

  /* --- Initialisation --------------------------------------------------------------- */

  function resolveType(raw) {
    const value = (raw || '').toLowerCase();
    if (value === 'store' || value === 'stores') return 'store';
    if (value === 'rideau' || value === 'rideaux' || value === 'curtain') return 'rideau';
    return null;
  }

  function init() {
    els = {
      intro: document.getElementById('quote-intro'),
      wizard: document.getElementById('quote-wizard'),
      confirmation: document.getElementById('quote-confirmation'),
      step: document.querySelector('[data-quote-step]'),
      sidebar: document.querySelector('[data-quote-sidebar]'),
      back: document.querySelector('[data-quote-back]'),
      next: document.querySelector('[data-quote-next]'),
      progress: document.querySelector('[data-quote-progress]'),
      progressText: document.querySelector('[data-quote-progress-text]'),
      progressBar: document.querySelector('[data-quote-progress-bar]')
    };
    if (!els.wizard) return;

    MDS.quoteSummary.init(els.wizard);
    if (els.confirmation) MDS.quoteSummary.init(els.confirmation);

    if (els.intro) {
      els.intro.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-quote-start]');
        if (trigger) showWizard(trigger.getAttribute('data-quote-start'));
      });
    }
    if (els.next) els.next.addEventListener('click', goNext);
    if (els.back) els.back.addEventListener('click', goBack);
    document.addEventListener('recap:edit', function (event) { jumpTo(event.detail.stepId); });
    document.addEventListener('quote:start', function (event) {
      showWizard(resolveType(event.detail && event.detail.type) || 'store');
    });

    const params = new URLSearchParams(window.location.search);
    const type = resolveType(params.get('type'));
    const productId = params.get('product');

    if (productId) {
      MDS.api.getProducts().then(function (products) {
        const product = products.filter(function (p) { return String(p.id) === String(productId); })[0];
        if (product) showWizard(product.type, { model: guessModelId(product.type, product.quoteModel) });
        else if (type) showWizard(type);
        else showIntro();
      }).catch(function () { if (type) showWizard(type); else showIntro(); });
    } else if (type) {
      showWizard(type);
    } else {
      showIntro();
    }
  }

  MDS.quote = { init, getState: function () { return state; } };
})((window.MDS = window.MDS || {}));
