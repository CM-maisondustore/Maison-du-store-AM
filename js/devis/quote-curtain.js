/* ==========================================================================
   devis/quote-curtain.js
   Fichier exclusivement consacré au DEVIS RIDEAU.
   Même principe que quote-store.js : données et rendu des étapes propres au
   rideau. Navigation et état dans quote.js ; validation dans
   quote-validation.js.
   ========================================================================== */
(function (MDS) {
  'use strict';

  const esc = function (text) { return MDS.validation.escapeHtml(text); };

  const MODELS = [
    { id: 'occultant', name: 'Rideau occultant', text: 'Tissu dense qui bloque la lumière du jour, idéal pour les chambres.' },
    { id: 'voilage', name: 'Voilage d\u2019Atelier', text: 'Lumière douce et tamisée, un tissu léger qui laisse passer le jour.' },
    { id: 'double', name: 'Double rideau', text: 'Superposition d\u2019un voilage et d\u2019un rideau pour un rendu élaboré.' },
    { id: 'automatique', name: 'Rideau automatique', text: 'Ouverture et fermeture pilotées à distance ou sur horaire.' }
  ];

  const FABRICS = [
    { id: 'voile-tulle', label: 'Voile de Tulle', text: 'Occultation minimale, transparence.' },
    { id: 'lin-sauvage', label: 'Pur Lin Sauvage', text: 'Texture authentique et chic, filtre la lumière avec élégance.' },
    { id: 'coton-satine', label: 'Coton Satiné', text: 'Doux au toucher, tombé parfait et entretien simple.' },
    { id: 'polyester-recycle', label: 'Polyester recyclé', text: 'Résistant, entretien facile pour un usage quotidien.' },
    { id: 'velours-double', label: 'Velours Doublé', text: 'Isolation renforcée et rendu majestueux.' },
    { id: 'metisse-lin-coton', label: 'Métisse Lin-Coton', text: 'Le parfait compromis entre la texture du lin et le confort du coton.' }
  ];

  const PATTERNS = [
    { id: 'uni', label: 'Uni épuré' },
    { id: 'rayures', label: 'Rayures Classiques' },
    { id: 'floral', label: 'Floral Raffiné' },
    { id: 'geometrique', label: 'Géométrique Architecte' },
    { id: 'texture', label: 'Texture Relief' }
  ];

  const LININGS = [
    { id: 'sans', label: 'Sans doublure', text: 'Laisse passer un maximum de lumière et préserve l\u2019aspect léger du tissu.' },
    { id: 'thermique', label: 'Doublure thermique Sortex\u00AE', text: 'Isole du froid et des déperditions de chaleur en toute saison.' },
    { id: 'occultante', label: 'Doublure occultante de prestige', text: 'Obscurité quasi totale, idéale pour les chambres ou salles de repos.' },
    { id: 'acoustique', label: 'Doublure acoustique Phonic', text: 'Réduit sensiblement les bruits extérieurs et de circulation.' }
  ];

  const FINISHES = [
    { id: 'oeillets', label: 'Œillets métalliques', text: 'Tombé fluide et ondulé, idéal pour une manipulation fréquente.' },
    { id: 'pattes', label: 'Galon fronces classique', text: 'Plis froncés traditionnels, style intemporel et bourgeois.' },
    { id: 'plis-flamands', label: 'Plis Flamands doublés', text: 'Souple et parfaitement structuré, grande tenue dans le temps.' },
    { id: 'plis-simples', label: 'Plis Pinces simples', text: 'Fini classique et épuré, effet soigné et discret.' },
    { id: 'ruban-fronceur', label: 'Ruban Fronceur de luxe', text: 'Les finitions les plus détaillées, drapé sur mesure.' }
  ];

  const HANGINGS = [
    { id: 'tringle', icon: 'i-curtain', label: 'Tringle à rideaux', text: 'Tube décoratif, embouts fixes, finitions au style affirmé.' },
    { id: 'rail-chemin', icon: 'i-blinds', label: 'Rail chemin de fer', text: 'Profilé discret et solide, recommandé pour un glissement sans friction.' },
    { id: 'rail-wave', icon: 'i-bolt', label: 'Rail motorisé Wave\u00AE', text: 'Module rideau motorisé intégré, pilotable par télécommande ou domotique.' },
    { id: 'sans', icon: 'i-info', label: 'Pose sans passage renseigné', text: 'Fixation par pression sans perçage, adaptée aux locations meublées.' }
  ];

  function findLabel(list, id) {
    const item = (list || []).filter(function (x) { return x.id === id; })[0];
    return item ? (item.label || item.name) : '';
  }

  function renderChoiceList(name, options, selected, withIcon) {
    return '<div class="choice-list" role="radiogroup">' + options.map(function (o) {
      const checked = selected === o.id;
      return (
        '<label class="choice-card choice-card--compact' + (checked ? ' is-selected' : '') + '">' +
          '<input type="radio" name="' + name + '" value="' + o.id + '"' + (checked ? ' checked' : '') + '>' +
          (withIcon
            ? '<span class="choice-card__icon"><svg class="icon" aria-hidden="true"><use href="#' + o.icon + '"/></svg></span>'
            : '<span class="choice-card__media"><span class="image-placeholder image-placeholder--thumb" aria-hidden="true"></span></span>') +
          '<span class="choice-card__body"><span class="choice-card__title">' + esc(o.label || o.name) + '</span>' +
            '<span class="choice-card__text">' + esc(o.text) + '</span></span>' +
          '<span class="choice-card__check" aria-hidden="true"><svg class="icon icon--sm"><use href="#i-check"/></svg></span>' +
        '</label>'
      );
    }).join('') + '</div>';
  }

  /* --- Étape 1 : modèle de rideau --------------------------------------------- */

  function renderModel(container, state) {
    container.innerHTML =
      '<h2 class="step__heading">Quel modèle de rideau recherchez-vous ?</h2>' +
      '<p class="step__intro">Sélectionnez le type de confection adapté à vos pièces à vivre.</p>' +
      renderChoiceList('curtain-model', MODELS, state.curtain.model, false);
  }
  function collectModel(container) {
    const c = container.querySelector('input[name="curtain-model"]:checked');
    return { model: c ? c.value : '' };
  }

  /* --- Étape 2 : tissu ---------------------------------------------------------- */

  function renderFabric(container, state) {
    container.innerHTML =
      '<h2 class="step__heading">Sélectionnez le tissu de votre confection</h2>' +
      '<p class="step__intro">Chaque matière possède un comportement unique de filtrage et de tombé.</p>' +
      '<div class="fabric-grid" role="radiogroup" aria-label="Tissu">' +
      FABRICS.map(function (f) {
        const checked = state.curtain.fabric === f.id;
        return (
          '<label class="fabric-card' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="curtain-fabric" value="' + f.id + '"' + (checked ? ' checked' : '') + '>' +
            '<span class="image-placeholder image-placeholder--fabric" aria-hidden="true"></span>' +
            '<span class="fabric-card__title">' + esc(f.label) + '</span>' +
            '<span class="fabric-card__text">' + esc(f.text) + '</span>' +
          '</label>'
        );
      }).join('') + '</div>';
  }
  function collectFabric(container) {
    const c = container.querySelector('input[name="curtain-fabric"]:checked');
    return { fabric: c ? c.value : '' };
  }

  /* --- Étape 3 : coloris & motif -------------------------------------------------- */

  function renderColor(container, state) {
    const colors = (MDS.data.colors || []).filter(function (c) { return c.types.indexOf('rideau') !== -1; });
    container.innerHTML =
      '<h2 class="step__heading">1. Choix du coloris</h2>' +
      '<p class="step__intro">Teintes d\u2019exception, sélectionnées à la décoratrice tendance.</p>' +
      '<div class="field" data-field="color"><div class="swatch-row swatch-row--lg" role="radiogroup" aria-label="Coloris">' +
      colors.map(function (c) {
        const checked = state.curtain.color === c.id;
        return (
          '<label class="swatch-choice' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="curtain-color" value="' + c.id + '"' + (checked ? ' checked' : '') + '>' +
            '<span class="swatch-choice__dot" style="--swatch:' + c.hex + '"></span>' +
            '<span class="swatch-choice__label">' + esc(c.label) + '</span>' +
          '</label>'
        );
      }).join('') + '</div></div>' +
      '<h2 class="step__heading step__heading--sub">2. Choix du motif</h2>' +
      '<div class="field form-grid__full" data-field="pattern"><div class="option-grid option-grid--compact" role="radiogroup" aria-label="Motif">' +
      PATTERNS.map(function (p) {
        const checked = state.curtain.pattern === p.id;
        return (
          '<label class="option-tile option-tile--sm' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="curtain-pattern" value="' + p.id + '"' + (checked ? ' checked' : '') + '>' +
            '<span class="option-tile__title">' + esc(p.label) + '</span>' +
          '</label>'
        );
      }).join('') + '</div></div>';
  }
  function collectColor(container) {
    const color = container.querySelector('input[name="curtain-color"]:checked');
    const pattern = container.querySelector('input[name="curtain-pattern"]:checked');
    return { color: color ? color.value : '', pattern: pattern ? pattern.value : '' };
  }

  /* --- Étape 4 : doublure ----------------------------------------------------------- */

  function renderLining(container, state) {
    container.innerHTML =
      '<h2 class="step__heading">Choisissez votre doublure</h2>' +
      '<p class="step__intro">La doublure prolonge la tenue du rideau et lui apporte des bénéfices thermiques ou acoustiques.</p>' +
      renderChoiceList('curtain-lining', LININGS, state.curtain.lining, false);
  }
  function collectLining(container) {
    const c = container.querySelector('input[name="curtain-lining"]:checked');
    return { lining: c ? c.value : '' };
  }

  /* --- Étape 5 : finition (tête de rideau) ------------------------------------------- */

  function renderFinish(container, state) {
    container.innerHTML =
      '<h2 class="step__heading">Sélectionnez la finition haute</h2>' +
      '<p class="step__intro">La tête de votre rideau détermine son style de suspension et le tombé du drapé.</p>' +
      '<div class="fabric-grid" role="radiogroup" aria-label="Finition">' +
      FINISHES.map(function (f) {
        const checked = state.curtain.finish === f.id;
        return (
          '<label class="fabric-card' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="curtain-finish" value="' + f.id + '"' + (checked ? ' checked' : '') + '>' +
            '<span class="image-placeholder image-placeholder--fabric" aria-hidden="true"></span>' +
            '<span class="fabric-card__title">' + esc(f.label) + '</span>' +
            '<span class="fabric-card__text">' + esc(f.text) + '</span>' +
          '</label>'
        );
      }).join('') + '</div>';
  }
  function collectFinish(container) {
    const c = container.querySelector('input[name="curtain-finish"]:checked');
    return { finish: c ? c.value : '' };
  }

  /* --- Étape 6 : pose & support ---------------------------------------------------- */

  function renderHanging(container, state) {
    container.innerHTML =
      '<h2 class="step__heading">Mode de pose &amp; support</h2>' +
      '<p class="step__intro">Choisissez le support adapté à votre mur ou plafond. Nos équipes ajustent l\u2019effort de pose.</p>' +
      '<div class="option-grid" role="radiogroup" aria-label="Mode de pose">' +
      HANGINGS.map(function (h) {
        const checked = state.curtain.hanging === h.id;
        return (
          '<label class="option-tile' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="curtain-hanging" value="' + h.id + '"' + (checked ? ' checked' : '') + '>' +
            '<svg class="icon icon--lg" aria-hidden="true"><use href="#' + h.icon + '"/></svg>' +
            '<span class="option-tile__title">' + esc(h.label) + '</span>' +
            '<span class="option-tile__text">' + esc(h.text) + '</span>' +
          '</label>'
        );
      }).join('') + '</div>' +
      '<div class="notice"><svg class="icon" aria-hidden="true"><use href="#i-tools"/></svg>' +
      '<span>Installation professionnelle incluse dans votre service Atelier : nos monteurs qualifiés s\u2019occupent de la levée, du transport et de la pose pour une finition impeccable.</span></div>';
  }
  function collectHanging(container) {
    const c = container.querySelector('input[name="curtain-hanging"]:checked');
    return { hanging: c ? c.value : '' };
  }

  const STEPS = [
    { id: 'curtain-model', title: 'Modèle', render: renderModel, collect: collectModel },
    { id: 'curtain-fabric', title: 'Tissu', render: renderFabric, collect: collectFabric },
    { id: 'curtain-color', title: 'Coloris &amp; motif', render: renderColor, collect: collectColor },
    { id: 'curtain-lining', title: 'Doublure', render: renderLining, collect: collectLining },
    { id: 'curtain-finish', title: 'Finition', render: renderFinish, collect: collectFinish },
    { id: 'curtain-hanging', title: 'Pose', render: renderHanging, collect: collectHanging }
  ];

  function labels(curtain) {
    return {
      model: findLabel(MODELS, curtain.model),
      fabric: findLabel(FABRICS, curtain.fabric),
      color: findLabel(MDS.data.colors, curtain.color),
      pattern: findLabel(PATTERNS, curtain.pattern),
      lining: findLabel(LININGS, curtain.lining),
      finish: findLabel(FINISHES, curtain.finish),
      hanging: findLabel(HANGINGS, curtain.hanging)
    };
  }

  MDS.quoteCurtain = { STEPS, MODELS, FABRICS, PATTERNS, LININGS, FINISHES, HANGINGS, labels };
})((window.MDS = window.MDS || {}));
