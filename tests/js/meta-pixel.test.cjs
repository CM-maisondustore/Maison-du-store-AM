const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../resources/js/mds/utils/meta-pixel.js'),'utf8');
function browser({saved=null,hostname='www.maisondustoream.com',pixel=true,storageFails=false}={}){
  const scripts=[],cookies=[],listeners={},elements=[],store={value:saved};
  function element(){const e={events:{},hidden:false,setAttribute(){},appendChild(){},focus(){},remove(){},querySelector(){return {focus(){}}},addEventListener(k,v){this.events[k]=v}};elements.push(e);return e;}
  const footer=element(),document={readyState:'complete',querySelector:s=>s.startsWith('meta')?(pixel?{content:'1407495181585849'}:null):footer,createElement:element,head:{appendChild:s=>scripts.push(s)},body:{appendChild(){}},set cookie(value){cookies.push(value)}};
  const window={addEventListener:(k,v)=>listeners[k]=v},localStorage={getItem(){if(storageFails)throw Error('blocked');return store.value;},setItem(k,v){if(storageFails)throw Error('blocked');store.value=v;}};
  vm.runInNewContext(source,{document,window,localStorage,location:{hostname},Date});
  const banner=elements.find(e=>e.id==='advertising-consent');
  return {window,scripts,cookies,banner,store,choose(value){banner.events.click({target:{closest(){return {dataset:{cookieChoice:value}}}}});},load(){scripts.at(-1).onload();},calls(){return Array.from(window.fbq?.queue||[],args=>Array.from(args));},storage(value){store.value=value;listeners.storage({key:'mds-advertising-consent-v1'});}};
}
const saved=(value,expires=Date.now()+100000)=>JSON.stringify({value,expires});
test('no Meta connection until acceptance, refusal persists across visits',()=>{
  const b=browser();assert.equal(b.scripts.length,0);assert.equal(b.banner.hidden,false);b.choose('rejected');assert.equal(b.scripts.length,0);
  const next=browser({saved:b.store.value});assert.equal(next.banner.hidden,true);assert.equal(next.scripts.length,0);
});
test('acceptance initializes specified pixel once, without automatic events or contact fields',()=>{
  const b=browser();b.choose('accepted');assert.equal(b.scripts.length,1);assert.equal(b.calls().length,0);b.load();
  assert.deepEqual(b.calls().slice(0,2),[['set','autoConfig',false,'1407495181585849'],['init','1407495181585849']]);
  assert.deepEqual(b.calls().filter(c=>c[0]==='track'),[['track','PageView']]);
  assert.equal(b.window.MDS.metaPixel.trackContact(),true);
  assert.equal(b.window.MDS.metaPixel.trackSchedule(),true);
  assert.deepEqual(b.calls().filter(c=>c[0]==='track'),[['track','PageView'],['track','Contact'],['track','Schedule']]);
  b.choose('accepted');assert.equal(b.scripts.length,1);assert.equal(b.calls().filter(c=>c[0]==='track').length,3);
});
test('withdrawal revokes consent and clears pixel cookies without resetting forms',()=>{
  const b=browser({saved:saved('accepted')});b.load();b.choose('rejected');assert.deepEqual(b.calls().at(-1),['consent','revoke']);assert(b.cookies.some(c=>c.startsWith('_fbp=; Max-Age=0')));assert(b.cookies.some(c=>c.startsWith('_fbc=; Max-Age=0')));
});
test('refusal while SDK downloads prevents any initialization or page view',()=>{
  const b=browser();b.choose('accepted');b.choose('rejected');b.load();assert.deepEqual(b.calls(),[]);
});
test('expired or corrupt choices ask again; local previews and private pages never load Meta',()=>{
  for(const value of [saved('accepted',Date.now()-1),'invalid']){const b=browser({saved:value});assert.equal(b.scripts.length,0);assert.equal(b.banner.hidden,false);}
  assert.equal(browser({hostname:'localhost',saved:saved('accepted')}).scripts.length,0);
  assert.equal(browser({pixel:false,saved:saved('accepted')}).scripts.length,0);
  const b=browser({storageFails:true});b.choose('rejected');assert.equal(b.scripts.length,0);
});
test('withdrawal from another tab stops tracking',()=>{
  const b=browser({saved:saved('accepted')});b.load();b.storage(saved('rejected'));assert.deepEqual(b.calls().at(-1),['consent','revoke']);
  assert.equal(b.window.MDS.metaPixel.trackContact(),false);
});
