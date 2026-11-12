{{-- Carte d'une image du site. Attend : $content, $label, $fallback (image d'origine affichée sans image importée, ou null). --}}
@php($shown=$content->media_asset_id ? route('staff.media.preview',$content->media_asset_id) : ($content->published ? $fallback : null))
<article class="media-card">
@if($shown)<img src="{{ $shown }}" alt="{{ $content->image_alt }}" loading="lazy">@else<div class="media-empty">@include('staff.partials.icon',['n'=>'images','s'=>26])<span>{{ $fallback ? 'Masquée : image d’origine' : 'Aucune image importée' }}</span></div>@endif
<div class="media-body">
@if($label!==$content->title)<span class="muted">{{ $label }}</span>@endif
<h2>{{ $content->title }}</h2>
@if($content->description)<p class="muted">{{ \Illuminate\Support\Str::limit($content->description,110) }}</p>@endif
<p class="media-meta">
@if($content->media_asset_id)<span class="badge {{ $content->published ? 'st-ok' : 'st-neutral' }}"><span aria-hidden="true">{{ $content->published ? '✓' : '○' }}</span>{{ $content->published ? 'Image importée, visible' : 'Image importée, masquée' }}</span>
@elseif($fallback)<span class="badge st-neutral"><span aria-hidden="true">○</span>Image d’origine du site</span>
@else<span class="badge st-neutral"><span aria-hidden="true">○</span>{{ $content->published ? 'Visible, sans image' : 'Masquée' }}</span>@endif
</p>
@if($content->media_asset_id && !$content->image_alt)<p class="undefined media-alert">Texte alternatif manquant</p>@endif
<a class="button quiet block" href="{{ route('staff.contents.edit',$content) }}">{{ $content->media_asset_id ? 'Modifier' : 'Remplacer l’image' }}</a>
</div>
</article>
