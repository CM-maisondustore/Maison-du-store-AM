(function () {
  'use strict';
  const KEY = 'mds-advertising-consent-v1';
  const DURATION = 180 * 24 * 60 * 60 * 1000;

  function init() {
    const api = (window.MDS = window.MDS || {}).metaPixel || {};
    api.trackContact = function () {};
    api.trackSchedule = function () {};
    window.MDS.metaPixel = api;
    const pixel = document.querySelector('meta[name="mds-meta-pixel"]');
    const footer = document.querySelector('.site-footer__bottom');
    if (!pixel || !/^\d+$/.test(pixel.content) || !footer) return;
    const id = pixel.content;
    const production = ['maisondustoream.com', 'www.maisondustoream.com'].includes(location.hostname);
    let choice = readChoice(), loading = false, loaded = false, initialized = false, tracked = false;

    function readChoice() {
      try {
        const saved = JSON.parse(localStorage.getItem(KEY));
        if (saved && ['accepted', 'rejected'].includes(saved.value) && Number.isFinite(saved.expires) && saved.expires > Date.now()) return saved.value;
      } catch (_) { /* Storage unavailable: ask again on the next visit. */ }
      return null;
    }

    const settings = document.createElement('button');
    settings.type = 'button';
    settings.className = 'cookie-settings';
    settings.textContent = 'Préférences cookies';
    settings.setAttribute('aria-controls', 'advertising-consent');
    footer.appendChild(settings);
    const banner = document.createElement('section');
    banner.id = 'advertising-consent';
    banner.className = 'cookie-consent';
    banner.setAttribute('aria-labelledby', 'advertising-consent-title');
    banner.innerHTML = '<h2 id="advertising-consent-title">Votre choix pour les cookies publicitaires</h2>' +
      '<p>Avec votre accord, le Meta Pixel transmet à Meta des informations sur vos visites pour mesurer nos publicités et personnaliser les annonces. Vous pouvez refuser et continuer à utiliser le site.</p>' +
      '<p class="cookie-consent__details">Votre choix est conservé 180 jours et reste modifiable dans « Préférences cookies ». <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener noreferrer">Politique de confidentialité de Meta</a></p>' +
      '<div class="cookie-consent__actions"><button type="button" data-cookie-choice="rejected">Refuser</button><button type="button" data-cookie-choice="accepted">Accepter</button></div>';
    document.body.appendChild(banner);
    banner.hidden = choice !== null;
    settings.addEventListener('click', function () {
      banner.hidden = false;
      banner.querySelector('button').focus();
    });
    banner.addEventListener('click', function (event) {
      const button = event.target.closest('[data-cookie-choice]');
      if (!button) return;
      choice = button.dataset.cookieChoice;
      try { localStorage.setItem(KEY, JSON.stringify({value: choice, expires: Date.now() + DURATION})); } catch (_) {}
      banner.hidden = true;
      settings.focus({preventScroll: true});
      applyChoice();
    });
    window.addEventListener('storage', function (event) {
      if (event.key !== KEY && event.key !== null) return;
      choice = readChoice();
      banner.hidden = choice !== null;
      applyChoice();
    });

    function clearPixelCookies() {
      for (const name of ['_fbp', '_fbc']) {
        for (const domain of ['', '; domain=' + location.hostname, '; domain=maisondustoream.com']) {
          document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax' + domain;
        }
      }
    }

    function trackPage() {
      if (choice !== 'accepted') return;
      if (!initialized) {
        // Explicit page views only: no automatic form events or advanced matching.
        window.fbq('set', 'autoConfig', false, id);
        window.fbq('init', id);
        initialized = true;
      }
      window.fbq('consent', 'grant');
      if (!tracked) {
        window.fbq('track', 'PageView');
        tracked = true;
      }
    }

    function trackContact() {
      if (choice !== 'accepted' || !loaded || !initialized || typeof window.fbq !== 'function') return false;
      window.fbq('track', 'Contact');
      return true;
    }
    api.trackContact = trackContact;

    function trackSchedule() {
      if (choice !== 'accepted' || !loaded || !initialized || typeof window.fbq !== 'function') return false;
      window.fbq('track', 'Schedule');
      return true;
    }
    api.trackSchedule = trackSchedule;

    function applyChoice() {
      if (choice !== 'accepted') {
        if (initialized) window.fbq('consent', 'revoke');
        clearPixelCookies();
        return;
      }
      if (!production) return;
      if (loaded) { trackPage(); return; }
      if (loading) return;
      loading = true;
      if (!window.fbq) {
        const fbq = function () { fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments); };
        window.fbq = fbq;
        if (!window._fbq) window._fbq = fbq;
        fbq.push = fbq;
        fbq.loaded = true;
        fbq.version = '2.0';
        fbq.queue = [];
      }
      const script = document.createElement('script');
      script.async = true;
      script.src = 'https://connect.facebook.net/en_US/fbevents.js';
      script.onload = function () { loaded = true; loading = false; trackPage(); };
      script.onerror = function () { loading = false; script.remove(); };
      document.head.appendChild(script);
    }
    applyChoice();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
