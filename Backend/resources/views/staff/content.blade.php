@extends('staff.layout')
@section('title', $content->exists ? 'Modifier l’illustration et les textes' : 'Nouvelle réalisation')
@section('back',route('staff.contents.index'))
@section('crumbs')<a href="{{ route('staff.contents.index') }}">Images & portfolio</a>@endsection
@section('body-class','has-action-bar')
@section('content')
<form method="post" enctype="multipart/form-data" action="{{ $content->exists ? route('staff.contents.update',$content) : route('staff.contents.store') }}">
@csrf @if($content->exists) @method('PUT') @endif
<div class="form-narrow">
<section class="panel portfolio-card" aria-label="Fiche de la réalisation">
<div class="portfolio-media">@include('staff.image-fields',['owner'=>$content,'bare'=>true])
@php([$aw,$ah,$aLabel]=\App\Support\Site\SiteImages::aspect($content))
{{-- Recadrage dans le navigateur : l'image envoyée est celle recadrée (aucun changement côté serveur). --}}
<div class="cropper" data-cropper data-aspect="{{ $aw }}/{{ $ah }}" hidden>
<div class="cropper-head"><h2>Recadrer l’image</h2><p class="hint">Faites glisser l’image pour choisir la partie visible, zoomez avec le curseur.</p></div>
<div class="chip-set" role="radiogroup" aria-label="Format">
<label class="chip-radio"><input type="radio" name="crop_ui" value="{{ $aw }}/{{ $ah }}" checked><span>Site ({{ $aw }}:{{ $ah }})</span></label>
<label class="chip-radio"><input type="radio" name="crop_ui" value="1/1"><span>Carré</span></label>
<label class="chip-radio"><input type="radio" name="crop_ui" value="4/5"><span>Portrait 4:5</span></label>
<label class="chip-radio"><input type="radio" name="crop_ui" value="16/9"><span>Paysage 16:9</span></label>
<label class="chip-radio"><input type="radio" name="crop_ui" value="original"><span>Sans recadrage</span></label>
</div>
<p class="hint">Format du site : {{ $aLabel }}. Un autre format sera rogné sur le site.</p>
<div class="cropper-stage"><canvas data-crop-canvas tabindex="0" aria-label="Aperçu du recadrage. Flèches pour déplacer, plus et moins pour zoomer."></canvas></div>
<label class="cropper-zoom">Zoom<input type="range" data-crop-zoom min="1" max="4" step="0.01" value="1"></label>
<div class="actions"><button type="button" data-crop-apply>Appliquer le recadrage</button><button type="button" class="quiet" data-crop-cancel>Garder l’image entière</button><span class="hint" data-crop-status role="status"></span></div>
</div>
<button type="button" class="quiet block" data-crop-current @unless($content->media_asset_id) hidden @endunless>Recadrer l’image actuelle</button>
</div>
<div class="portfolio-body">
@php($slot=\App\Support\Site\SiteImages::SLOTS[$content->slot] ?? null)
{{-- Textes repris sur le site : bandeau Stores & Rideaux, réalisations et cartes du devis. Ailleurs, seule l'image compte. --}}
@php($withText=!$slot || $content->slot==='hero')
<p class="badge mt-12">{{ $slot ? $slot[1].' · '.$slot[2] : ['hero'=>'Hero — accueil','contact'=>'Contact — showroom','portfolio'=>'Réalisation','quote'=>str_starts_with($content->slot ?? '', 'finish-') ? 'Finition du devis public' : (str_starts_with($content->slot ?? '', 'quote-message-') ? 'Message du récapitulatif' : 'Carte — démarrer un devis')][$content->kind] ?? 'Image du site' }}</p>
@if($slot)<p class="hint">Sans image importée (ou fiche masquée), le site affiche son image d’origine :</p><img class="content-fallback" src="{{ asset($slot[3]) }}" alt="Image d’origine du site" loading="lazy">@endif
@if($withText)
<label>Titre<input name="title" required maxlength="150" value="{{ old('title',$content->title) }}"></label>
<label for="content-description">Description</label>
<textarea id="content-description" name="description" rows="4" maxlength="3000">{{ old('description',$content->description) }}</textarea>
@else
<input name="title" type="hidden" value="{{ old('title',$content->title) }}"><input name="description" type="hidden" value="{{ old('description',$content->description) }}">
@endif
@if($content->kind==='portfolio')<label>Ordre d’affichage<span class="hint">Les plus petits nombres en premier.</span><input name="position" type="number" inputmode="numeric" required min="0" max="9999" step="1" value="{{ old('position',$content->position) }}"></label>@else<input name="position" type="hidden" value="0">@endif
<input name="published" type="hidden" value="0"><label class="toggle-row"><span>Visible sur le site<small>{{ $slot ? 'Masquée, le site reprend son image d’origine.' : 'Masquée, la fiche reste enregistrée ici.' }}</small></span><input class="switch" name="published" type="checkbox" value="1" @checked(old('published',$content->published))></label>
@if($content->kind==='hero')<p class="muted">Si ce contenu est masqué, l’accueil conserve son titre et sa description par défaut, sans cette image.</p>@endif
</div>
</section>
</div>
<div class="action-bar"><a class="button quiet" href="{{ route('staff.contents.index') }}">Retour à la liste</a><button>{{ $content->kind==='portfolio' ? 'Enregistrer la réalisation' : 'Enregistrer les images et les textes' }}</button></div>
</form>

@if($content->exists)
@php($shareImage=$content->media_asset_id ? route('staff.media.preview',$content->media_asset_id) : ($slot ? asset($slot[3]) : ''))
{{-- Carte de partage dessinée dans le navigateur, à télécharger pour WhatsApp, Facebook ou Instagram. Rien n'est enregistré. --}}
<section class="panel share-card form-narrow" id="partage" aria-labelledby="h-share" data-share-card data-image="{{ $shareImage }}" data-logo="{{ asset('site/favicon.svg') }}" data-slot="{{ \Illuminate\Support\Str::slug($content->slot ?: 'image') }}">
<h2 id="h-share">Carte à partager</h2>
<p class="muted">Une image aux couleurs de la maison, prête à publier. Elle reprend l’image ci-dessus (ou celle que vous venez de choisir).</p>
<div class="share-grid">
<div class="share-controls">
<div class="chip-set" role="radiogroup" aria-label="Format de la carte">
<label class="chip-radio"><input type="radio" name="share_format" value="link" checked><span>Lien · 1200×630</span></label>
<label class="chip-radio"><input type="radio" name="share_format" value="square"><span>Carré · 1080×1080</span></label>
<label class="chip-radio"><input type="radio" name="share_format" value="story"><span>Story · 1080×1920</span></label>
</div>
<label>Titre de la carte<input data-share-title maxlength="70" value="{{ $content->kind==='portfolio' || $content->slot==='hero' ? $content->title : 'Stores et rideaux sur mesure' }}"></label>
<label>Ligne secondaire<input data-share-line maxlength="90" value="{{ $content->kind==='portfolio' && $content->description ? \Illuminate\Support\Str::limit($content->description,80,'') : 'Confection à Abidjan, pose par nos équipes' }}"></label>
<button type="button" data-share-download>Télécharger la carte</button>
<p class="hint" data-share-status role="status"></p>
</div>
<div class="share-preview"><canvas data-share-canvas role="img" aria-label="Aperçu de la carte à partager"></canvas></div>
</div>
</section>
@endif
<script src="{{ asset('image-studio.js') }}?v={{ @filemtime(public_path('image-studio.js')) }}" defer></script>
@endsection
