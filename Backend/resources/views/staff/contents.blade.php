@extends('staff.layout')
@section('title', 'Images & portfolio')
@section('subtitle','Les images affichées sur le site, emplacement par emplacement. Sans image importée, le site garde son image d’origine. Les images des produits se gèrent dans le catalogue.')
@section('page-actions')
<a class="button quiet" href="{{ route('home') }}" target="_blank" rel="noopener">Voir le site</a>
<a class="button" href="{{ route('staff.contents.create') }}">Ajouter une réalisation</a>
@endsection
@section('content')
@php
 $slots=\App\Support\Site\SiteImages::SLOTS;
 $fixed=$contents->whereIn('slot',array_keys($slots))->sortBy(fn($c)=>array_search($c->slot,array_keys($slots)))->groupBy(fn($c)=>$slots[$c->slot][1]);
 $portfolio=$contents->where('kind','portfolio')->sortBy([['position','asc'],['id','asc']]);
 $portfolioDefaults=\App\Support\Site\SiteImages::portfolioFallbacks($portfolio);
 $quote=$contents->where('kind','quote');
 $quoteLabel=fn($c)=>str_starts_with($c->slot ?? '','finish-') ? 'Finition du devis public' : (str_starts_with($c->slot ?? '','quote-message-') ? 'Message du récapitulatif' : 'Carte — démarrer un devis');
@endphp
<nav class="chips" aria-label="Rubriques">
<a class="chip" href="#images-site">Images du site<b>{{ $fixed->flatten()->count() }}</b></a>
<a class="chip" href="#realisations">Réalisations<b>{{ $portfolio->count() }}</b></a>
@if($quote->isNotEmpty())<a class="chip" href="#devis-en-ligne">Devis en ligne<b>{{ $quote->count() }}</b></a>@endif
</nav>

<h2 class="section-title" id="images-site">Images du site</h2>
@foreach($fixed as $group=>$items)
<h3 class="choice-title">{{ $group }}</h3>
<div class="media-grid content-group">
@foreach($items as $content)
@include('staff.partials.content-card',['content'=>$content,'label'=>$slots[$content->slot][2],'fallback'=>\App\Support\Site\SiteImages::fallback($content->slot)])
@endforeach
</div>
@endforeach

<h2 class="section-title" id="realisations">Réalisations</h2>
<p class="muted">Affichées sur l’accueil, des plus petits numéros d’ordre aux plus grands.@if($portfolio->isEmpty()) Aucune réalisation enregistrée : le site affiche ses réalisations d’origine.@endif</p>
<div class="media-grid content-group">
@foreach($portfolio as $content)
@include('staff.partials.content-card',['content'=>$content,'label'=>'Réalisation · ordre '.$content->position,'fallback'=>$portfolioDefaults[$content->id] ?? null])
@endforeach
</div>

@if($quote->isNotEmpty())
<h2 class="section-title" id="devis-en-ligne">Devis en ligne</h2>
<div class="media-grid content-group">
@foreach($quote as $content)
@include('staff.partials.content-card',['content'=>$content,'label'=>$quoteLabel($content),'fallback'=>null])
@endforeach
</div>
@endif
@endsection
