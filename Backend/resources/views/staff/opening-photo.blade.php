{{-- Photo facultative de l'ouverture : l'appareil photo s'ouvre sur mobile, un fichier se choisit ailleurs. --}}
@php($photoId=(isset($quote) && $quote->exists && !empty($window['photo_media_id']) && in_array((int)$window['photo_media_id'],\App\Services\QuotePhotos::ids($quote),true)) ? (int)$window['photo_media_id'] : null)
<div class="opening-photo" data-opening-photo>
@if($photoId)
<img class="opening-photo-img" src="{{ route('staff.quotes.photo',[$quote,$photoId]) }}" alt="Photo de {{ $window['label'] ?? 'l’ouverture' }}" loading="lazy" data-photo-current>
<input type="hidden" name="windows[{{ $index }}][photo_media_id]" value="{{ $photoId }}">
<label class="check"><input type="checkbox" name="windows[{{ $index }}][photo_remove]" value="1">Retirer la photo</label>
@endif
<img class="opening-photo-img" alt="Nouvelle photo de l’ouverture" data-photo-preview hidden>
<label class="button quiet camera-button">@include('staff.partials.icon',['n'=>'camera','s'=>18])<span>{{ $photoId ? 'Remplacer la photo' : 'Photo de l’ouverture' }} <small>(facultatif)</small></span><input class="sr-only" type="file" name="windows[{{ $index }}][photo]" accept="image/*" capture="environment" data-photo-input></label>
</div>
