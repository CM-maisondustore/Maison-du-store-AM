/* ==========================================================================
   devis/quote-store.js
   Fichier exclusivement consacré au DEVIS STORE.
   Fournit les données (modèles, pose, commande, couleurs/finitions) et le
   rendu de chaque étape propre au store. La navigation entre étapes, l'état
   général et l'envoi restent dans quote.js ; la validation reste dans
   quote-validation.js.
   ========================================================================== */
(function (MDS) {
  'use strict';

  const esc = function (text) { return MDS.validation.escapeHtml(text); };

  const MODELS = [
    { id: 'enrouleur', name: 'Store enrouleur', text: 'Toile enroulée, sobre et facile à vivre, idéale pour vos baies vitrées.' },
    { id: 'venitien', name: 'Store vénitien', text: 'Lames orientables très précises pour doser la lumière au degré près.' },
    { id: 'californien', name: 'Store californien', text: 'Bandes alternées qui structurent la lumière du jour à la nuit.' },
    { id: 'plisse', name: 'Store plissé', text: 'Compact et thermique, régule légèrement la température de la pièce.' },
    { id: 'bateau', name: 'Store bateau', text: 'Plis retenus par un drapé souple, pour une allure décorative.' }
  ];

  const ORDER_TYPES = [
    { id: 'installation', label: 'Nouvelle installation' },
    { id: 'remplacement', label: 'Remplacement' },
    { id: 'renovation', label: 'Rénovation' }
  ];

  const MOUNTS = [
    { id: 'murale', icon: 'i-home', label: 'Pose murale', text: 'Fixation directe dans la façade, idéale pour une pose apparente.' },
    { id: 'plafond', icon: 'i-building', label: 'Pose plafond', text: 'Fixation au-dessus de la fenêtre, pour un rendu discret et propre.' },
    { id: 'encastree', icon: 'i-blinds', label: 'Pose en feuillure', text: 'Intégration discrète dans le cadre, pour les fenêtres adaptées.' },
    { id: 'inconnu', icon: 'i-info', label: 'Je ne sais pas', text: 'Nos conseillers valident le type de pose lors de l\u2019étude à domicile.' }
  ];

  const CONTROLS = [
    { id: 'manuelle', icon: 'i-tools', label: 'Commande manuelle', text: 'Cordon, chaînette ou tige de manœuvre classique et fiable.' },
    { id: 'motorisee', icon: 'i-bolt', label: 'Commande motorisée', text: 'Ouverture et fermeture automatisées, pilotables à distance.' },
    { id: 'inconnu', icon: 'i-info', label: 'Je ne sais pas', text: 'Nous vous conseillons selon votre installation électrique.' }
  ];

  const POWER_SOURCES = [
    { id: 'filaire', label: 'Filaire / Secteur', text: 'Raccordement électrique classique.' },
    { id: 'batterie', label: 'Batterie rechargeable', text: 'Sans fil, recharge périodique.' },
    { id: 'solaire', label: 'Panneau solaire', text: 'Autonome, recharge par la lumière du jour.' }
  ];

  const FINISHES = [
    { id: 'voile', label: 'Voile suédé', text: 'Toucher doux et velouté, tenue impeccable.' },
    { id: 'oxford', label: 'Oxford', text: 'Tissage serré, aspect mat et régulier.' },
    { id: 'technique', label: 'Toile technique Sortex\u00AE', text: 'Résistance renforcée pour un usage intensif.' }
  ];

  function findLabel(list, id) {
    const item = (list || []).filter(function (x) { return x.id === id; })[0];
    return item ? (item.label || item.name) : '';
  }

  /* --- Étape 1 : modèle de store ------------------------------------------- */

  function renderModel(container, state) {
    container.innerHTML =
      '<h2 class="step__heading">Quel modèle de store souhaitez-vous ?</h2>' +
      '<p class="step__intro">Sélectionnez le type de confection idéal pour vos fenêtres.</p>' +
      '<div class="choice-list" role="radiogroup" aria-label="Modèle de store">' +
      MODELS.map(function (m) {
        const checked = state.store.model === m.id;
        return (
          '<label class="choice-card' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="store-model" value="' + m.id + '"' + (checked ? ' checked' : '') + '>' +
            '<span class="choice-card__media"><span class="image-placeholder image-placeholder--thumb" aria-hidden="true"></span></span>' +
            '<span class="choice-card__body"><span class="choice-card__title">' + esc(m.name) + '</span>' +
              '<span class="choice-card__text">' + esc(m.text) + '</span></span>' +
            '<span class="choice-card__check" aria-hidden="true"><svg class="icon icon--sm"><use href="#i-check"/></svg></span>' +
          '</label>'
        );
      }).join('') + '</div>';
  }

  function collectModel(container) {
    const checked = container.querySelector('input[name="store-model"]:checked');
    return { model: checked ? checked.value : '' };
  }

  /* --- Étape 2 : type de pose ------------------------------------------------ */

  function renderMount(container, state) {
    container.innerHTML =
      '<h2 class="step__heading">Choisissez votre type de pose</h2>' +
      '<p class="step__intro">Le type de fixage détermine l\u2019encombrement final et le rendu visuel.</p>' +
      '<div class="option-grid" role="radiogroup" aria-label="Type de pose">' +
      MOUNTS.map(function (o) {
        const checked = state.store.mount === o.id;
        return (
          '<label class="option-tile' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="store-mount" value="' + o.id + '"' + (checked ? ' checked' : '') + '>' +
            '<svg class="icon icon--lg" aria-hidden="true"><use href="#' + o.icon + '"/></svg>' +
            '<span class="option-tile__title">' + esc(o.label) + '</span>' +
            '<span class="option-tile__text">' + esc(o.text) + '</span>' +
          '</label>'
        );
      }).join('') + '</div>' +
      '<div class="notice notice--info"><svg class="icon" aria-hidden="true"><use href="#i-info"/></svg>' +
      '<span>Besoin d\u2019aide pour l\u2019installation ? Nos équipes peuvent réaliser une visite technique avant la fabrication.</span></div>' +
      '<div class="field form-grid__full" data-field="orderType"><label class="field__label" for="store-order-type">Type de commande</label>' +
      '<select class="select" id="store-order-type" name="orderType">' + ORDER_TYPES.map(function (o) {
        return '<option value="' + o.id + '"' + (state.store.orderType === o.id ? ' selected' : '') + '>' + esc(o.label) + '</option>';
      }).join('') + '</select></div>';
  }

  function collectMount(container) {
    const checked = container.querySelector('input[name="store-mount"]:checked');
    const select = container.querySelector('#store-order-type');
    return { mount: checked ? checked.value : '', orderType: select ? select.value : '' };
  }

  /* --- Étape 3 : commande / motorisation -------------------------------------- */

  function renderControl(container, state) {
    const powerVisible = state.store.control === 'motorisee';
    const power =
      '<div class="field form-grid__full" data-field="powerSource" data-power-block' + (powerVisible ? '' : ' hidden') + '>' +
      '<label class="field__label">Type d\u2019alimentation retenu</label>' +
      '<div class="option-grid option-grid--compact" role="radiogroup" aria-label="Alimentation">' +
      POWER_SOURCES.map(function (p) {
        const checked = state.store.powerSource === p.id;
        return (
          '<label class="option-tile option-tile--sm' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="store-power" value="' + p.id + '"' + (checked ? ' checked' : '') + '>' +
            '<span class="option-tile__title">' + esc(p.label) + '</span><span class="option-tile__text">' + esc(p.text) + '</span>' +
          '</label>'
        );
      }).join('') + '</div></div>';

    container.innerHTML =
      '<h2 class="step__heading">Type de manœuvre &amp; commande</h2>' +
      '<p class="step__intro">Sélectionnez comment vous souhaitez manœuvrer vos stores sur mesure.</p>' +
      '<div class="choice-list" role="radiogroup" aria-label="Type de commande">' +
      CONTROLS.map(function (c) {
        const checked = state.store.control === c.id;
        return (
          '<label class="choice-card choice-card--compact' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="store-control" value="' + c.id + '"' + (checked ? ' checked' : '') + '>' +
            '<span class="choice-card__icon"><svg class="icon" aria-hidden="true"><use href="#' + c.icon + '"/></svg></span>' +
            '<span class="choice-card__body"><span class="choice-card__title">' + esc(c.label) + '</span>' +
              '<span class="choice-card__text">' + esc(c.text) + '</span></span>' +
            '<span class="choice-card__check" aria-hidden="true"><svg class="icon icon--sm"><use href="#i-check"/></svg></span>' +
          '</label>'
        );
      }).join('') + '</div>' + power;

    // Le bloc « alimentation » n'apparaît que si la commande motorisée est choisie ;
    // pas de nouvel appel à render() ici, pour ne perdre aucune autre saisie de l'étape.
    container.addEventListener('change', function (event) {
      if (!event.target.matches('input[name="store-control"]')) return;
      const block = container.querySelector('[data-power-block]');
      if (!block) return;
      const show = event.target.value === 'motorisee';
      block.hidden = !show;
      if (!show) {
        block.querySelectorAll('input[name="store-power"]').forEach(function (input) { input.checked = false; });
      }
    });
  }

  function collectControl(container) {
    const checked = container.querySelector('input[name="store-control"]:checked');
    const power = container.querySelector('input[name="store-power"]:checked');
    return { control: checked ? checked.value : '', powerSource: power ? power.value : '' };
  }

  /* --- Étape 4 : matières, couleurs & finitions -------------------------------- */

  function renderFinish(container, state) {
    const colors = (MDS.data.colors || []).filter(function (c) { return c.types.indexOf('store') !== -1; });
    container.innerHTML =
      '<h2 class="step__heading">Matières, couleurs &amp; finitions</h2>' +
      '<p class="step__intro">Personnalisez l\u2019aspect final de votre store avec nos finitions artisanales.</p>' +
      '<div class="field" data-field="color"><label class="field__label">Couleur de la toile</label>' +
      '<div class="swatch-row swatch-row--lg" role="radiogroup" aria-label="Couleur">' +
      colors.map(function (c) {
        const checked = state.store.color === c.id;
        return (
          '<label class="swatch-choice' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="store-color" value="' + c.id + '"' + (checked ? ' checked' : '') + '>' +
            '<span class="swatch-choice__dot" style="--swatch:' + c.hex + '"></span>' +
            '<span class="swatch-choice__label">' + esc(c.label) + '</span>' +
          '</label>'
        );
      }).join('') + '</div></div>' +
      '<div class="field form-grid__full" data-field="finish"><label class="field__label">Type de toile</label>' +
      '<div class="option-grid option-grid--compact" role="radiogroup" aria-label="Type de toile">' +
      FINISHES.map(function (f) {
        const checked = state.store.finish === f.id;
        return (
          '<label class="option-tile option-tile--sm' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="store-finish" value="' + f.id + '"' + (checked ? ' checked' : '') + '>' +
            '<span class="option-tile__title">' + esc(f.label) + '</span><span class="option-tile__text">' + esc(f.text) + '</span>' +
          '</label>'
        );
      }).join('') + '</div></div>';
  }

  function collectFinish(container) {
    const color = container.querySelector('input[name="store-color"]:checked');
    const finish = container.querySelector('input[name="store-finish"]:checked');
    return { color: color ? color.value : '', finish: finish ? finish.value : '' };
  }

  /* --- Déclaration des étapes, lues par quote.js ------------------------------- */

  const STEPS = [
    { id: 'store-model', title: 'Modèle', render: renderModel, collect: collectModel },
    { id: 'store-mount', title: 'Type de pose', render: renderMount, collect: collectMount },
    { id: 'store-control', title: 'Commande', render: renderControl, collect: collectControl },
    { id: 'store-finish', title: 'Matières &amp; finitions', render: renderFinish, collect: collectFinish }
  ];

  function labels(store) {
    return {
      model: findLabel(MODELS, store.model),
      orderType: findLabel(ORDER_TYPES, store.orderType),
      mount: findLabel(MOUNTS, store.mount),
      control: findLabel(CONTROLS, store.control),
      powerSource: findLabel(POWER_SOURCES, store.powerSource),
      color: findLabel(MDS.data.colors, store.color),
      finish: findLabel(FINISHES, store.finish)
    };
  }

  MDS.quoteStore = { STEPS, MODELS, ORDER_TYPES, MOUNTS, CONTROLS, POWER_SOURCES, FINISHES, labels };
})((window.MDS = window.MDS || {}));
