@extends('staff.layout')
@php
 $me=auth()->user(); $p=$me->profile ?? []; $agent=!$me->isAdmin();
 $initials=\App\Services\StaffInterface::initials($me->name);
 $field=fn($k,$d='')=>old($k,$p[$k] ?? $d);
 $listed=fn($k)=>(array)old($k,$p[$k] ?? []);
 $cover=!empty($p['cover_media_id']) ? route('staff.profile.cover').'?v='.$p['cover_media_id'] : null;
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
<label>Communes couvertes<input name="zones" maxlength="300" value="{{ $field('zones') }}" placeholder="Cocody, Marcory, Plateau…"></label>
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
<canvas id="profile-card" width="1080" height="1080" class="image-preview" role="img" aria-label="Aperçu de la carte de profil" data-initials="{{ $initials }}" @if($cover) data-cover="{{ $cover }}" @endif></canvas>
<fieldset class="bare"><legend>Fond de la carte</legend>
<div class="tile-group card-backgrounds">
@foreach(['green'=>'Vert maison','ink'=>'Noir et or','sand'=>'Sable'] as $bg=>$label)<label class="tile"><input type="radio" name="card_bg" value="{{ $bg }}" @checked($loop->first)><span><span class="card-swatch is-{{ $bg }}" aria-hidden="true"></span><strong>{{ $label }}</strong></span></label>@endforeach
</div></fieldset>
<div class="grid">
<label>Nom<input data-card="name" maxlength="60" value="{{ $me->name }}"></label>
<label>Fonction<input data-card="role" maxlength="80" value="{{ $p['title'] ?? ($me->isAdmin() ? 'Maison du Store AM' : 'Technicien Maison du Store AM') }}"></label>
<label>Téléphone<input data-card="phone" type="tel" maxlength="30" value="{{ $p['phone'] ?? '' }}"></label>
<label>Pied de carte<input data-card="foot" maxlength="60" value="maisondustoream.com · Abidjan"></label>
</div>
<label class="check"><input type="checkbox" id="card-phone" checked>Afficher mon téléphone</label>
@if($cover)<label class="check"><input type="checkbox" id="card-cover" checked>Afficher ma photo de couverture</label>@endif
<div class="modal-actions"><button type="button" class="quiet" data-close-dialog>Fermer</button><a class="button" id="card-download" href="#" download="carte-maison-du-store.png">Télécharger l’image</a></div>
</div>
</dialog>
<script>
(() => {
  const canvas=document.getElementById('profile-card'), dialog=document.getElementById('card-dialog'), link=document.getElementById('card-download');
  const themes={green:{bg:'#20332c',text:'#fff',accent:'#c09f6a',sub:'#d3dcd7',foot:'#a9bab2',initials:'#1c1c1c'},ink:{bg:'#1c1c1c',text:'#fff',accent:'#c09f6a',sub:'#d6d2c9',foot:'#a8a296',initials:'#1c1c1c'},sand:{bg:'#f6f0e4',text:'#1c1c1c',accent:'#84642f',sub:'#4d4a44',foot:'#6b665e',initials:'#fff'}};
  const value=key=>dialog.querySelector('[data-card="'+key+'"]').value.trim();
  let cover=null;
  const draw=()=>{
    const c=canvas.getContext('2d'), t=themes[dialog.querySelector('[name=card_bg]:checked').value];
    const showCover=cover&&cover.complete&&cover.naturalWidth&&document.getElementById('card-cover')?.checked;
    c.fillStyle=t.bg;c.fillRect(0,0,1080,1080);
    if(showCover){
      const scale=Math.max(1080/cover.naturalWidth,300/cover.naturalHeight), w=cover.naturalWidth*scale, h=cover.naturalHeight*scale;
      c.save();c.beginPath();c.rect(0,0,1080,300);c.clip();c.drawImage(cover,(1080-w)/2,(300-h)/2,w,h);c.fillStyle='rgba(0,0,0,.4)';c.fillRect(0,0,1080,300);c.restore();
    }
    c.strokeStyle=t.accent;c.lineWidth=6;c.beginPath();c.moveTo(90,150);c.lineTo(990,150);c.stroke();
    c.fillStyle=t.accent;c.beginPath();c.arc(90,150,14,0,7);c.fill();
    c.fillStyle=showCover?'#fff':t.text;c.font='600 46px "Cormorant Garamond", Georgia, serif';c.fillText('Maison du Store AM',90,100);
    c.fillStyle=t.accent;c.beginPath();c.arc(210,380,120,0,7);c.fill();
    c.fillStyle=t.initials;c.font='600 90px Inter, sans-serif';c.textAlign='center';c.fillText(canvas.dataset.initials,210,412);c.textAlign='left';
    c.fillStyle=t.text;c.font='500 96px "Cormorant Garamond", Georgia, serif';c.fillText(value('name'),90,640,900);
    c.fillStyle=t.sub;c.font='500 44px Inter, sans-serif';c.fillText(value('role'),90,716,900);
    if(document.getElementById('card-phone').checked&&value('phone')){c.fillStyle=t.text;c.font='500 50px "IBM Plex Mono", monospace';c.fillText(value('phone'),90,830,900);}
    c.fillStyle=t.foot;c.font='400 36px Inter, sans-serif';c.fillText(value('foot'),90,990,900);
    link.href=canvas.toDataURL('image/png');
  };
  if(canvas.dataset.cover){cover=new Image();cover.onload=draw;cover.src=canvas.dataset.cover;}
  dialog.addEventListener('input',draw);
  dialog.addEventListener('change',draw);
  document.querySelector('[data-open-dialog="card-dialog"]').addEventListener('click',draw);
})();
</script>
@endsection
