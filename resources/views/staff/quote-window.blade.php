@php($hasMeasures=!empty($window['width']) || !empty($window['height']))
<section class="panel opening" data-window>
<details class="opening-card" @if($openCard ?? true) open @endif>
<summary><span class="opening-name" data-opening-name>{{ $window['label'] ?? 'Ouverture' }}</span><span class="opening-sum" data-opening-sum>{{ $hasMeasures ? ($window['width'] ?? '?').' × '.($window['height'] ?? '?').' cm' : 'Mesures à saisir' }}</span></summary>
<div class="opening-body">
<label>Pièce / repère<input required maxlength="100" name="windows[{{ $index }}][label]" value="{{ $window['label'] ?? '' }}" placeholder="Salon — ouverture 1"></label>
<div class="opening-measures">
<label>Largeur<span class="field-unit measure"><input required inputmode="decimal" name="windows[{{ $index }}][width]" value="{{ $window['width'] ?? '' }}"><span class="unit">cm</span></span></label>
<label>Hauteur<span class="field-unit measure"><input required inputmode="decimal" name="windows[{{ $index }}][height]" value="{{ $window['height'] ?? '' }}"><span class="unit">cm</span></span></label>
</div>
<label>Quantité identique<span class="stepper"><input required type="number" min="1" max="20" step="1" inputmode="numeric" name="windows[{{ $index }}][quantity]" value="{{ $window['quantity'] ?? 1 }}"><button type="button" class="minus" data-step-by="-1" aria-label="Une ouverture identique de moins">−</button><button type="button" class="plus" data-step-by="1" aria-label="Une ouverture identique de plus">+</button></span></label>
<input type="hidden" name="windows[{{ $index }}][inherit_defaults]" value="0">
<label class="check"><input type="checkbox" data-inherit name="windows[{{ $index }}][inherit_defaults]" value="1" @checked($window['inherit_defaults'] ?? !isset($window['product_id']))>Utiliser les choix communs (décochez pour personnaliser cette ouverture)</label>
<details class="quote-opening-details"><summary>Choix techniques, quantités et prestations</summary>
@include('staff.quote-selection',['prefix'=>'windows['.$index.']','selection'=>$window])
@include('staff.quote-costing')
<label>Précisions pour cette ouverture<textarea name="windows[{{ $index }}][notes]" maxlength="1000">{{ $window['notes'] ?? '' }}</textarea></label>
</details>
<div class="opening-foot"><button type="button" class="quiet" data-remove-window>Retirer cette ouverture</button></div>
</div>
</details>
</section>
