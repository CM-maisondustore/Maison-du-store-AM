@extends('staff.layout')
@section('title', 'Catalogue')
@section('content')
<form class="panel" method="post" action="{{ route('staff.catalogue.visibility') }}">
@csrf @method('PATCH')
<h2>Visibilité du catalogue sur le site</h2>
<p role="status">Le catalogue est actuellement <strong>{{ $catalogueVisible ? 'affiché' : 'masqué' }}</strong> sur le site public.</p>
<p>Masquer le catalogue retire les sections produits et leurs liens du site. Les produits, leurs réglages et leur utilisation dans les devis internes sont conservés.</p>
<input type="hidden" name="visible" value="{{ $catalogueVisible ? '0' : '1' }}">
<button>{{ $catalogueVisible ? 'Masquer le catalogue' : 'Afficher le catalogue' }}</button>
</form>
<div class="stats">@foreach($counts as $label=>$count)<div class="stat"><strong>{{ $count }}</strong><span>{{ $label }}</span></div>@endforeach</div>
<div class="actions"><a class="button" href="{{ route('staff.products.create') }}">Ajouter un produit</a><a class="button" href="{{ route('staff.quote-options.index') }}">Tarifs, matériaux et finitions des devis</a></div>
<form class="panel" method="get" action="{{ route('staff.products.index') }}">
<div class="grid">
<label>Rechercher<input name="q" maxlength="150" placeholder="Nom ou référence" value="{{ request('q') }}"></label>
<label>Visibilité<select name="published"><option value="">Toutes</option>@foreach(['1'=>'Visible sur le site','0'=>'Masqué'] as $value=>$label)<option value="{{ $value }}" @selected((string)request('published')===(string)$value)>{{ $label }}</option>@endforeach</select></label>
<label>Disponibilité<select name="available"><option value="">Toutes</option>@foreach(['1'=>'Disponible','0'=>'Sur commande'] as $value=>$label)<option value="{{ $value }}" @selected((string)request('available')===(string)$value)>{{ $label }}</option>@endforeach</select></label>
<label>Type<select name="type"><option value="">Tous</option>@foreach(['store'=>'Store','rideau'=>'Rideau'] as $value=>$label)<option value="{{ $value }}" @selected(request('type')===$value)>{{ $label }}</option>@endforeach</select></label>
</div>
<div class="actions"><button>Filtrer</button><a href="{{ route('staff.products.index') }}">Réinitialiser</a></div>
</form>
<p class="muted">{{ $products->total() }} résultat(s). Les coûts restent internes ; les prix publics sont sur devis.</p>
@forelse($products as $product)
<article class="panel">
<h2><a href="{{ route('staff.products.edit',$product) }}">{{ $product->details['name'] ?? $product->reference }}</a></h2>
<p class="muted">{{ $product->reference }} · {{ $product->details['category'] ?? '' }}</p>
<div class="grid">
<div>
<span class="badge">{{ $product->published ? ($catalogueVisible ? 'Visible sur le site' : 'Masqué avec le catalogue') : 'Masqué' }}</span>
<span class="badge">{{ ($product->details['available'] ?? false) ? 'Disponible' : 'Sur commande' }}</span>
<form method="post" action="{{ route('staff.products.visibility',$product) }}">
@csrf @method('PATCH')
<input type="hidden" name="published" value="0">
<label class="check"><input type="checkbox" name="published" value="1" @checked($product->published)>{{ $catalogueVisible ? 'Visible sur le site' : 'Visible lorsque le catalogue est affiché' }}</label>
<input type="hidden" name="available" value="0">
<label class="check"><input type="checkbox" name="available" value="1" @checked($product->details['available'] ?? false)>Disponible</label>
<div class="actions"><button class="secondary">Enregistrer les statuts</button></div>
</form>
</div>
<dl class="detail-list">
@foreach(\App\Models\Product::COSTS as $cost=>$label)
<dt>{{ $label }}</dt>
<dd>@if($product->{$cost.'_cost'}===null)Non renseigné@else{{ number_format($product->{$cost.'_cost'},0,',',' ') }} FCFA · {{ \App\Models\Product::COST_UNITS[$product->{$cost.'_cost_unit'}] }}@endif</dd>
@endforeach
</dl>
</div>
<div class="actions"><a class="button" href="{{ route('staff.products.edit',$product) }}">Modifier la fiche et les coûts</a></div>
</article>
@empty
<div class="panel empty">Aucun produit ne correspond à ces critères.</div>
@endforelse
@include('staff.pagination',['paginator'=>$products])
@endsection
