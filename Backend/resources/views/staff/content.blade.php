@extends('staff.layout')
@section('title', $content->exists ? 'Modifier l’illustration et les textes' : 'Nouvelle réalisation')
@section('content')
<div class="actions"><a href="{{ route('staff.contents.index') }}">← Images & portfolio</a></div>
<form method="post" enctype="multipart/form-data" action="{{ $content->exists ? route('staff.contents.update',$content) : route('staff.contents.store') }}">
@csrf @if($content->exists) @method('PUT') @endif
<section class="panel">
<p class="badge">{{ ['hero'=>'Hero — accueil','contact'=>'Contact — showroom','portfolio'=>'Portfolio','quote'=>'Carte — démarrer un devis'][$content->kind] }}</p>
<label>Titre<input name="title" required maxlength="150" value="{{ old('title',$content->title) }}"></label>
<label for="content-description">Description</label>
<textarea id="content-description" name="description" maxlength="3000">{{ old('description',$content->description) }}</textarea>
@if($content->kind==='portfolio')<label>Ordre d’affichage (les plus petits nombres en premier)<input name="position" type="number" required min="0" max="9999" step="1" value="{{ old('position',$content->position) }}"></label>@else<input name="position" type="hidden" value="0">@endif
<input name="published" type="hidden" value="0"><label class="check"><input name="published" type="checkbox" value="1" @checked(old('published',$content->published))>Visible sur le site</label>
@if($content->kind==='hero')<p class="muted">Si ce contenu est masqué, l’accueil conserve son titre et sa description par défaut, sans cette image.</p>@endif
</section>
<section class="panel">@include('staff.image-fields',['owner'=>$content])</section>
<div class="actions"><button>Enregistrer les images et les textes</button><a href="{{ route('staff.contents.index') }}">Retour à la liste</a></div>
</form>
@endsection
