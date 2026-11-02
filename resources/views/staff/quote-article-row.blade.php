<div class="article-row">
<label class="wide">Désignation<input data-article-name required maxlength="200" name="articles[{{ $index }}][name]" value="{{ $article['name'] ?? '' }}" list="article-catalogue"></label>
<label>Quantité<span class="stepper"><input required type="number" min="1" max="1000" step="1" inputmode="numeric" name="articles[{{ $index }}][quantity]" value="{{ $article['quantity'] ?? 1 }}"><button type="button" class="minus" data-step-by="-1" aria-label="Un article de moins">−</button><button type="button" class="plus" data-step-by="1" aria-label="Un article de plus">+</button></span></label>
<label>{{ $variantLabel }}<input maxlength="150" name="articles[{{ $index }}][variant]" value="{{ $article['variant'] ?? '' }}"></label>
@if(auth()->user()->isAdmin())<label class="price-cell">Prix unitaire<span class="field-unit is-price"><input type="number" min="0" max="100000000" step="1" inputmode="numeric" name="articles[{{ $index }}][unit_price]" value="{{ $article['unit_price'] ?? '' }}" placeholder="À définir"><span class="unit">FCFA</span></span></label>@endif
<button type="button" class="quiet icon-btn remove" data-remove-article aria-label="Retirer cet article">@include('staff.partials.icon',['n'=>'close','s'=>18])</button>
</div>
