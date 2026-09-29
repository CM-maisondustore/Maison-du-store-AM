(() => {
  const form = document.getElementById('message-compose');
  const selector = document.getElementById('message-channel');
  if (!form || !selector) return;
  const recipient = form.elements.recipient;
  const recipients = {email: form.dataset.email, whatsapp: form.dataset.whatsapp};
  let channel = form.elements.channel.value;
  recipients[channel] = recipient.value;
  function update() {
    recipients[channel] = recipient.value;
    channel = selector.value;
    const whatsapp = channel === 'whatsapp';
    form.elements.channel.value = channel;
    recipient.type = whatsapp ? 'tel' : 'email';
    recipient.value = recipients[channel] || '';
    recipient.placeholder = whatsapp ? '+2250700000000' : 'client@exemple.com';
    form.querySelector('[data-recipient-label]').textContent = whatsapp ? 'Numéro du client avec indicatif pays' : 'Destinataire';
    form.querySelector('[data-message-sender]').textContent = whatsapp ? form.dataset.senderWhatsapp : form.dataset.senderEmail;
    form.elements.contact_id.options[0].textContent = whatsapp ? 'Aucun dossier associé' : 'Aucun / association par e-mail';
    form.elements.body.maxLength = whatsapp ? 3000 : 30000;
    const attachment = form.querySelector('[data-message-attachment]');
    if (attachment) attachment.textContent = whatsapp ? 'Le PDF devra être ajouté manuellement dans WhatsApp.' : 'Le document PDF sera joint lors de l’envoi.';
  }
  selector.addEventListener('change', update);
  form.elements.contact_id.addEventListener('change', () => {
    if (form.dataset.document === '1') return;
    const option = form.elements.contact_id.selectedOptions[0];
    recipients.email = option.dataset.email || '';
    recipients.whatsapp = option.dataset.whatsapp || '';
    recipient.value = recipients[channel];
  });
  update();
})();
