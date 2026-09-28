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
  const unitLabels = {m:'mètre(s)',m2:'m²',piece:'pièce(s)',lot100:'lot(s) de 100',opening:'ouverture(s)',flat:'forfait(s)'};
  function components(row, initial = false) {
    const v=values(row.querySelector('[data-selection]'));
    const finish=data.options.find(o=>o.id===Number(v.finish_id));
    const material=data.options.find(o=>o.id===Number(v.material_id));
    const materialQuantity=row.querySelector('[data-material-quantity]');
    materialQuantity.disabled=material?.unit==='m2';
    materialQuantity.placeholder=material?.unit==='m2' ? 'Surface calculée : largeur × hauteur' : 'Quantité en '+(unitLabels[material?.unit] || 'mètres');
    row.querySelectorAll('[data-component]').forEach(select=>{
      const kind=select.dataset.component;
      const previous=initial ? select.dataset.value : select.value;
      if (initial && !previous && row.querySelector('[data-costing]').dataset.costingSaved==='1') select.dataset.userChoice='1';
      const choices=data.options.filter(o=>o.kind===(kind==='secondary_material' ? 'material' : kind) && (kind!=='secondary_material' || v.category==='rideau') && o.active && o.category===v.category && Number(v.product_id) && (!o.product_ids?.length || o.product_ids.includes(Number(v.product_id))) && (o.motorization==='any' || o.motorization===(v.motorized==='1' ? 'yes':'no')) && (!o.finish_codes?.length || o.finish_codes.includes(finish?.code)));
      select.replaceChildren(new Option(kind==='installation' ? 'À définir' : 'Non inclus',''));
      choices.forEach(o=>select.add(new Option(o.label+' — '+(o.unit_price===null ? 'à définir' : o.unit_price.toLocaleString('fr-FR')+' FCFA / '+unitLabels[o.unit]),String(o.id))));
      select.value=previous || '';
      if (select.selectedIndex<0) select.value='';
      if (!select.value && !previous && !select.dataset.userChoice && ['installation','sewing','motor'].includes(kind) && choices.length===1) select.value=String(choices[0].id);
      const selected=choices.find(o=>o.id===Number(select.value));
      select.closest('[data-cost-part]').querySelector('[data-unit-label]').textContent=selected ? '('+unitLabels[selected.unit]+')' : '';
    });
    row.dataset.costCategory=v.category; row.dataset.costMotor=v.motorized;
  }
  function inherit(row) {
    if (row.querySelector('[data-inherit]').checked) refresh(row.querySelector('[data-selection]'), values(defaults));
  }
  function buttons() {
    document.getElementById('add-quote-window').disabled = windows.children.length >= 30;
    windows.querySelectorAll('[data-remove-window]').forEach(button => button.disabled = windows.children.length <= 1);
  }
  init(defaults);
  windows.querySelectorAll('[data-window]').forEach(row => { init(row.querySelector('[data-selection]')); inherit(row); components(row,true); });
  buttons();
  form.addEventListener('change', event => {
    if (event.target.matches('[data-component]')) event.target.dataset.userChoice='1';
    const root = event.target.closest('[data-selection]');
    if (root) {
      if (root !== defaults) root.closest('[data-window]').querySelector('[data-inherit]').checked = false;
      refresh(root);
      if (root === defaults) windows.querySelectorAll('[data-window]').forEach(inherit);
    }
    if (event.target.matches('[data-inherit]')) inherit(event.target.closest('[data-window]'));
    if (root || event.target.matches('[data-inherit], [data-component]')) windows.querySelectorAll('[data-window]').forEach(row=>components(row));
    if (event.target.name === 'payment_mode' && event.target.value === 'full') form.elements.deposit_amount.value = 0;
  });
  let next = Math.max(-1,...[...windows.querySelectorAll('[name]')].map(el => Number(el.name.match(/^windows\[(\d+)\]/)?.[1] ?? -1))) + 1;
  document.getElementById('add-quote-window').addEventListener('click', () => {
    if (windows.children.length >= 30) return;
    const fragment = document.getElementById('quote-window-template').content.cloneNode(true);
    fragment.querySelectorAll('[name]').forEach(el => el.name = el.name.replace('__INDEX__',next)); next++;
    const row = fragment.querySelector('[data-window]'); windows.appendChild(fragment); init(row.querySelector('[data-selection]')); inherit(row); components(row,true); buttons();
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
  const calculate=document.getElementById('calculate-quote');
  const status=document.getElementById('calculation-status');
  let calculating=false;
  let revision=0;
  form.addEventListener('input',()=>revision++);
  form.addEventListener('change',()=>revision++);
  windows.addEventListener('click',()=>revision++);
  document.getElementById('add-quote-window').addEventListener('click',()=>revision++);
  calculate.addEventListener('click',async()=>{
    if (calculating) return;
    if ([...form.querySelectorAll('#lines [name$="[unit_price]"]')].some(el=>Number(el.value)>0) && !window.confirm('Remplacer les lignes actuelles par le calcul du catalogue ?')) return;
    calculating=true; calculate.disabled=true;
    const requestedRevision=revision;
    status.textContent='Calcul en cours…';
    try {
      const payload=new FormData(form); payload.delete('_method');
      const response=await fetch(calculate.dataset.url,{method:'POST',body:payload,headers:{Accept:'application/json'},credentials:'same-origin'});
      const result=await response.json();
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || 'Le calcul est indisponible.');
      if (revision!==requestedRevision) throw new Error('Le formulaire a changé pendant le calcul. Relancez le calcul.');
      const container=document.getElementById('lines'); container.replaceChildren(); container.dataset.next=String(result.lines.length);
      result.lines.forEach((line,index)=>{
        const fragment=document.getElementById('lines-template').content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach(el=>{
          el.name=el.name.replace('__INDEX__',index);
          const key=el.name.match(/\[([^\]]+)\]$/)[1]; el.value=line[key];
        }); container.appendChild(fragment);
      });
      form.elements.calculation_token.value=result.token; form.elements.use_catalogue.value='1';
      status.textContent=result.pending.length ? 'Total provisoire. À définir : '+result.pending.join(' ; ')+'. Complétez avant validation.' : 'Chiffrage préparé. Vous pouvez ajuster les lignes avant enregistrement.';
      dirty=true; totals();
    } catch(error) {status.textContent=error.message || 'Calcul impossible. Réessayez.';}
    finally {calculating=false;calculate.disabled=false;}
  });
  form.addEventListener('submit',event=>{
    if (calculating || (form.elements.use_catalogue.value==='1' && !form.elements.calculation_token.value)) {
      event.preventDefault(); status.textContent='Calculez le chiffrage depuis le catalogue avant enregistrement.'; status.scrollIntoView({block:'center'});
    }
  });
  form.addEventListener('input',()=>dirty=true); form.addEventListener('change',()=>dirty=true);
  form.addEventListener('submit',event=>{if(!event.defaultPrevented)dirty=false;});
  window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
})();
