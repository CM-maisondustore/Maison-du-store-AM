@extends('staff.layout')
@section('title', 'Images & portfolio')
@section('subtitle','Modifiez les illustrations et les textes affichés sur le site. Les images des produits se gèrent dans le catalogue.')
@section('page-actions')
<a class="button quiet" href="{{ route('home') }}#realisations" target="_blank" rel="noopener">Voir le portfolio</a>
<a class="button" href="{{ route('staff.contents.create') }}">Ajouter une réalisation</a>
@endsection
@php($kinds=['hero'=>'Accueil','portfolio'=>'Portfolio','quote'=>'Devis','contact'=>'Contact'])
@section('content')
<div class="chips" data-chip-filter="media-grid">
<button type="button" class="chip" data-chip="all" aria-pressed="true">Toutes</button>
@foreach($kinds as $key=>$label)@if($contents->getCollection()->where('kind',$key)->isNotEmpty())<button type="button" class="chip" data-chip="{{ $key }}" aria-pressed="false">{{ $label }}</button>@endif @endforeach
</div>
<div class="media-grid" id="media-grid">
@foreach($contents as $content)
<article class="media-card" data-chip-item="{{ $content->kind }}">
@if($content->media_asset_id)<img src="{{ route('staff.media.preview',$content->media_asset_id) }}" alt="{{ $content->image_alt }}" loading="lazy">@else<div class="media-empty">@include('staff.partials.icon',['n'=>'images','s'=>26])<span>Aucune image importée</span></div>@endif
<div class="media-body">
<span class="muted">{{ ['hero'=>'Hero — accueil','contact'=>'Contact — showroom','portfolio'=>'Portfolio','quote'=>str_starts_with($content->slot ?? '', 'finish-') ? 'Finition du devis public' : (str_starts_with($content->slot ?? '', 'quote-message-') ? 'Message du récapitulatif' : 'Carte — démarrer un devis')][$content->kind] }}</span>
<h2>{{ $content->title }}</h2>
<p class="muted">{{ \Illuminate\Support\Str::limit($content->description,110) }}</p>
<p class="media-meta"><span class="badge {{ $content->published ? 'st-ok' : 'st-neutral' }}"><span aria-hidden="true">{{ $content->published ? '✓' : '○' }}</span>{{ $content->published ? 'Visible sur le site' : 'Masqué' }}</span>@if($content->kind==='portfolio')<span class="muted">Ordre {{ $content->position }}</span>@endif</p>
@if($content->media_asset_id && !$content->image_alt)<p class="undefined media-alert">Texte alternatif manquant</p>@endif
<a class="button quiet block" href="{{ route('staff.contents.edit',$content) }}">Modifier</a>
</div>
</article>
@endforeach
</div>
@include('staff.pagination',['paginator'=>$contents])
@endsection
