@php($hasMeasures=!empty($window['width']) || !empty($window['height']))
@php($openingSum=$hasMeasures ? ($window['width'] ?? '?').' × '.($window['height'] ?? '?').' cm'.(($window['quantity'] ?? 1)>1 ? ' ×'.$window['quantity'] : '') : 'Mesures à saisir')
<section class="panel opening" data-window>
<details class="opening-card" data-step-part="1" @if($openCard ?? true) open @endif>
<summary><span class="opening-name" data-opening-name>{{ $window['label'] ?? 'Ouverture' }}</span><span class="opening-sum" data-opening-sum>{{ $openingSum }}</span></summary>
<div class="opening-body">
<div class="opening-top"><span class="opening-shape" data-opening-shape aria-hidden="true"><i></i></span><label>Désignation<input class="opening-title" required maxlength="100" name="windows[{{ $index }}][label]" value="{{ $window['label'] ?? '' }}" placeholder="Salon — baie vitrée"></label></div>
<div class="opening-measures">
<label>Largeur<span class="field-unit measure"><input required inputmode="decimal" name="windows[{{ $index }}][width]" value="{{ $window['width'] ?? '' }}"><span class="unit">cm</span></span></label>
<label>Hauteur<span class="field-unit measure"><input required inputmode="decimal" name="windows[{{ $index }}][height]" value="{{ $window['height'] ?? '' }}"><span class="unit">cm</span></span></label>
</div>
<label>Exemplaires identiques<span class="stepper"><input required type="number" min="1" max="20" step="1" inputmode="numeric" name="windows[{{ $index }}][quantity]" value="{{ $window['quantity'] ?? 1 }}"><button type="button" class="minus" data-step-by="-1" aria-label="Un exemplaire de moins">−</button><button type="button" class="plus" data-step-by="1" aria-label="Un exemplaire de plus">+</button></span></label>
<label>Notes de l’ouverture<textarea name="windows[{{ $index }}][notes]" rows="2" maxlength="1000" placeholder="Accès, prise électrique, contrainte de pose…">{{ $window['notes'] ?? '' }}</textarea></label>
@include('staff.opening-photo')
<div class="opening-foot"><button type="button" class="quiet" data-remove-window>Retirer cette ouverture</button></div>
</div>
</details>
{{-- Étape 2 : une ligne du tableau « Par ouverture » dès 768 px, une carte en dessous. --}}
<div class="opening-row" data-step-part="2">
<div class="opening-id">
<p class="opening-row-name"><span class="opening-name" data-opening-name>{{ $window['label'] ?? 'Ouverture' }}</span><span class="opening-sum" data-opening-sum>{{ $openingSum }}</span></p>
<input type="hidden" name="windows[{{ $index }}][inherit_defaults]" value="0">
<label class="check opening-inherit"><input type="checkbox" data-inherit name="windows[{{ $index }}][inherit_defaults]" value="1" @checked($window['inherit_defaults'] ?? !isset($window['product_id']))>Choix communs</label>
<span class="badge opening-custom">Personnalisée</span>
</div>
@include('staff.quote-selection',['prefix'=>'windows['.$index.']','selection'=>$window])
<span class="cell-na cell-na-sheer" aria-hidden="true">Sans objet pour un store</span>
<div class="cell-support" data-cost-part="support"><span class="cell-label" data-part-label>Rail / support</span><input type="hidden" data-component="support" data-value="{{ $window['costing']['support']['option_id'] ?? '' }}" name="windows[{{ $index }}][costing][support][option_id]"><div data-component-list></div></div>
<label class="cell-coef"><span class="cell-label">Coefficient de tissu</span><span class="field-unit"><input inputmode="decimal" name="windows[{{ $index }}][costing][material_coefficient]" value="{{ $window['costing']['material_coefficient'] ?? '2.5' }}"<span class="unit">×</span></span><span class="cell-na" aria-hidden="true">Surface</span></label>
<details class="quote-opening-details"><summary>Quantités, fournitures et prix de cette ouverture</summary>
@include('staff.quote-costing',['rowParts'=>['support']])
</details>
</div>
</section>
