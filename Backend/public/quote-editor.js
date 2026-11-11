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
  // Un menu déroulant par choix ; il est réutilisé pour garder le focus pendant la saisie.
  function choices(container,options,value,key,attribute,empty,name){
    let select=container.querySelector('select');
    if(!select){select=document.createElement('select');select.dataset[attribute]=key;if(name)select.setAttribute('aria-label',name);container.append(select);}
    select.replaceChildren(new Option(empty,''),...options.map(o=>new Option(label(o),String(o.id))));
    select.value=String(value||'');if(select.selectedIndex<0)select.value='';
  }
  function refresh(root,current=values(root)){
    keys.forEach(key=>field(root,key).value=String(current[key]??''));
    const v=values(root);v.category=v.category||'rideau';v.motorized=v.motorized||'0';field(root,'category').value=v.category;field(root,'motorized').value=v.motorized;
    for(const [key,group] of Object.entries({curtain_id:'rideau',sheer_id:'voilage',store_id:'store'})){
      const box=root.querySelector('[data-fabric-group="'+key+'"]'),list=box.querySelector('[data-fabric-list]');
      box.hidden=(group==='store')!==(v.category==='store');
      const options=box.hidden?[]:data.options.filter(o=>o.kind==='material'&&(o.fabric_group||o.category)===group&&compatible(o,v));
      if(!options.some(o=>String(o.id)===field(root,key).value))field(root,key).value='';
      choices(list,options,field(root,key).value,key,'fabric',group==='store'?'Choisir le matériau…':'Aucun');
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
  /* Chiffrage configuré dans « Matériaux et finitions » (calcul, coefficient, base si quantité vide) :
     appliqué quand une option est choisie sur l'ouverture ; un devis enregistré garde ses réglages au chargement. */
  const setCalc=(el,value)=>{if(!el||value===''||value==null)return;if(el.tagName==='SELECT'&&![...el.options].some(o=>o.value===value))return;el.value=value;};
  function applyCalc(row,holder,option,fields,initial){
    const id=option?String(option.id):'',first=holder.dataset.calcFor===undefined;
    if(holder.dataset.calcFor===id)return;
    holder.dataset.calcFor=id;
    if(!option||(first&&initial&&row.querySelector('[data-costing]')?.dataset.costingSaved==='1'))return;
    const coef=option.calc_coefficient!=null&&option.calc_coefficient!==''?String(Number(option.calc_coefficient)):'';
    setCalc(fields.mode,option.calc_mode||'');setCalc(fields.coef,coef);setCalc(fields.basis,option.calc_basis||'');
  }
  function components(row,initial=false){
    const v=values(row.querySelector('[data-selection]')),finish=data.options.find(o=>o.id===Number(v.finish_id));
    const material=data.options.find(o=>o.id===Number(v.category==='store'?v.store_id:(v.curtain_id||v.sheer_id)));
    row.dataset.category=v.category;
    applyCalc(row,row.querySelector('[data-material-mode]'),material,{mode:row.querySelector('[data-material-mode]'),coef:row.querySelector('[name$="[costing][material_coefficient]"]')},initial);
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
      // Le rail se choisit dans la ligne de l'ouverture ; ses quantités restent dans le bloc replié.
      input.value=selected||'';row.querySelectorAll('[data-cost-part="'+kind+'"]').forEach(p=>p.hidden=options.length===0);
      if(kind==='secondary_material')list.textContent=options[0]?.label||'';
      else{choices(list,options,input.value,kind,'part','Aucun',list.dataset.name||part.querySelector('h4,[data-part-label]')?.textContent);if(list.dataset.name)partChips(list,options,input);}
      const chosen=options.find(o=>String(o.id)===input.value),unit=row.querySelector('[data-cost-part="'+kind+'"] [data-unit-label]');
      if(unit)unit.textContent=chosen?'('+units[chosen.unit]+')':'';
      const blocks=[...row.querySelectorAll('[data-cost-part="'+kind+'"]')],inPart=sel=>blocks.map(b=>b.querySelector(sel)).find(Boolean);
      applyCalc(row,input,chosen,{mode:inPart('[data-part-mode]'),coef:inPart('.cost-coef input'),basis:inPart('select[name$="[basis]"]')||inPart('[data-sewing-basis]')},initial);
    });
    coefChips(row);
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
  /* Choix communs en pastilles : chaque pastille pilote le menu d'origine, qui reste le champ enregistré.
     Fournitures (rails, ruflettes, accroches) : Oui / Non, appliqué aux ouvertures qui suivent les choix communs. */
  const board=document.querySelector('[data-choice-board]'),supplyBoard=document.querySelector('[data-supply-board]');
  const chip=(text,pressed,act)=>{const b=document.createElement('button');b.type='button';b.className='chip-option';b.textContent=text;b.setAttribute('aria-pressed',String(pressed));b.addEventListener('click',act);return b;};
  const line=(name,chips)=>{const row=document.createElement('div'),title=document.createElement('span'),set=document.createElement('div');row.className='choice-line';title.className='choice-name';title.textContent=name;set.className='chip-set';set.setAttribute('role','group');set.setAttribute('aria-label',name);chips.forEach(b=>b.dataset.key=name+'|'+b.textContent);set.append(...chips);row.append(title,set);return row;};
  const keepFocus=(box,paint)=>{const key=box.contains(document.activeElement)?document.activeElement.dataset.key:null;paint();if(key)[...box.querySelectorAll('[data-key]')].find(b=>b.dataset.key===key)?.focus();};
  // « Tissu occultant 60 % », « Tissu occultant 80 % » : la ligne s'appelle « Tissu occultant », les pastilles « 60 % » et « 80 % ».
  function split(options,fallback){
    const words=options.map(o=>o.label.split(/\s+/));let common=0;
    if(options.length>1)while(words.every(w=>w.length>common+1&&w[common]===words[0][common]))common++;
    return {name:common?words[0].slice(0,common).join(' '):fallback,label:o=>common?o.label.split(/\s+/).slice(common).join(' '):o.label};
  }
  const pick=(control,value)=>{control.value=String(value);control.dispatchEvent(new Event('change',{bubbles:true}));};
  /* Étape 2, « Quantités, fournitures et prix » : Oui / Non (ou un choix par fourniture) à la place du menu, qui reste le champ enregistré. */
  function partChips(list,options,input){
    const select=list.querySelector('select');select.hidden=true;
    let set=list.querySelector('.chip-set');
    if(!set){set=document.createElement('div');set.className='chip-set';set.setAttribute('role','group');set.setAttribute('aria-label',list.dataset.name);list.append(set);}
    const make=(text,value)=>{const b=chip(text,input.value===value,()=>pick(select,value));b.dataset.key=text+'|'+value;return b;};
    const chips=options.length===1?[make('Oui',String(options[0].id)),make('Non','')]:[...options.map(o=>make(o.label,String(o.id))),make('Non','')];
    if(options.length===1)chips.push(Object.assign(document.createElement('small'),{className:'hint',textContent:label(options[0])}));
    keepFocus(set,()=>set.replaceChildren(...chips));
  }
  const decimal=value=>String(value??'').trim().replace(',','.');
  function coefChips(scope=form){scope.querySelectorAll('.cost-coef').forEach(box=>{const value=decimal(box.querySelector('input').value);box.querySelectorAll('[data-set-coef]').forEach(b=>b.setAttribute('aria-pressed',String(Number(b.dataset.setCoef)===Number(value)&&value!=='')));});}
  form.addEventListener('click',event=>{
    const b=event.target.closest('[data-set-coef]');if(!b)return;
    const input=b.closest('.cost-coef').querySelector('input'),mode=b.closest('[data-cost-part]')?.querySelector('[data-part-mode]');
    if(mode&&mode.value!=='coefficient')mode.value='coefficient';
    input.value=b.dataset.setCoef;input.dispatchEvent(new Event('change',{bubbles:true}));
  });
  ['input','change'].forEach(type=>form.addEventListener(type,event=>{if(event.target.closest?.('.cost-coef,[data-calc-form]'))coefChips();}));
  function paintBoard(){
    if(!board)return;
    defaults.classList.add('has-board');
    const v=values(defaults),rows=[];
    const category=field(defaults,'category'),motorized=field(defaults,'motorized');
    rows.push(line('Projet',[...category.options].map(o=>chip(o.textContent,o.value===v.category,()=>pick(category,o.value)))));
    rows.push(line('Motorisé',[['0','Non'],['1','Oui']].map(([value,text])=>chip(text,v.motorized===value,()=>pick(motorized,value)))));
    for(const [key,group,fallback] of [['curtain_id','rideau','Rideaux — tissu'],['sheer_id','voilage','Voilages'],['store_id','store','Store — matériau']]){
      const box=defaults.querySelector('[data-fabric-group="'+key+'"]'),select=box.querySelector('[data-fabric-list] select');
      if(box.hidden||!select)continue;
      const options=data.options.filter(o=>[...select.options].some(s=>s.value===String(o.id))),named=split(options,fallback);
      options.sort((x,y)=>named.label(x).localeCompare(named.label(y),'fr',{numeric:true}));
      const chips=options.map(o=>chip(named.label(o),String(o.id)===v[key],()=>pick(select,o.id)));
      if(group!=='store')chips.unshift(chip('Aucun',!v[key],()=>pick(select,'')));
      if(options.length)rows.push(line(named.name,chips));
    }
    keepFocus(board,()=>board.replaceChildren(...rows));
    paintSupplies(v);
  }
  function paintSupplies(v=values(defaults)){
    if(!supplyBoard)return;
    const inheriting=[...windows.querySelectorAll('[data-window]')].filter(row=>row.querySelector('[data-inherit]').checked),first=inheriting[0];
    const finish=data.options.find(o=>o.id===Number(v.finish_id)),groups=[];
    for(const [kind,slots,title] of [['support',['support','extra_support'],'Rails et barres'],['tape',['tape','extra_tape'],'Ruflettes'],['hooks',['hooks'],'Accroches']]){
      const options=data.options.filter(o=>o.kind===kind&&compatible(o,v)&&(!o.finish_codes?.length||o.finish_codes.includes(finish?.code)));
      if(!options.length)continue;
      const chosen=first?slots.map(slot=>first.querySelector('[data-component="'+slot+'"]')?.value).filter(Boolean):[];
      const apply=list=>{inheriting.forEach(row=>slots.forEach((slot,i)=>{const input=row.querySelector('[data-component="'+slot+'"]');if(input){input.value=list[i]?String(list[i]):'';input.dataset.userChoice='1';}}));
        windows.querySelectorAll('[data-window]').forEach(row=>components(row));dirty=true;revision++;invalidate(supplyBoard);selectionValidity();paintSupplies();form.dispatchEvent(new Event('change'));};
      const heading=document.createElement('p');heading.className='choice-group';heading.textContent=title+(slots.length>1?' (deux au plus)':'');
      groups.push(heading,...options.map(o=>{const on=chosen.includes(String(o.id));return line(o.label,[chip('Oui',on,()=>{if(!on)apply([...chosen,String(o.id)].slice(-slots.length));}),chip('Non',!on,()=>{if(on)apply(chosen.filter(id=>id!==String(o.id)));})]);}));
    }
    supplyBoard.hidden=!groups.length||!first;
    keepFocus(supplyBoard,()=>supplyBoard.querySelector('[data-supply-lines]').replaceChildren(...groups));
  }
  init(defaults);windows.querySelectorAll('[data-window]').forEach(row=>{
    const root=row.querySelector('[data-selection]');init(root);inherit(row);
    const legacy=data.options.find(o=>String(o.id)===row.querySelector('[data-component="secondary_material"]').dataset.value);
    if(legacy?.fabric_group==='voilage'&&field(root,'curtain_id').value&&!field(root,'sheer_id').value){field(root,'sheer_id').value=String(legacy.id);row.querySelector('[data-inherit]').checked=false;refresh(root);}
    components(row,true);
  });buttons();paintBoard();coefChips();
  let dirty=false,revision=0,calculating=false,pending=null;
  const live=document.getElementById('quote-live-total'),recapBox=document.getElementById('quote-recap');
  // Récapitulatif de l'étape 4, relu dans les champs du formulaire.
  function recap(){
    if(!recapBox)return;
    const set=(key,text)=>{recapBox.querySelector('[data-recap="'+key+'"]').textContent=text;};
    const value=name=>form.elements[name]?.value.trim()||'';
    const phone=[value('contact[phoneCountryCode]'),value('contact[phone]')].filter(Boolean).join(' ');
    set('client',[[value('contact[firstName]'),value('contact[lastName]')].filter(Boolean).join(' '),phone].filter(Boolean).join(' · ')||'À saisir à l’étape 1');
    set('openings',[...windows.querySelectorAll('[data-window]')].map(row=>{
      const get=key=>row.querySelector('[name$="]['+key+']"]')?.value.trim()||'',quantity=Number(get('quantity'))||1;
      return [get('label')||'Ouverture',get('width')||get('height')?(get('width')||'?')+' × '+(get('height')||'?'):'',quantity>1?'×'+quantity:''].filter(Boolean).join(' ');
    }).join(' · '));
    const agent=form.elements.agent_id;set('agent',agent?.value?agent.selectedOptions[0].textContent:'Non affecté');
    const calculated=!live.hidden&&live.dataset.state!=='stale';
    set('total-label',live.hidden?'Total':live.querySelector('[data-live-label]').textContent);
    set('total',calculated?live.querySelector('[data-live-amount]').textContent:(live.hidden?'À calculer à l’étape 3':'À recalculer à l’étape 3'));
    const note=recapBox.querySelector('[data-recap="pending"]');pending??=JSON.parse(note.dataset.pending);
    set('pending-count',pending.length+(pending.length>1?' lignes':' ligne')+' à définir.');set('pending-list',pending.join(' ; '));
    note.hidden=!calculated||pending.length===0;
  }
  function showTotal(state,label,amount){live.hidden=false;live.dataset.state=state;live.querySelector('[data-live-label]').textContent=label;if(amount!==undefined)live.querySelector('[data-live-amount]').textContent=amount.toLocaleString('fr-FR')+' FCFA';}
  function invalidate(target){
    if(!form.elements.calculation_token)return;
    if(target.closest('[data-window],#quote-defaults')||/^(pricing\[|tax_rate|lines\[)/.test(target.name||'')){
      form.elements.calculation_token.value='';
      document.getElementById('calculation-status').textContent='Des paramètres ont changé. Recalculez pour actualiser les montants affichés.';
      if(!live.hidden)showTotal('stale','Total à recalculer');
    }
  }
  // Échéancier de l'étape 4 : lignes reprises du dernier calcul, jamais calculées ici.
  const scheduleBox=form.querySelector('[data-schedule]');
  function showSchedule(financial){
    if(!scheduleBox)return;
    const table=scheduleBox.querySelector('[data-schedule-table]'),rows=scheduleBox.querySelector('[data-schedule-rows]'),empty=scheduleBox.querySelector('[data-schedule-empty]');
    const steps=financial?.schedule||[];
    rows.replaceChildren(...steps.map(step=>{
      const tr=document.createElement('tr'),percent=document.createElement('td'),label=document.createElement('td'),amount=document.createElement('td');
      percent.className=amount.className='mono';percent.textContent=String(step.percent).replace('.',',')+' %';
      label.append(Object.assign(document.createElement('strong'),{textContent:step.label}));if(step.note)label.append(Object.assign(document.createElement('small'),{textContent:step.note}));
      amount.textContent=Number(step.amount).toLocaleString('fr-FR')+' FCFA';tr.append(percent,label,amount);return tr;
    }));
    table.hidden=!steps.length;empty.hidden=!!steps.length;
    if(!steps.length)empty.textContent=financial?'Recalculez le devis pour afficher son échéancier.':'Les montants s’affichent après le calcul de l’étape 3.';
  }
  form.addEventListener('input',event=>{dirty=true;revision++;invalidate(event.target);});
  form.addEventListener('change',event=>{
    dirty=true;revision++;const target=event.target,root=target.closest('[data-selection]');invalidate(target);
    if(target.dataset.fabric)field(root,target.dataset.fabric).value=target.type==='checkbox'?(target.checked?target.value:''):target.value;
    if(root){alignFinish(root,target);if(root!==defaults)root.closest('[data-window]').querySelector('[data-inherit]').checked=false;refresh(root);if(root===defaults)windows.querySelectorAll('[data-window]').forEach(inherit);}
    if(target.matches('[data-inherit]'))inherit(target.closest('[data-window]'));
    if(target.dataset.part){const input=target.closest('[data-cost-part]').querySelector('[data-component]');input.value=target.type==='checkbox'?(target.checked?target.value:''):target.value;input.dataset.userChoice='1';}
    if(root||target.matches('[data-inherit],[data-material-mode]')||target.dataset.part)windows.querySelectorAll('[data-window]').forEach(row=>components(row));
    selectionValidity();paintBoard();
    if(target.name==='payment_mode'&&target.value==='full')form.elements.deposit_amount.value=0;
    if(/^pricing\[(schedule|deposit_percent)\]$/.test(target.name)){showSchedule(null);calculate?.click();}
  });
  let next=Math.max(-1,...[...windows.querySelectorAll('[name]')].map(el=>Number(el.name.match(/^windows\[(\d+)\]/)?.[1]??-1)))+1;
  document.getElementById('add-quote-window').addEventListener('click',()=>{
    if(windows.children.length>=30)return;
    const fragment=document.getElementById('quote-window-template').content.cloneNode(true);fragment.querySelectorAll('[name]').forEach(el=>el.name=el.name.replace('__INDEX__',next));next++;
    const row=fragment.querySelector('[data-window]');windows.append(fragment);init(row.querySelector('[data-selection]'));inherit(row);components(row,true);buttons();paintSupplies();dirty=true;revision++;invalidate(row);
  });
  windows.addEventListener('click',event=>{const b=event.target.closest('[data-remove-window]');if(b&&windows.children.length>1){invalidate(b);b.closest('[data-window]').remove();buttons();dirty=true;revision++;}});
  const lines=document.getElementById('lines');
  const calculate=document.getElementById('calculate-quote'),status=document.getElementById('calculation-status');
  calculate?.addEventListener('click',async()=>{
    if(calculating)return;
    if(!selectionValidity(true)){status.textContent='Complétez les tissus / matériaux et les finitions des ouvertures. Les choix communs sont nécessaires uniquement pour les ouvertures qui les utilisent.';return;}
    calculating=true;calculate.disabled=true;const requestedRevision=revision;status.textContent='Calcul en cours…';
    try{
      const payload=new FormData(form);payload.delete('_method');[...payload.keys()].filter(key=>payload.get(key) instanceof File).forEach(key=>payload.delete(key));
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
      pending=result.pending;showSchedule(result.financial);form.dispatchEvent(new CustomEvent('mds:calculated'));
    }catch(error){status.textContent=error.message;}finally{calculating=false;calculate.disabled=false;}
  });
  /* Étape 3 : coefficients communs et pose. Ils écrivent dans les champs de chaque ouverture et dans les frais « Pose et livraison ». */
  const calcForm=form.querySelector('[data-calc-form]');
  if(calcForm){
    calcForm.hidden=false;
    const targets={material:'[name$="[costing][material_coefficient]"]',secondary:'[name$="[costing][secondary_material][coefficient]"]',tape:'[name$="[costing][tape][coefficient]"],[name$="[costing][extra_tape][coefficient]"]'};
    const normal=value=>String(value||'').trim().replace(',','.');
    const syncCoef=key=>{
      const line=calcForm.querySelector('[data-coef="'+key+'"]'),fields=[...windows.querySelectorAll(targets[key])],seen=[...new Set(fields.map(f=>normal(f.value)).filter(Boolean))];
      const custom=line.querySelector('[name$="_custom]"]'),radios=[...line.querySelectorAll('[type=radio]')],note=line.querySelector('[data-coef-note]');
      radios.forEach(r=>r.checked=false);note.textContent='';
      if(seen.length===1){const preset=radios.find(r=>r.value===seen[0]);if(preset)preset.checked=true;else{radios.find(r=>r.value==='custom').checked=true;custom.value=seen[0].replace('.',',');}}
      else if(seen.length>1)note.textContent='Valeurs différentes selon les ouvertures.';
      custom.disabled=!radios.find(r=>r.value==='custom').checked;
    };
    const applyCoef=key=>{
      const line=calcForm.querySelector('[data-coef="'+key+'"]'),choice=line.querySelector('[type=radio]:checked'),custom=line.querySelector('[name$="_custom]"]');
      custom.disabled=choice?.value!=='custom';if(!choice)return;
      const value=choice.value==='custom'?normal(custom.value):choice.value;
      if(!/^\d+(\.\d+)?$/.test(value)||Number(value)<=0)return;
      windows.querySelectorAll(targets[key]).forEach(f=>{if(f.value!==value){f.value=value;invalidate(f);}});dirty=true;revision++;coefChips();
    };
    const fee=key=>form.elements['pricing[fees][installation_delivery]['+key+']'];
    const syncInstall=()=>{
      const manual=fee('enabled')?.checked,price=calcForm.querySelector('[name=install_ui_price]');
      calcForm.querySelector('[name=install_ui][value='+(manual?'manual':'auto')+']').checked=true;
      price.disabled=!manual;if(manual)price.value=fee('unit_price')?.value||'';
    };
    const applyInstall=()=>{
      const manual=calcForm.querySelector('[name=install_ui]:checked')?.value==='manual',price=calcForm.querySelector('[name=install_ui_price]');
      price.disabled=!manual;if(!fee('enabled'))return;
      fee('enabled').checked=manual;if(manual){fee('scope').value='global';fee('option_id').value='';fee('unit_price').value=price.value;}
      // Forfait unique : plus de pose par ouverture ; valeurs auto : la pose du catalogue revient.
      windows.querySelectorAll('[data-component="installation"]').forEach(input=>{input.value='';if(manual)input.dataset.userChoice='1';else delete input.dataset.userChoice;});
      windows.querySelectorAll('[data-window]').forEach(row=>components(row));invalidate(fee('enabled'));dirty=true;revision++;
    };
    /* Confection : tissus, voilage et store pilotent la confection (sewing) de chaque ouverture concernée.
       Rideau et voilage sur une même ouverture : une seule ligne de confection, au tarif des tissus, qui compte aussi le métrage du voilage. */
    form.classList.add('calc-ready');
    const rows=()=>[...windows.querySelectorAll('[data-window]')];
    const own=(row,key)=>[...row.querySelectorAll('[name]')].find(el=>el.name.endsWith(']['+key+']')&&!el.name.includes('[costing]')&&!el.closest('[data-selection]'));
    const costValue=(row,path)=>Number(decimal(row.querySelector('[name$="[costing]'+path+'"]')?.value))||0;
    const kindOf=row=>{const v=values(row.querySelector('[data-selection]'));return {curtain:v.category==='rideau'&&!!v.curtain_id,sheer:v.category==='rideau'&&!!v.sheer_id,store:v.category==='store'};};
    const sew=row=>{const part=row.querySelector('[data-cost-part="sewing"]');return {input:part.querySelector('[data-component]'),price:part.querySelector('[data-part-price]'),mode:part.querySelector('[data-part-mode]'),basis:part.querySelector('[data-sewing-basis]'),ids:[...part.querySelectorAll('[data-component-list] select option')].map(o=>o.value).filter(Boolean)};};
    const sewingRows={curtain:row=>kindOf(row).curtain,sheer:row=>kindOf(row).sheer&&!kindOf(row).curtain,store:row=>kindOf(row).store};
    const mixed=()=>rows().filter(row=>kindOf(row).curtain&&kindOf(row).sheer);
    // Quantité coefficiée affichée : métrage saisi, sinon largeur × coefficient ; surface pour un store ; multipliée par les exemplaires.
    const quantity=(key,row)=>{
      const width=(Number(decimal(own(row,'width')?.value))||0)/100,copies=Number(own(row,'quantity')?.value)||1;
      if(key==='store')return width*((Number(decimal(own(row,'height')?.value))||0)/100)*copies;
      if(key==='sheer'&&kindOf(row).curtain)return (costValue(row,'[secondary_material][quantity]')||width*costValue(row,'[secondary_material][coefficient]'))*copies;
      return (costValue(row,'[material_quantity]')||width*costValue(row,'[material_coefficient]'))*copies;
    };
    const amount=n=>n.toLocaleString('fr-FR',{maximumFractionDigits:2});
    const syncSewing=key=>{
      const line=calcForm.querySelector('[data-sewing="'+key+'"]'),mine=rows().filter(sewingRows[key]),shared=key==='sheer'?mixed():[];
      line.hidden=!mine.length&&!shared.length;if(line.hidden)return;
      const radios=[...line.querySelectorAll('[type=radio]')],price=line.querySelector('[type=number]'),note=line.querySelector('[data-sewing-note]'),parts=mine.map(sew);
      const total=[...mine,...shared].reduce((sum,row)=>sum+quantity(key,row),0);
      line.querySelector('[data-sewing-qty]').textContent=total?'≈ '+amount(total)+(key==='store'?' m²':' m'):'à mesurer';
      const available=parts.some(p=>p.ids.length)||(shared.length&&mixed().some(row=>sew(row).ids.length));
      radios.forEach(r=>{r.checked=false;r.disabled=!available||(r.value==='manual'&&!mine.length);});note.textContent='';
      if(!available){price.disabled=true;note.textContent='Aucun tarif « Confection » pour '+(key==='store'?'les stores':'ces ouvertures')+' au catalogue : ajoutez-le dans Matériaux et finitions.';return;}
      const option=data.options.find(o=>o.kind==='sewing'&&parts.concat(shared.map(sew)).some(p=>p.ids.includes(String(o.id))));
      const catalogue=option&&Object.hasOwn(option,'unit_price')?(option.unit_price===null?'à définir':option.unit_price.toLocaleString('fr-FR')+' FCFA / '+units[option.unit]):'';
      const on=parts.filter(p=>p.input.value),prices=[...new Set(on.map(p=>p.price?.value||''))];
      let state;
      if(mine.length)state=!on.length?'none':on.length<parts.length||prices.length>1?'':prices[0]?'manual':'auto';
      else state=shared.every(row=>sew(row).basis.value==='all_fabrics')?'auto':shared.every(row=>sew(row).basis.value==='primary')?'none':'';
      if(state)radios.find(r=>r.value===state).checked=true;else note.textContent='Valeurs différentes selon les ouvertures.';
      price.disabled=state!=='manual';if(state==='manual')price.value=prices[0];
      if(!note.textContent)note.textContent=[catalogue&&'Catalogue : '+catalogue,shared.length?'Avec un rideau sur la même ouverture : compté dans la confection des tissus.':''].filter(Boolean).join(' · ');
    };
    const applySewing=key=>{
      const line=calcForm.querySelector('[data-sewing="'+key+'"]'),choice=line.querySelector('[type=radio]:checked')?.value,price=line.querySelector('[type=number]');
      price.disabled=choice!=='manual';if(!choice)return;
      rows().filter(sewingRows[key]).forEach(row=>{
        const p=sew(row);p.input.dataset.userChoice='1';
        if(choice==='none')p.input.value='';
        else if(p.ids.length){p.input.value=p.ids.includes(p.input.value)?p.input.value:p.ids[0];if(p.mode)p.mode.value='per_meter';if(p.price)p.price.value=choice==='manual'?price.value:'';}
        invalidate(p.input);
      });
      if(key==='sheer')mixed().forEach(row=>{const p=sew(row);p.basis.value=choice==='none'?'primary':'all_fabrics';invalidate(p.basis);});
      rows().forEach(row=>components(row));dirty=true;revision++;
    };
    // Zone de pose de toutes les ouvertures, reprise de la commune de l'étape 1.
    const zoneLine=calcForm.querySelector('[data-zone-line]');
    const syncZone=()=>{const seen=[...new Set(rows().map(row=>row.querySelector('[data-zone]').value))];zoneLine.querySelectorAll('[type=radio]').forEach(r=>r.checked=seen.length===1&&r.value===seen[0]);};
    const applyZone=value=>{rows().forEach(row=>{const select=row.querySelector('[data-zone]');if(select.value!==value){select.value=value;invalidate(select);}});syncZone();dirty=true;revision++;};
    const city=form.elements['contact[city]'];
    city?.addEventListener('change',()=>{if(city.value)applyZone(city.value==='Hors Abidjan'?'other':'abidjan');});
    const syncCount=()=>{const n=rows().reduce((sum,row)=>sum+(Number(own(row,'quantity')?.value)||1),0);calcForm.querySelector('[data-install-count]').textContent=n+(n>1?' ouvertures':' ouverture');};
    const sewingKeys=Object.keys(sewingRows),syncAll=()=>{sewingKeys.forEach(syncSewing);syncZone();syncCount();};
    Object.keys(targets).forEach(syncCoef);syncInstall();syncAll();
    calcForm.addEventListener('change',event=>{
      const key=event.target.closest('[data-coef]')?.dataset.coef;
      if(key){applyCoef(key);if(event.target.type==='radio'&&event.target.value==='custom')calcForm.querySelector('[name="coefficient_ui['+key+'_custom]"]').focus();}
      if(event.target.closest('[data-install]'))applyInstall();
      const sewing=event.target.closest('[data-sewing]')?.dataset.sewing;
      if(sewing&&event.target.type==='radio'){applySewing(sewing);if(event.target.value==='manual')event.target.closest('[data-sewing]').querySelector('[type=number]').focus();else syncSewing(sewing);}
      if(event.target.name==='zone_ui')applyZone(event.target.value);
      event.stopPropagation();
    });
    calcForm.addEventListener('input',event=>{const sewing=event.target.closest('[data-sewing]')?.dataset.sewing;if(sewing&&event.target.type==='number')applySewing(sewing);const key=event.target.closest('[data-coef]')?.dataset.coef;if(key&&event.target.type!=='radio')applyCoef(key);if(event.target.name==='install_ui_price'&&fee('unit_price')){fee('unit_price').value=event.target.value;invalidate(fee('unit_price'));}event.stopPropagation();});
    form.addEventListener('change',event=>{if(!calcForm.contains(event.target)&&event.target.closest('[data-window],#quote-defaults')){Object.keys(targets).forEach(syncCoef);syncAll();}if(event.target.name?.startsWith('pricing[fees][installation_delivery]'))syncInstall();});
    document.getElementById('add-quote-window').addEventListener('click',()=>{Object.keys(targets).forEach(key=>calcForm.querySelector('[data-coef="'+key+'"] [type=radio]:checked')?applyCoef(key):syncCoef(key));const zone=zoneLine.querySelector('[type=radio]:checked');if(zone)applyZone(zone.value);sewingKeys.forEach(key=>{if(calcForm.querySelector('[data-sewing="'+key+'"] [type=radio]:checked'))applySewing(key);});syncAll();coefChips();});
    form.addEventListener('input',event=>{if(!calcForm.contains(event.target)&&event.target.closest('[data-window]'))syncAll();});
    windows.addEventListener('click',event=>{if(event.target.closest('[data-remove-window]'))setTimeout(syncAll);});
  }
  ['input','change','mds:calculated'].forEach(type=>form.addEventListener(type,recap));
  document.getElementById('add-quote-window').addEventListener('click',()=>setTimeout(recap));
  windows.addEventListener('click',()=>setTimeout(recap));
  recap();
  form.addEventListener('submit',event=>{
    if(calculate&&(calculating||(form.elements.use_catalogue.value==='1'&&!form.elements.calculation_token.value))){event.preventDefault();status.textContent='Calculez le chiffrage avant enregistrement.';if(typeof revealField==='function')revealField(status);status.scrollIntoView({block:'center'});}
    if(!event.defaultPrevented)dirty=false;
  });
  window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
})();
