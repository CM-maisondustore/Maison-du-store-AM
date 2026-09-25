<div class="grid" data-selection>
<label>Catégorie<select required data-choice="category" name="{{ $prefix }}[category]" data-value="{{ $selection['category'] ?? 'store' }}"><option value="store">Stores</option><option value="rideau">Rideaux</option></select></label>
<label>Type / modèle<select required data-choice="product_id" name="{{ $prefix }}[product_id]" data-value="{{ $selection['product_id'] ?? '' }}"></select></label>
<label>Motorisé<select required data-choice="motorized" name="{{ $prefix }}[motorized]" data-value="{{ !empty($selection['motorized']) ? 1 : 0 }}"><option value="0">Non</option><option value="1">Oui</option></select></label>
<label>Matériau<select required data-choice="material_id" name="{{ $prefix }}[material_id]" data-value="{{ $selection['material_id'] ?? '' }}"></select></label>
<label>Finition<select required data-choice="finish_id" name="{{ $prefix }}[finish_id]" data-value="{{ $selection['finish_id'] ?? '' }}"></select></label>
</div>
