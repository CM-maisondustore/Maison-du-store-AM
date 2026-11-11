@php
    $shops = config('site.shops');
    $order = config('site.shops_order');
    $phone = config('site.contact.phones')[0];
    $curShop = $shop ?: 'all';
    $megaStart = isset($shops[$shop]) ? $shop : 'rideaux';
    // Catalogue masqué depuis l'espace pro : les boutiques mènent directement au devis.
    $shopHref = fn (string $k) => $catalogue ? route('site.shop.show', $k) : route('quote', ['type' => $shops[$k]['type']]);
    // Liens tirés des produits publiés dans l'espace pro (vide si le catalogue est masqué).
    $menu = \App\Support\Site\Catalog::menu();
    $truck = 'M3 7h11v9H3zM14 10h4l3 3v3h-7M5 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0M15 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0';
@endphp
<div class="sticky-top" data-header>
    <div class="bars">
        <div class="topbar">
            <div class="wrap topbar-in">
                <span class="topbar-l"><x-icon :d="$truck" size="16" stroke="1.6" class="gold-i" />Confection, livraison et pose à Abidjan</span>
                <span class="topbar-r"><a href="{{ config('site.contact.whatsapp') }}" rel="noopener" class="wa-btn" aria-label="Nous écrire sur WhatsApp"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3z"/></svg>WhatsApp</a></span>
            </div>
        </div>
        <header class="hd">
            <div class="wrap hd-in">
                <a href="{{ route('home') }}" class="brand" aria-label="Maison du Store AM, accueil">
                    <span aria-hidden="true" class="mark">AM</span>
                    <span class="brand-t"><span class="brand-n">Maison du Store</span><span class="brand-s">{{ $active === 'shop' ? 'MDS-Shop' : 'Abidjan' }}</span></span>
                </a>
                <nav aria-label="Navigation principale" class="hd-nav">
                    <ul>
                        <li><a href="{{ route('home') }}" @class(['tab', 'on' => $active === 'home']) @if($active === 'home') aria-current="page" @endif>Accueil</a></li>
                        <li><button type="button" @class(['tab', 'on' => $active === 'shop']) aria-expanded="false" aria-controls="mds-mega" data-mega-toggle>MDS-Shop<x-icon d="M6 9l6 6 6-6" size="14" stroke="2" class="chev" /></button></li>
                        <li><span aria-disabled="true" title="Bientôt disponible" class="tab tab-off">MDS-Services<span class="soon">Bientôt</span></span></li>
                    </ul>
                </nav>
                <div class="hd-act">
                    @if($catalogue)
                        <button type="button" class="ibtn" aria-label="Rechercher dans MDS-Shop" title="Rechercher" data-search-open><x-icon d="M4 11a7 7 0 1 0 14 0a7 7 0 1 0-14 0M20 20l-3.5-3.5" stroke="1.6" /></button>
                    @endif
                    <a href="{{ route('quote') }}" class="ibtn" aria-label="Demander un devis" title="Demander un devis"><x-icon d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h4" size="21" stroke="1.6" /></a>
                    <a href="{{ route('home') }}#rendez-vous" class="btn btn-gold btn-rdv">Prendre rendez-vous</a>
                    <button type="button" class="ibtn menu-btn" aria-label="Ouvrir le menu" aria-expanded="false" data-menu-open><x-icon d="M4 7h16M4 12h16M4 17h16" size="24" stroke="1.6" /></button>
                </div>
            </div>
            <div id="mds-mega" class="mega" role="region" aria-label="Boutiques MDS-Shop" hidden>
                <div class="wrap mega-in">
                    <ul class="mega-shops">
                        @foreach($order as $k)
                            <li><button type="button" @class(['mega-shop', 'is-soon' => ! $shops[$k]['open']]) data-shop="{{ $k }}" data-mega-pick="{{ $k }}" aria-expanded="{{ $k === $megaStart ? 'true' : 'false' }}"><span aria-hidden="true" class="dot"></span><span class="mega-shop-t"><span class="serif-22">{{ $shops[$k]['name'] }}</span><span class="small">{{ $shops[$k]['line'] }}</span></span><x-icon d="M9 6l6 6-6 6" size="16" stroke="1.8" /></button></li>
                        @endforeach
                        @if($catalogue)<li class="mega-all"><a href="{{ route('site.shop') }}" class="ulink">Tout MDS-Shop</a></li>@endif
                    </ul>
                    @foreach($order as $k)
                        <div class="mega-panel" data-shop="{{ $k }}" data-mega-panel="{{ $k }}" @if($k !== $megaStart) hidden @endif>
                            @if($shops[$k]['open'])
                                <div class="mega-types">
                                    <span class="kicker">Boutique {{ $shops[$k]['boutique'] }}</span>
                                    @foreach($menu[$k] ?? [] as $group)
                                        @if($group['label'])<span class="mega-group">{{ $group['label'] }}</span>@endif
                                        <ul>
                                            @foreach($group['items'] as $item)
                                                <li><a href="{{ $item['href'] }}">{{ $item['name'] }}<x-icon d="M9 6l6 6-6 6" size="14" stroke="1.8" /></a></li>
                                            @endforeach
                                        </ul>
                                    @endforeach
                                    <a href="{{ $shopHref($k) }}" class="btn btn-tint">Entrer dans la boutique {{ $shops[$k]['name'] }}</a>
                                </div>
                                <div class="mega-feat">
                                    <img src="{{ asset($shops[$k]['featImage']) }}" alt="" loading="lazy" class="mega-img">
                                    <span class="serif-20">{{ $shops[$k]['featTitle'] }}</span>
                                    <span class="small">{{ $shops[$k]['featText'] }}</span>
                                </div>
                            @else
                                <div class="mega-soon">
                                    <span class="kicker">Boutique {{ $shops[$k]['boutique'] }}</span>
                                    <span class="soon soon-13">Bientôt disponible</span>
                                    <p class="small">{{ $shops[$k]['soonText'] }}</p>
                                    <a href="{{ route('home') }}#rendez-vous" class="ulink">Nous parler de votre projet</a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </header>
    </div>

    <span role="status" aria-live="polite" class="sr" data-live></span>
    <div @class(['pill-wrap', 'is-nav' => $pill])>
        <div class="pill-in">
            @if($pill)
                <nav aria-label="Boutiques MDS-Shop" class="pill" data-pill-nav>
                    <a href="{{ route('site.shop') }}" @class(['pill-a', 'on-all' => $curShop === 'all']) @if($curShop === 'all') aria-current="page" @endif>Tout</a>
                    @foreach($order as $k)
                        @continue(! $shops[$k]['open'])
                        <a href="{{ route('site.shop.show', $k) }}" data-shop="{{ $k }}" @class(['pill-a', 'on' => $curShop === $k]) @if($curShop === $k) aria-current="page" @endif><span aria-hidden="true" class="dot"></span><span class="lg-only">{{ $shops[$k]['name'] }}</span><span class="sm-only">{{ $shops[$k]['short'] }}</span></a>
                    @endforeach
                </nav>
            @endif
            <div class="notif" aria-hidden="true" data-notif hidden>
                <span class="n-icon" data-n-icon>
                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" class="n-ring"><circle cx="18" cy="18" r="16" stroke="rgba(255,255,255,0.14)" stroke-width="2"></circle><circle cx="18" cy="18" r="16" pathLength="100" stroke-dasharray="100" stroke-dashoffset="100" stroke-linecap="round" stroke-width="2" data-n-arc></circle></svg>
                    <span class="n-kind" data-n-kind><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path data-n-path></path></svg></span>
                    <span class="n-done" data-n-done><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path pathLength="1" stroke-dasharray="1" stroke-dashoffset="1" data-n-check></path></svg></span>
                </span>
                <span class="n-text"><span class="n-title" data-n-title></span><span class="n-sub" data-n-text></span></span>
                <span class="n-rail"><span class="n-fill" data-n-fill></span></span>
            </div>
        </div>
    </div>
</div>

<div class="menu" role="dialog" aria-modal="true" aria-label="Menu" data-menu hidden>
    <div class="menu-head">
        <a href="{{ route('home') }}" class="brand"><span aria-hidden="true" class="mark">AM</span><span class="brand-n">Maison du Store</span></a>
        <button type="button" class="ibtn" aria-label="Fermer le menu" data-menu-close><x-icon d="M6 6l12 12M18 6 6 18" size="22" /></button>
    </div>
    <nav aria-label="Navigation principale" class="menu-nav">
        <a href="{{ route('home') }}" class="m-item">Accueil</a>
        <details class="m-shop" open>
            <summary class="m-item">MDS-Shop<x-icon d="M6 9l6 6 6-6" size="18" stroke="1.8" class="chev" /></summary>
            <div class="m-shops">
                @if($catalogue)<a href="{{ route('site.shop') }}" class="m-all">Tout MDS-Shop</a>@endif
                @foreach($order as $k)
                    @if($shops[$k]['open'])
                        <details class="m-sub" data-shop="{{ $k }}" @if($k === $megaStart) open @endif>
                            <summary>{{ $shops[$k]['name'] }}<x-icon d="M6 9l6 6 6-6" size="16" stroke="1.8" class="chev" /></summary>
                            <ul>
                                <li><a href="{{ $shopHref($k) }}" class="m-whole">Toute la boutique</a></li>
                                @foreach($menu[$k] ?? [] as $group)
                                    @if($group['label'])<li class="m-group">{{ $group['label'] }}</li>@endif
                                    @foreach($group['items'] as $item)
                                        <li><a href="{{ $item['href'] }}">{{ $item['name'] }}</a></li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </details>
                    @else
                        <span class="m-item m-off" data-shop="{{ $k }}">{{ $shops[$k]['name'] }}<span class="soon">Bientôt</span></span>
                    @endif
                @endforeach
            </div>
        </details>
        <span aria-disabled="true" class="m-item m-off">MDS-Services<span class="soon">Bientôt</span></span>
        <a href="{{ route('quote') }}" class="m-item">Demander un devis</a>
    </nav>
    <div class="menu-foot">
        <a href="{{ route('home') }}#rendez-vous" class="btn btn-gold btn-52" data-menu-close>Prendre rendez-vous</a>
        <a href="{{ config('site.contact.whatsapp') }}" rel="noopener" class="btn btn-line btn-52">Écrire sur WhatsApp</a>
    </div>
</div>

@if($catalogue)
<div class="search" role="dialog" aria-modal="true" aria-label="Rechercher" data-search hidden>
    <form action="{{ route('site.shop') }}" method="get" class="search-in">
        <label class="sr" for="search-q">Rechercher dans MDS-Shop</label>
        <x-icon d="M4 11a7 7 0 1 0 14 0a7 7 0 1 0-14 0M20 20l-3.5-3.5" stroke="1.6" />
        <input id="search-q" type="search" name="q" placeholder="Rechercher dans MDS-Shop…" autocomplete="off">
        <button type="button" class="ibtn" aria-label="Fermer la recherche" data-search-close><x-icon d="M6 6l12 12M18 6 6 18" size="20" /></button>
    </form>
</div>
@endif
