
@extends('staff.layout')
@section('title', $product->exists ? 'Modifier le produit' : 'Nouveau produit')
@section('content')<form class="panel" method="post" action="{{ $product->exists ? route('staff.products.update',$product) : route('staff.products.store') }}">
@csrf
@if($product->exists)
@method('PUT')
@endif<div class="grid">
<label>Référence<input name="reference" required value="{{ old('reference',$product->reference) }}">
</label>
<label>Nom<input name="name" required value="{{ old('name',$product->details['name'] ?? '') }}">
</label>
<label>Type<select name="type">
@foreach(['store'=>'Store','rideau'=>'Rideau'] as $value=>$label)<option value="{{ $value }}" @selected(old('type',$product->details['type'] ?? '')===$value)>{{ $label }}</option>
@endforeach</select>
</label>
<label>Catégorie<input name="category" required value="{{ old('category',$product->details['category'] ?? '') }}">
</label>
<label>Modèle du formulaire de devis<input name="quoteModel" required value="{{ old('quoteModel',$product->details['quoteModel'] ?? '') }}">
</label>
</div>
<label>Description<textarea name="description" required>{{ old('description',$product->details['description'] ?? '') }}</textarea>
</label>
<div class="grid">
<div>
<h3>Caractéristiques</h3>
@foreach(['occultant','tamisant','motorise','decoratif'] as $feature)<label class="check">
<input type="checkbox" name="features[]" value="{{ $feature }}" @checked(in_array($feature,old('features',$product->details['features'] ?? [])))>{{ ucfirst($feature) }}</label>
@endforeach</div>
<div>
<h3>Coloris</h3>
@foreach(['blanc','ecru','beige','taupe','gris','anthracite','bleu','vert','bordeaux'] as $color)<label class="check">
<input type="checkbox" name="colors[]" value="{{ $color }}" @checked(in_array($color,old('colors',$product->details['colors'] ?? [])))>{{ ucfirst($color) }}</label>
@endforeach</div>
<div>
<h3>Visibilité</h3>
@foreach(['published'=>'Publié sur le site','available'=>'Disponible','featured'=>'Mis en avant'] as $key=>$label)<label class="check">
<input type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$key==='published' ? ($product->exists ? $product->published : true) : ($product->details[$key] ?? false)))>{{ $label }}</label>
@endforeach</div>
</div>
<label>Fiche technique (une ligne « Libellé : valeur » par caractéristique)<textarea name="specs_text">{{ old('specs_text',collect($product->details['specs'] ?? [])->map(fn($s)=>$s['label'].' : '.$s['value'])->implode("\n")) }}</textarea>
</label>
<p class="muted">Les tarifs publics restent sur devis. Les montants se renseignent dans les devis commerciaux.</p>
<div class="actions">
<button>Enregistrer</button>
</div>
</form>
@endsection
