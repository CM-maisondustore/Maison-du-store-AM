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

  MDS.quoteMedia = function (item,size) {
    return item?.imageUrl ? '<img class="quote-image quote-image--'+size+'" src="'+esc(item.imageUrl)+'" alt="'+esc(item.imageAlt||item.label||item.name||'Illustration')+'" loading="lazy">' : '<span class="image-placeholder image-placeholder--'+size+'" aria-hidden="true"></span>';
  };
  const MODELS = (MDS.quoteProducts || []).filter(p => p.category === 'store');

  const ORDER_TYPES = [
    { id: 'installation', label: 'Nouvelle installation' },
    { id: 'remplacement', label: 'Remplacement' },
    { id: 'renovation', label: 'Rénovation' }
  ];

  const CONTROLS = [
    { id: 'manuelle', icon: 'i-tools', label: 'Commande manuelle', text: 'Cordon, chaînette ou tige de manœuvre classique et fiable.' },
    { id: 'motorisee', icon: 'i-bolt', label: 'Commande motorisée', text: 'Ouverture et fermeture automatisées, pilotables à distance.' },
    { id: 'inconnu', icon: 'i-info', label: 'Je ne sais pas', text: 'Nous vous conseillons selon votre installation électrique.' }
  ];


  const FABRICS = (MDS.quoteOptions || []).filter(o=>o.category==='store' && o.kind==='material');
  const FINISHES = (MDS.quoteOptions || []).filter(o=>o.category==='store' && o.kind==='finish');
  function findLabel(list,id) { const item=list.find(x=>x.id===id); return item ? (item.label||item.name) : ''; }
  function compatible(o,state) {
    return (!o.product_ids?.length || o.product_ids.includes(Number(state.store.model.replace('product-','')))) &&
      (o.motorization==='any' || state.store.control==='inconnu' || o.motorization===(state.store.control==='motorisee'?'yes':'no'));
  }
  /* --- Étape 1 : modèle de store ------------------------------------------- */

  function renderModel(container, state) {
    if(!MODELS.length){container.innerHTML='<p class="notice">Aucun store disponible. Contactez-nous sur WhatsApp.</p>';return;}
    container.innerHTML =
      '<h2 class="step__heading">Quel modèle de store souhaitez-vous ?</h2>' +
      '<p class="step__intro">Sélectionnez le type de confection idéal pour vos fenêtres.</p>' +
      '<div class="choice-list" role="radiogroup" aria-label="Modèle de store">' +
      MODELS.map(function (m) {
        const checked = state.store.model === m.id;
        return (
          '<label class="choice-card' + (checked ? ' is-selected' : '') + '">' +
            '<input type="radio" name="store-model" value="' + m.id + '"' + (checked ? ' checked' : '') + '>' +
            '<span class="choice-card__media">' + MDS.quoteMedia(m,'thumb') + '</span>' +
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

  function renderControl(container, state) {
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
      }).join('') + '</div>';

    container.innerHTML += '<div class="field" data-field="orderType"><label class="field__label" for="store-order-type">Type de commande</label><select class="select" id="store-order-type">'+ORDER_TYPES.map(o=>'<option value="'+o.id+'"'+(state.store.orderType===o.id?' selected':'')+'>'+esc(o.label)+'</option>').join('')+'</select></div>';

  }

  function collectControl(container) {
    const checked = container.querySelector('input[name="store-control"]:checked');
    return { control: checked ? checked.value : '', orderType: container.querySelector('#store-order-type').value };
  }

  /* --- Étape 4 : matières, couleurs & finitions -------------------------------- */

  function renderFinish(container, state) {
    const colors = (MDS.data.colors || []).filter(function (c) { return c.types.indexOf('store') !== -1; });
    container.innerHTML =
      '<h2 class="step__heading">Matières, couleurs &amp; finitions</h2>' +
      '<p class="step__intro">Indiquez votre préférence. Le choix définitif du coloris sera effectué sur place avec l’agent.</p>' +
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
      [['fabric','Type de toile',FABRICS],['finish','Finition',FINISHES]].map(function ([field,title,list]) {
        const options=list.filter(o=>compatible(o,state));
        if(field==='finish' && !options.length) return '';
        return '<div class="field" data-field="'+field+'"><h3>'+title+'</h3><div class="option-grid">'+
          (options.length ? options.map(f=>'<label class="option-tile"><input type="radio" name="store-'+field+'" value="'+esc(f.id)+'"'+(state.store[field]===f.id?' checked':'')+'>'+MDS.quoteMedia(f,'fabric')+'<span>'+esc(f.label)+'</span></label>').join('') : '<p>Aucun choix disponible pour cette configuration. Contactez-nous.</p>')+'</div></div>';
      }).join('');
  }
  function collectFinish(container) {
    return Object.fromEntries(['color','fabric','finish'].map(field=>[field,container.querySelector('input[name="store-'+field+'"]:checked')?.value || '']));
  }

  /* --- Déclaration des étapes, lues par quote.js ------------------------------- */

  const STEPS = [
    { id: 'store-model', title: 'Modèle', render: renderModel, collect: collectModel },
    { id: 'store-control', title: 'Commande', render: renderControl, collect: collectControl },
    { id: 'store-finish', title: 'Matières &amp; finitions', render: renderFinish, collect: collectFinish }
  ];

  function labels(store) {
    return {
      model: findLabel(MODELS, store.model),
      orderType: findLabel(ORDER_TYPES, store.orderType),
      control: findLabel(CONTROLS, store.control),
      color: findLabel(MDS.data.colors, store.color),
      fabric: findLabel(FABRICS, store.fabric),
      finish: findLabel(FINISHES, store.finish)
    };
  }

  MDS.quoteStore = { STEPS, MODELS, ORDER_TYPES, CONTROLS, FABRICS, FINISHES, labels, hasFinish: store=>FINISHES.some(o=>compatible(o,{store})) };
})((window.MDS = window.MDS || {}));
