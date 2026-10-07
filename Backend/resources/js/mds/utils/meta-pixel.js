(function () {
  'use strict';
  const KEY = 'mds-advertising-consent-v1', ANALYTICS_KEY = 'mds-analytics-consent-v1';
  const DURATION = 180 * 24 * 60 * 60 * 1000;

  function init() {
    const mds = window.MDS = window.MDS || {};
    const api = mds.metaPixel = mds.metaPixel || {};
    api.trackContact = function () {};
    api.trackSchedule = function () {};
    const pixel = document.querySelector('meta[name="mds-meta-pixel"]');
    const footer = document.querySelector('.site-footer__bottom');
    let choice = read(KEY), analytics = readAnalytics(), loading = false, loaded = false, initialized = false, tracked = false;

    function read(key) {
      try { const saved = JSON.parse(localStorage.getItem(key)); return saved && saved.expires > Date.now() ? saved.value : null; }
      catch (_) { return null; }
    }
    function readAnalytics() { return read(ANALYTICS_KEY) === true; }
    function save(key, value) { try { localStorage.setItem(key, JSON.stringify({value, expires: Date.now() + DURATION})); } catch (_) {} }
    function setConsent(next, measure, ads) {
      choice = next; analytics = !!measure;
      save(KEY, next); save(ANALYTICS_KEY, analytics);
      banner.hidden = true;
      applyChoice();
      if (mds.analytics) mds.analytics.syncConsent(analytics);
    }

    const settings = document.createElement('button');
    settings.type = 'button'; settings.className = 'cookie-settings'; settings.textContent = 'Préférences cookies';
    settings.setAttribute('aria-controls', 'advertising-consent');
    if (footer) footer.appendChild(settings);
    const banner = document.createElement('section');
    banner.id = 'advertising-consent'; banner.className = 'cookie-consent';
    banner.setAttribute('aria-labelledby', 'advertising-consent-title');
    banner.innerHTML = '<h2 id="advertising-consent-title">Votre choix pour les cookies</h2>' +
      '<p>Nous utilisons des cookies pour mesurer les visites et, avec votre accord, personnaliser nos publicités sur Facebook et Instagram. Vous pouvez accepter, refuser ou personnaliser vos choix.</p>' +
      '<p class="cookie-consent__details">Votre choix est conservé 180 jours et reste modifiable dans « Préférences cookies ». <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener noreferrer">Politique de confidentialité de Meta</a></p>' +
      '<div class="cookie-consent__preferences" hidden><label><input type="checkbox" data-consent-analytics> Mesure des visites</label><label><input type="checkbox" data-consent-ads> Publicités personnalisées Meta</label></div>' +
      '<div class="cookie-consent__actions"><button type="button" data-cookie-choice="accepted">Tout accepter</button><button type="button" data-cookie-choice="rejected">Tout refuser</button><button type="button" data-cookie-choice="custom">Personnaliser</button></div>';
    document.body.appendChild(banner);
    banner.hidden = choice !== null;
    const prefs = banner.querySelector('.cookie-consent__preferences');
    const measure = banner.querySelector('[data-consent-analytics]');
    const ads = banner.querySelector('[data-consent-ads]');
    measure.checked = analytics; ads.checked = choice === 'accepted';
    if (settings) settings.addEventListener('click', function () { banner.hidden = false; banner.querySelector('button').focus(); });
    banner.addEventListener('click', function (event) {
      const button = event.target.closest('[data-cookie-choice]'); if (!button) return;
      const selected = button.dataset.cookieChoice;
      if (selected === 'custom') {
        prefs.hidden = false; button.dataset.cookieChoice = 'save-custom'; button.textContent = 'Enregistrer mes choix';
        measure.focus(); return;
      }
      if (selected === 'accepted') setConsent('accepted', true, true);
      else if (selected === 'rejected') setConsent('rejected', false, false);
      else if (selected === 'save-custom') {
        choice = ads.checked ? 'accepted' : 'custom';
        setConsent(choice, measure.checked, ads.checked);
      }
      if (settings) settings.focus({preventScroll: true});
    });
    window.addEventListener('storage', function (event) {
      if (event.key !== KEY && event.key !== ANALYTICS_KEY && event.key !== null) return;
      choice = read(KEY); analytics = readAnalytics(); banner.hidden = choice !== null;
      applyChoice(); if (mds.analytics) mds.analytics.syncConsent(analytics);
    });

    function clearPixelCookies() {
      for (const name of ['_fbp', '_fbc']) for (const domain of ['', '; domain=' + location.hostname, '; domain=maisondustoream.com'])
        document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax' + domain;
    }
    function trackPage() {
      if (choice !== 'accepted' || !pixel || !/^\d+$/.test(pixel.content)) return;
      if (!initialized) { window.fbq('set', 'autoConfig', false, pixel.content); window.fbq('init', pixel.content); initialized = true; }
      window.fbq('consent', 'grant');
      if (!tracked) { window.fbq('track', 'PageView'); tracked = true; }
    }
    api.trackContact = function () { if (choice !== 'accepted' || !loaded || !initialized) return false; window.fbq('track', 'Contact'); return true; };
    api.trackSchedule = function () { if (choice !== 'accepted' || !loaded || !initialized) return false; window.fbq('track', 'Schedule'); return true; };
    function applyChoice() {
      if (choice !== 'accepted') { if (initialized && window.fbq) window.fbq('consent', 'revoke'); clearPixelCookies(); return; }
      const production = ['maisondustoream.com', 'www.maisondustoream.com'].includes(location.hostname);
      if (!production || !pixel || !/^\d+$/.test(pixel.content)) return;
      if (loaded) { trackPage(); return; } if (loading) return; loading = true;
      if (!window.fbq) { const fbq = function () { fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments); }; window.fbq = fbq; window._fbq = window._fbq || fbq; fbq.push = fbq; fbq.loaded = true; fbq.version = '2.0'; fbq.queue = []; }
      const script = document.createElement('script'); script.async = true; script.src = 'https://connect.facebook.net/en_US/fbevents.js';
      script.onload = function () { loaded = true; loading = false; trackPage(); };
      script.onerror = function () { loading = false; script.remove(); }; document.head.appendChild(script);
    }
    applyChoice(); if (mds.analytics) mds.analytics.syncConsent(analytics);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
