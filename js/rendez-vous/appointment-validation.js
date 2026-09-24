/* ==========================================================================
   rendez-vous/appointment-validation.js
   Valide uniquement le formulaire de rendez-vous : nom, prénom, téléphone,
   e-mail, date, heure, type de rendez-vous.
   validate(data) -> { valid, errors: { champ: "message" } }
   ========================================================================== */
(function (MDS) {
  'use strict';

  const TYPES = ['showroom', 'domicile', 'telephone'];

  function validate(data) {
    const v = MDS.validation;
    const errors = {};

    if (!v.isRequired(data.lastName)) errors.lastName = 'Indiquez votre nom.';
    else if (!v.isValidText(data.lastName, { min: 2, max: 60 })) errors.lastName = 'Votre nom doit contenir entre 2 et 60 caractères.';

    if (!v.isRequired(data.firstName)) errors.firstName = 'Indiquez votre prénom.';
    else if (!v.isValidText(data.firstName, { min: 2, max: 60 })) errors.firstName = 'Votre prénom doit contenir entre 2 et 60 caractères.';

    if (!v.isRequired(data.phone)) errors.phone = 'Indiquez votre numéro de téléphone.';
    else if (!v.isPhone(data.phone)) errors.phone = v.messages.phone;

    if (!v.isRequired(data.email)) errors.email = 'Indiquez votre adresse e-mail.';
    else if (!v.isEmail(data.email)) errors.email = v.messages.email;

    if (!v.isRequired(data.appointmentType) || TYPES.indexOf(data.appointmentType) === -1) {
      errors.appointmentType = 'Choisissez le type de rendez-vous.';
    }

    if (!v.isRequired(data.date) || !MDS.calendar.parseISO(data.date)) errors.date = 'Choisissez une date.';

    if (!v.isRequired(data.time) || MDS.timeSlots.SLOTS.indexOf(data.time) === -1) {
      errors.time = data.date ? 'Choisissez un créneau horaire.' : 'Choisissez d\u2019abord une date, puis un créneau.';
    }

    if (v.isRequired(data.message) && !v.isValidText(data.message, { min: 1, max: 1000 })) {
      errors.message = 'Votre message ne doit pas dépasser 1000 caractères ni contenir de balises.';
    }

    return { valid: Object.keys(errors).length === 0, errors: errors };
  }

  MDS.appointmentValidation = { validate, TYPES };
})((window.MDS = window.MDS || {}));
