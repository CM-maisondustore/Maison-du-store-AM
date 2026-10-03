(function(MDS){
  'use strict';
  const esc=s=>MDS.validation.escapeHtml(s);
  const MODELS=(MDS.quoteProducts||[]).filter(p=>p.category==='rideau');
  const FABRICS=(MDS.quoteOptions||[]).filter(o=>o.category==='rideau'&&o.kind==='material');
  const FINISHES=MDS.quoteFinishes||[];
  const matches=(o,s)=>!o.product_ids?.length||o.product_ids.includes(Number(s.curtain.model.replace('product-','')));
  const label=(list,id)=>{const o=list.find(o=>o.id===id);return o?(o.label||o.name):'';};
  const read=(c,name)=>c.querySelector('input[name="'+name+'"]:checked')?.value||'';
  function cards(name,list,selected,size='fabric'){
    return list.map(o=>'<label class="fabric-card"><input type="radio" name="'+name+'" value="'+esc(o.id)+'"'+(selected===o.id?' checked':'')+'>'+MDS.quoteMedia(o,size)+'<span class="fabric-card__title">'+esc(o.label||o.name)+'</span><span class="fabric-card__text">'+esc(o.text||'')+'</span></label>').join('');
  }
  function renderModel(c,s){c.innerHTML='<h2 class="step__heading">Votre modèle de rideau ou voilage</h2><div class="fabric-grid">'+(MODELS.length?cards('curtain-model',MODELS,s.curtain.model):'<p>Aucun produit disponible. Contactez-nous.</p>')+'</div>';}
  function renderFabric(c,s){
    c.innerHTML='<h2 class="step__heading">Sélectionnez vos tissus et voilages</h2><p class="step__intro">Choisissez un tissu, un voilage, ou les deux.</p>'+[['fabric','rideau','Tissu rideau','Sans tissu rideau'],['sheer','voilage','Voilage','Sans voilage']].map(([field,group,title,none])=>{
      const list=FABRICS.filter(o=>(o.fabric_group||o.category)===group&&matches(o,s));
      return '<div class="field" data-field="'+field+'"><h3>'+title+'</h3><div class="fabric-grid">'+cards('curtain-'+field,[{id:'',label:none},...list],s.curtain[field])+'</div></div>';
    }).join('');
  }
  function renderColor(c,s){c.innerHTML='<h2 class="step__heading">Choix du coloris</h2><p class="step__intro">Indiquez votre préférence. Le choix définitif sera effectué sur place avec l’agent.</p><div class="field" data-field="color"><div class="swatch-row swatch-row--lg">'+(MDS.data.colors||[]).filter(o=>o.types.includes('rideau')).map(o=>'<label class="swatch-choice"><input type="radio" name="curtain-color" value="'+esc(o.id)+'"'+(s.curtain.color===o.id?' checked':'')+'><span class="swatch-choice__dot" style="--swatch:'+o.hex+'"></span><span class="swatch-choice__label">'+esc(o.label)+'</span></label>').join('')+'</div></div>';}
  function renderFinish(c,s){c.innerHTML='<h2 class="step__heading">Choisissez votre finition</h2><p class="step__intro">Les détails techniques et les accessoires seront définis avec notre équipe.</p><div class="field" data-field="finish"><div class="fabric-grid">'+cards('curtain-finish',FINISHES,s.curtain.finish)+'</div></div>';}
  const STEPS=[
    {id:'curtain-model',title:'Modèle',render:renderModel,collect:c=>({model:read(c,'curtain-model')})},
    {id:'curtain-fabric',title:'Tissus et voilages',render:renderFabric,collect:c=>({fabric:read(c,'curtain-fabric'),sheer:read(c,'curtain-sheer')})},
    {id:'curtain-color',title:'Coloris',render:renderColor,collect:c=>({color:read(c,'curtain-color')})},
    {id:'curtain-finish',title:'Finition',render:renderFinish,collect:c=>({finish:read(c,'curtain-finish')})}
  ];
  function labels(s){return {model:label(MODELS,s.model),fabric:[label(FABRICS,s.fabric),label(FABRICS,s.sheer)].filter(Boolean).join(' + '),color:label(MDS.data.colors,s.color),finish:label(FINISHES,s.finish)};}
  MDS.quoteCurtain={STEPS,MODELS,FABRICS,FINISHES,labels};
})((window.MDS=window.MDS||{}));
