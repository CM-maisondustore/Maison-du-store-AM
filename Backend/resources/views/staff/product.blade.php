@extends('staff.layout')
@section('title', $product->exists ? 'Modifier le produit' : 'Nouveau produit')
@section('content')
<div class="actions"><a href="{{ route('staff.products.index') }}">← Retour au catalogue</a></div>
<form method="post" enctype="multipart/form-data" action="{{ $product->exists ? route('staff.products.update',$product) : route('staff.products.store') }}">
@csrf
@if($product->exists) @method('PUT') @endif
<section class="panel">
<h2>Fiche produit</h2>
<div class="grid">
<label>Référence<input name="reference" required maxlength="50" value="{{ old('reference',$product->reference) }}"></label>
<label>Nom<input name="name" required maxlength="150" value="{{ old('name',$product->details['name'] ?? '') }}"></label>
<label>Type<select name="type">
@foreach(['store'=>'Store','rideau'=>'Rideau'] as $value=>$label)<option value="{{ $value }}" @selected(old('type',$product->details['type'] ?? '')===$value)>{{ $label }}</option>@endforeach
</select></label>
<label>Catégorie<input name="category" required maxlength="150" value="{{ old('category',$product->details['category'] ?? '') }}"></label>
<label>Modèle du formulaire de devis<input name="quoteModel" required maxlength="150" value="{{ old('quoteModel',$product->details['quoteModel'] ?? '') }}"></label>
</div>
<label>Description<textarea name="description" required maxlength="3000">{{ old('description',$product->details['description'] ?? '') }}</textarea></label>
</section>
<section class="panel">
<h2>Disponibilité et visibilité</h2>
<p class="muted">Un produit masqué reste enregistré dans le catalogue interne. Un produit visible sans disponibilité apparaît sur commande sur le site.</p>
@foreach(['published'=>'Visible sur le site','available'=>'Disponible','featured'=>'Mis en avant'] as $key=>$label)
<input type="hidden" name="{{ $key }}" value="0">
<label class="check"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$key==='published' ? ($product->exists ? $product->published : false) : ($product->details[$key] ?? false)))>{{ $label }}</label>
@endforeach
</section>
<section class="panel">
<h2>Coûts internes</h2>
<p class="muted">Montants en FCFA, réservés à Admin-CM. Laissez un montant vide s’il n’est pas encore renseigné ; saisissez 0 pour un coût nul. Les prix du site restent sur devis.</p>
<div class="grid">
@foreach(\App\Models\Product::COSTS as $cost=>$label)
<div>
<label for="{{ $cost }}_cost">{{ $label }} (FCFA)<input id="{{ $cost }}_cost" name="{{ $cost }}_cost" type="number" min="0" max="100000000" step="1" inputmode="numeric" placeholder="Non renseigné" value="{{ old($cost.'_cost',$product->{$cost.'_cost'}) }}"></label>
<label for="{{ $cost }}_cost_unit">Unité — {{ mb_strtolower($label) }}<select id="{{ $cost }}_cost_unit" name="{{ $cost }}_cost_unit">
@foreach(\App\Models\Product::COST_UNITS as $unit=>$unitLabel)<option value="{{ $unit }}" @selected(old($cost.'_cost_unit',$product->{$cost.'_cost_unit'})===$unit)>{{ $unitLabel }}</option>@endforeach
</select></label>
</div>
@endforeach
</div>
<p class="muted">Ces coûts servent de référence pour préparer les devis. Le montant du devis dépend des quantités et des prestations retenues.</p>
</section>
<section class="panel">
<h2>Caractéristiques et coloris</h2>
<div class="grid"><div>
<h3>Caractéristiques</h3>
@foreach(['occultant','tamisant','motorise','decoratif'] as $feature)<label class="check"><input type="checkbox" name="features[]" value="{{ $feature }}" @checked(in_array($feature,session()->hasOldInput() ? old('features',[]) : ($product->details['features'] ?? [])))>{{ ucfirst($feature) }}</label>@endforeach
</div><div>
<h3>Coloris</h3>
@foreach(['blanc','ecru','beige','taupe','gris','anthracite','bleu','vert','bordeaux'] as $color)<label class="check"><input type="checkbox" name="colors[]" value="{{ $color }}" @checked(in_array($color,session()->hasOldInput() ? old('colors',[]) : ($product->details['colors'] ?? [])))>{{ ucfirst($color) }}</label>@endforeach
</div></div>
<label>Fiche technique (une ligne « Libellé : valeur » par caractéristique)<textarea name="specs_text" maxlength="3000">{{ old('specs_text',collect($product->details['specs'] ?? [])->map(fn($s)=>$s['label'].' : '.$s['value'])->implode("\n")) }}</textarea></label>
</section>
<section class="panel">@include('staff.image-fields',['owner'=>$product])</section>
<div class="actions"><button>Enregistrer le produit et les coûts</button><a href="{{ route('staff.products.index') }}">Retour au catalogue</a></div>
</form>
@endsection
