@extends('site.layout', ['active' => 'shop', 'pill' => true, 'shop' => $p->shop])
@php
    $off = ! $p->available;
    $devis = route('quote', ['modele' => $p->model]);
    $specs = $p->specs;
    if ($p->type) array_unshift($specs, ['Type', $p->type]);
    if ($p->features) $specs[] = ['Fonctions', implode(', ', $p->features)];
    if ($p->colors) $specs[] = ['Coloris', implode(', ', array_column($p->colors, 0))];
    $panels = [
        ['Caractéristiques', $specs ?: [['Fabrication', 'Sur mesure, au centimètre']]],
        ['Confection, livraison et pose', [
            ['Confection', 'Atelier d’Abidjan, finitions à la main'],
            ['Mesures', 'Relevées chez vous par un conseiller'],
            ['Livraison', 'Partout à Abidjan, date fixée avec vous'],
            ['Pose', 'Incluse à Abidjan, par nos équipes'],
        ]],
        ['Garantie et service après-vente', [
            ['SAV', 'Un seul interlocuteur, au '.config('site.contact.phones')[0]['label']],
            ['Réglages', 'Par l’équipe qui a posé vos stores et rideaux'],
        ]],
    ];
    $assure = [
        ['M3 11l9-8 9 8M5 10v10h14V10', 'Prise de mesures à domicile'],
        ['M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z', 'Pose incluse par nos équipes'],
        ['M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z', 'Garantie et SAV par la maison'],
    ];
@endphp
@section('title', $p->name.' — '.$m['name'].' | Maison du Store AM')
@section('description', \Illuminate\Support\Str::limit(trim($p->description.' Sur mesure, pose incluse à Abidjan.'), 155))
@push('head')
<script type="application/ld+json">{!! json_encode(array_filter(['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p->name, 'sku' => $p->ref, 'description' => $p->description, 'image' => $p->image ? url($p->image) : null, 'category' => $m['name']]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
<main id="contenu" class="wrap page-pad" data-shop="{{ $p->shop }}" data-product data-name="{{ $p->name }}">
    <div class="pr-top">
        <div class="row-c minw0">
            <a href="{{ route('site.shop.show', $p->shop) }}" class="ibtn ibtn-line" aria-label="Retour à la boutique {{ $m['name'] }}" title="Retour"><x-icon d="M15 6l-6 6 6 6" size="18" stroke="1.8" /></a>
            <nav aria-label="Fil d’Ariane" class="crumb ell"><a href="{{ route('site.shop') }}">MDS-Shop</a><span aria-hidden="true"> / </span><a href="{{ route('site.shop.show', $p->shop) }}">{{ $m['name'] }}</a><span class="lg-only"><span aria-hidden="true"> / </span><span aria-current="page">{{ $p->name }}</span></span></nav>
        </div>
        <div class="row-8">
            <button type="button" class="ibtn ibtn-line" aria-label="Partager ce modèle" title="Partager" data-share data-url="{{ route('site.product', $p->ref) }}"><x-icon d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M16 6l-4-4-4 4M12 2v13" size="18" /></button>
        </div>
    </div>

    <div class="pr-grid">
        <section aria-label="Galerie" class="stack-12 minw0">
            <div class="pr-img">
                @if($p->image)
                    <img src="{{ $p->image }}" alt="{{ $p->imageAlt }}" fetchpriority="high">
                @else
                    <x-icon d="M3 5h18v14H3zM7 10a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0M21 16l-5-5-8 8" size="44" stroke="1.1" />
                    <span class="mono-12">Photo à venir</span>
                @endif
                <span aria-hidden="true" class="rod rod-pr"><span></span><span></span></span>
                <span class="badge badge-pr">Sur mesure</span>
            </div>
        </section>

        <section aria-labelledby="pr-t" class="stack-18 minw0">
            <span class="kicker">{{ $m['name'] }}{{ $p->type ? ', '.mb_strtolower($p->type) : '' }}</span>
            <h1 id="pr-t" class="h1-52">{{ $p->name }}</h1>
            <span class="muted-14">Réf. {{ strtoupper($p->ref) }}, {{ $off ? 'bientôt de retour' : 'disponible sur mesure' }}</span>
            <div class="pr-price">
                <span class="pr-price-n">Sur devis</span>
                <span class="pr-note">Prix établi après la prise de mesures, selon dimensions, tissu et finition. Pose incluse à Abidjan.</span>
            </div>
            @if($p->description)<p class="pr-desc">{{ $p->description }}</p>@endif
            @if($p->colors)
                <div class="stack-10">
                    <span class="lbl-t">Coloris disponibles</span>
                    <span class="row-10">
                        @foreach($p->colors as [$n, $h])<span role="img" aria-label="{{ $n }}" title="{{ $n }}" class="color" style="background:{{ $h }}"></span>@endforeach
                    </span>
                </div>
            @endif
            <div class="row-c-12">
                @if($off)
                    <button type="button" class="btn btn-gold btn-round btn-add" disabled>Bientôt de retour</button>
                @else
                    <a href="{{ $devis }}" class="btn btn-gold btn-round btn-add"><x-icon d="M6 3h9l4 4v14H6zM14 3v5h5M12 11v6M9 14h6" />Demander un devis pour ce modèle</a>
                @endif
            </div>
            <a href="{{ route('home') }}#rendez-vous" class="ulink self-start">Prendre rendez-vous pour une prise de mesures</a>
            <ul class="assure">
                @foreach($assure as [$d, $t])<li><x-icon :d="$d" stroke="1.5" />{{ $t }}</li>@endforeach
            </ul>
        </section>
    </div>

    <section aria-label="Détails" class="pr-panels">
        @foreach($panels as $i => [$title, $rows])
            <details class="panel" @if($i === 0) open @endif data-panel>
                <summary>{{ $title }}<x-icon d="M6 9l6 6 6-6" size="18" stroke="1.8" class="chev" /></summary>
                <dl class="dl">@foreach($rows as [$k, $val])<div><dt>{{ $k }}</dt><dd>{{ $val }}</dd></div>@endforeach</dl>
            </details>
        @endforeach
    </section>

    @if($similar->isNotEmpty())
    <section aria-labelledby="sim-t" class="pr-sim">
        <div class="pr-sim-h">
            <h2 id="sim-t" class="serif-36">Modèles similaires</h2>
            <a href="{{ route('site.shop.show', $p->shop) }}" class="ibtn ibtn-dark" aria-label="Voir toute la boutique {{ $m['name'] }}" title="Toute la boutique"><x-icon d="M5 12h14M13 6l6 6-6 6" size="18" stroke="1.8" /></a>
        </div>
        <ul class="grid-sim">
            @foreach($similar as $s)<li>@include('site.shop._card', ['p' => $s])</li>@endforeach
        </ul>
    </section>
    @endif
</main>
@endsection
