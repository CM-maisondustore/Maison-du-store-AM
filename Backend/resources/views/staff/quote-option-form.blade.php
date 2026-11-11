{{-- Même formulaire pour l'ajout et la modification. Plusieurs formulaires partagent la page : seul celui qui a échoué reprend la saisie. --}}
@php
 $use=fn(string $key,$default)=>$failed ? old($key,$default) : $default;
 $formKey=$option->exists ? (string)$option->id : 'new';
 $chosenProducts=$failed ? array_map('intval',old('product_ids',[])) : ($option->product_ids ?? []);
 $chosenFinishes=$failed ? old('finish_codes',[]) : ($option->finish_codes ?? []);
 $rareOpen=$failed && $errors->hasAny(['motorization','product_ids','public_choice','image','image_alt','remove_image']);
 foreach (array_keys($errors->getMessages()) as $errorKey) if (str_starts_with($errorKey,'product_ids.')) $rareOpen=$failed;
@endphp
<form method="post" enctype="multipart/form-data" data-option-form @if($failed) data-failed @endif action="{{ $option->exists ? route('staff.quote-options.update',$option) : route('staff.quote-options.store') }}">@csrf @if($option->exists) @method('PUT') @endif
<input type="hidden" name="tariff_form" value="1">
<input type="hidden" name="open_form" value="{{ $formKey }}">
<div class="grid">
<label>Nature<select name="kind">@foreach(\App\Models\QuoteOption::KINDS as $key=>$label)<option value="{{ $key }}" @selected($use('kind',$option->kind)===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Catégorie<select name="category">@foreach(['rideau'=>'Rideaux','store'=>'Stores'] as $key=>$label)<option value="{{ $key }}" @selected($use('category',$option->category)===$key)>{{ $label }}</option>@endforeach</select></label>
</div>
<label>Libellé<input required name="label" maxlength="150" value="{{ $use('label',$option->label) }}"></label>
<div class="grid">
<label>Prix unitaire<span class="field-unit is-price"><input name="unit_price" type="number" inputmode="numeric" min="0" max="100000000" step="1" value="{{ $use('unit_price',$option->unit_price) }}" placeholder="À définir"><span class="unit">FCFA</span></span><span class="hint">Vide signifie « à définir », jamais gratuit.</span></label>
<label>Unité<select name="unit">@foreach(\App\Models\QuoteOption::UNITS as $key=>$label)<option value="{{ $key }}" @selected($use('unit',$option->unit ?? 'm')===$key)>{{ $label }}</option>@endforeach</select></label>
</div>
<div data-for-kinds="material">
<label>Groupe du tissu / matériau<select name="fabric_group">@foreach([''=>'Sans objet (fourniture ou finition)','rideau'=>'Tissu rideau','voilage'=>'Voilage','store'=>'Matériau de store'] as $key=>$label)<option value="{{ $key }}" @selected(($use('fabric_group',$option->fabric_group) ?? '')===$key)>{{ $label }}</option>@endforeach</select></label>
</div>
<fieldset class="bare mt-12" data-for-kinds="support tape hooks">
<legend>Finitions compatibles</legend>
<p class="hint mt-0">Ne rien cocher pour toutes les finitions de la catégorie.</p>
<div class="scroll-list">@foreach($options->where('kind','finish') as $finish)<label class="check" data-category="{{ $finish->category }}"><input type="checkbox" name="finish_codes[]" value="{{ $finish->code }}" @checked(in_array($finish->code,$chosenFinishes,true))>{{ $finish->label }}</label>@endforeach<p class="hint" data-empty-note hidden>Aucune finition dans cette catégorie.</p></div>
</fieldset>
{{-- Configuration du chiffrage : reprise dans le devis quand l'option est choisie. Vide = réglage habituel du formulaire. --}}
@foreach(\App\Models\QuoteOption::CALC_MODES as $calcKind=>$modes)
@php($calcOff=$use('kind',$option->kind ?? 'material')!==$calcKind)
<fieldset class="bare mt-12" data-for-kinds="{{ $calcKind }}" data-calc-block @disabled($calcOff) @if($calcOff) hidden @endif>
<legend>Chiffrage dans les devis</legend>
<p class="hint mt-0">Proposé automatiquement quand cette option est choisie sur une ouverture ; l’agent peut toujours le modifier. Les devis déjà enregistrés gardent leurs réglages.</p>
<div class="grid">
<label>Calcul<select name="calc_mode"><option value="">Réglage habituel du formulaire</option>@foreach($modes as $key=>$label)<option value="{{ $key }}" @selected($use('calc_mode',$option->calc_mode)===$key)>{{ $label }}</option>@endforeach</select></label>
@if(in_array($calcKind,\App\Models\QuoteOption::CALC_COEFFICIENT_KINDS,true))
<label>Coefficient<span class="field-unit"><input name="calc_coefficient" inputmode="decimal" maxlength="7" value="{{ str_replace('.',',',(string)$use('calc_coefficient',$option->calcCoefficient())) }}" placeholder="{{ str_replace('.',',',\App\Models\QuoteOption::CALC_DEFAULT_COEFFICIENT[$calcKind]) }}"><span class="unit">× largeur</span></span><span class="hint">Vide : {{ str_replace('.',',',\App\Models\QuoteOption::CALC_DEFAULT_COEFFICIENT[$calcKind]) }}.</span></label>
@endif
@if(in_array($calcKind,\App\Models\QuoteOption::CALC_BASIS_KINDS,true))
<label>Base si quantité vide<select name="calc_basis"><option value="">Réglage habituel du formulaire</option>@foreach(\App\Models\QuoteOption::CALC_BASES as $key=>$label)<option value="{{ $key }}" @selected($use('calc_basis',$option->calc_basis)===$key)>{{ $label }}</option>@endforeach</select></label>
@endif
</div>
</fieldset>
@endforeach
<input name="active" type="hidden" value="0"><label class="toggle-row"><span>Proposer cette option dans les nouveaux choix<small>Les devis déjà enregistrés conservent leur chiffrage.</small></span><input class="switch" name="active" type="checkbox" value="1" @checked($use('active',$option->active))></label>
<details class="fold" @if($rareOpen) open @endif>
<summary>Réglages rares<small>Motorisation, produits associés, demande publique, image</small></summary>
<label class="mt-0">Motorisation compatible<select name="motorization">@foreach(['any'=>'Oui et non','yes'=>'Motorisé uniquement','no'=>'Manuel uniquement'] as $key=>$label)<option value="{{ $key }}" @selected($use('motorization',$option->motorization)===$key)>{{ $label }}</option>@endforeach</select></label>
<fieldset class="bare mt-12">
<legend>Produits associés</legend>
<p class="hint mt-0">Même catégorie ; aucun coché = toute la catégorie.</p>
<div class="scroll-list">@foreach($products as $product)<label class="check" data-category="{{ ($product->details['type'] ?? '')==='store' ? 'store' : 'rideau' }}"><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id,$chosenProducts,true))>{{ ($product->details['type'] ?? '')==='store' ? 'Store' : 'Rideau' }} — {{ $product->details['name'] ?? $product->reference }}</label>@endforeach<p class="hint" data-empty-note hidden>Aucun produit dans cette catégorie.</p></div>
</fieldset>
<input name="public_choice" type="hidden" value="0"><label class="toggle-row"><span>Proposer aussi dans la demande de devis publique<small>Tissus, finitions et supports, sans prix.</small></span><input class="switch" name="public_choice" type="checkbox" value="1" @checked($use('public_choice',$option->public_choice))></label>
<div class="mt-12">@include('staff.image-fields',['owner'=>$option,'keepOld'=>$failed])</div>
</details>
@if($option->exists)<div class="actions"><button>Enregistrer l’option</button></div>
@else<div class="action-bar"><button>Ajouter le matériau</button></div>@endif
</form>
