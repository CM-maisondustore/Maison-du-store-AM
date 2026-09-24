/* ==========================================================================
   rendez-vous/appointment.js
   Processus général de prise de rendez-vous (système indépendant des devis) :
   données du rendez-vous, type de rendez-vous, informations client, résumé,
   validation puis soumission.

   Il délègue : la date à calendar.js, l'heure à time-slots.js, les règles de
   validation à appointment-validation.js, l'envoi à MDS.api.
   ========================================================================== */
(function (MDS) {
  'use strict';

  const TYPE_LABELS = {
    showroom: 'Au showroom',
    domicile: 'À domicile',
    telephone: 'Par téléphone ou visio'
  };

  let form = null;
  let calendar = null;
  let slots = null;
  let els = {};
  let busy = false;

  function value(name) {
    const control = form.elements[name];
    return control ? String(control.value || '').trim() : '';
  }

  /** Données du rendez-vous telles que saisies. */
  function getData() {
    return {
      lastName: value('lastName'),
      firstName: value('firstName'),
      phone: value('phone'),
      email: value('email'),
      appointmentType: value('appointmentType'),
      project: value('project'),
      message: value('message'),
      date: calendar.getSelected() || '',
      time: slots.getSelected() || ''
    };
  }

  /* --- Résumé « Votre rendez-vous » ------------------------------------------- */

  function setSummary(key, text) {
    const target = els.summary && els.summary.querySelector('[data-summary="' + key + '"]');
    if (!target) return;
    target.textContent = text || 'À choisir';
    target.classList.toggle('is-empty', !text);
  }

  function updateSummary() {
    const d = getData();
    setSummary('type', TYPE_LABELS[d.appointmentType]);
    setSummary('client', [d.firstName, d.lastName].filter(Boolean).join(' '));
    setSummary('project', d.project);
    setSummary('date', MDS.calendar.formatLong(d.date));
    setSummary('time', d.time);
  }

  /* --- Erreurs ---------------------------------------------------------------- */

  function fieldOf(key) {
    return form.querySelector('[data-field="' + key + '"]');
  }

  function focusField(key) {
    const field = fieldOf(key);
    if (!field) return;
    let target = null;
    if (key === 'date') target = field.querySelector('.calendar__day:not(:disabled)');
    else if (key === 'time') target = field.querySelector('.slot:not(:disabled)');
    if (!target) target = field.querySelector('input:not([disabled]), select, textarea, button:not([disabled])');
    if (target) target.focus();
    else field.scrollIntoView({ block: 'center' });
  }

  function showErrors(errors) {
    MDS.notifications.fieldError.clearAll(form);
    const keys = Object.keys(errors);
    keys.forEach(function (key) {
      const field = fieldOf(key);
      if (field) MDS.notifications.fieldError.show(field, errors[key]);
    });
    // Premier champ en erreur, dans l'ordre du formulaire
    const first = Array.prototype.filter.call(form.querySelectorAll('[data-field]'), function (f) {
      return keys.indexOf(f.getAttribute('data-field')) !== -1;
    })[0];
    if (first) focusField(first.getAttribute('data-field'));
  }

  /* --- Confirmation ---------------------------------------------------------------- */

  function mailBody(data) {
    return [
      'Demande de rendez-vous',
      '',
      'Type : ' + (TYPE_LABELS[data.appointmentType] || ''),
      'Date : ' + MDS.calendar.formatLong(data.date) + ' à ' + data.time,
      'Nom : ' + data.firstName + ' ' + data.lastName,
      'Téléphone : ' + data.phone,
      'E-mail : ' + data.email,
      data.project ? 'Projet : ' + data.project : '',
      data.message ? 'Message : ' + data.message : ''
    ].filter(function (line, i) { return line !== '' || i === 1; }).join('\n');
  }

  function showConfirmation(data, result) {
    if (!els.confirmation) return;
    const when = MDS.calendar.formatLong(data.date) + ' à ' + data.time;
    els.confirmationText.textContent = result.demo
      ? data.firstName + ', votre demande est enregistrée pour le ' + when + '.'
      : data.firstName + ', votre demande a bien été envoyée pour le ' + when + '. Nous vous confirmerons ce rendez-vous rapidement.';

    els.demo.hidden = !result.demo;
    if (result.demo && els.mailto) {
      els.mailto.href = MDS.api.mailtoLink('Demande de rendez-vous - ' + data.firstName + ' ' + data.lastName, mailBody(data));
    }
    els.summaryBody.hidden = true;
    els.confirmation.hidden = false;
    els.confirmation.focus();
  }

  function hideConfirmation() {
    els.confirmation.hidden = true;
    els.summaryBody.hidden = false;
  }

  /* --- Soumission ------------------------------------------------------------------ */

  function reset() {
    form.reset();
    calendar.reset();
    slots.reset();
    MDS.notifications.fieldError.clearAll(form);
    updateSummary();
  }

  function setBusy(on) {
    busy = on;
    form.setAttribute('aria-busy', String(on));
    document.querySelectorAll('[data-rdv-submit]').forEach(function (button) {
      button.disabled = on;
      button.textContent = on ? 'Envoi en cours…' : 'Confirmer le rendez-vous';
    });
  }

  function onSubmit(event) {
    event.preventDefault();
    if (busy) return;

    const data = getData();
    const result = MDS.appointmentValidation.validate(data);
    if (!result.valid) {
      showErrors(result.errors);
      MDS.notifications.error('Vérifiez les champs signalés avant de confirmer.');
      return;
    }

    const payload = Object.assign({}, data, { submittedAt: new Date().toISOString() });
    setBusy(true);
    MDS.api.submitAppointment(payload)
      .then(function (response) {
        showConfirmation(data, response);
        reset();
        if (response.demo) {
          MDS.notifications.info('Rendez-vous enregistré en mode démonstration : il n\u2019a pas été transmis.');
        } else {
          MDS.notifications.success('Votre demande de rendez-vous a été envoyée.');
        }
      })
      .catch(function () {
        MDS.notifications.error('L\u2019envoi a échoué. Réessayez ou appelez-nous directement.');
      })
      .finally(function () { setBusy(false); });
  }

  function init() {
    form = document.getElementById('appointment-form');
    if (!form) return;

    els = {
      summary: document.querySelector('[data-rdv-summary]'),
      summaryBody: document.querySelector('[data-rdv-summary-body]'),
      confirmation: document.querySelector('[data-rdv-confirmation]'),
      confirmationText: document.querySelector('[data-rdv-confirmation-text]'),
      demo: document.querySelector('[data-rdv-demo]'),
      mailto: document.querySelector('[data-rdv-mailto]')
    };

    calendar = MDS.calendar.create(form.querySelector('[data-calendar]'));
    slots = MDS.timeSlots.create(form.querySelector('[data-time-slots]'));

    form.addEventListener('calendar:select', function (event) {
      slots.setDate(event.detail.date);
      MDS.notifications.fieldError.clear(fieldOf('date'));
      updateSummary();
    });
    form.addEventListener('slots:select', function () {
      MDS.notifications.fieldError.clear(fieldOf('time'));
      updateSummary();
    });
    form.addEventListener('input', function (event) {
      const field = event.target.closest('.field');
      if (field) MDS.notifications.fieldError.clear(field);
      updateSummary();
    });
    form.addEventListener('change', function (event) {
      const field = event.target.closest('.field');
      if (field) MDS.notifications.fieldError.clear(field);
      updateSummary();
    });
    form.addEventListener('submit', onSubmit);

    const again = document.querySelector('[data-rdv-again]');
    if (again) again.addEventListener('click', hideConfirmation);

    updateSummary();
  }

  MDS.appointment = { init, getData, reset };
})((window.MDS = window.MDS || {}));
