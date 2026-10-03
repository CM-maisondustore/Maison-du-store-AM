const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
function load(products=[],options=[],finishes=[]){
 const MDS={quoteProducts:products,quoteOptions:options,quoteFinishes:finishes,data:{colors:[]},validation:{isRequired:s=>String(s??'').trim().length>0,escapeHtml:s=>String(s??'').replaceAll('"','&quot;')}};
 for(const file of ['../utils/quote-phone.js','quote-store.js','quote-curtain.js','quote-validation.js'])vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../resources/js/mds/devis',file),'utf8'),{window:{MDS}});
 return MDS;
}
test('quote choices contain only database entries, reuse images and omit power supplies',()=>{
 const MDS=load([{id:'product-7',category:'store',name:'Store DB',imageUrl:'/devis/produits/7/image'}],[{id:'cloth',kind:'material',category:'store',label:'Toile DB',motorization:'any',imageUrl:'/devis/produits/7/image'},{id:'finish',kind:'finish',category:'store',label:'Finition DB',motorization:'any'}]);
 const container={innerHTML:''},state={store:{model:'product-7',control:'motorisee',orderType:'installation',fabric:'cloth',finish:'finish'}};
 MDS.quoteStore.STEPS[0].render(container,state);assert.match(container.innerHTML,/Store DB/);assert.match(container.innerHTML,/quote-image--thumb/);assert.doesNotMatch(container.innerHTML,/vénitien/);
 MDS.quoteStore.STEPS.find(s=>s.id==='store-control').render(container,state);assert.doesNotMatch(container.innerHTML,/alimentation|store-power|batterie|solaire/i);
 assert.equal(MDS.quoteValidation.validateStoreStep('store-control',state.store).valid,true);
 MDS.quoteStore.STEPS.find(s=>s.id==='store-finish').render(container,state);assert.match(container.innerHTML,/Toile DB/);assert.match(container.innerHTML,/Finition DB/);assert.match(container.innerHTML,/quote-image--fabric/);
});
test('public curtain finishes use editable family cards without technical accessories',()=>{
 const MDS=load([{id:'product-8',category:'rideau',name:'Rideau DB'}],[{id:'rail',kind:'support',category:'rideau',label:'Rail DB'}],[{id:'wave',label:'Wave personnalisé',text:'Description DB',imageUrl:'/media/wave'}]);
 const container={innerHTML:''};MDS.quoteCurtain.STEPS.find(s=>s.id==='curtain-finish').render(container,{curtain:{model:'product-8',finish:'wave'}});assert.match(container.innerHTML,/Wave personnalisé/);assert.match(container.innerHTML,/Description DB/);assert.doesNotMatch(container.innerHTML,/Rail DB/);
 assert(!MDS.quoteCurtain.STEPS.some(s=>s.id==='curtain-hanging'));assert(!MDS.quoteStore.STEPS.some(s=>s.id==='store-mount'));
 const empty=load();empty.quoteStore.STEPS[0].render(container,{store:{}});assert.match(container.innerHTML,/Aucun store/);assert.equal(empty.quoteCurtain.FABRICS.length,0);
});
test('curtain fabric selection permits curtain, sheer or both but not neither',()=>{
 const MDS=load([],[{id:'cloth',kind:'material',category:'rideau',fabric_group:'rideau',label:'Tissu DB'},{id:'sheer',kind:'material',category:'rideau',fabric_group:'voilage',label:'Voilage DB'}]);
 const step=MDS.quoteCurtain.STEPS.find(s=>s.id==='curtain-fabric'),container={innerHTML:''};step.render(container,{curtain:{model:'product-1',fabric:'cloth',sheer:'sheer'}});
 assert.match(container.innerHTML,/name="curtain-fabric"/);assert.match(container.innerHTML,/name="curtain-sheer"/);
 for(const data of [{fabric:'cloth',sheer:'sheer'},{fabric:'cloth',sheer:''},{fabric:'',sheer:'sheer'}])assert.equal(MDS.quoteValidation.validateCurtainStep('curtain-fabric',data).valid,true);
 assert.equal(MDS.quoteValidation.validateCurtainStep('curtain-fabric',{}).valid,false);
 const color=MDS.quoteCurtain.STEPS.find(s=>s.id==='curtain-color');color.render(container,{curtain:{}});assert.match(container.innerHTML,/sur place avec l’agent/);assert.doesNotMatch(container.innerHTML,/Motif|curtain-pattern/);
});
test('store without a published compatible finish does not block a request',()=>{
 const MDS=load([],[{id:'cloth',kind:'material',category:'store',label:'Toile DB',motorization:'any'}]);
 const state={store:{model:'product-7',control:'motorisee',fabric:'cloth',color:'beige',finish:''}},container={innerHTML:''};
 MDS.quoteStore.STEPS.find(s=>s.id==='store-finish').render(container,state);assert.doesNotMatch(container.innerHTML,/name="store-finish"|data-field="finish"/);
 assert.equal(MDS.quoteValidation.validateStoreStep('store-finish',state.store).valid,true);
});
