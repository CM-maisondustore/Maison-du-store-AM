const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
function load(products=[],options=[]){
 const MDS={quoteProducts:products,quoteOptions:options,data:{colors:[]},validation:{isRequired:s=>String(s??'').trim().length>0,escapeHtml:s=>String(s??'').replaceAll('"','&quot;')}};
 for(const file of ['quote-store.js','quote-curtain.js','quote-validation.js'])vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../resources/js/mds/devis',file),'utf8'),{window:{MDS}});
 return MDS;
}
test('quote choices contain only database entries, reuse images and omit power supplies',()=>{
 const MDS=load([{id:'product-7',category:'store',name:'Store DB',imageUrl:'/devis/produits/7/image'}],[{id:'cloth',kind:'material',category:'store',label:'Toile DB',motorization:'any',imageUrl:'/devis/produits/7/image'},{id:'finish',kind:'finish',category:'store',label:'Finition DB',motorization:'any'}]);
 const container={innerHTML:''},state={store:{model:'product-7',control:'motorisee',fabric:'cloth',finish:'finish'}};
 MDS.quoteStore.STEPS[0].render(container,state);assert.match(container.innerHTML,/Store DB/);assert.match(container.innerHTML,/quote-image--thumb/);assert.doesNotMatch(container.innerHTML,/vénitien/);
 MDS.quoteStore.STEPS.find(s=>s.id==='store-control').render(container,state);assert.doesNotMatch(container.innerHTML,/alimentation|store-power|batterie|solaire/i);
 assert.equal(MDS.quoteValidation.validateStoreStep('store-control',state.store).valid,true);
 MDS.quoteStore.STEPS.find(s=>s.id==='store-finish').render(container,state);assert.match(container.innerHTML,/Toile DB/);assert.match(container.innerHTML,/Finition DB/);assert.match(container.innerHTML,/quote-image--fabric/);
});
test('curtain supports render compatible database options without a fixed fallback',()=>{
 const MDS=load([{id:'product-8',category:'rideau',name:'Rideau DB'}],[{id:'rail',kind:'support',category:'rideau',label:'Rail DB',finish_codes:['wave'],product_ids:[8]}]);
 const container={innerHTML:''};MDS.quoteCurtain.STEPS.find(s=>s.id==='curtain-hanging').render(container,{curtain:{model:'product-8',finish:'wave',hanging:''}});assert.match(container.innerHTML,/Rail DB/);
 const empty=load();empty.quoteStore.STEPS[0].render(container,{store:{}});assert.match(container.innerHTML,/Aucun store/);assert.equal(empty.quoteCurtain.FABRICS.length,0);
});
test('store without a published compatible finish does not block a request',()=>{
 const MDS=load([],[{id:'cloth',kind:'material',category:'store',label:'Toile DB',motorization:'any'}]);
 const state={store:{model:'product-7',control:'motorisee',fabric:'cloth',color:'beige',finish:''}},container={innerHTML:''};
 MDS.quoteStore.STEPS.find(s=>s.id==='store-finish').render(container,state);assert.doesNotMatch(container.innerHTML,/name="store-finish"|data-field="finish"/);
 assert.equal(MDS.quoteValidation.validateStoreStep('store-finish',state.store).valid,true);
});
