@extends('staff.layout')
@section('title', 'Catalogue')
@section('page-actions')
<a class="button" href="{{ route('staff.products.create') }}">@include('staff.partials.icon',['n'=>'plus','s'=>18])Ajouter un produit</a>
<a class="button quiet" href="{{ route('staff.quote-options.index') }}">Tarifs, matériaux et finitions des devis</a>
@endsection
@section('content')
<form class="panel" method="post" action="{{ route('staff.catalogue.visibility') }}" data-confirm="{{ $catalogueVisible ? 'Masquer le catalogue sur le site ? Les produits et leur utilisation dans les devis sont conservés.' : 'Afficher le catalogue sur le site ?' }}">
@csrf @method('PATCH')
<div class="title-row between">
<div class="grow"><h2>Visibilité du catalogue sur le site</h2>
<p role="status" class="mb-0">Le catalogue est actuellement <strong>{{ $catalogueVisible ? 'affiché' : 'masqué' }}</strong> sur le site public. <span class="muted">Masquer le catalogue retire les sections produits et leurs liens du site. Les produits, leurs réglages et leur utilisation dans les devis internes sont conservés.</span></p></div>
<input type="hidden" name="visible" value="{{ $catalogueVisible ? '0' : '1' }}">
<button class="secondary">{{ $catalogueVisible ? 'Masquer le catalogue' : 'Afficher le catalogue' }}</button>
</div>
</form>
<div class="stats">@foreach($counts as $label=>$count)<div class="stat"><strong>{{ $count }}</strong><span>{{ $label }}</span></div>@endforeach</div>
<section class="panel flush" aria-label="Produits">
<form class="filters" method="get" action="{{ route('staff.products.index') }}">
<label class="grow">Rechercher<input type="search" name="q" maxlength="150" placeholder="Nom ou référence" value="{{ request('q') }}"></label>
<details class="more-filters" @if(request()->hasAny(['universe','published','available','type'])) data-active @endif><summary>Autres filtres</summary><div>
<label>Univers<select name="universe"><option value="">Tous</option>@foreach(\App\Services\StaffInterface::UNIVERSES as $value=>$label)<option value="{{ $value }}" @selected(request('universe')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Visibilité<select name="published"><option value="">Toutes</option>@foreach(['1'=>'Visible sur le site','0'=>'Masqué'] as $value=>$label)<option value="{{ $value }}" @selected((string)request('published')===(string)$value)>{{ $label }}</option>@endforeach</select></label>
<label>Disponibilité<select name="available"><option value="">Toutes</option>@foreach(['1'=>'Disponible','0'=>'Sur commande'] as $value=>$label)<option value="{{ $value }}" @selected((string)request('available')===(string)$value)>{{ $label }}</option>@endforeach</select></label>
<label>Type<select name="type"><option value="">Tous</option>@foreach(['store'=>'Store','rideau'=>'Rideau'] as $value=>$label)<option value="{{ $value }}" @selected(request('type')===$value)>{{ $label }}</option>@endforeach</select></label>
</div></details>
<button class="secondary">Filtrer</button><a href="{{ route('staff.products.index') }}">Réinitialiser</a>
</form>
<p class="muted" class="panel-note">{{ $products->total() }} résultat(s). Les coûts restent internes ; les prix publics sont sur devis.</p>
<div class="table-wrap">
<table class="cards">
<thead><tr><th scope="col">Produit</th><th scope="col">Univers et type</th><th scope="col">Statuts</th><th scope="col">Coûts internes</th></tr></thead>
<tbody>
@forelse($products as $product)
@php($universe=\App\Services\StaffInterface::universeOf($product->details))
<tr>
<td class="primary"><a class="row-link" href="{{ route('staff.products.edit',$product) }}">{{ $product->details['name'] ?? $product->reference }}</a><small class="mono">{{ $product->reference }}</small></td>
<td data-label="Univers"><span class="uni {{ \App\Services\StaffInterface::universeClass($universe) }}">{{ \App\Services\StaffInterface::UNIVERSES[$universe] }}</span><small>{{ $product->details['category'] ?? '' }}</small></td>
<td>
<span class="badge {{ $product->published && $catalogueVisible ? 'st-ok' : 'st-neutral' }}">{{ $product->published ? ($catalogueVisible ? 'Visible sur le site' : 'Masqué avec le catalogue') : 'Masqué' }}</span>
<span class="badge {{ ($product->details['available'] ?? false) ? 'st-ok' : 'st-neutral' }}">{{ ($product->details['available'] ?? false) ? 'Disponible' : 'Sur commande' }}</span>
<form class="status-form" method="post" action="{{ route('staff.products.visibility',$product) }}">
@csrf @method('PATCH')
<input type="hidden" name="published" value="0">
<label class="switch"><input class="switch" role="switch" type="checkbox" name="published" value="1" @checked($product->published)>{{ $catalogueVisible ? 'Visible sur le site' : 'Visible lorsque le catalogue est affiché' }}</label>
<input type="hidden" name="available" value="0">
<label class="switch"><input class="switch" role="switch" type="checkbox" name="available" value="1" @checked($product->details['available'] ?? false)>Disponible</label>
<button class="quiet">Enregistrer les statuts</button>
</form>
</td>
<td>
<details><summary class="link">Voir les coûts</summary>
<dl class="detail-list mt-8">
@foreach(\App\Models\Product::COSTS as $cost=>$label)
<dt>{{ $label }}</dt>
<dd>@if($product->{$cost.'_cost'}===null)<span class="undefined">Non renseigné</span>@else<span class="amount">{{ number_format($product->{$cost.'_cost'},0,',',' ') }} FCFA</span> · {{ \App\Models\Product::COST_UNITS[$product->{$cost.'_cost_unit'}] }}@endif</dd>
@endforeach
</dl>
</details>
</td>
</tr>
@empty
<tr><td colspan="4" class="empty">Aucun produit ne correspond à ces critères.</td></tr>
@endforelse
</tbody>
</table>
</div>
@include('staff.pagination',['paginator'=>$products])
</section>
@endsection
