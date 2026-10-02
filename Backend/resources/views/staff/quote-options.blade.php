@extends('staff.layout')
@section('title','Catalogue : tarifs, matériaux & finitions')
@section('content')
<p>Définissez les tissus, fournitures et finitions proposés dans le formulaire de devis. <a href="{{ route('staff.products.index') }}">Retour au catalogue</a>.</p>
<p>Tarifs en FCFA, utilisés pour préparer les devis internes. Un prix vide signifie « à définir », jamais gratuit. Les métrages des rideaux sont saisis manuellement. Les accroches sont facturées par lots de 100. Les devis déjà enregistrés conservent leur chiffrage.</p>
@foreach(collect([new \App\Models\QuoteOption(['active'=>true,'category'=>'rideau','kind'=>'material','motorization'=>'any'])])->concat($options) as $option)
<details class="panel" @if(!$option->exists) open @endif><summary>{{ $option->exists ? $option->label.' · '.$option->category.' · '.($option->active ? 'Actif' : 'Inactif') : 'Ajouter un matériau ou une finition' }}</summary>
<form method="post" action="{{ $option->exists ? route('staff.quote-options.update',$option) : route('staff.quote-options.store') }}">@csrf @if($option->exists) @method('PUT') @endif
<input type="hidden" name="tariff_form" value="1">
<label>Produits associés (même catégorie ; aucun = toute la catégorie)<select name="product_ids[]" multiple>@foreach($products as $product)<option value="{{ $product->id }}" @selected(in_array($product->id,$option->product_ids ?? [],true))>{{ ($product->details['type'] ?? '')==='store' ? 'Store' : 'Rideau' }} — {{ $product->details['name'] ?? $product->reference }}</option>@endforeach</select></label>
<div class="grid">
<label>Libellé<input required name="label" maxlength="150" value="{{ $option->label }}"></label>
<label>Nature<select name="kind">@foreach(\App\Models\QuoteOption::KINDS as $key=>$label)<option value="{{ $key }}" @selected($option->kind===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Catégorie<select name="category">@foreach(['rideau'=>'Rideaux','store'=>'Stores'] as $key=>$label)<option value="{{ $key }}" @selected($option->category===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Groupe du tissu / matériau<select name="fabric_group">@foreach([''=>'Sans objet (fourniture ou finition)','rideau'=>'Tissu rideau','voilage'=>'Voilage','store'=>'Matériau de store'] as $key=>$label)<option value="{{ $key }}" @selected(($option->fabric_group ?? '')===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Motorisation compatible<select name="motorization">@foreach(['any'=>'Oui et non','yes'=>'Motorisé uniquement','no'=>'Manuel uniquement'] as $key=>$label)<option value="{{ $key }}" @selected($option->motorization===$key)>{{ $label }}</option>@endforeach</select></label>
</div>
<div class="grid">
<label>Prix unitaire (FCFA)<input name="unit_price" type="number" min="0" max="100000000" step="1" value="{{ $option->unit_price }}" placeholder="À définir"></label>
<label>Unité<select name="unit">@foreach(\App\Models\QuoteOption::UNITS as $key=>$label)<option value="{{ $key }}" @selected(($option->unit ?? 'm')===$key)>{{ $label }}</option>@endforeach</select></label>
</div>
<p class="muted">Pour un rail, une barre ou un accessoire : finitions compatibles. Ne rien cocher pour toutes les finitions de la catégorie.</p>
<div class="grid">@foreach($options->where('kind','finish')->where('category',$option->category) as $finish)<label class="check"><input type="checkbox" name="finish_codes[]" value="{{ $finish->code }}" @checked(in_array($finish->code,$option->finish_codes ?? [],true))>{{ $finish->label }}</label>@endforeach</div>
<input name="active" type="hidden" value="0"><label class="check"><input name="active" type="checkbox" value="1" @checked($option->active)>Proposer cette option dans les nouveaux choix</label>
<input name="public_choice" type="hidden" value="0"><label class="check"><input name="public_choice" type="checkbox" value="1" @checked($option->public_choice)>Proposer aussi dans la demande de devis publique (tissus, finitions et supports, sans prix)</label>
<button>Enregistrer l’option</button></form></details>
@endforeach
@endsection
