@php($hasImage=(bool)$owner->media_asset_id)
@php($keepOld=$keepOld ?? true)
<div class="image-block" data-image-block>
@unless($bare ?? false)<h2>Image d’illustration</h2>@endunless
<div class="drop @if($hasImage) has-image @endif">
<img data-image-preview loading="lazy" @if($hasImage) src="{{ route('staff.media.preview',$owner->media_asset_id) }}" @else hidden @endif alt="">
<span class="drop-empty" aria-hidden="true">@include('staff.partials.icon',['n'=>'upload','s'=>22])<span><strong>Choisir un fichier</strong> ou le déposer ici</span><small>JPG, PNG ou WebP, 2 Mo et 6000 pixels maximum par côté</small></span>
<span class="drop-change" aria-hidden="true">Remplacer l’image</span>
<label class="drop-input"><span class="sr-only">Importer ou remplacer l’image</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-image-input></label>
</div>
<p class="hint" data-image-name aria-live="polite"></p>
@if($errors->any() && $keepOld)<p class="hint">Après une erreur de formulaire, sélectionnez à nouveau le fichier : le navigateur ne le conserve pas.</p>@endif
@if($hasImage)<label class="check"><input type="checkbox" name="remove_image" value="1" @checked($keepOld && old('remove_image',false))>Retirer l’image actuelle</label>@endif
<div class="alt-block">
<label>Texte alternatif de l’image<span class="hint">Décrit la photo pour les lecteurs d’écran et le référencement.</span><input name="image_alt" maxlength="255" value="{{ $keepOld ? old('image_alt',$owner->image_alt) : $owner->image_alt }}" placeholder="Décrivez brièvement ce que montre l’image"></label>
<p class="alt-warning" data-alt-warning hidden><span aria-hidden="true">! </span>Texte alternatif manquant</p>
</div>
</div>
