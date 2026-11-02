<div class="line-row dimensions">
<div>
<label>Pièce / ouverture<input name="dimensions[{{ $index }}][label]" required value="{{ $line['label'] ?? '' }}">
</label>
</div>
<div>
<label>Largeur (cm)<input name="dimensions[{{ $index }}][width]" type="number" min="20" max="400" step="0.1" required value="{{ $line['width'] ?? '' }}">
</label>
</div>
<div>
<label>Hauteur (cm)<input name="dimensions[{{ $index }}][height]" type="number" min="20" max="400" step="0.1" required value="{{ $line['height'] ?? '' }}">
</label>
</div>
<div>
<label>Quantité<input name="dimensions[{{ $index }}][quantity]" type="number" min="1" max="20" required value="{{ $line['quantity'] ?? 1 }}">
</label>
</div>
<button type="button" data-remove aria-label="Supprimer la mesure">×</button>
</div>
