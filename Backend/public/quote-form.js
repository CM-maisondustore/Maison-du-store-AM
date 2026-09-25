(() => {
  'use strict';
  const form = document.getElementById('staff-quote-form');
  if (!form) return;
  const data = JSON.parse(document.getElementById('quote-catalogue-data').textContent);
  const defaults = document.querySelector('#quote-defaults [data-selection]');
  const windows = document.getElementById('quote-windows');
  const choice = (root, key) => root.querySelector('[data-choice="' + key + '"]');
  const values = root => Object.fromEntries(['category','product_id','motorized','material_id','finish_id'].map(key => [key, choice(root,key).value]));
  function options(select, list, selected) {
    select.replaceChildren(new Option(list.length ? 'Sélectionner…' : 'Aucune option compatible', ''));
    list.forEach(item => select.add(new Option(item.label, String(item.id))));
    select.value = String(selected ?? '');
    if (select.selectedIndex < 0) select.value = '';
  }
  function refresh(root, initial) {
    const current = initial || values(root);
    choice(root,'category').value = current.category || 'store';
    choice(root,'motorized').value = String(Number(current.motorized || 0));
    const category = choice(root,'category').value;
    const motor = choice(root,'motorized').value === '1';
    options(choice(root,'product_id'), data.products.filter(p => p.category === category).map(p => ({id:p.id,label:p.name+' · '+p.reference+(p.published ? '' : ' (masqué sur le site)')})), current.product_id);
    const product = Number(choice(root,'product_id').value);
    ['material','finish'].forEach(kind => options(choice(root,kind+'_id'), data.options.filter(o => o.active && o.kind === kind && o.category === category && product && (!o.product_ids?.length || o.product_ids.includes(product)) && (o.motorization === 'any' || o.motorization === (motor ? 'yes' : 'no'))), current[kind+'_id']));
  }
  function init(root) {
    refresh(root, Object.fromEntries([...root.querySelectorAll('[data-choice]')].map(el => [el.dataset.choice,el.dataset.value])));
  }
  function inherit(row) {
    if (row.querySelector('[data-inherit]').checked) refresh(row.querySelector('[data-selection]'), values(defaults));
  }
  function buttons() {
    document.getElementById('add-quote-window').disabled = windows.children.length >= 30;
    windows.querySelectorAll('[data-remove-window]').forEach(button => button.disabled = windows.children.length <= 1);
  }
  init(defaults);
  windows.querySelectorAll('[data-window]').forEach(row => { init(row.querySelector('[data-selection]')); inherit(row); });
  buttons();
  form.addEventListener('change', event => {
    const root = event.target.closest('[data-selection]');
    if (root) {
      if (root !== defaults) root.closest('[data-window]').querySelector('[data-inherit]').checked = false;
      refresh(root);
      if (root === defaults) windows.querySelectorAll('[data-window]').forEach(inherit);
    }
    if (event.target.matches('[data-inherit]')) inherit(event.target.closest('[data-window]'));
    if (event.target.name === 'payment_mode' && event.target.value === 'full') form.elements.deposit_amount.value = 0;
  });
  let next = Math.max(-1,...[...windows.querySelectorAll('[name]')].map(el => Number(el.name.match(/^windows\[(\d+)\]/)?.[1] ?? -1))) + 1;
  document.getElementById('add-quote-window').addEventListener('click', () => {
    if (windows.children.length >= 30) return;
    const fragment = document.getElementById('quote-window-template').content.cloneNode(true);
    fragment.querySelectorAll('[name]').forEach(el => el.name = el.name.replace('__INDEX__',next)); next++;
    const row = fragment.querySelector('[data-window]'); windows.appendChild(fragment); init(row.querySelector('[data-selection]')); inherit(row); buttons();
  });
  windows.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-window]');
    if (button && windows.children.length > 1) { button.closest('[data-window]').remove(); buttons(); }
  });
  function totals() {
    let subtotal = 0;
    form.querySelectorAll('#lines .line-row').forEach(row => { subtotal += Number(row.querySelector('[name$="[quantity]"]').value) * Number(row.querySelector('[name$="[unit_price]"]').value); });
    const tax = Number(form.elements.tax_rate.value);
    const total = subtotal + Math.round(subtotal * Math.round(tax * 100) / 10000);
    form.querySelector('[data-quote-total]').textContent = Number.isFinite(total) ? total.toLocaleString('fr-FR') : 'À vérifier';
  }
  form.addEventListener('input',totals); new MutationObserver(totals).observe(document.getElementById('lines'),{childList:true}); totals();
  let dirty = false;
  form.addEventListener('input',()=>dirty=true); form.addEventListener('change',()=>dirty=true);
  form.addEventListener('submit',()=>dirty=false);
  window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
})();
