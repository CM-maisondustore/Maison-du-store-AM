<div data-selection data-material="{{ $selection['material_id'] ?? '' }}">
@if(!empty($selection['project_type']))<p class="notice">Relevé de l’agent : {{ \App\Services\AgentQuoteData::TYPES[$selection['project_type']] ?? $selection['product_name'] ?? '' }}. Complétez les tissus et fournitures pour ce projet.</p>@endif
<div class="grid choice-row">
<label class="cell-category"><span class="cell-label">Projet</span><select required data-choice="category" name="{{ $prefix }}[category]" data-value="{{ $selection['category'] ?? 'rideau' }}"><option value="rideau">Rideaux et / ou voilages</option><option value="store">Store</option></select></label>
<label class="cell-motorized"><span class="cell-label">Motorisé</span><select required data-choice="motorized" name="{{ $prefix }}[motorized]" data-value="{{ !empty($selection['motorized']) ? 1 : 0 }}"><option value="0">Non</option><option value="1">Oui</option></select></label>
@foreach(['curtain_id'=>'Rideaux — type de tissu','sheer_id'=>'Voilages — type de tissu','store_id'=>'Store — matériau'] as $key=>$label)
<label data-fabric-group="{{ $key }}"><span class="cell-label">{{ $label }}</span><input type="hidden" data-choice="{{ $key }}" name="{{ $prefix }}[{{ $key }}]" data-value="{{ $selection[$key] ?? '' }}"><span class="fabric-select" data-fabric-list></span></label>
@endforeach
<label class="cell-finish"><span class="cell-label">Finition</span><select required data-choice="finish_id" name="{{ $prefix }}[finish_id]" data-value="{{ $selection['finish_id'] ?? '' }}"></select></label>
</div>
<p class="hint">Choisissez un tissu rideau, un voilage ou les deux. Pour un store, choisissez son matériau.</p>
</div>
