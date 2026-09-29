<div data-selection data-material="{{ $selection['material_id'] ?? '' }}">
<div class="grid">
<label>Projet<select required data-choice="category" name="{{ $prefix }}[category]" data-value="{{ $selection['category'] ?? 'rideau' }}"><option value="rideau">Rideaux et / ou voilages</option><option value="store">Store</option></select></label>
<label>Motorisé<select required data-choice="motorized" name="{{ $prefix }}[motorized]" data-value="{{ !empty($selection['motorized']) ? 1 : 0 }}"><option value="0">Non</option><option value="1">Oui</option></select></label>
</div>
@foreach(['curtain_id'=>'Rideaux — type de tissu','sheer_id'=>'Voilages — type de tissu','store_id'=>'Store — matériau'] as $key=>$label)
<div data-fabric-group="{{ $key }}"><h4>{{ $label }}</h4><input type="hidden" data-choice="{{ $key }}" name="{{ $prefix }}[{{ $key }}]" data-value="{{ $selection[$key] ?? '' }}"><div class="grid" data-fabric-list></div></div>
@endforeach
<p class="muted">Cochez un tissu rideau, un voilage, ou les deux. Pour un store, cochez le matériau souhaité.</p>
<label>Finition<select required data-choice="finish_id" name="{{ $prefix }}[finish_id]" data-value="{{ $selection['finish_id'] ?? '' }}"></select></label>
</div>
