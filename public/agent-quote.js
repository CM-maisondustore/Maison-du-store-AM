(()=>{'use strict';
const form=document.getElementById('agent-quote-form');if(!form)return;
form.addEventListener('invalid',e=>e.target.closest('details')?.setAttribute('open',''),true);
const options=JSON.parse(document.getElementById('agent-catalogue').textContent).options,defaults=document.querySelector('#agent-defaults [data-agent-selection]'),openings=document.getElementById('agent-openings');
const field=(r,k)=>r.querySelector('[data-agent-choice="'+k+'"]'),keys=['project_type','motorized','finish_id','zone'];
function refresh(r,initial=false){const category=field(r,'project_type').value==='store'?'store':'rideau',select=field(r,'finish_id'),value=initial?select.dataset.value:select.value;select.replaceChildren(new Option('Choisissez la finition',''));options.filter(o=>o.category===category).forEach(o=>select.add(new Option(o.label,o.id)));select.value=value||'';if(select.selectedIndex<0)select.value='';}
function alignFinish(r,target){const option=options.find(o=>String(o.id)===field(r,'finish_id').value);if(target.dataset.agentChoice==='finish_id'&&(option?.motorization==='yes'||option?.motorization==='no'))field(r,'motorized').value=option.motorization==='yes'?'1':'0';else if(target.dataset.agentChoice==='motorized'&&option?.motorization!=='any'&&option?.motorization!==undefined)field(r,'finish_id').value='';}
function inherit(row){if(!row.querySelector('[data-agent-inherit]').checked)return;const r=row.querySelector('[data-agent-selection]');keys.filter(k=>k!=='finish_id').forEach(k=>field(r,k).value=field(defaults,k).value);refresh(r);field(r,'finish_id').value=field(defaults,'finish_id').value;}
function validity(){const rows=[...openings.children];field(defaults,'finish_id').required=rows.some(r=>r.querySelector('[data-agent-inherit]').checked);rows.forEach(r=>r.querySelector('[data-remove-opening]').disabled=rows.length===1);document.getElementById('agent-add-opening').disabled=rows.length>=30;}
refresh(defaults,true);openings.querySelectorAll('[data-agent-selection]').forEach(r=>refresh(r,true));[...openings.children].forEach(inherit);validity();
form.addEventListener('change',e=>{const r=e.target.closest('[data-agent-selection]');if(r){alignFinish(r,e.target);refresh(r);if(r===defaults)[...openings.children].forEach(inherit);else r.closest('[data-agent-window]').querySelector('[data-agent-inherit]').checked=false;}if(e.target.matches('[data-agent-inherit]'))inherit(e.target.closest('[data-agent-window]'));validity();});
let next=Math.max(-1,...[...openings.querySelectorAll('[name]')].map(e=>Number(e.name.match(/^windows\[(\d+)\]/)?.[1]??-1)))+1;
function addOpening(){if(openings.children.length>=30)return;const fragment=document.getElementById('agent-opening-template').content.cloneNode(true);fragment.querySelectorAll('[name]').forEach(e=>e.name=e.name.replace('__INDEX__',next));next++;const row=fragment.querySelector('[data-agent-window]');openings.append(fragment);refresh(row.querySelector('[data-agent-selection]'),true);inherit(row);validity();}
document.getElementById('agent-add-opening').addEventListener('click',addOpening);
openings.addEventListener('click',e=>{const button=e.target.closest('[data-remove-opening]');if(button&&openings.children.length>1){button.closest('[data-agent-window]').remove();validity();}});
/* Brouillon local d'un nouveau relevé : les ouvertures ajoutées et les choix reviennent aussi, jamais après une erreur du serveur. */
if(form.dataset.localDraft.endsWith('-new')&&!document.getElementById('form-errors')){
let saved={};try{saved=JSON.parse(localStorage.getItem('mds-draft:'+form.dataset.localDraft)||'{}')||{};}catch(_){}
if(Object.keys(saved).length){
Object.keys(saved).forEach(name=>{const index=name.match(/^windows\[(\d+)\]/);if(index)while(Number(index[1])>=next&&openings.children.length<30)addOpening();});
const restore=(selector)=>Object.entries(saved).forEach(([name,value])=>{const el=form.elements[name];if(el?.matches?.(selector))el.value=value;});
restore('input,textarea');restore('select:not([data-agent-choice="finish_id"])');
[defaults,...openings.querySelectorAll('[data-agent-selection]')].forEach(r=>refresh(r));
restore('select[data-agent-choice="finish_id"]');
[...openings.children].forEach(inherit);validity();
openings.querySelectorAll('[data-agent-window]').forEach(row=>row.querySelector('input:not([type=hidden])')?.dispatchEvent(new Event('input',{bubbles:true})));
document.querySelector('[data-draft-status="'+form.dataset.localDraft+'"]').textContent='Saisie retrouvée sur cet appareil.';
}}
})();
