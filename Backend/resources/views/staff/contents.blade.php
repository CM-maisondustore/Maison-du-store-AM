@extends('staff.layout')
@section('title', 'Images & portfolio')
@section('content')
<p>Modifiez les illustrations et les textes affichés sur le site. Les images des produits se gèrent dans le <a href="{{ route('staff.products.index') }}">catalogue</a>.</p>
<div class="actions"><a class="button" href="{{ route('staff.contents.create') }}">Ajouter une réalisation</a><a href="{{ route('home') }}#realisations" target="_blank" rel="noopener">Voir le portfolio</a></div>
<div class="grid">
@foreach($contents as $content)
<article class="panel">
<span class="badge">{{ ['hero'=>'Hero — accueil','contact'=>'Contact — showroom','portfolio'=>'Portfolio'][$content->kind] }}</span>
<h2>{{ $content->title }}</h2>
@if($content->media_asset_id)<img src="{{ route('staff.media.preview',$content->media_asset_id) }}" alt="{{ $content->image_alt }}" loading="lazy" style="width:100%;height:160px;object-fit:cover;border-radius:6px">@else<p class="muted">Aucune image importée</p>@endif
<p>{{ \Illuminate\Support\Str::limit($content->description,150) }}</p>
<p class="muted">{{ $content->published ? 'Visible sur le site' : 'Masqué' }}@if($content->kind==='portfolio') · Ordre {{ $content->position }}@endif</p>
<a class="button" href="{{ route('staff.contents.edit',$content) }}">Modifier</a>
</article>
@endforeach
</div>
@include('staff.pagination',['paginator'=>$contents])
@endsection
