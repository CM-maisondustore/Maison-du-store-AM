<div class="res-bar">
    <span role="status" class="muted-14" data-result-label data-total="{{ $total }}">{{ $total }} {{ $total > 1 ? 'modèles' : 'modèle' }}</span>
    @foreach($chips as $c)
        <button type="button" class="chip" aria-label="Retirer le filtre {{ $c['label'] }}" data-chip-group="{{ $c['group'] }}" data-chip-value="{{ $c['label'] }}">{{ $c['label'] }}<x-icon d="M6 6l12 12M18 6 6 18" size="14" stroke="2" /></button>
    @endforeach
    @if($chips || $filter->q !== '')
        <button type="button" class="ibtn-36" aria-label="Effacer tous les filtres" title="Tout effacer" data-filters-reset><x-icon d="M4 4v6h6M20 20v-6h-6M5 15a7 7 0 0 0 12 3M19 9A7 7 0 0 0 7 6" size="16" stroke="1.8" /></button>
    @endif
</div>
@if($items->isNotEmpty())
    <ul class="grid-cards">
        @foreach($items as $p)
            <li>@include('site.shop._card', ['p' => $p])</li>
        @endforeach
    </ul>
@else
    <div class="empty-box dashed"><span class="serif-26">Aucun modèle ne correspond</span><span class="small">Retirez un filtre, ou décrivez-nous votre projet : nous le réalisons sur mesure.</span><div class="row"><button type="button" class="btn btn-dark" data-filters-reset>Effacer les filtres</button><a href="{{ route('quote', $shop ? ['type' => config('site.shops.'.$shop.'.type')] : []) }}" class="btn btn-line">Demander un devis</a></div></div>
@endif
@if($total > $shown)
    @php($left = $total - $shown)
    <div class="more"><button type="button" class="btn btn-line btn-round" data-more="{{ $shown + 24 }}">Afficher {{ min(24, $left) }} modèles de plus ({{ $left }} restants)</button></div>
@endif
