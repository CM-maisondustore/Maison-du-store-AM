<section class="panel" id="pdf-letterhead">
<h2>Fonds de page / filigranes</h2>
<input type="hidden" name="letterhead_enabled" value="0">
<label class="toggle-row"><span>Activer les fonds de page pour ce modèle PDF</span><input class="switch" type="checkbox" name="letterhead_enabled" value="1" @checked(old('letterhead_enabled',$options['letterhead_enabled'] ?? false))></label>
<p>La page unique ou la dernière page utilise l’image standard avec en-tête et pied de page. Les pages précédentes utilisent l’image avec en-tête seul. Les images sont conservées avec les versions du modèle.</p>
<p class="muted">Importez deux images de page entière au format A4 portrait, idéalement 1 240 × 1 754 px. PNG ou JPEG, 2 Mo maximum par image, au plus 2 500 × 3 600 px. L’image est centrée et ajustée à la page sans déformation.</p>
<div class="grid">
@foreach(['letterhead_standard'=>'Page standard — en-tête et pied de page','letterhead_continuation'=>'Pages précédentes — en-tête seul'] as $key=>$label)
<div data-letterhead-image="{{ $key }}">
<label>{{ $label }}<input type="file" name="{{ $key }}" accept="image/png,image/jpeg"></label>
@if(!empty($options[$key]))<label class="check"><input type="checkbox" name="remove_{{ $key }}" value="1" @checked(old('remove_'.$key))>Retirer cette image</label>@endif
<div class="letterhead-page" data-letterhead-page>
<img class="letterhead-image" data-letterhead-preview @if(!empty($options[$key])) src="{{ $options[$key] }}" @else hidden @endif alt="Aperçu du fond de page">
<div class="letterhead-text" data-letterhead-text><strong>Zone du texte</strong><p>Devis / Facture<br>Informations client</p><p>Désignation · Quantité · Montant</p><p>Prestations, total et conditions…</p></div>
</div>
</div>
@endforeach
</div>
<h3>Position du texte</h3>
<p>Les marges réservent l’espace de l’en-tête et du pied de page sur toutes les pages. Augmentez la marge haute pour descendre le texte, ou la marge basse pour le faire terminer plus haut.</p>
<div class="grid">
@foreach(['top'=>'Marge haute','bottom'=>'Marge basse','left'=>'Marge gauche','right'=>'Marge droite'] as $side=>$label)
<label>{{ $label }}<span class="field-unit"><input type="number" name="margin_{{ $side }}_mm" value="{{ old('margin_'.$side.'_mm',$options['margin_'.$side.'_mm'] ?? \App\Services\PdfLetterhead::MARGINS[$side]) }}" min="5" max="{{ in_array($side,['top','bottom']) ? 100 : 50 }}" step="0.5" required><span class="unit">mm</span></span></label>
@endforeach
<label>Opacité du fond<span class="field-unit"><input type="number" name="letterhead_opacity" value="{{ old('letterhead_opacity',$options['letterhead_opacity'] ?? 100) }}" min="10" max="100" step="1" required><span class="unit">%</span></span></label>
</div>
<p class="muted">Le cadre vert est un repère, il n’apparaît pas dans le PDF. Les fonds et ces marges s’appliquent lorsque l’option est activée. Vérifiez le résultat avec l’aperçu A4, notamment sur plusieurs pages, avant de publier. Les PDF déjà archivés restent inchangés.</p>
</section>
