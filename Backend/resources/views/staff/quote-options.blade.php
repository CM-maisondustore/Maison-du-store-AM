@extends('staff.layout')
@section('title','Matériaux & finitions des devis')
@section('content')
<p>Définissez les choix proposés dans le formulaire de devis. Les types de produits se gèrent dans le <a href="{{ route('staff.products.index') }}">catalogue</a>.</p>
@foreach(collect([new \App\Models\QuoteOption(['active'=>true,'category'=>'rideau','kind'=>'material','motorization'=>'any'])])->concat($options) as $option)
<details class="panel" @if(!$option->exists) open @endif><summary>{{ $option->exists ? $option->label.' · '.$option->category.' · '.($option->active ? 'Actif' : 'Inactif') : 'Ajouter un matériau ou une finition' }}</summary>
<form method="post" action="{{ $option->exists ? route('staff.quote-options.update',$option) : route('staff.quote-options.store') }}">@csrf @if($option->exists) @method('PUT') @endif
<div class="grid">
<label>Libellé<input required name="label" maxlength="150" value="{{ $option->label }}"></label>
<label>Nature<select name="kind">@foreach(['material'=>'Matériau','finish'=>'Finition'] as $key=>$label)<option value="{{ $key }}" @selected($option->kind===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Catégorie<select name="category">@foreach(['rideau'=>'Rideaux','store'=>'Stores'] as $key=>$label)<option value="{{ $key }}" @selected($option->category===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Motorisation compatible<select name="motorization">@foreach(['any'=>'Oui et non','yes'=>'Motorisé uniquement','no'=>'Manuel uniquement'] as $key=>$label)<option value="{{ $key }}" @selected($option->motorization===$key)>{{ $label }}</option>@endforeach</select></label>
</div>
<p class="muted">Types autorisés : ne rien cocher pour proposer cette option à tous les types de la catégorie.</p>
<div class="grid">@foreach($products as $p)<label class="check"><input type="checkbox" name="product_ids[]" value="{{ $p->id }}" @checked(in_array($p->id,$option->product_ids ?? [],true))>{{ $p->details['name'] }} · {{ $p->reference }}</label>@endforeach</div>
<input name="active" type="hidden" value="0"><label class="check"><input name="active" type="checkbox" value="1" @checked($option->active)>Proposer cette option dans les nouveaux choix</label>
<button>Enregistrer l’option</button></form></details>
@endforeach
@endsection
