<div data-costing data-costing-saved="{{ isset($window['costing']) ? '1' : '0' }}">
<h4>Quantités et prestations par ouverture</h4>
<p class="muted">Pour des fenêtres identiques, ces quantités seront multipliées par le nombre d’ouvertures. Les mètres de tissu et de confection sont saisis manuellement ; la surface des stores est calculée à partir des mesures.</p>
<div class="grid">
<label>Tissu / matériau : quantité hors m²<input data-material-quantity inputmode="decimal" name="windows[{{ $index }}][costing][material_quantity]" value="{{ $window['costing']['material_quantity'] ?? '' }}" placeholder="Métrage de tissu à saisir"></label>
<label>Zone de pose<select name="windows[{{ $index }}][costing][zone]"><option value="abidjan" @selected(($window['costing']['zone'] ?? 'abidjan')==='abidjan')>Abidjan</option><option value="other" @selected(($window['costing']['zone'] ?? '')==='other')>Hors Abidjan — à chiffrer</option></select></label>
</div>
@foreach(\App\Services\QuoteCosting::PARTS as $kind)
<div class="grid" data-cost-part="{{ $kind }}">
<div><h4>{{ \App\Models\QuoteOption::KINDS[$kind] ?? 'Voilage sélectionné — métrage séparé' }}</h4><input type="hidden" data-component="{{ $kind }}" data-value="{{ $window['costing'][$kind]['option_id'] ?? '' }}" name="windows[{{ $index }}][costing][{{ $kind }}][option_id]"><div data-component-list></div></div>
<label>Quantité <span data-unit-label></span><input inputmode="decimal" data-component-quantity name="windows[{{ $index }}][costing][{{ $kind }}][quantity]" value="{{ $window['costing'][$kind]['quantity'] ?? (in_array($kind,['motor','installation']) ? 1 : '') }}"></label>
@if($kind==='installation' && auth()->user()->isAdmin())<label>Prix de pose à définir (FCFA / unité)<input type="number" min="0" max="100000000" step="1" name="windows[{{ $index }}][costing][installation][unit_price]" value="{{ $window['costing']['installation']['unit_price'] ?? '' }}" placeholder="Rideaux / hors Abidjan : à définir"></label>@endif
</div>
@endforeach
<p class="muted">Accroches : nombre de lots complets de 100. Les supports et reflètes proposés dépendent de la finition.</p>
</div>
