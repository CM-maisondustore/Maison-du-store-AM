@extends('staff.layout')
@php
 $me=auth()->user(); $p=$me->profile ?? []; $agent=!$me->isAdmin();
 $initials=\App\Services\StaffInterface::initials($me->name);
 $field=fn($k,$d='')=>old($k,$p[$k] ?? $d);
 $listed=fn($k)=>(array)old($k,$p[$k] ?? []);
 // Après une erreur, old('zone_list') vaut [commune => 0|1] ; en base, la liste des communes « Oui ».
 $zones=is_array(old('zone_list')) ? array_keys(array_filter(old('zone_list'),fn($v)=>$v==='1')) : (array)($p['zone_list'] ?? []);
 $cover=!empty($p['cover_media_id']) ? route('staff.profile.cover').'?v='.$p['cover_media_id'] : null;
 // Compétences cochées puis savoir-faire libres, proposés sur la carte à partager.
 $cardSkills=implode(' · ',array_filter([...array_map(fn($k)=>\App\Http\Controllers\Staff\ProfileController::SKILLS[$k] ?? null,(array)($p['skill_list'] ?? [])),trim($p['skills'] ?? '')]));
 $available=old('availability',$p['availability'] ?? 'available')!=='away';
@endphp
@section('title','Modifier mon profil')
@section('back',route('staff.settings'))
@section('head')
<div class="page-head keep">
@if($cover)<figure class="profile-cover"><img src="{{ $cover }}" alt="Photo de couverture"></figure>@endif
<div class="title-row"><span class="avatar lg" aria-hidden="true">{{ $initials }}</span>
<div><h1>{{ $me->name }}</h1><p class="sub">{{ $p['title'] ?? $me->roleLabel() }}</p></div></div>
<div class="page-actions"><button type="button" class="quiet" data-open-dialog="card-dialog">Carte à partager</button><a class="button quiet" href="{{ route('staff.settings') }}">Paramètres</a></div></div>
@endsection
@section('content')
<form method="post" action="{{ route('staff.profile.update') }}" class="small-form mw-820" enctype="multipart/form-data">
@csrf @method('PATCH')
<section class="panel" aria-labelledby="p-id"><h2 id="p-id">Identité</h2>
<div class="grid">
<label>Civilité<select name="civility"><option value="">—</option>@foreach(['M.','Mme'] as $civility)<option @selected($field('civility')===$civility)>{{ $civility }}</option>@endforeach</select></label>
<label>Nom et prénom<input name="name" required maxlength="150" autocomplete="name" value="{{ old('name',$me->name) }}"></label>
<label>Fonction affichée<input name="title" maxlength="100" value="{{ $field('title') }}" placeholder="{{ $agent ? 'Technicien poseur' : 'Gérance' }}"></label>
</div></section>
<section class="panel" aria-labelledby="p-cover"><h2 id="p-cover">Photo de couverture</h2>
<p class="muted">Affichée en haut de votre profil, et sur votre carte à partager si vous le souhaitez.</p>
<label>Choisir une photo<input type="file" name="cover" accept="image/jpeg,image/png,image/webp"><span class="hint">JPG, PNG ou WebP, 2 Mo maximum.</span></label>
@if($cover)<label class="check"><input type="checkbox" name="remove_cover" value="1">Retirer la photo actuelle</label>@endif
</section>
<section class="panel" aria-labelledby="p-co"><h2 id="p-co">Coordonnées</h2>
<div class="grid">
<label>Téléphone professionnel<input name="phone" type="tel" maxlength="30" autocomplete="tel" inputmode="tel" value="{{ $field('phone') }}" placeholder="+225 07 00 00 00 00"></label>
<label>Adresse e-mail<input type="email" value="{{ $me->email }}" readonly aria-describedby="mail-help"><span class="muted fw-400" id="mail-help">Modifiable par l’admin principal : elle identifie votre compte.</span></label>
</div>
<input type="hidden" name="whatsapp" value="0"><label class="check"><input type="checkbox" name="whatsapp" value="1" @checked($field('whatsapp'))>Joignable sur WhatsApp à ce numéro</label>
</section>
@if($agent)
<section class="panel" aria-labelledby="p-dispo"><h2 id="p-dispo">Disponibilités</h2><p class="muted">Le bureau s’en sert pour vous affecter les prestations.</p>
<input type="hidden" name="availability" value="away"><label class="switch"><input class="switch" role="switch" type="checkbox" name="availability" value="available" @checked($available)>Disponible pour de nouvelles prestations</label>
<fieldset class="bare mt-12"><legend>Jours de travail</legend>
<div class="check-row">@foreach(\App\Http\Controllers\Staff\ProfileController::DAYS as $day=>$label)<label class="check"><input type="checkbox" name="days[]" value="{{ $day }}" @checked(in_array($day,$listed('days'),true))>{{ $label }}</label>@endforeach</div></fieldset>
<label>Horaires<input name="hours" maxlength="150" value="{{ $field('hours') }}" placeholder="08:00–18:00"></label>
<fieldset class="bare mt-12"><legend>Communes couvertes</legend>
<div class="grid zone-grid">@foreach(\App\Http\Controllers\Staff\ProfileController::zones() as $commune)<label>{{ $commune }}<select name="zone_list[{{ $commune }}]"><option value="0">Non</option><option value="1" @selected(in_array($commune,$zones,true))>Oui</option></select></label>@endforeach</div></fieldset>
</section>
<section class="panel" aria-labelledby="p-terrain"><h2 id="p-terrain">Compétences</h2>
<fieldset class="bare"><legend class="sr-only">Savoir-faire</legend>
<div class="check-row">@foreach(\App\Http\Controllers\Staff\ProfileController::SKILLS as $skill=>$label)<label class="check"><input type="checkbox" name="skill_list[]" value="{{ $skill }}" @checked(in_array($skill,$listed('skill_list'),true))>{{ $label }}</label>@endforeach</div></fieldset>
<label>Autres savoir-faire<input name="skills" maxlength="300" value="{{ $field('skills') }}" placeholder="Pose de parquet, tapisserie…"></label>
</section>
<section class="panel" aria-labelledby="p-urg"><h2 id="p-urg">Contact en cas d’urgence</h2>
<div class="grid">
<label>Nom et lien<input name="emergency_name" maxlength="150" value="{{ $field('emergency_name') }}"></label>
<label>Téléphone<input name="emergency_phone" type="tel" maxlength="30" value="{{ $field('emergency_phone') }}"></label>
</div><p class="muted">Visible uniquement par le bureau.</p></section>
@else
<section class="panel" aria-labelledby="p-sig"><h2 id="p-sig">Signature</h2><p class="muted">Pour vos échanges : à copier sous vos messages.</p>
<label>Texte de la signature<textarea name="signature" rows="3" maxlength="500">{{ $field('signature') }}</textarea></label>
</section>
<section class="panel" aria-labelledby="p-dispo"><h2 id="p-dispo">Disponibilité</h2>
<input type="hidden" name="availability" value="away"><label class="switch"><input class="switch" role="switch" type="checkbox" name="availability" value="available" @checked($available)>Disponible</label>
<label>De retour le<input type="date" name="back_on" value="{{ $field('back_on') }}"><span class="hint">Si vous êtes absent.</span></label>
</section>
@endif
<div class="action-bar"><a class="button quiet" href="{{ route('staff.settings') }}">Annuler</a><button>Enregistrer le profil</button></div>
</form>
<dialog class="modal" id="card-dialog" aria-labelledby="sh-t">
<div class="modal-body">
<h2 id="sh-t">Carte de profil à partager</h2>
<p class="muted">Une image aux couleurs de la maison pour WhatsApp, Facebook, Instagram ou LinkedIn. Modifiez librement les textes : rien n’est enregistré, l’image est créée sur votre appareil.</p>
<fieldset class="bare"><legend>Format</legend>
<div class="tile-group card-formats">
@foreach(['story'=>['Story mobile','1080 × 1920, WhatsApp et Instagram'],'card'=>['Carte de visite','85 × 55 mm, à imprimer']] as $format=>[$label,$hint])<label class="tile"><input type="radio" name="card_format" value="{{ $format }}" @checked($loop->first)><span><strong>{{ $label }}</strong><small>{{ $hint }}</small></span></label>@endforeach
</div></fieldset>
<canvas id="profile-card" width="1080" height="1920" class="profile-card-canvas" tabindex="0" role="img" aria-label="Aperçu de la carte de profil. Glissez la photo, ou utilisez les flèches du clavier, pour la recadrer." data-initials="{{ $initials }}" @if($cover) data-cover="{{ $cover }}" @endif></canvas>
<fieldset class="bare"><legend>Fond de la carte</legend>
<div class="tile-group card-backgrounds">
@foreach(['green'=>'Vert maison','ink'=>'Noir et or','sand'=>'Sable'] as $bg=>$label)<label class="tile"><input type="radio" name="card_bg" value="{{ $bg }}" @checked($loop->first)><span><span class="card-swatch is-{{ $bg }}" aria-hidden="true"></span><strong>{{ $label }}</strong></span></label>@endforeach
</div></fieldset>
<div class="grid">
<label>Nom<input data-card="name" maxlength="60" value="{{ $me->name }}"></label>
<label>Fonction<input data-card="role" maxlength="80" value="{{ $p['title'] ?? ($me->isAdmin() ? 'Maison du Store AM' : 'Technicien Maison du Store AM') }}"></label>
<label>Téléphone<input data-card="phone" type="tel" maxlength="30" value="{{ $p['phone'] ?? '' }}"></label>
<label>Mon e-mail<input data-card="email" type="email" maxlength="80" value="{{ $me->email }}"></label>
<label>Compétences<input data-card="skills" maxlength="160" value="{{ $cardSkills }}" placeholder="Stores · Motorisation · SAV"></label>
<label>Pied de carte<input data-card="foot" maxlength="90" value="contact@maisondustoream.com · maisondustoream.com"><span class="hint">Coordonnées de la maison, en bas de la carte sur toute la largeur. Séparez les éléments par « · ».</span></label>
</div>
<label class="check"><input type="checkbox" id="card-phone" checked>Afficher mon téléphone</label>
<label class="check"><input type="checkbox" id="card-email">Afficher mon e-mail</label>
<label class="check"><input type="checkbox" id="card-skills" @checked($cardSkills!=='')>Afficher mes compétences</label>
<label class="check"><input type="checkbox" id="card-photo" @checked($cover)>Afficher une photo à droite</label>
<label>Photo de la carte<input type="file" id="card-photo-file" accept="image/jpeg,image/png,image/webp"><span class="hint">{{ $cover ? 'Par défaut, votre photo de couverture.' : 'Choisissez une photo de votre appareil.' }} Elle n’est pas envoyée au serveur.</span></label>
<div class="card-crop" id="card-crop" hidden><label>Zoom de la photo<input type="range" id="card-zoom" min="100" max="300" step="5" value="100"></label><button type="button" class="quiet" id="card-recenter">Recentrer la photo</button><span class="hint">Glissez la photo sur l’aperçu, horizontalement ou verticalement, pour la recadrer. Zoomez pour la déplacer dans les deux sens.</span></div>
<div class="modal-actions"><button type="button" class="quiet" data-close-dialog>Fermer</button><a class="button" id="card-download" href="#" download="carte-maison-du-store-story.png">Télécharger l’image</a></div>
</div>
</dialog>
<script>
(() => {
  const canvas=document.getElementById('profile-card'), dialog=document.getElementById('card-dialog'), link=document.getElementById('card-download');
  const themes={green:{bg:'#20332c',text:'#fff',accent:'#c09f6a',sub:'#d3dcd7',foot:'#a9bab2',initials:'#1c1c1c'},ink:{bg:'#1c1c1c',text:'#fff',accent:'#c09f6a',sub:'#d6d2c9',foot:'#a8a296',initials:'#1c1c1c'},sand:{bg:'#f6f0e4',text:'#1c1c1c',accent:'#84642f',sub:'#4d4a44',foot:'#6b665e',initials:'#fff'}};
  // Tailles en pixels de l'image finale ; la carte de visite fait 85 × 55 mm à 300 dpi.
  const formats={
    story:{w:1080,h:1920,pad:80,gap:56,photo:.44,radius:24,brand:52,name:108,role:46,phone:52,email:36,skills:34,skillLines:3,foot:34,initials:120,file:'story'},
    card:{w:1004,h:650,pad:56,gap:40,photo:.38,radius:14,brand:32,name:64,role:28,phone:32,email:24,skills:22,skillLines:2,foot:20,initials:0,file:'carte-de-visite'}
  };
  const value=key=>dialog.querySelector('[data-card="'+key+'"]').value.trim();
  const checked=name=>dialog.querySelector('[name='+name+']:checked').value;
  let cover=null, picked=null, pan={x:.5,y:.5}, area=null, drag=null, frame=0;
  const zoom=document.getElementById('card-zoom'), crop=document.getElementById('card-crop');
  const load=src=>{const img=new Image();img.onload=()=>draw();img.src=src;return img;};
  // Coupe en deux lignes équilibrées si besoin, puis réduit la police jusqu'à tenir dans la colonne.
  const fit=(c,text,weight,size,family,maxW)=>{
    const min=size/2, words=text.split(/\s+/).filter(Boolean), width=l=>c.measureText(l).width; let lines=[text];
    c.font=weight+' '+size+'px '+family;
    if(words.length>1&&width(text)>maxW){
      for(let cut=1;cut<words.length;cut++){
        const pair=[words.slice(0,cut).join(' '),words.slice(cut).join(' ')];
        if(lines.length<2||Math.max(...pair.map(width))<Math.max(...lines.map(width)))lines=pair;
      }
    }
    while(size>min&&Math.max(...lines.map(l=>c.measureText(l).width))>maxW){size-=2;c.font=weight+' '+size+'px '+family;}
    return {lines,size,font:c.font};
  };
  // Retour à la ligne mot à mot, au plus max lignes ; la dernière se termine par « … » si le texte déborde.
  const wrap=(c,text,font,maxW,max)=>{
    c.font=font; const lines=[]; let line='';
    for(const word of text.split(/\s+/).filter(Boolean)){
      const next=line?line+' '+word:word;
      if(line&&c.measureText(next).width>maxW){lines.push(line);line=word;}else line=next;
    }
    if(line)lines.push(line);
    if(lines.length>max){lines.length=max;let last=lines[max-1];while(last&&c.measureText(last+' …').width>maxW)last=last.replace(/\s*\S+$/,'');lines[max-1]=last+' …';}
    return lines;
  };
  const draw=()=>{
    const f=formats[checked('card_format')], t=themes[checked('card_bg')], c=canvas.getContext('2d');
    canvas.width=f.w;canvas.height=f.h;
    const photo=picked||cover, showPhoto=document.getElementById('card-photo').checked&&photo&&photo.complete&&photo.naturalWidth;
    const top=Math.round(f.h*.1), bottom=Math.round(f.h*.9), x=f.pad;
    c.fillStyle=t.bg;c.fillRect(0,0,f.w,f.h);
    // Colonne droite : photo recadrée, marges de 10 % en haut et en bas.
    let maxW=f.w-2*f.pad;
    if(showPhoto){
      const pw=Math.round(f.w*f.photo), px=f.w-f.pad-pw, ph=bottom-top;
      const scale=Math.max(pw/photo.naturalWidth,ph/photo.naturalHeight)*zoom.value/100, w=photo.naturalWidth*scale, h=photo.naturalHeight*scale;
      c.save();c.beginPath();if(c.roundRect)c.roundRect(px,top,pw,ph,f.radius);else c.rect(px,top,pw,ph);c.clip();
      // pan 0 montre le bord gauche (ou haut) de la photo, 1 le bord droit (ou bas).
      c.drawImage(photo,px+(pw-w)*pan.x,top+(ph-h)*pan.y,w,h);c.restore();
      area={x:px,y:top,w:pw,h:ph,ow:w-pw,oh:h-ph};
      maxW=px-f.gap-f.pad;
    }else area=null;
    crop.hidden=!area;canvas.classList.toggle('is-draggable',!!area);
    // Colonne gauche : marque en haut, identité au centre, pied de carte en bas.
    c.textAlign='left';c.textBaseline='alphabetic';
    const brand=fit(c,'Maison du Store AM','600',f.brand,'"Cormorant Garamond", Georgia, serif',maxW);
    c.fillStyle=t.text;c.font=brand.font;
    let y=top;brand.lines.forEach(l=>{y+=brand.size;c.fillText(l,x,y,maxW);});
    const lineY=y+brand.size*.6;
    c.strokeStyle=t.accent;c.lineWidth=Math.max(3,f.brand/9);c.beginPath();c.moveTo(x,lineY);c.lineTo(x+maxW,lineY);c.stroke();
    c.fillStyle=t.accent;c.beginPath();c.arc(x,lineY,f.brand/4,0,7);c.fill();
    const name=fit(c,value('name'),'500',f.name,'"Cormorant Garamond", Georgia, serif',maxW);
    const role=fit(c,value('role'),'500',f.role,'Inter, sans-serif',maxW);
    const phone=document.getElementById('card-phone').checked&&value('phone');
    const email=document.getElementById('card-email').checked&&value('email');
    // Une adresse trop longue passe sur deux lignes, coupée avant « @ ».
    const emailFont=email&&fit(c,email.replace('@',' @'),'400',f.email,'"IBM Plex Mono", monospace',maxW);
    const skillFont='500 '+f.skills+'px Inter, sans-serif';
    const skills=document.getElementById('card-skills').checked&&value('skills')?wrap(c,value('skills'),skillFont,maxW,f.skillLines):[];
    // Pied de carte sur toute la largeur, dans la marge du bas, sous la photo ; un élément par ligne s'il ne tient pas.
    const fullW=f.w-2*f.pad;
    c.font='400 '+f.foot+'px Inter, sans-serif';
    const foot=value('foot'), footLines=c.measureText(foot).width>fullW?foot.split(/\s+·\s+/).filter(Boolean):[foot];
    const footTop=bottom+(f.h-bottom-(footLines.length-1)*f.foot*1.4)/2+f.foot*.35;
    const r=showPhoto?0:f.initials;
    const height=(r?2*r+f.name*.6:0)+name.lines.length*name.size*1.1+role.lines.length*role.size*1.35+(phone?f.phone*2.2:0)
      +(email?emailFont.size*(phone?1.6:2.2)+(emailFont.lines.length-1)*emailFont.size*1.3:0)+(skills.length?f.skills*1.4+skills.length*f.skills*1.35:0);
    y=lineY+Math.max(0,(bottom-lineY-height)/2);
    if(r){
      c.fillStyle=t.accent;c.beginPath();c.arc(x+r,y+r,r,0,7);c.fill();
      c.fillStyle=t.initials;c.font='600 '+Math.round(r*.75)+'px Inter, sans-serif';c.textAlign='center';c.textBaseline='middle';c.fillText(canvas.dataset.initials,x+r,y+r);
      c.textAlign='left';c.textBaseline='alphabetic';y+=2*r+f.name*.6;
    }
    c.fillStyle=t.text;c.font=name.font;name.lines.forEach(l=>{y+=name.size*1.1;c.fillText(l,x,y,maxW);});
    c.fillStyle=t.sub;c.font=role.font;role.lines.forEach(l=>{y+=role.size*1.35;c.fillText(l,x,y,maxW);});
    if(phone){y+=f.phone*2.2;c.fillStyle=t.text;c.font='500 '+f.phone+'px "IBM Plex Mono", monospace';c.fillText(value('phone'),x,y,maxW);}
    if(email){y+=emailFont.size*(phone?1.6:2.2)-emailFont.size*1.3;c.fillStyle=t.text;c.font=emailFont.font;emailFont.lines.forEach(l=>{y+=emailFont.size*1.3;c.fillText(emailFont.lines.length>1?l:email,x,y,maxW);});}
    if(skills.length){
      y+=f.skills*1.4;c.fillStyle=t.accent;c.fillRect(x,y,f.skills*1.5,Math.max(2,f.skills/10));
      c.fillStyle=t.sub;c.font=skillFont;skills.forEach(l=>{y+=f.skills*1.35;c.fillText(l,x,y,maxW);});
    }
    c.fillStyle=t.foot;c.font='400 '+f.foot+'px Inter, sans-serif';footLines.forEach((l,i)=>c.fillText(l,x,footTop+i*f.foot*1.4,fullW));
    link.download='carte-maison-du-store-'+f.file+'.png';
  };
  const redraw=()=>{if(!frame)frame=requestAnimationFrame(()=>{frame=0;draw();});};
  const clamp=v=>Math.min(1,Math.max(0,v));
  // Déplacement en pixels de l'image finale : la photo suit le doigt.
  const move=(dx,dy)=>{if(!area)return;if(area.ow>0)pan.x=clamp(pan.x-dx/area.ow);if(area.oh>0)pan.y=clamp(pan.y-dy/area.oh);redraw();};
  const point=e=>{const r=canvas.getBoundingClientRect();return {x:(e.clientX-r.left)*canvas.width/r.width,y:(e.clientY-r.top)*canvas.height/r.height};};
  canvas.addEventListener('pointerdown',e=>{
    if(!area)return; const p=point(e);
    if(p.x<area.x||p.x>area.x+area.w||p.y<area.y||p.y>area.y+area.h)return;
    drag=p;canvas.setPointerCapture(e.pointerId);canvas.classList.add('is-dragging');e.preventDefault();
  });
  canvas.addEventListener('pointermove',e=>{if(!drag)return;const p=point(e);move(p.x-drag.x,p.y-drag.y);drag=p;});
  ['pointerup','pointercancel'].forEach(type=>canvas.addEventListener(type,()=>{drag=null;canvas.classList.remove('is-dragging');}));
  canvas.addEventListener('keydown',e=>{
    const step=area?area.w/20:0, keys={ArrowLeft:[step,0],ArrowRight:[-step,0],ArrowUp:[0,step],ArrowDown:[0,-step]};
    if(!area||!keys[e.key])return; e.preventDefault(); move(...keys[e.key]);
  });
  const recenter=()=>{pan={x:.5,y:.5};zoom.value=100;};
  document.getElementById('card-recenter').addEventListener('click',()=>{recenter();draw();});
  // L'image n'est générée qu'au téléchargement, pour garder le glisser fluide.
  link.addEventListener('click',()=>{link.href=canvas.toDataURL('image/png');});
  if(canvas.dataset.cover)cover=load(canvas.dataset.cover);
  document.getElementById('card-photo-file').addEventListener('change',e=>{
    const file=e.target.files[0]; if(!file)return;
    if(picked)URL.revokeObjectURL(picked.src);
    recenter();picked=load(URL.createObjectURL(file));document.getElementById('card-photo').checked=true;
  });
  dialog.addEventListener('input',draw);
  dialog.addEventListener('change',draw);
  document.querySelector('[data-open-dialog="card-dialog"]').addEventListener('click',()=>(document.fonts?document.fonts.ready:Promise.resolve()).then(draw));
})();
</script>
@endsection
