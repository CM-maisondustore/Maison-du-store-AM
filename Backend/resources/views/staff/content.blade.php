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
<div class="portfolio-media">@include('staff.image-fields',['owner'=>$content,'bare'=>true])</div>
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
@endsection
