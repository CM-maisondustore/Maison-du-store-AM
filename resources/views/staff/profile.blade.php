@extends('staff.layout')
@php
 $me=auth()->user(); $p=$me->profile ?? []; $agent=!$me->isAdmin();
 $initials=\App\Services\StaffInterface::initials($me->name);
 $field=fn($k,$d='')=>old($k,$p[$k] ?? $d);
@endphp
@section('title','Modifier mon profil')
@section('back',route('staff.settings'))
@section('head')
<div class="page-head keep"><div class="title-row"><span class="avatar lg" aria-hidden="true">{{ $initials }}</span>
<div><h1>{{ $me->name }}</h1><p class="sub">{{ $p['title'] ?? ($me->isAdmin() ? 'Admin-CM' : 'Agent') }}</p></div></div>
<div class="page-actions"><button type="button" class="quiet" data-open-dialog="card-dialog">Carte à partager</button><a class="button quiet" href="{{ route('staff.settings') }}">Paramètres</a></div></div>
@endsection
@section('content')
<form method="post" action="{{ route('staff.profile.update') }}" class="small-form mw-820">
@csrf @method('PATCH')
<section class="panel" aria-labelledby="p-id"><h2 id="p-id">Identité</h2>
<div class="grid">
<label>Civilité<select name="civility"><option value="">—</option>@foreach(['M.','Mme'] as $civility)<option @selected($field('civility')===$civility)>{{ $civility }}</option>@endforeach</select></label>
<label>Nom et prénom<input name="name" required maxlength="150" autocomplete="name" value="{{ old('name',$me->name) }}"></label>
<label>Fonction affichée<input name="title" maxlength="100" value="{{ $field('title') }}" placeholder="{{ $agent ? 'Technicien poseur' : 'Gérance' }}"></label>
</div></section>
<section class="panel" aria-labelledby="p-co"><h2 id="p-co">Coordonnées</h2>
<div class="grid">
<label>Téléphone professionnel<input name="phone" type="tel" maxlength="30" autocomplete="tel" inputmode="tel" value="{{ $field('phone') }}" placeholder="+225 07 00 00 00 00"></label>
<label>Adresse e-mail<input type="email" value="{{ $me->email }}" readonly aria-describedby="mail-help"><span class="muted fw-400" id="mail-help">Modifiable par Admin-CM : elle identifie votre compte.</span></label>
</div>
<input type="hidden" name="whatsapp" value="0"><label class="check"><input type="checkbox" name="whatsapp" value="1" @checked($field('whatsapp'))>Joignable sur WhatsApp à ce numéro</label>
</section>
@if($agent)
<section class="panel" aria-labelledby="p-terrain"><h2 id="p-terrain">Terrain</h2><p class="muted">Le bureau s’en sert pour vous affecter les prestations.</p>
<label>Communes couvertes<input name="zones" maxlength="300" value="{{ $field('zones') }}" placeholder="Cocody, Marcory, Plateau…"></label>
<label>Savoir-faire<input name="skills" maxlength="300" value="{{ $field('skills') }}" placeholder="Stores, rideaux, électroménager, montage de meubles…"></label>
<label>Jours et horaires<input name="hours" maxlength="150" value="{{ $field('hours') }}" placeholder="Lundi à samedi, 08:00–18:00"></label>
</section>
<section class="panel" aria-labelledby="p-urg"><h2 id="p-urg">Contact en cas d’urgence</h2>
<div class="grid">
<label>Nom et lien<input name="emergency_name" maxlength="150" value="{{ $field('emergency_name') }}"></label>
<label>Téléphone<input name="emergency_phone" type="tel" maxlength="30" value="{{ $field('emergency_phone') }}"></label>
</div><p class="muted">Visible uniquement par les Admin-CM.</p></section>
@else
<section class="panel" aria-labelledby="p-sig"><h2 id="p-sig">Signature</h2><p class="muted">Pour vos échanges : à copier sous vos messages.</p>
<label>Texte de la signature<textarea name="signature" rows="3" maxlength="500">{{ $field('signature') }}</textarea></label>
</section>
<section class="panel" aria-labelledby="p-dispo"><h2 id="p-dispo">Disponibilité</h2>
<div class="grid">
<label>Statut<select name="availability"><option value="available" @selected($field('availability')==='available')>Disponible</option><option value="away" @selected($field('availability')==='away')>Absent</option></select></label>
<label>De retour le<input type="date" name="back_on" value="{{ $field('back_on') }}"></label>
</div></section>
@endif
<div class="action-bar"><a class="button quiet" href="{{ route('staff.settings') }}">Annuler</a><button>Enregistrer le profil</button></div>
</form>
<dialog class="modal" id="card-dialog" aria-labelledby="sh-t">
<div class="modal-body">
<h2 id="sh-t">Carte de profil à partager</h2>
<p class="muted">Une image aux couleurs de la maison pour WhatsApp, Facebook, Instagram ou LinkedIn. Elle est créée sur votre appareil.</p>
<canvas id="profile-card" width="1080" height="1080" class="image-preview" role="img" aria-label="Aperçu de la carte de profil" data-name="{{ $me->name }}" data-role="{{ $p['title'] ?? ($me->isAdmin() ? 'Maison du Store AM' : 'Technicien Maison du Store AM') }}" data-phone="{{ $p['phone'] ?? '' }}" data-initials="{{ $initials }}"></canvas>
<label class="check"><input type="checkbox" id="card-phone" checked>Afficher mon téléphone</label>
<div class="modal-actions"><button type="button" class="quiet" data-close-dialog>Fermer</button><a class="button" id="card-download" href="#" download="carte-maison-du-store.png">Télécharger l’image</a></div>
</div>
</dialog>
<script>
(() => {
  const canvas=document.getElementById('profile-card'), toggle=document.getElementById('card-phone'), link=document.getElementById('card-download');
  const draw=()=>{
    const c=canvas.getContext('2d'), d=canvas.dataset;
    c.fillStyle='#20332c';c.fillRect(0,0,1080,1080);
    c.strokeStyle='#c09f6a';c.lineWidth=6;c.beginPath();c.moveTo(90,150);c.lineTo(990,150);c.stroke();
    c.fillStyle='#c09f6a';c.beginPath();c.arc(90,150,14,0,7);c.fill();
    c.fillStyle='#fff';c.font='600 46px "Cormorant Garamond", Georgia, serif';c.fillText('Maison du Store AM',90,100);
    c.fillStyle='#c09f6a';c.beginPath();c.arc(210,380,120,0,7);c.fill();
    c.fillStyle='#1c1c1c';c.font='600 90px Inter, sans-serif';c.textAlign='center';c.fillText(d.initials,210,412);c.textAlign='left';
    c.fillStyle='#fff';c.font='500 96px "Cormorant Garamond", Georgia, serif';c.fillText(d.name,90,640,900);
    c.fillStyle='#d3dcd7';c.font='500 44px Inter, sans-serif';c.fillText(d.role,90,716,900);
    if(toggle.checked&&d.phone){c.fillStyle='#fff';c.font='500 50px "IBM Plex Mono", monospace';c.fillText(d.phone,90,830,900);}
    c.fillStyle='#a9bab2';c.font='400 36px Inter, sans-serif';c.fillText('maisondustoream.com · Abidjan',90,990);
    link.href=canvas.toDataURL('image/png');
  };
  toggle.addEventListener('change',draw);
  document.querySelector('[data-open-dialog="card-dialog"]').addEventListener('click',draw);
})();
</script>
@endsection
