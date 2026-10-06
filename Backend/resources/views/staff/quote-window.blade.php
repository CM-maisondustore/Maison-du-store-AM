<section class="panel" data-window>
<div class="actions"><h3>Ouverture</h3><button type="button" class="secondary" data-remove-window>Retirer cette ouverture</button></div>
<div class="grid">
<label>Pièce / repère<input required maxlength="100" name="windows[{{ $index }}][label]" value="{{ $window['label'] ?? '' }}" placeholder="Salon — ouverture 1"></label>
<label>Largeur (cm)<input required inputmode="decimal" name="windows[{{ $index }}][width]" value="{{ $window['width'] ?? '' }}"></label>
<label>Hauteur (cm)<input required inputmode="decimal" name="windows[{{ $index }}][height]" value="{{ $window['height'] ?? '' }}"></label>
<label>Quantité identique<input required type="number" min="1" max="20" step="1" name="windows[{{ $index }}][quantity]" value="{{ $window['quantity'] ?? 1 }}"></label>
</div>
<input type="hidden" name="windows[{{ $index }}][inherit_defaults]" value="0">
<label class="check"><input type="checkbox" data-inherit name="windows[{{ $index }}][inherit_defaults]" value="1" @checked($window['inherit_defaults'] ?? !isset($window['product_id']))>Utiliser les choix communs (décochez pour personnaliser cette ouverture)</label>
<details class="quote-opening-details"><summary>Choix techniques, quantités et prestations</summary>
@include('staff.quote-selection',['prefix'=>'windows['.$index.']','selection'=>$window])
@include('staff.quote-costing')
<label>Précisions pour cette ouverture<textarea name="windows[{{ $index }}][notes]" maxlength="1000">{{ $window['notes'] ?? '' }}</textarea></label>
</details>
</section>
