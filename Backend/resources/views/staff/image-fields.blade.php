<h2>Image d’illustration</h2>
@if($owner->media_asset_id)
<img src="{{ route('staff.media.preview',$owner->media_asset_id) }}" alt="{{ $owner->image_alt }}" style="display:block;width:100%;max-width:460px;max-height:300px;object-fit:contain;border-radius:8px">
<label class="check"><input type="checkbox" name="remove_image" value="1" @checked(old('remove_image',false))>Retirer l’image actuelle</label>
@endif
<label>Importer ou remplacer l’image<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
<p class="muted">JPG, PNG ou WebP · 2 Mo maximum · 6000 pixels maximum par côté. Une nouvelle image remplace l’ancienne. Après une erreur de formulaire, sélectionnez à nouveau le fichier.</p>
<label>Texte alternatif de l’image<input name="image_alt" maxlength="255" value="{{ old('image_alt',$owner->image_alt) }}" placeholder="Décrivez brièvement ce que montre l’image"></label>
