/* ==========================================================================
   devis/quote-validation.js
   Gère uniquement la validation des demandes de devis : informations
   client, téléphone, e-mail, type de produit, dimensions, quantité, champs
   obligatoires. Distingue explicitement la validation Store et la
   validation Rideau.
   Chaque fonction renvoie { valid, errors: { champ: "message" } }.
   ========================================================================== */
(function (MDS) {
  'use strict';

  const v = MDS.validation;

  function requiredChoice(value, message, errors, key) {
    if (!v.isRequired(value)) errors[key] = message;
  }

  /** Coordonnées client : commune au devis Store et au devis Rideau. */
  function validateContact(data) {
    const errors = {};
    requiredChoice(data.civility, 'Choisissez une civilité.', errors, 'civility');

    if (!v.isRequired(data.lastName)) errors.lastName = 'Indiquez votre nom.';
    else if (!v.isValidText(data.lastName, { min: 2, max: 60 })) errors.lastName = 'Votre nom doit contenir entre 2 et 60 caractères.';

    if (!v.isRequired(data.firstName)) errors.firstName = 'Indiquez votre prénom.';
    else if (!v.isValidText(data.firstName, { min: 2, max: 60 })) errors.firstName = 'Votre prénom doit contenir entre 2 et 60 caractères.';

    if (!v.isRequired(data.email)) errors.email = 'Indiquez votre adresse e-mail.';
    else if (!v.isEmail(data.email)) errors.email = v.messages.email;

    if (!v.isRequired(data.phone)) errors.phone = 'Indiquez votre numéro de téléphone.';
    else if (!v.isPhone(data.phone)) errors.phone = v.messages.phone;

    if (!v.isRequired(data.address)) errors.address = 'Indiquez votre adresse.';
    else if (!v.isValidText(data.address, { min: 4, max: 120 })) errors.address = 'Votre adresse doit contenir entre 4 et 120 caractères.';

    if (!v.isRequired(data.city)) errors.city = 'Indiquez votre ville.';
    else if (!v.isValidText(data.city, { min: 2, max: 60 })) errors.city = 'Votre ville doit contenir entre 2 et 60 caractères.';

    // Facultatif : validé uniquement s'il est renseigné.
    if (v.isRequired(data.postalCode) && !v.isValidText(data.postalCode, { min: 2, max: 12 })) {
      errors.postalCode = 'Le code postal saisi n\u2019est pas valide.';
    }

    return { valid: Object.keys(errors).length === 0, errors: errors };
  }

  /** Dimensions : commune, utilisée par les deux parcours (via dimensions.js). */
  function validateDimensions(windows) {
    const result = MDS.dimensions.validateWindows(windows);
    const errors = {};
    result.errors.forEach(function (e) {
      // Un message par fenêtre suffit à l'affichage ; on garde le premier par type de champ.
      const key = e.id ? e.field + ':' + e.id : e.field;
      errors[key] = e.message;
    });
    return { valid: result.valid, errors: errors };
  }

  /** Validation Store : une étape à la fois (id d'étape MDS.quoteStore.STEPS). */
  function validateStoreStep(stepId, data) {
    const errors = {};
    if (stepId === 'store-model') {
      requiredChoice(data.model, 'Choisissez un modèle de store.', errors, 'model');
    } else if (stepId === 'store-mount') {
      requiredChoice(data.mount, 'Choisissez un type de pose.', errors, 'mount');
      requiredChoice(data.orderType, 'Choisissez un type de commande.', errors, 'orderType');
    } else if (stepId === 'store-control') {
      requiredChoice(data.control, 'Choisissez un type de commande.', errors, 'control');
      if (data.control === 'motorisee') requiredChoice(data.powerSource, 'Choisissez une alimentation.', errors, 'powerSource');
    } else if (stepId === 'store-finish') {
      requiredChoice(data.color, 'Choisissez une couleur.', errors, 'color');
      requiredChoice(data.finish, 'Choisissez un type de toile.', errors, 'finish');
    }
    return { valid: Object.keys(errors).length === 0, errors: errors };
  }

  /** Validation Rideau : une étape à la fois (id d'étape MDS.quoteCurtain.STEPS). */
  function validateCurtainStep(stepId, data) {
    const errors = {};
    if (stepId === 'curtain-model') {
      requiredChoice(data.model, 'Choisissez un modèle de rideau.', errors, 'model');
    } else if (stepId === 'curtain-fabric') {
      requiredChoice(data.fabric, 'Choisissez un tissu.', errors, 'fabric');
    } else if (stepId === 'curtain-color') {
      requiredChoice(data.color, 'Choisissez un coloris.', errors, 'color');
      requiredChoice(data.pattern, 'Choisissez un motif.', errors, 'pattern');
    } else if (stepId === 'curtain-lining') {
      requiredChoice(data.lining, 'Choisissez une doublure.', errors, 'lining');
    } else if (stepId === 'curtain-finish') {
      requiredChoice(data.finish, 'Choisissez une finition.', errors, 'finish');
    } else if (stepId === 'curtain-hanging') {
      requiredChoice(data.hanging, 'Choisissez un mode de pose.', errors, 'hanging');
    }
    return { valid: Object.keys(errors).length === 0, errors: errors };
  }

  /** Validation finale avant envoi : type de produit + acceptation des conditions. */
  function validateSubmission(state) {
    const errors = {};
    if (state.type !== 'store' && state.type !== 'rideau') errors.type = 'Le type de produit est manquant.';
    if (!state.acceptTerms) errors.acceptTerms = 'Merci d\u2019accepter les conditions générales de vente pour envoyer votre demande.';
    return { valid: Object.keys(errors).length === 0, errors: errors };
  }

  MDS.quoteValidation = {
    validateContact,
    validateDimensions,
    validateStoreStep,
    validateCurtainStep,
    validateSubmission
  };
})((window.MDS = window.MDS || {}));
