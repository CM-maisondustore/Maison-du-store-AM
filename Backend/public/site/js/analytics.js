/* Copie de resources/js/mds/utils/analytics.js (site précédent), servie sans Vite au site V2. */
(function () {
  'use strict';
  const CONSENT = 'mds-analytics-consent-v1', VISITOR = 'mds-visitor-v1', SESSION = 'mds-session-v1';
  const mds = window.MDS = window.MDS || {};
  let enabled = false, startedForms = new WeakSet();
  function uuid(key, storage, expiring) {
    try {
      if (expiring) {
        const record = JSON.parse(storage.getItem(key) || 'null');
        if (record && record.expires > Date.now()) return record.id;
        const id = crypto.randomUUID(); storage.setItem(key, JSON.stringify({id,expires:Date.now()+90*86400000})); return id;
      }
      let value = storage.getItem(key); if (!value) { value = crypto.randomUUID(); storage.setItem(key, value); } return value;
    }
    catch (_) { return crypto.randomUUID(); }
  }
  function consent() {
    try { const item = JSON.parse(localStorage.getItem(CONSENT)); return !!(item && item.value === true && item.expires > Date.now()); }
    catch (_) { return false; }
  }
  let visitor = null, session = null;
  function send(event, extra) {
    if (!enabled) return;
    if (!visitor) visitor = uuid(VISITOR, localStorage, true);
    if (!session) session = uuid(SESSION, sessionStorage);
    const campaign = new URLSearchParams(location.search);
    const payload = Object.assign({
      visitor_id: visitor, session_id: session, event,
      path: location.pathname, title: document.title.slice(0, 180),
      campaign: ['utm_source','utm_medium','utm_campaign'].map(k => campaign.has(k) ? k + '=' + campaign.get(k) : '').filter(Boolean).join('&').slice(0, 190)
    }, extra || {});
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    fetch('/api/analytics/collect', {method:'POST', credentials:'same-origin', keepalive:true,
      headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest',...(token?{'X-CSRF-TOKEN':token}:{})}, body:JSON.stringify(payload)}).catch(()=>{});
  }
  function syncConsent(value) {
    enabled = !!value && consent();
    if (enabled && !window.__mdsAnalyticsPageSent) { window.__mdsAnalyticsPageSent = true; send('page_view'); setInterval(()=>send('heartbeat'), 20000); }
  }
  mds.analytics = {syncConsent, track:send};
  syncConsent(consent());
  document.addEventListener('click', event => {
    const action = event.target.closest('a,button,[role="button"]'); if (!action) return;
    if (action.matches('[data-product-open]')) send('product_view', {target:action.getAttribute('data-product-open')||''});
    else if (/wa\.me|whatsapp\.com/i.test(action.href||'')) send('whatsapp_click');
    else if (/devis/i.test(action.textContent) || action.matches('[data-analytics-quote],[data-quote-start]')) send('quote_click');
    else if (/rendez-vous|rdv/i.test(action.textContent) || action.matches('[data-analytics-appointment],[data-action="appointment"]')) send('appointment');
    else if (action.closest('[data-product-card],.product-card,.catalogue-card')) send('product_view', {target:action.getAttribute('href')||''});
  }, true);
  document.addEventListener('focusin', event => {
    const form = event.target.closest('form'); if (!form || startedForms.has(form)) return;
    startedForms.add(form); send('form_start', {target:form.id||form.getAttribute('name')||'formulaire'});
  });
})();
