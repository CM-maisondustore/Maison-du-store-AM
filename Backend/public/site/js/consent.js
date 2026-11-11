/* Bandeau cookies du site V2. Reprend les clés et la logique du site précédent (meta-pixel.js) :
   mesure des visites (outil interne, /api/analytics/collect) et pixel Meta, chacun seulement avec accord. */
(function () {
  'use strict';
  var KEY = 'mds-advertising-consent-v1', ANALYTICS_KEY = 'mds-analytics-consent-v1';
  var DURATION = 180 * 24 * 60 * 60 * 1000;
  var mds = window.MDS = window.MDS || {};
  var api = mds.metaPixel = mds.metaPixel || {};
  api.trackContact = function () { return false; };
  api.trackSchedule = function () { return false; };

  function read(key) {
    try { var saved = JSON.parse(localStorage.getItem(key)); return saved && saved.expires > Date.now() ? saved.value : null; }
    catch (_) { return null; }
  }
  function save(key, value) { try { localStorage.setItem(key, JSON.stringify({ value: value, expires: Date.now() + DURATION })); } catch (_) {} }

  function init() {
    var pixel = document.querySelector('meta[name="mds-meta-pixel"]');
    var choice = read(KEY), analytics = read(ANALYTICS_KEY) === true;
    var loading = false, loaded = false, initialized = false, tracked = false, opener = null;

    var banner = document.createElement('section');
    banner.className = 'ck';
    banner.id = 'mds-cookies';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-labelledby', 'ck-t');
    banner.innerHTML =
      '<div class="ck-in">' +
        '<div class="ck-head"><span aria-hidden="true" class="mark">AM</span><h2 id="ck-t" class="serif-26">Votre choix pour les cookies</h2></div>' +
        '<p class="ck-p">Avec votre accord, nous mesurons les visites avec notre propre outil et suivons l’efficacité de nos publicités Facebook et Instagram. Refuser ne change rien à votre demande de devis.</p>' +
        '<div class="ck-prefs" data-ck-prefs hidden>' +
          '<label class="ck-opt"><span><strong>Nécessaires</strong><span class="small">Session et sécurité des formulaires</span></span><input type="checkbox" checked disabled></label>' +
          '<label class="ck-opt"><span><strong>Mesure des visites</strong><span class="small">Pages vues, sans adresse IP</span></span><input type="checkbox" data-ck-analytics></label>' +
          '<label class="ck-opt"><span><strong>Publicité</strong><span class="small">Pixel Meta (Facebook, Instagram)</span></span><input type="checkbox" data-ck-ads></label>' +
        '</div>' +
        '<div class="ck-act">' +
          '<button type="button" class="btn btn-gold btn-46" data-ck="accepted">Tout accepter</button>' +
          '<button type="button" class="btn btn-line btn-46" data-ck="rejected">Tout refuser</button>' +
          '<button type="button" class="alink-btn" data-ck="custom">Personnaliser</button>' +
        '</div>' +
        '<p class="ck-small">Choix conservé 180 jours, modifiable avec « Gérer les cookies » en bas de page. <a class="alink" href="/confidentialite#cookies">En savoir plus</a></p>' +
      '</div>';
    document.body.appendChild(banner);
    var prefs = banner.querySelector('[data-ck-prefs]');
    var measure = banner.querySelector('[data-ck-analytics]');
    var ads = banner.querySelector('[data-ck-ads]');
    var customBtn = banner.querySelector('[data-ck=custom]');

    function show(open) {
      banner.hidden = !open;
      document.body.classList.toggle('has-ck', open);
      if (!open) return;
      measure.checked = analytics; ads.checked = choice === 'accepted';
    }
    function setConsent(next, measureOn) {
      choice = next; analytics = !!measureOn;
      save(KEY, next); save(ANALYTICS_KEY, analytics);
      show(false);
      prefs.hidden = true; customBtn.dataset.ck = 'custom'; customBtn.textContent = 'Personnaliser';
      applyChoice();
      if (mds.analytics) mds.analytics.syncConsent(analytics);
      if (opener) { opener.focus({ preventScroll: true }); opener = null; }
    }
    banner.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ck]'); if (!b) return;
      var k = b.dataset.ck;
      if (k === 'custom') { prefs.hidden = false; b.dataset.ck = 'save'; b.textContent = 'Enregistrer mes choix'; measure.focus(); return; }
      if (k === 'accepted') setConsent('accepted', true);
      else if (k === 'rejected') setConsent('rejected', false);
      else if (k === 'save') setConsent(ads.checked ? 'accepted' : 'custom', measure.checked);
    });
    document.addEventListener('click', function (e) {
      var o = e.target.closest('[data-cookie-open]'); if (!o) return;
      opener = o; show(true); banner.querySelector('button').focus();
    });
    window.addEventListener('storage', function (e) {
      if (e.key !== KEY && e.key !== ANALYTICS_KEY && e.key !== null) return;
      choice = read(KEY); analytics = read(ANALYTICS_KEY) === true; show(choice === null);
      applyChoice(); if (mds.analytics) mds.analytics.syncConsent(analytics);
    });

    function clearPixelCookies() {
      ['_fbp', '_fbc'].forEach(function (name) {
        ['', '; domain=' + location.hostname, '; domain=maisondustoream.com'].forEach(function (domain) {
          document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax' + domain;
        });
      });
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
      // Le pixel n'est chargé que sur le domaine de production.
      var production = ['maisondustoream.com', 'www.maisondustoream.com'].indexOf(location.hostname) >= 0;
      if (!production || !pixel || !/^\d+$/.test(pixel.content)) return;
      if (loaded) { trackPage(); return; } if (loading) return; loading = true;
      if (!window.fbq) { var fbq = function () { fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments); }; window.fbq = fbq; window._fbq = window._fbq || fbq; fbq.push = fbq; fbq.loaded = true; fbq.version = '2.0'; fbq.queue = []; }
      var s = document.createElement('script'); s.async = true; s.src = 'https://connect.facebook.net/en_US/fbevents.js';
      s.onload = function () { loaded = true; loading = false; trackPage(); };
      s.onerror = function () { loading = false; s.remove(); };
      document.head.appendChild(s);
    }

    show(choice === null);
    applyChoice();
    if (mds.analytics) mds.analytics.syncConsent(analytics);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
