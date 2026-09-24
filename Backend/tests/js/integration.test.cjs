const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function load(file, context) {
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../resources/js/mds', file), 'utf8'), context);
}

test('API preserves Laravel navigation and sends the CSRF token', async () => {
  let request;
  const context = {
    window: { MDS: { config: { routes: { home: '/', quote: '/devis' }, api: { enabled: true } } } },
    document: { querySelector: () => ({ getAttribute: () => 'test-token' }) },
    fetch: async (url, options) => { request = { url, options }; return { ok: true, json: async () => ({ data: { reference: 'test' } }) }; }
  };
  load('utils/api.js', context);
  assert.equal(context.window.MDS.config.routes.quote, '/devis');
  await context.window.MDS.api.submitStoreQuote({ acceptTerms: true });
  assert.equal(request.options.headers['X-CSRF-TOKEN'], 'test-token');
  assert.equal(request.options.credentials, 'same-origin');
  assert.equal(request.options.body, '{"acceptTerms":true}');
});

test('a newly unavailable slot clears selection and emits the change', () => {
  const listeners = {}, events = [];
  const container = {
    innerHTML: '', addEventListener: (name, callback) => { listeners[name] = callback; },
    querySelectorAll: () => [], dispatchEvent: event => events.push(event)
  };
  const context = {
    window: { MDS: { api: { isDemo: () => false }, calendar: { formatLong: date => date } } },
    CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } }
  };
  load('rendez-vous/time-slots.js', context);
  const slots = context.window.MDS.timeSlots.create(container);
  slots.setDate('2026-09-24');
  listeners.click({ target: { closest: () => ({ disabled: false, getAttribute: () => '09:00' }) } });
  assert.equal(slots.getSelected(), '09:00');
  slots.setUnavailable('2026-09-24', ['09:00']);
  assert.equal(slots.getSelected(), null);
  assert.equal(events.at(-1).detail.time, null);
  assert.match(container.innerHTML, /data-time="09:00" data-state="disabled"/);
});
