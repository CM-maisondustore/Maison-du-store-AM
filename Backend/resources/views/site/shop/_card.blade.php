@php
    $off = ! $p->available;
    $devis = route('quote', ['modele' => $p->model]);
@endphp
<article @class(['card', 'is-off' => $off]) data-shop="{{ $p->shop }}">
    <a href="{{ route('site.product', $p->ref) }}" class="card-img" aria-label="Voir la fiche : {{ $p->name }}">
        @if($p->image)
            <img src="{{ $p->image }}" alt="{{ $p->imageAlt }}" loading="lazy">
        @else
            <x-icon d="M3 5h18v14H3zM7 10a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0M21 16l-5-5-8 8" size="30" stroke="1.2" />
        @endif
        <span aria-hidden="true" class="rod"><span></span></span>
        <span @class(['badge', 'badge-off' => $off])>{{ $off ? 'Bientôt de retour' : 'Sur mesure' }}</span>
    </a>
    <div class="card-b">
        <span class="kicker">{{ $p->type ?: config('site.shops.'.$p->shop.'.name') }}</span>
        <a href="{{ route('site.product', $p->ref) }}" class="card-n">{{ $p->name }}</a>
        <p class="card-d">{{ $p->description }}</p>
        <span class="card-sw">
            @foreach($p->colors as [$n, $h])<span title="{{ $n }}" class="sw" style="background:{{ $h }}"></span>@endforeach
            @if($p->features)<span class="card-m">{{ implode(', ', $p->features) }}</span>@endif
        </span>
        <div class="card-f">
            <span class="card-p"><span class="price">Sur devis</span><span class="small">selon dimensions</span></span>
            @if($off)
                <button type="button" class="add" disabled aria-label="{{ $p->name }} : bientôt de retour"><x-icon d="M6 3h9l4 4v14H6zM14 3v5h5M12 11v6M9 14h6" /></button>
            @else
                <a href="{{ $devis }}" class="add" aria-label="Demander un devis : {{ $p->name }}" title="Demander un devis"><x-icon d="M6 3h9l4 4v14H6zM14 3v5h5M12 11v6M9 14h6" /></a>
            @endif
        </div>
    </div>
</article>
