@extends('site.layout', ['active' => 'shop', 'pill' => true])
@php
    $order = config('site.shops_order');
    $shops = config('site.shops');
    $isAll = ! $shop;
    $end = $isAll ? ['Un projet sur mesure ?', 'Stores, rideaux et voilages : un conseiller vient prendre les mesures et compose avec vous une proposition, confectionnée et posée.', 'Demander un devis'] : $m['end'];
    $devisHref = route('quote', $shop ? ['type' => $m['type']] : []);
    $rdvHref = route('home').'#rendez-vous';
@endphp
@section('title', ($m['name'] ?? 'MDS-Shop').' — Maison du Store AM à Abidjan')
@section('description', $m['hero'] ?? 'Stores, rideaux et voilages sur mesure : le catalogue de Maison du Store, confectionné, livré et posé à Abidjan.')
@section('content')
<main id="contenu" @if($shop) data-shop="{{ $shop }}" @endif>
    <section @class(['shop-hero', 'tinted' => $shop])>
        <div class="wrap shop-hero-in">
            <div class="row-c">
                <a href="{{ $isAll ? route('home') : route('site.shop') }}" class="ibtn ibtn-line" aria-label="{{ $isAll ? 'Retour à l’accueil' : 'Retour à MDS-Shop' }}" title="Retour"><x-icon d="M15 6l-6 6 6 6" size="18" stroke="1.8" /></a>
                <nav aria-label="Fil d’Ariane" class="crumb"><a href="{{ route('home') }}">Accueil</a><span aria-hidden="true"> / </span>@if($shop)<a href="{{ route('site.shop') }}">MDS-Shop</a><span aria-hidden="true"> / </span><span aria-current="page">{{ $m['name'] }}</span>@else<span aria-current="page">MDS-Shop</span>@endif</nav>
            </div>
            <div class="shop-hero-g">
                <div class="stack-14">
                    @if($shop)<span class="kicker">Boutique {{ $m['boutique'] }}</span>@endif
                    <h1 class="h1-64">{{ $m['name'] ?? 'MDS-Shop' }}</h1>
                    <p class="lead">{{ $m['hero'] ?? 'La boutique Stores & Rideaux est ouverte : rideaux, voilages et stores sur mesure. Électroménager et Meubles arrivent bientôt.' }}</p>
                </div>
                @if($shop)
                    <div class="row hero-cta"><a href="{{ $devisHref }}" class="btn btn-gold">{{ $end[2] }}</a><a href="{{ $rdvHref }}" class="btn btn-line">Prendre rendez-vous</a></div>
                @endif
            </div>
            @if($shop)
                <div class="type-row" aria-label="Types" role="group" data-type-chips>@include('site.shop._type_chips')</div>
            @else
                <ul class="tiles">
                    @foreach($order as $k)
                        <li data-shop="{{ $k }}">
                            @if($shops[$k]['open'])
                                <a href="{{ route('site.shop.show', $k) }}" class="tile">
                                    <span aria-hidden="true" class="rod rod-t"><span></span></span>
                                    <span class="kicker kicker-s">Boutique {{ $shops[$k]['boutique'] }}</span>
                                    <span class="serif-30">{{ $shops[$k]['name'] }}</span>
                                    <span class="small">{{ $shops[$k]['line'] }}</span>
                                    <span class="tile-f">{{ $counts[$k] ?? 0 }} modèles<x-icon d="M5 12h14M13 6l6 6-6 6" size="18" stroke="1.8" /></span>
                                </a>
                            @else
                                <span class="tile" aria-disabled="true">
                                    <span aria-hidden="true" class="rod rod-t"><span></span></span>
                                    <span class="kicker kicker-s">Boutique {{ $shops[$k]['boutique'] }}</span>
                                    <span class="serif-30">{{ $shops[$k]['name'] }}</span>
                                    <span class="small">{{ $shops[$k]['soonText'] }}</span>
                                    <span class="tile-f"><span class="soon soon-13">Bientôt disponible</span></span>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section aria-label="Catalogue" class="wrap cat-wrap">
        <form method="get" action="{{ url()->current() }}" data-shop-form>
            <div class="sheet-scrim" data-sheet-scrim></div>
            <div class="f-toggle">
                <button type="button" class="btn btn-line btn-round grow" data-sheet-open><x-icon d="M4 6h16M7 12h10M10 18h4" size="18" /><span data-filters-label>Filtres</span></button>
            </div>
            <div class="toolbar" role="toolbar" aria-label="Filtres du catalogue">
                <label class="f-search"><span class="sr">Rechercher</span><x-icon d="M4 11a7 7 0 1 0 14 0a7 7 0 1 0-14 0M20 20l-3.5-3.5" size="18" stroke="1.6" /><input type="search" name="q" value="{{ $filter->q }}" placeholder="Rechercher dans {{ $m['name'] ?? 'MDS-Shop' }}…" data-shop-q></label>
                <div class="f-groups" data-sheet>
                    <div class="sheet-head"><span class="serif-26">Filtres</span><button type="button" class="ibtn ibtn-grey" aria-label="Fermer les filtres" data-sheet-close><x-icon d="M6 6l12 12M18 6 6 18" size="18" stroke="1.8" /></button></div>
                    <div class="sheet-body" data-filter-groups>@include('site.shop._filters')</div>
                    <div class="sheet-foot"><button type="button" class="btn btn-line btn-round" data-filters-reset aria-label="Effacer tous les filtres">Effacer</button><button type="button" class="btn btn-dark btn-round grow" data-sheet-close data-see-label>Voir les {{ $total }} modèles</button></div>
                </div>
                <label class="f-sort"><span class="f-sort-t">Trier</span><select name="sort" data-shop-sort>@foreach(\App\Support\Site\Catalog::SORTS as $k => $label)<option value="{{ $k }}" @selected($filter->sort === $k)>{{ $label }}</option>@endforeach</select></label>
                <noscript><button class="btn btn-dark">Appliquer</button></noscript>
            </div>
        </form>
        <div data-results>@include('site.shop._results')</div>
    </section>

    <section class="wrap end-wrap">
        <div class="end-card">
            <h2 class="serif-34">{{ $end[0] }}</h2>
            <p>{{ $end[1] }}</p>
            <div class="row"><a href="{{ $devisHref }}" class="btn btn-gold">{{ $end[2] }}</a><a href="{{ $rdvHref }}" class="btn btn-line">Prendre rendez-vous</a></div>
        </div>
        <nav aria-label="Autres boutiques" class="others">
            <span class="kicker kicker-grey">Autres boutiques</span>
            @foreach($order as $k)
                @continue($k === $shop)
                @if($shops[$k]['open'])
                    <a href="{{ route('site.shop.show', $k) }}" class="other" data-shop="{{ $k }}"><span class="grow stack-0"><span class="serif-22">{{ $shops[$k]['name'] }}</span><span class="small">{{ $shops[$k]['line'] }}</span></span><x-icon d="M5 12h14M13 6l6 6-6 6" size="18" /></a>
                @else
                    <span class="other" data-shop="{{ $k }}" aria-disabled="true"><span class="grow stack-0"><span class="serif-22">{{ $shops[$k]['name'] }}</span><span class="small">Bientôt disponible</span></span></span>
                @endif
            @endforeach
        </nav>
    </section>
</main>
@endsection
