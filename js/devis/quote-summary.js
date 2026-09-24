/* ==========================================================================
   devis/quote-summary.js
   Gère uniquement l'affichage du récapitulatif : la carte « Votre
   Configuration » visible tout au long du parcours, le récapitulatif final
   avant envoi, et l'écran de confirmation. Ne calcule ni n'affiche jamais de
   montant réel : le prix est toujours affiché sous la forme exacte « Prix ».

   Émet (bubbles) : recap:edit { stepId } quand l'utilisateur clique sur
   « Modifier » dans le récapitulatif final ; quote.js s'en charge.
   ========================================================================== */
(function (MDS) {
  'use strict';

  const esc = function (text) { return MDS.validation.escapeHtml(text); };

  function labelsFor(state) {
    return state.type === 'store' ? MDS.quoteStore.labels(state.store) : MDS.quoteCurtain.labels(state.curtain);
  }

  function clientLine(state) {
    const name = [state.contact.firstName, state.contact.lastName].filter(Boolean).join(' ');
    if (!name && !state.contact.phone) return '';
    return [name, state.contact.phone].filter(Boolean).join(' · ');
  }

  /* --- Carte « Votre configuration » (barre latérale, tout au long du parcours) ---- */

  function row(label, value) {
    return (
      '<div class="config-card__row"><dt>' + esc(label) + '</dt>' +
      '<dd' + (value ? '' : ' class="is-empty"') + '>' + esc(value || 'À définir') + '</dd></div>'
    );
  }

  function renderSidebar(container, state) {
    if (!container) return;
    if (!state.type) {
      container.innerHTML =
        '<h2 class="config-card__title">Votre configuration</h2>' +
        '<p class="config-card__hint">Choisissez un type de projet pour commencer votre configuration sur mesure.</p>';
      return;
    }

    const type = state.type === 'store' ? 'Store' : 'Rideau';
    const l = labelsFor(state);
    const windows = state.dimensions.filter(function (w) { return w.width && w.height; });

    let rows = row('Projet', type) + (l.model ? row('Modèle', l.model) : '');
    if (windows.length) rows += row('Dimensions', MDS.dimensions.formatWindows(windows));

    if (state.type === 'store') {
      if (l.mount) rows += row('Pose', l.mount);
      if (l.control) rows += row('Commande', l.control + (l.powerSource ? ' \u2014 ' + l.powerSource : ''));
      if (l.color) rows += row('Couleur', l.color + (l.finish ? ' \u2014 ' + l.finish : ''));
    } else {
      if (l.fabric) rows += row('Tissu', l.fabric);
      if (l.color) rows += row('Coloris', l.color + (l.pattern ? ' \u2014 ' + l.pattern : ''));
      if (l.lining) rows += row('Doublure', l.lining);
      if (l.finish) rows += row('Finition', l.finish + (l.hanging ? ' \u2014 ' + l.hanging : ''));
    }

    const client = clientLine(state);

    container.innerHTML =
      '<h2 class="config-card__title">Votre configuration</h2>' +
      (client ? '<p class="config-card__client">' + esc(client) + '</p>' : '') +
      '<dl class="config-card__list">' + rows + '</dl>' +
      '<div class="config-card__price"><span>Estimation du devis</span><strong>Prix</strong></div>' +
      '<p class="config-card__hint">Ce tarif final vous sera communiqué sous 24 h ouvrées après étude de votre projet.</p>';
  }

  /* --- Récapitulatif final ------------------------------------------------------------ */

  function recapRow(label, value, stepId) {
    return (
      '<div class="recap-row">' +
        '<div class="recap-row__text"><dt>' + esc(label) + '</dt><dd>' + esc(value || 'Non renseigné') + '</dd></div>' +
        (stepId ? '<button type="button" class="link-btn" data-recap-edit="' + stepId + '">Modifier</button>' : '') +
      '</div>'
    );
  }

  function renderRecap(container, state) {
    if (!container) return;
    const l = labelsFor(state);
    const type = state.type === 'store' ? 'Store' : 'Rideau';
    const contact = state.contact;
    const clientText = (contact.civility === 'mme' ? 'Mme ' : contact.civility === 'm' ? 'M. ' : '') +
      contact.firstName + ' ' + contact.lastName + ' \u2014 ' + contact.phone + ' \u2014 ' + contact.email;

    let rows = recapRow('Coordonnées client', clientText, 'contact') +
      recapRow('Type de projet configuré', type + (l.model ? ' \u2014 ' + l.model : ''), state.type === 'store' ? 'store-model' : 'curtain-model') +
      recapRow('Dimensions saisies', MDS.dimensions.formatWindows(state.dimensions), 'dimensions');

    if (state.type === 'store') {
      rows += recapRow('Pose et commande', [l.mount, l.orderType, l.control, l.powerSource].filter(Boolean).join(' \u2014 '), 'store-mount');
      rows += recapRow('Matière et finition', [l.color, l.finish].filter(Boolean).join(' \u2014 '), 'store-finish');
    } else {
      rows += recapRow('Tissu et coloris', [l.fabric, l.color, l.pattern].filter(Boolean).join(' \u2014 '), 'curtain-fabric');
      rows += recapRow('Doublure et finition', [l.lining, l.finish].filter(Boolean).join(' \u2014 '), 'curtain-lining');
      rows += recapRow('Pose et support', l.hanging, 'curtain-hanging');
    }
    if (contact.notes) rows += recapRow('Précisions sur le projet', contact.notes, 'contact');

    container.innerHTML =
      '<h2 class="recap__title">Récapitulatif de votre projet</h2>' +
      '<p class="recap__intro">Vérifiez l\u2019ensemble des éléments de votre configuration avant d\u2019envoyer votre demande de devis d\u2019excellence.</p>' +
      '<div class="recap-list">' + rows + '</div>' +
      '<div class="notice recap__estimate"><div><strong>Estimation budgétaire du projet</strong>' +
      '<p class="field__hint">Cette étude de prix individualisée vous sera envoyée sous 24 h par votre conseiller.</p></div>' +
      '<span class="recap__price">Prix</span></div>' +
      '<label class="check recap__accept"><input type="checkbox" id="quote-accept" data-field="acceptTerms"' +
      (state.acceptTerms ? ' checked' : '') + '><span>J\u2019accepte que Maison du Store AM me contacte au sujet de ma demande, ' +
      'conformément aux conditions générales de vente de l\u2019Atelier de confection.</span></label>';
  }

  /* --- Confirmation ------------------------------------------------------------------- */

  function renderConfirmation(container, state, result) {
    if (!container) return;
    const type = state.type === 'store' ? 'Store' : 'Rideau';
    const l = labelsFor(state);
    container.innerHTML =
      '<div class="confirmation">' +
        '<span class="confirmation__icon" aria-hidden="true"><svg class="icon icon--lg"><use href="#i-check-circle"/></svg></span>' +
        '<h2 class="confirmation__title">Votre demande de devis a été envoyée</h2>' +
        '<p class="confirmation__text">' + (result.demo
          ? 'Votre demande a été enregistrée en mode démonstration : elle n\u2019a pas été transmise. Une fois le site raccordé à notre système, ' +
            'vous recevrez ici un accusé de réception avec le récapitulatif complet de votre configuration.'
          : 'Vous recevrez un accusé de réception avec le récapitulatif de votre configuration. Un conseiller Maison du Store AM étudiera votre ' +
            'projet et vous recontactera sous 24 h ouvrées.') + '</p>' +
        (result.demo
          ? '<a class="btn btn--outline" data-confirmation-mailto href="#">Nous envoyer ma demande par e-mail</a>'
          : '') +
        '<h3 class="confirmation__recap-title">Récapitulatif de votre configuration</h3>' +
        '<dl class="confirmation__list">' +
          '<div><dt>Coordonnées</dt><dd>' + esc(state.contact.firstName + ' ' + state.contact.lastName + ' \u2014 ' + state.contact.phone + ' \u2014 ' + state.contact.email) + '</dd></div>' +
          '<div><dt>Projet</dt><dd>' + esc(type + (l.model ? ' \u2014 ' + l.model : '')) + '</dd></div>' +
          '<div><dt>Dimensions</dt><dd>' + esc(MDS.dimensions.formatWindows(state.dimensions)) + '</dd></div>' +
          '<div><dt>Estimation</dt><dd>Prix</dd></div>' +
        '</dl>' +
        '<button type="button" class="btn btn--dark" data-action="back-to-catalogue">Retourner au catalogue</button>' +
      '</div>';

    const mailtoLink = container.querySelector('[data-confirmation-mailto]');
    if (mailtoLink) {
      const subject = 'Demande de devis ' + type + ' - ' + state.contact.firstName + ' ' + state.contact.lastName;
      const body = 'Type : ' + type + (l.model ? '\nModèle : ' + l.model : '') +
        '\nDimensions : ' + MDS.dimensions.formatWindows(state.dimensions) +
        '\nNom : ' + state.contact.firstName + ' ' + state.contact.lastName +
        '\nTéléphone : ' + state.contact.phone + '\nE-mail : ' + state.contact.email;
      mailtoLink.href = MDS.api.mailtoLink(subject, body);
    }
  }

  function init(container) {
    if (!container) return;
    container.addEventListener('click', function (event) {
      const edit = event.target.closest('[data-recap-edit]');
      if (edit) {
        container.dispatchEvent(new CustomEvent('recap:edit', { bubbles: true, detail: { stepId: edit.getAttribute('data-recap-edit') } }));
      }
    });
  }

  MDS.quoteSummary = { init, renderSidebar, renderRecap, renderConfirmation };
})((window.MDS = window.MDS || {}));
