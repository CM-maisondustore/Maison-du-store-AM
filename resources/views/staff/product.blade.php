@extends('staff.layout')
@section('title', $product->exists ? 'Modifier le produit' : 'Nouveau produit')
@section('back',route('staff.products.index'))
@section('crumbs')<a href="{{ route('staff.products.index') }}">Catalogue</a>@endsection
@section('body-class','has-action-bar')
@section('content')
@php
 $typeNow=old('type',$product->details['type'] ?? 'store');
 $features=session()->hasOldInput() ? old('features',[]) : ($product->details['features'] ?? []);
 $colors=session()->hasOldInput() ? old('colors',[]) : ($product->details['colors'] ?? []);
@endphp
<form id="product-form" method="post" enctype="multipart/form-data" action="{{ $product->exists ? route('staff.products.update',$product) : route('staff.products.store') }}">
@csrf
@if($product->exists) @method('PUT') @endif
<div class="form-toc-layout">
<nav class="form-toc" aria-label="Étapes de la fiche produit" data-step-nav="product-form"><ol>
@foreach(['s-fiche'=>'Fiche','s-image'=>'Image','s-dispo'=>'Disponibilité','s-carac'=>'Caractéristiques','s-couts'=>'Coûts internes'] as $anchor=>$label)<li><a href="#{{ $anchor }}"><strong>{{ $label }}</strong><small></small></a></li>@endforeach
</ol></nav>
<div class="form-steps form-narrow">
<section class="panel" id="s-fiche" aria-labelledby="h-fiche">
<h2 id="h-fiche" class="section-title"><span class="step" aria-hidden="true">1</span>Fiche produit</h2>
<div class="grid">
<label>Référence<input name="reference" required maxlength="50" value="{{ old('reference',$product->reference) }}"></label>
<label>Nom<input name="name" required maxlength="150" value="{{ old('name',$product->details['name'] ?? '') }}"></label>
</div>
<fieldset class="bare mt-12"><legend>Type</legend>
<div class="tile-group">
@foreach(['store'=>'Store','rideau'=>'Rideau','electromenager'=>'Électroménager','meuble'=>'Meuble'] as $value=>$label)<label class="tile"><input type="radio" name="type" value="{{ $value }}" @checked($typeNow===$value)><span><strong>{{ $label }}</strong></span></label>@endforeach
</div></fieldset>
<div class="grid">
<label>Univers<select name="universe">@foreach(\App\Services\StaffInterface::UNIVERSES as $value=>$label)<option value="{{ $value }}" @selected(old('universe',\App\Services\StaffInterface::universeOf($product->details ?? []))===$value)>{{ $label }}</option>@endforeach</select><span class="hint">Proposé selon le type choisi.</span></label>
<label>Catégorie<input name="category" required maxlength="150" value="{{ old('category',$product->details['category'] ?? '') }}"></label>
</div>
<label>Modèle du formulaire de devis<input name="quoteModel" required maxlength="150" value="{{ old('quoteModel',$product->details['quoteModel'] ?? '') }}"></label>
<label>Description<textarea name="description" rows="4" required maxlength="3000">{{ old('description',$product->details['description'] ?? '') }}</textarea></label>
</section>
<section class="panel" id="s-image" aria-labelledby="h-image">
<h2 id="h-image" class="section-title mb-8"><span class="step" aria-hidden="true">2</span>Image d’illustration</h2>
@include('staff.image-fields',['owner'=>$product,'bare'=>true])
</section>
<section class="panel" id="s-dispo" aria-labelledby="h-dispo">
<h2 id="h-dispo" class="section-title"><span class="step" aria-hidden="true">3</span>Disponibilité et visibilité</h2>
<p class="muted">Un produit masqué reste enregistré dans le catalogue interne. Un produit visible sans disponibilité apparaît sur commande sur le site.</p>
@foreach(['published'=>'Visible sur le site','available'=>'Disponible','featured'=>'Mis en avant'] as $key=>$label)
<input type="hidden" name="{{ $key }}" value="0">
<label class="toggle-row"><span>{{ $label }}</span><input class="switch" type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$key==='published' ? ($product->exists ? $product->published : false) : ($product->details[$key] ?? false)))></label>
@endforeach
</section>
<section class="panel" id="s-carac" aria-labelledby="h-carac">
<h2 id="h-carac" class="section-title"><span class="step" aria-hidden="true">4</span>Caractéristiques et coloris</h2>
<fieldset class="bare mt-12"><legend>Caractéristiques</legend><div class="pills">
@foreach(['occultant','tamisant','motorise','decoratif'] as $feature)<label class="pill plain"><input type="checkbox" name="features[]" value="{{ $feature }}" @checked(in_array($feature,$features))><span>{{ ucfirst($feature) }}</span></label>@endforeach
</div></fieldset>
<fieldset class="bare mt-12"><legend>Coloris</legend><div class="pills">
@foreach(['blanc','ecru','beige','taupe','gris','anthracite','bleu','vert','bordeaux'] as $color)<label class="pill"><input type="checkbox" name="colors[]" value="{{ $color }}" @checked(in_array($color,$colors))><span><i class="dot dot-{{ $color }}" aria-hidden="true"></i>{{ ucfirst($color) }}</span></label>@endforeach
</div></fieldset>
<div class="mt-12" data-specs>
<label>Fiche technique (une ligne « Libellé : valeur » par caractéristique)<textarea name="specs_text" rows="4" maxlength="3000">{{ old('specs_text',collect($product->details['specs'] ?? [])->map(fn($s)=>$s['label'].' : '.$s['value'])->implode("\n")) }}</textarea></label>
<div data-spec-ui>
<p class="legend mb-0">Fiche technique</p>
<p class="hint mt-0">Une ligne par caractéristique, par exemple Largeur maximale et 3 m.</p>
<div class="spec-rows" data-spec-rows></div>
<div class="actions"><button type="button" class="quiet" data-spec-add>Ajouter une ligne</button></div>
<template><div class="spec-row"><input aria-label="Libellé de la caractéristique" placeholder="Libellé"><input aria-label="Valeur de la caractéristique" placeholder="Valeur"><button type="button" class="quiet" data-spec-remove aria-label="Retirer cette ligne">@include('staff.partials.icon',['n'=>'close','s'=>18])</button></div></template>
</div>
</div>
</section>
<section class="panel" id="s-couts" aria-labelledby="h-couts">
<h2 id="h-couts" class="section-title"><span class="step" aria-hidden="true">5</span>Coûts internes</h2>
@if(!empty($product->details['reference_price']))<p class="notice info mt-12">Prix relevé à titre indicatif : {{ number_format($product->details['reference_price'],0,',',' ') }} FCFA ({{ $product->details['reference_source'] ?? '' }}). Ce n’est pas un prix de vente et il n’est jamais affiché sur le site.</p>@endif
<p class="muted">Réservés à Admin-CM. Laissez un montant vide s’il n’est pas encore renseigné ; saisissez 0 pour un coût nul. Les prix du site restent sur devis.</p>
<div class="costs">
@foreach(\App\Models\Product::COSTS as $cost=>$label)
@php($costValue=old($cost.'_cost',$product->{$cost.'_cost'}))
<div class="cost-card">
<label for="{{ $cost }}_cost">{{ $label }}<span class="field-unit is-cost"><input id="{{ $cost }}_cost" data-cost name="{{ $cost }}_cost" type="number" min="0" max="100000000" step="1" inputmode="numeric" placeholder="Non renseigné" value="{{ $costValue }}"><span class="unit">FCFA</span></span><span class="cost-state" aria-live="polite">{{ $costValue===null || $costValue==='' ? 'Non renseigné' : ((int)$costValue===0 ? 'Coût nul' : '') }}</span></label>
<label for="{{ $cost }}_cost_unit">Unité — {{ mb_strtolower($label) }}<select id="{{ $cost }}_cost_unit" name="{{ $cost }}_cost_unit">
@foreach(\App\Models\Product::COST_UNITS as $unit=>$unitLabel)<option value="{{ $unit }}" @selected(old($cost.'_cost_unit',$product->{$cost.'_cost_unit'})===$unit)>{{ $unitLabel }}</option>@endforeach
</select></label>
</div>
@endforeach
</div>
<p class="muted">Ces coûts servent de référence pour préparer les devis. Le montant du devis dépend des quantités et des prestations retenues.</p>
</section>
</div>
</div>
<div class="action-bar"><a class="button quiet" href="{{ route('staff.products.index') }}">Retour au catalogue</a><button>Enregistrer le produit et les coûts</button></div>
</form>
@endsection
