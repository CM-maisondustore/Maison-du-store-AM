const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

test('a click before the detail request finishes opens the product with its image', async () => {
  let finish;
  let opened = 0;
  const content = { innerHTML: '', querySelectorAll: () => [] };
  const modal = { querySelector: () => content, addEventListener: () => {} };
  const MDS = {
    api: { getProducts: () => new Promise(resolve => { finish = resolve; }) },
    data: {}, config: {}, validation: { escapeHtml: value => String(value).replaceAll('"', '&quot;') },
    modal: { isOpen: () => false, open: () => { opened++; } }
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../resources/js/mds/catalogue/product-details.js'), 'utf8'), {
    window: { MDS }, document: { getElementById: () => modal, addEventListener: () => {} }
  });
  MDS.productDetails.init();
  const opening = MDS.productDetails.open('1');
  assert.equal(opened, 0);
  finish([{ id: 1, type: 'store', name: 'Store', reference: 'S1', description: 'Description', available: true, imageUrl: '/media/1/hash', imageAlt: 'Store photographié' }]);
  await opening;
  assert.equal(opened, 1);
  assert.match(content.innerHTML, /src="\/media\/1\/hash"/);
  assert.match(content.innerHTML, /alt="Store photographié"/);
});
