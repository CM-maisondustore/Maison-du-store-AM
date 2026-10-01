(() => {
  const form = document.getElementById('layout-form');
  if (!form) return;
  const frame = document.getElementById('layout-preview');
  const status = document.getElementById('preview-status');
  const download = document.getElementById('preview-download');
  let timer, controller, url, sequence = 0;
  async function preview() {
    const current = ++sequence;
    controller?.abort();
    controller = new AbortController();
    status.textContent = 'Génération du PDF A4…';
    try {
      const response = await fetch(form.dataset.preview, {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}, signal:controller.signal, credentials:'same-origin'});
      if (!response.ok) {
        let message = 'Aperçu indisponible. Vérifiez les champs puis réessayez.';
        if (response.status === 419) message = 'Votre session a expiré. Enregistrez votre travail avant de recharger la page.';
        else if (response.status === 429) message = 'Patientez quelques secondes avant de relancer l’aperçu.';
        else if (response.headers.get('content-type')?.includes('json')) {
          const data = await response.json();
          message = Object.values(data.errors || {}).flat().join('\n') || message;
        }
        throw new Error(message);
      }
      if (!response.headers.get('content-type')?.includes('application/pdf')) throw new Error('Reconnectez-vous pour afficher l’aperçu.');
      const blob = await response.blob();
      if (current !== sequence) return;
      if (url) URL.revokeObjectURL(url);
      url = URL.createObjectURL(blob); frame.src = url; download.href = url; download.hidden = false;
      status.textContent = 'Aperçu à jour · données fictives · modifications non publiées';
    } catch (error) {
      if (error.name !== 'AbortError' && current === sequence) status.textContent = error.message;
    }
  }
  const schedule = () => { clearTimeout(timer); timer = setTimeout(preview, 1000); };
  form.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  document.getElementById('refresh-preview').addEventListener('click', () => { clearTimeout(timer); preview(); });
  window.addEventListener('pagehide', () => {controller?.abort(); if (url) URL.revokeObjectURL(url);});
  preview();
})();
