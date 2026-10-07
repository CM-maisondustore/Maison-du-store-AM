const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../resources/js/mds/utils/analytics.js'),'utf8');
function browser(consentValue=null){
  const stored=new Map(),events={},requests=[]; if(consentValue)stored.set('mds-analytics-consent-v1',JSON.stringify(consentValue));
  const localStorage={getItem:k=>stored.get(k)||null,setItem:(k,v)=>stored.set(k,v)},sessionStorage={getItem:k=>stored.get('session:'+k)||null,setItem:(k,v)=>stored.set('session:'+k,v)};
  const document={title:'Maison',querySelector:()=>({content:'csrf'}),addEventListener:(k,fn)=>events[k]=fn};
  const window={MDS:{},setInterval(){}};
  vm.runInNewContext(source,{window,document,localStorage,sessionStorage,location:{pathname:'/devis',search:'?utm_source=facebook'},crypto:{randomUUID:()=>`uuid-${Math.random()}`},URLSearchParams,Date,setInterval:()=>{},fetch:(url,options)=>{requests.push(JSON.parse(options.body));return Promise.resolve({ok:true});}});
  return {stored,requests,events};
}
test('does not create visitor identifiers or transmit analytics without consent',()=>{
  const b=browser({value:false,expires:Date.now()+60000});
  assert.equal(b.requests.length,0);assert.equal(b.stored.has('mds-visitor-v1'),false);assert.equal(b.stored.has('session:mds-session-v1'),false);
});
test('records consented page views and WhatsApp clicks without URL query strings',()=>{
  const b=browser({value:true,expires:Date.now()+60000});
  assert.equal(b.requests[0].event,'page_view');assert.equal(b.requests[0].path,'/devis');
  const action={href:'https://wa.me/2250712449319',textContent:'WhatsApp',matches:()=>false,closest:()=>null};
  b.events.click({target:{closest:()=>action}});assert.equal(b.requests[1].event,'whatsapp_click');
});
