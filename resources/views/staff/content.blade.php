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
<p class="badge mt-12">{{ ['hero'=>'Hero — accueil','contact'=>'Contact — showroom','portfolio'=>'Portfolio','quote'=>str_starts_with($content->slot ?? '', 'finish-') ? 'Finition du devis public' : (str_starts_with($content->slot ?? '', 'quote-message-') ? 'Message du récapitulatif' : 'Carte — démarrer un devis')][$content->kind] }}</p>
<label>Titre<input name="title" required maxlength="150" value="{{ old('title',$content->title) }}"></label>
<label for="content-description">Description</label>
<textarea id="content-description" name="description" rows="4" maxlength="3000">{{ old('description',$content->description) }}</textarea>
@if($content->kind==='portfolio')<label>Ordre d’affichage<span class="hint">Les plus petits nombres en premier.</span><input name="position" type="number" inputmode="numeric" required min="0" max="9999" step="1" value="{{ old('position',$content->position) }}"></label>@else<input name="position" type="hidden" value="0">@endif
<input name="published" type="hidden" value="0"><label class="toggle-row"><span>Visible sur le site<small>Masquée, la fiche reste enregistrée ici.</small></span><input class="switch" name="published" type="checkbox" value="1" @checked(old('published',$content->published))></label>
@if($content->kind==='hero')<p class="muted">Si ce contenu est masqué, l’accueil conserve son titre et sa description par défaut, sans cette image.</p>@endif
</div>
</section>
</div>
<div class="action-bar"><a class="button quiet" href="{{ route('staff.contents.index') }}">Retour à la liste</a><button>{{ $content->kind==='portfolio' ? 'Enregistrer la réalisation' : 'Enregistrer les images et les textes' }}</button></div>
</form>
@endsection
