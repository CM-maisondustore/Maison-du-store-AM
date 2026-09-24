<div class="line-row">
<div>
<label>Description<input name="lines[{{ $index }}][description]" required value="{{ $line['description'] ?? '' }}">
</label>
</div>
<div>
<label>Quantité<input name="lines[{{ $index }}][quantity]" type="number" min="1" max="1000" required value="{{ $line['quantity'] ?? 1 }}">
</label>
</div>
<div>
<label>Prix unitaire (FCFA)<input name="lines[{{ $index }}][unit_price]" type="number" min="0" max="100000000" required value="{{ $line['unit_price'] ?? 0 }}">
</label>
</div>
<button type="button" data-remove aria-label="Supprimer la ligne">×</button>
</div>
