(() => {
  'use strict';
  const form=document.getElementById('staff-quote-form'); if(!form)return;
  form.addEventListener('invalid',event=>event.target.closest('details')?.setAttribute('open',''),true);
  const data=JSON.parse(document.getElementById('quote-catalogue-data').textContent);
  const defaults=document.querySelector('#quote-defaults [data-selection]'),windows=document.getElementById('quote-windows');
  const keys=['category','motorized','curtain_id','sheer_id','store_id','finish_id'];
  const field=(root,key)=>root.querySelector('[data-choice="'+key+'"]');
  const values=root=>Object.fromEntries(keys.map(key=>[key,field(root,key).value]));
  const units={m:'mètre(s)',m2:'m²',piece:'pièce(s)',lot100:'lot(s) de 100',opening:'ouverture(s)',flat:'forfait(s)'};
  const compatible=(o,v)=>o.active&&o.category===v.category&&(o.motorization==='any'||o.motorization===(v.motorized==='1'?'yes':'no'));
  const finishLabel=o=>({'wave-manuel':'Wave manuel','wave-electric':'Wave motoris\u00e9','ruflette-wave':'Ruflette Wave','reflete-manuel':'Ruflette','barre-fer':'Barre de fer'}[o.code]||o.label);
  const label=o=>o.label+(Object.hasOwn(o,'unit_price')?' — '+(o.unit_price===null?'à définir':o.unit_price.toLocaleString('fr-FR')+' FCFA / '+units[o.unit]):'');
  function checkbox(container,o,checked,key,attribute){
    const el=document.createElement('label');el.className='check';
    const input=document.createElement('input');input.type='checkbox';input.value=o.id;input.checked=checked;input.dataset[attribute]=key;
    el.append(input);
    el.append(document.createTextNode(label(o)));container.append(el);
  }
  function refresh(root,current=values(root)){
    keys.forEach(key=>field(root,key).value=String(current[key]??''));
    const v=values(root);v.category=v.category||'rideau';v.motorized=v.motorized||'0';field(root,'category').value=v.category;field(root,'motorized').value=v.motorized;
    for(const [key,group] of Object.entries({curtain_id:'rideau',sheer_id:'voilage',store_id:'store'})){
      const box=root.querySelector('[data-fabric-group="'+key+'"]'),list=box.querySelector('[data-fabric-list]');
      box.hidden=(group==='store')!==(v.category==='store');list.replaceChildren();
      const options=box.hidden?[]:data.options.filter(o=>o.kind==='material'&&(o.fabric_group||o.category)===group&&compatible(o,v));
      if(!options.some(o=>String(o.id)===field(root,key).value))field(root,key).value='';
      options.forEach(o=>checkbox(list,o,String(o.id)===field(root,key).value,key,'fabric'));
    }
    const select=field(root,'finish_id'),selected=current.finish_id;
    select.replaceChildren(new Option('Sélectionner une finition…',''));
    data.options.filter(o=>o.kind==='finish'&&o.active&&o.category===v.category).forEach(o=>select.add(new Option(finishLabel(o),String(o.id))));
    select.value=String(selected??'');if(select.selectedIndex<0)select.value='';
  }
  function alignFinish(root,target){const selected=data.options.find(o=>String(o.id)===field(root,'finish_id').value);if(target.dataset.choice==='finish_id'&&(selected?.motorization==='yes'||selected?.motorization==='no'))field(root,'motorized').value=selected.motorization==='yes'?'1':'0';else if(target.dataset.choice==='motorized'&&selected?.motorization!=='any'&&selected?.motorization!==undefined)field(root,'finish_id').value='';}
  function init(root){
    const initial=Object.fromEntries(keys.map(key=>[key,field(root,key).dataset.value]));
    if(!initial.curtain_id&&!initial.sheer_id&&!initial.store_id){
      const material=data.options.find(o=>String(o.id)===root.dataset.material);
      if(material)initial[({rideau:'curtain_id',voilage:'sheer_id',store:'store_id'})[material.fabric_group||material.category]]=material.id;
    }
    refresh(root,initial);
  }
  function inherit(row){if(row.querySelector('[data-inherit]').checked)refresh(row.querySelector('[data-selection]'),values(defaults));}
  function components(row,initial=false){
    const v=values(row.querySelector('[data-selection]')),finish=data.options.find(o=>o.id===Number(v.finish_id));
    const material=data.options.find(o=>o.id===Number(v.category==='store'?v.store_id:(v.curtain_id||v.sheer_id)));
    const meters=row.querySelector('[data-material-quantity]');meters.disabled=material?.unit==='m2'&&row.querySelector('[data-material-mode]').value!=='manual_override';meters.placeholder=meters.disabled?'Surface calculée à partir des mesures':'Quantité en '+(units[material?.unit]||'mètres');
    row.querySelectorAll('[data-component]').forEach(input=>{
      const kind=input.dataset.component,part=input.closest('[data-cost-part]'),list=part.querySelector('[data-component-list]');
      let selected=initial?input.dataset.value:input.value;
      if(initial&&!selected&&row.querySelector('[data-costing]').dataset.costingSaved==='1')input.dataset.userChoice='1';
      const optionKind=({secondary_material:'material',extra_support:'support',extra_tape:'tape'})[kind]||kind;
      let options=data.options.filter(o=>o.kind===optionKind&&(compatible(o,v)||(kind==='extra_support'&&compatible(o,{...v,motorized:'0'})))&&(kind==='extra_support'||!o.finish_codes?.length||o.finish_codes.includes(finish?.code)));
      if(kind==='secondary_material'){
        selected=v.category==='rideau'&&v.curtain_id?v.sheer_id:'';options=options.filter(o=>String(o.id)===selected);
      }
      if(!options.some(o=>String(o.id)===String(selected)))selected='';
      if(!selected&&!input.dataset.userChoice&&['sewing','installation','motor'].includes(kind)&&options.length===1)selected=String(options[0].id);
      input.value=selected||'';part.hidden=options.length===0;list.replaceChildren();
      if(kind==='secondary_material')list.textContent=options[0]?.label||'';
      else options.forEach(o=>checkbox(list,o,String(o.id)===input.value,kind,'part'));
      const chosen=options.find(o=>String(o.id)===input.value);
      part.querySelector('[data-unit-label]').textContent=chosen?'('+units[chosen.unit]+')':'';
    });
  }
  function selectionValidity(report=false){
    const usesDefaults=[...windows.querySelectorAll('[data-inherit]')].some(input=>input.checked);
    field(defaults,'finish_id').required=usesDefaults;
    const roots=[defaults,...windows.querySelectorAll('[data-selection]')];
    let valid=true;
    for(const root of roots){
      const used=root!==defaults||usesDefaults,v=values(root),category=field(root,'category'),finish=field(root,'finish_id');
      const material=v.category==='store'?v.store_id:(v.curtain_id||v.sheer_id);
      category.setCustomValidity(used&&!material?'Sélectionnez un tissu rideau, un voilage ou un matériau de store.':'');
      if(used&&(!category.validity.valid||!finish.validity.valid)){
        if(report&&valid){const missing=!category.validity.valid?category:finish;missing.reportValidity();missing.focus();}
        valid=false;
      }
    }
    return valid;
  }
  function buttons(){document.getElementById('add-quote-window').disabled=windows.children.length>=30;windows.querySelectorAll('[data-remove-window]').forEach(b=>b.disabled=windows.children.length<=1);selectionValidity();}
  init(defaults);windows.querySelectorAll('[data-window]').forEach(row=>{
    const root=row.querySelector('[data-selection]');init(root);inherit(row);
    const legacy=data.options.find(o=>String(o.id)===row.querySelector('[data-component="secondary_material"]').dataset.value);
    if(legacy?.fabric_group==='voilage'&&field(root,'curtain_id').value&&!field(root,'sheer_id').value){field(root,'sheer_id').value=String(legacy.id);row.querySelector('[data-inherit]').checked=false;refresh(root);}
    components(row,true);
  });buttons();
  let dirty=false,revision=0,calculating=false;
  const live=document.getElementById('quote-live-total');
  function showTotal(state,label,amount){live.hidden=false;live.dataset.state=state;live.querySelector('[data-live-label]').textContent=label;if(amount!==undefined)live.querySelector('[data-live-amount]').textContent=amount.toLocaleString('fr-FR')+' FCFA';}
  function invalidate(target){
    if(!form.elements.calculation_token)return;
    if(target.closest('[data-window],#quote-defaults')||/^(pricing\[|tax_rate|lines\[)/.test(target.name||'')){
      form.elements.calculation_token.value='';
      document.getElementById('calculation-status').textContent='Des paramètres ont changé. Recalculez pour actualiser les montants affichés.';
      if(!live.hidden)showTotal('stale','Total à recalculer');
    }
  }
  form.addEventListener('input',event=>{dirty=true;revision++;invalidate(event.target);});
  form.addEventListener('change',event=>{
    dirty=true;revision++;const target=event.target,root=target.closest('[data-selection]');invalidate(target);
    if(target.dataset.fabric)field(root,target.dataset.fabric).value=target.checked?target.value:'';
    if(root){alignFinish(root,target);if(root!==defaults)root.closest('[data-window]').querySelector('[data-inherit]').checked=false;refresh(root);if(root===defaults)windows.querySelectorAll('[data-window]').forEach(inherit);}
    if(target.matches('[data-inherit]'))inherit(target.closest('[data-window]'));
    if(target.dataset.part){const input=target.closest('[data-cost-part]').querySelector('[data-component]');input.value=target.checked?target.value:'';input.dataset.userChoice='1';}
    if(root||target.matches('[data-inherit],[data-material-mode]')||target.dataset.part)windows.querySelectorAll('[data-window]').forEach(row=>components(row));
    selectionValidity();
    if(target.name==='payment_mode'&&target.value==='full')form.elements.deposit_amount.value=0;
  });
  let next=Math.max(-1,...[...windows.querySelectorAll('[name]')].map(el=>Number(el.name.match(/^windows\[(\d+)\]/)?.[1]??-1)))+1;
  document.getElementById('add-quote-window').addEventListener('click',()=>{
    if(windows.children.length>=30)return;
    const fragment=document.getElementById('quote-window-template').content.cloneNode(true);fragment.querySelectorAll('[name]').forEach(el=>el.name=el.name.replace('__INDEX__',next));next++;
    const row=fragment.querySelector('[data-window]');windows.append(fragment);init(row.querySelector('[data-selection]'));inherit(row);components(row,true);buttons();dirty=true;revision++;invalidate(row);
  });
  windows.addEventListener('click',event=>{const b=event.target.closest('[data-remove-window]');if(b&&windows.children.length>1){invalidate(b);b.closest('[data-window]').remove();buttons();dirty=true;revision++;}});
  const lines=document.getElementById('lines');
  const calculate=document.getElementById('calculate-quote'),status=document.getElementById('calculation-status');
  calculate?.addEventListener('click',async()=>{
    if(calculating)return;
    if(!selectionValidity(true)){status.textContent='Complétez les tissus / matériaux et les finitions des ouvertures. Les choix communs sont nécessaires uniquement pour les ouvertures qui les utilisent.';return;}
    calculating=true;calculate.disabled=true;const requestedRevision=revision;status.textContent='Calcul en cours…';
    try{
      const payload=new FormData(form);payload.delete('_method');
      const response=await fetch(calculate.dataset.url,{method:'POST',body:payload,headers:{Accept:'application/json'},credentials:'same-origin'}),result=await response.json();
      if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||'Calcul indisponible.');
      if(revision!==requestedRevision)throw new Error('Le formulaire a changé. Relancez le calcul.');
      lines.replaceChildren();lines.dataset.next=String(result.lines.length);
      result.lines.forEach((line,index)=>{const fragment=document.getElementById('lines-template').content.cloneNode(true);fragment.querySelectorAll('[name]').forEach(el=>{el.name=el.name.replace('__INDEX__',index);el.value=line[el.name.match(/\[([^\]]+)\]$/)[1]];});lines.append(fragment);});
      form.elements.calculation_token.value=result.token;form.elements.use_catalogue.value='1';
      if(result.summary)document.getElementById('quote-financial-result').innerHTML=result.summary;
      if(result.financial){form.elements.deposit_amount.value=result.financial.deposit;form.elements.payment_mode.value=result.financial.depositPercent==='100'?'full':'advance';}
      status.textContent=result.pending.length?'Total provisoire. À définir : '+result.pending.join(' ; '):'Chiffrage préparé. Vos quantités et prix personnalisés ont été conservés.';dirty=true;
      if(result.financial)showTotal(result.pending.length?'pending':'ok',result.pending.length?'Total provisoire':'Total définitif',result.financial.finalTotal);
      form.dispatchEvent(new CustomEvent('mds:calculated'));
    }catch(error){status.textContent=error.message;}finally{calculating=false;calculate.disabled=false;}
  });
  form.addEventListener('submit',event=>{
    if(calculate&&(calculating||(form.elements.use_catalogue.value==='1'&&!form.elements.calculation_token.value))){event.preventDefault();status.textContent='Calculez le chiffrage avant enregistrement.';status.scrollIntoView({block:'center'});}
    if(!event.defaultPrevented)dirty=false;
  });
  window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
})();
