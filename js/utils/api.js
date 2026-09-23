/* ==========================================================================
   utils/api.js
   Point unique de raccordement au backend Laravel / API JSON.

   Tant que `config.api.enabled` vaut false (état actuel), le site fonctionne
   seul : les produits viennent de js/catalogue/products.js et les envois de
   formulaires sont simulés (rien n'est transmis).

   Les vues Blade injectent automatiquement la configuration Laravel avant
   le chargement de ce fichier. En dehors de Laravel (maquette statique),
   le mode démonstration reste disponible.

   Pour utiliser une autre API, avant le chargement des scripts :
     <script>window.MDS = { config: { api: { enabled: true, baseUrl: "" } } };</script>
   ou modifier `defaults.api.enabled` ci-dessous.

   Endpoints prévus :
     GET  /api/products/
     POST /api/quotes/stores/
     POST /api/quotes/curtains/
     POST /api/appointments/
   ========================================================================== */
(function (MDS) {
  'use strict';

  const defaults = {
    business: {
      name: 'Maison du Store AM',
      email: 'maisondustore.ci@gmail.com',
      phones: ['+225 07 12 44 93 19', '+225 07 67 49 58 04', '+225 01 73 82 63 76'],
      address: 'Cocody - Marché Saint Jean, Abidjan, Côte d\u2019Ivoire'
    },
    api: {
      enabled: false,
      baseUrl: '',
      timeoutMs: 12000,
     csrfCookie: 'XSRF-TOKEN',
     csrfHeader: 'X-CSRF-TOKEN',
      endpoints: {
        products: '/api/products/',
        storeQuotes: '/api/quotes/stores/',
        curtainQuotes: '/api/quotes/curtains/',
        appointments: '/api/appointments/'
      }
    }
  };

  // Fusionne une configuration éventuellement posée avant le chargement des scripts.
  const preset = MDS.config || {};
  const config = {
    business: Object.assign({}, defaults.business, preset.business),
    api: Object.assign({}, defaults.api, preset.api, {
      endpoints: Object.assign({}, defaults.api.endpoints, preset.api && preset.api.endpoints)
    })
  };
  MDS.config = config;

  function isDemo() {
    return !config.api.enabled;
  }

  function readCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }

  /** Jeton CSRF Laravel (meta @csrf en priorité, cookie Sanctum sinon). */
  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return (meta && meta.getAttribute('content')) || readCookie(config.api.csrfCookie);
  }

  /** Requête JSON générique. Rejette avec { status, data } si la réponse n'est pas 2xx. */
  function request(method, path, payload) {
    const controller = typeof AbortController === 'function' ? new AbortController() : null;
    const timer = controller
      ? window.setTimeout(function () { controller.abort(); }, config.api.timeoutMs)
      : null;

    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const options = { method, headers, credentials: 'same-origin' };
    if (controller) options.signal = controller.signal;
    if (payload !== undefined) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(payload);
      const csrf = csrfToken();
      if (csrf) headers[config.api.csrfHeader] = csrf;
    }

    return fetch(config.api.baseUrl + path, options)
      .then(function (response) {
        return response
          .json()
          .catch(function () { return null; })
          .then(function (data) {
            if (!response.ok) {
              const err = new Error('HTTP ' + response.status);
              err.status = response.status;
              err.data = data;
              throw err;
            }
            return { ok: true, demo: false, data: data };
          });
      })
      .finally(function () {
        if (timer) window.clearTimeout(timer);
      });
  }

  /** Simule un envoi réussi quand l'API n'est pas branchée. */
  function simulate(label, payload) {
    if (window.console && console.info) console.info('[MDS mode démonstration] ' + label, payload);
    return new Promise(function (resolve) {
      window.setTimeout(function () {
        resolve({ ok: true, demo: true, data: null });
      }, 600);
    });
  }

  function send(label, endpointKey, payload) {
    return isDemo() ? simulate(label, payload) : request('POST', config.api.endpoints[endpointKey], payload);
  }

  /** Rend tolérante la lecture d'une Laravel API Resource (snake_case ou camelCase). */
  function normalizeProduct(product) {
    const item = product || {};
    return Object.assign({}, item, {
      type: item.type || item.product_type || '',
      quoteModel: item.quoteModel || item.quote_model || '',
      available: item.available !== undefined ? item.available : Boolean(item.is_available),
      featured: item.featured !== undefined ? item.featured : Boolean(item.is_featured),
      features: Array.isArray(item.features) ? item.features : [],
      colors: Array.isArray(item.colors) ? item.colors : [],
      specs: Array.isArray(item.specs) ? item.specs : []
    });
  }

  /** Liste des produits : données locales tant que l'API n'est pas branchée. */
  function getProducts() {
    if (isDemo()) {
      const local = (MDS.data && MDS.data.products) || [];
      return Promise.resolve(local.slice());
    }
    return request('GET', config.api.endpoints.products).then(function (res) {
      const data = res.data;
      const list = Array.isArray(data) ? data : (data && (data.data || data.results)) || [];
      return list.map(normalizeProduct);
    });
  }

  const submitStoreQuote = function (payload) { return send('POST /api/quotes/stores/', 'storeQuotes', payload); };
  const submitCurtainQuote = function (payload) { return send('POST /api/quotes/curtains/', 'curtainQuotes', payload); };
  const submitAppointment = function (payload) { return send('POST /api/appointments/', 'appointments', payload); };

  /** Lien mailto: vers l'entreprise, utilisé comme solution de repli en mode démonstration. */
  function mailtoLink(subject, body) {
    return (
      'mailto:' + config.business.email +
      '?subject=' + encodeURIComponent(subject) +
      '&body=' + encodeURIComponent(body)
    );
  }

  MDS.api = {
    config,
    isDemo,
    getProducts,
    submitStoreQuote,
    submitCurtainQuote,
    submitAppointment,
    normalizeProduct,
    mailtoLink
  };
})((window.MDS = window.MDS || {}));
