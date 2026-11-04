{{-- Quantités, fournitures et prix d'une ouverture : une ligne par poste, Oui / Non — quantité — coefficient — prix.
     Les pastilles Oui / Non pilotent le menu construit par quote-editor.js, qui reste le champ enregistré.
     Confection et pose (data-step3) se règlent à l'étape 3 quand le formulaire de calcul est affiché. --}}
@php($isOffice=auth()->user()->isAdmin())
@php($coefPresets=['secondary_material'=>['2','2.5','3'],'support'=>['1','1.5','2'],'extra_support'=>['1','1.5','2'],'tape'=>['1','1.5','2'],'extra_tape'=>['1','1.5','2']])
<div data-costing data-costing-saved="{{ isset($window['costing']) ? '1' : '0' }}">
<p class="hint mt-0">Quantités multipliées par le nombre d’exemplaires. Quantité vide : largeur × coefficient (tissu et voilage 2,5 ; rails et ruflettes 1, en cumulant tissu et voilage). Au-delà de 2,80 m de hauteur pour des rideaux, saisissez les métrages. La surface des stores vient des mesures.</p>
<div class="cost-lines">
<div class="cost-line">
<span class="choice-name">Tissu / matériau</span><div class="cost-body">
<label class="cost-field">Quantité<input data-material-quantity inputmode="decimal" name="windows[{{ $index }}][costing][material_quantity]" value="{{ $window['costing']['material_quantity'] ?? '' }}" placeholder="Métrage à saisir"></label>
<label class="cost-field cost-wide">Calcul<select data-material-mode name="windows[{{ $index }}][costing][material_mode]">@foreach(['manual'=>'Métrage manuel / surface automatique','coefficient'=>'Largeur × coefficient si métrage vide','manual_override'=>'Quantité manuelle, même en m²'] as $mode=>$label)<option value="{{ $mode }}" @selected(($window['costing']['material_mode'] ?? 'coefficient')===$mode)>{{ $label }}</option>@endforeach</select></label>
@if($isOffice)<label class="cost-field cost-price">Prix<span class="field-unit is-price"><input name="windows[{{ $index }}][costing][material_unit_price]" type="number" min="0" max="100000000" step="1" value="{{ $window['costing']['material_unit_price'] ?? '' }}" placeholder="Catalogue"><span class="unit">FCFA</span></span></label>@endif
</div></div>
@if($isOffice)
<div class="cost-line">
<span class="choice-name">Finition</span><div class="cost-body">
<label class="cost-field">Quantité<input name="windows[{{ $index }}][costing][finish_quantity]" inputmode="decimal" value="{{ $window['costing']['finish_quantity'] ?? '' }}" placeholder="Largeur ou 1"></label>
<label class="cost-field cost-price">Prix<span class="field-unit is-price"><input name="windows[{{ $index }}][costing][finish_unit_price]" type="number" min="0" max="100000000" step="1" value="{{ $window['costing']['finish_unit_price'] ?? '' }}" placeholder="Catalogue"><span class="unit">FCFA</span></span></label>
<small class="hint cost-note">Si la finition est facturée.</small>
</div></div>
@endif
<div class="cost-line" data-step3>
<span class="choice-name">Zone de pose</span><div class="cost-body">
<label class="cost-field cost-wide"><span class="sr-only">Zone de pose</span><select data-zone name="windows[{{ $index }}][costing][zone]"><option value="abidjan" @selected(($window['zone'] ?? $window['costing']['zone'] ?? 'abidjan')==='abidjan')>Abidjan</option><option value="other" @selected(($window['zone'] ?? $window['costing']['zone'] ?? '')==='other')>Hors Abidjan — à chiffrer</option></select></label>
</div></div>
@foreach(\App\Services\QuoteCosting::PARTS as $kind)
@php($partName=['extra_support'=>'Rail supplémentaire','extra_tape'=>'Ruflette supplémentaire','tape'=>'Ruflette','secondary_material'=>'Voilage — métrage séparé'][$kind] ?? \App\Models\QuoteOption::KINDS[$kind])
<div class="cost-line" data-cost-part="{{ $kind }}" @if(in_array($kind,['sewing','installation'])) data-step3 @endif>
<span class="choice-name">{{ $partName }}</span><div class="cost-body">
@if(in_array($kind,$rowParts ?? []))<small class="hint cost-choice">Choisi dans la ligne de l’ouverture</small>
@else
<div class="cost-choice"><input type="hidden" data-component="{{ $kind }}" data-value="{{ $window['costing'][$kind]['option_id'] ?? '' }}" name="windows[{{ $index }}][costing][{{ $kind }}][option_id]"><div data-component-list data-name="{{ $partName }}"></div></div>
@endif
<label class="cost-field">Quantité <span data-unit-label></span><input inputmode="decimal" data-component-quantity name="windows[{{ $index }}][costing][{{ $kind }}][quantity]" value="{{ $window['costing'][$kind]['quantity'] ?? (in_array($kind,['motor','installation']) ? 1 : '') }}" placeholder="Auto"></label>
@if(in_array($kind,['sewing','secondary_material','support','extra_support','tape','extra_tape']))
<label class="cost-field cost-wide">Calcul<select data-part-mode name="windows[{{ $index }}][costing][{{ $kind }}][mode]">@foreach(($kind==='sewing' ? ($isOffice ? ['per_meter'=>'Par mètre','fixed'=>'Au forfait','manual'=>'Montant manuel'] : ['per_meter'=>'Par mètre']) : ['manual'=>'Quantité manuelle / quantité par défaut','coefficient'=>'Largeur × coefficient si quantité vide']) as $mode=>$label)<option value="{{ $mode }}" @selected(($window['costing'][$kind]['mode'] ?? (in_array($kind,['secondary_material','support','extra_support','tape','extra_tape'])?'coefficient':'per_meter'))===$mode)>{{ $label }}</option>@endforeach</select></label>
@if($kind!=='sewing')
<div class="cost-field cost-coef" role="group" aria-label="{{ $partName }} : coefficient"><span>Coefficient</span>
<span class="chip-set">@foreach($coefPresets[$kind] as $preset)<button type="button" class="chip-option" data-set-coef="{{ $preset }}" aria-pressed="false">{{ str_replace('.',',',$preset) }}</button>@endforeach
<input name="windows[{{ $index }}][costing][{{ $kind }}][coefficient]" inputmode="decimal" aria-label="{{ $partName }} : coefficient saisi" value="{{ $window['costing'][$kind]['coefficient'] ?? (in_array($kind,['support','extra_support','tape','extra_tape'])?'1':'2.5') }}"></span></div>
@endif
@if(in_array($kind,['support','extra_support','tape','extra_tape']))<label class="cost-field cost-wide">Base si quantité vide<select name="windows[{{ $index }}][costing][{{ $kind }}][basis]"><option value="primary" @selected(($window['costing'][$kind]['basis'] ?? '')==='primary')>Tissu principal</option><option value="all_fabrics" @selected(($window['costing'][$kind]['basis'] ?? 'all_fabrics')==='all_fabrics')>Tissu principal + voilage</option></select></label>@endif
@if($kind==='sewing')<input type="hidden" data-sewing-basis name="windows[{{ $index }}][costing][sewing][basis]" value="{{ $window['costing']['sewing']['basis'] ?? 'primary' }}">@endif
@endif
@if($isOffice)<label class="cost-field cost-price">Prix<span class="field-unit is-price"><input type="number" min="0" max="100000000" step="1" data-part-price name="windows[{{ $index }}][costing][{{ $kind }}][unit_price]" value="{{ $window['costing'][$kind]['unit_price'] ?? '' }}" placeholder="{{ $kind==='installation' ? 'À définir' : 'Catalogue' }}"><span class="unit">FCFA</span></span></label>@endif
</div></div>
@endforeach
</div>
<p class="hint">Accroches : nombre de lots complets de 100. Les rails et ruflettes proposés dépendent de la finition.</p>
</div>
