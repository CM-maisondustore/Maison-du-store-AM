(() => {
  const button = document.getElementById('push-toggle');
  if (!button) return;
  if (button.dataset.configured !== '1') {
    button.disabled = true;
    button.textContent = 'Configurer les notifications';
    return;
  }
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const b64ToBytes = value => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
  const request = async (url, method, data) => {
    const response = await fetch(url, { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: data ? JSON.stringify(data) : undefined });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json().catch(() => ({}));
  };
  async function refresh() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      button.disabled = true; button.textContent = 'Notifications Push indisponibles'; return;
    }
    const registration = await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
    const subscription = await registration.pushManager.getSubscription();
    button.dataset.endpoint = subscription?.endpoint || '';
    button.textContent = subscription ? 'Désactiver les notifications' : 'Activer les notifications';
  }
  button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      const registration = await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
      const existing = await registration.pushManager.getSubscription();
      if (existing) {
        await request(button.dataset.unsubscribeUrl, 'DELETE', { endpoint: existing.endpoint });
        await existing.unsubscribe();
        button.textContent = 'Activer les notifications';
      } else {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') throw new Error('Autorisation refusée par le navigateur.');
        const { publicKey } = await request(button.dataset.publicKeyUrl, 'GET');
        const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(publicKey) });
        const raw = subscription.toJSON();
        await request(button.dataset.subscribeUrl, 'POST', { ...raw, contentEncoding: 'aes128gcm', label: navigator.userAgent.slice(0, 120) });
        button.textContent = 'Désactiver les notifications';
      }
    } catch (error) {
      window.alert(error.message || 'Impossible de configurer les notifications sur cet appareil.');
    } finally { button.disabled = false; }
  });
  refresh().catch(() => { button.disabled = true; button.textContent = 'Configuration Push requise'; });
})();
