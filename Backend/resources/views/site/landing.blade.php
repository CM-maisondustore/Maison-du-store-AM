@extends('site.layout', ['active' => 'home'])
@php
    $catalogue = \App\Support\Site\Catalog::visible();
    $shopAll = $catalogue ? route('site.shop') : route('quote');
    $rideaux = $catalogue ? route('site.shop.show', 'rideaux') : route('quote', ['type' => 'rideau']);
    $stores = $catalogue ? route('site.shop.show', 'stores') : route('quote', ['type' => 'store']);
    $heroImg = $hero?->media ? $hero->media->url() : asset('site/images/realisations/habillage-salon.webp');
    $heroTabs = [
        ['k' => 'stores', 'name' => 'Stores & Rideaux', 'mob' => 'Stores & Rideaux', 'short' => 'Confection sur mesure', 'title' => $hero?->title, 'line' => $hero?->description ?: 'Stores et rideaux confectionnés à Abidjan, mesurés et posés chez vous par nos équipes.', 'img' => $heroImg, 'caption' => $hero?->image_alt ?: 'Habillage complet d’un salon, réalisation Maison du Store', 'cats' => ['Stores intérieurs', 'Stores extérieurs', 'Rideaux', 'Voilages'], 'ctas' => [['Rideaux & voilages', $rideaux], ['Stores', $stores]], 'soon' => false],
        ['k' => 'electro', 'name' => 'Électroménager', 'mob' => 'Électro', 'short' => 'Bientôt disponible', 'title' => null, 'line' => 'Réfrigérateurs, lave-linge, cuisinières et climatiseurs, livrés et installés à domicile. La boutique ouvre bientôt.', 'img' => asset('site/images/soon/electro-hero.jpg'), 'caption' => 'Photo d’illustration', 'cats' => ['Téléviseurs', 'Climatisation', 'Froid', 'Cuisine', 'Lavage'], 'ctas' => [], 'soon' => true],
        ['k' => 'meubles', 'name' => 'Meubles', 'mob' => 'Meubles', 'short' => 'Bientôt disponible', 'title' => null, 'line' => 'Du bureau de direction à la table de famille, un mobilier choisi pour durer. La boutique ouvre bientôt.', 'img' => asset('site/images/soon/meubles-hero.jpg'), 'caption' => 'Photo d’illustration', 'cats' => ['Bureau', 'Domicile'], 'ctas' => [], 'soon' => true],
    ];
    $reassure = [
        ['Pose incluse', 'Stores et rideaux posés par nos équipes.', 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z'],
        ['Conseil à domicile', 'Un conseiller se déplace chez vous.', 'M3 11l9-8 9 8M5 10v10h14V10'],
        ['Fabrication sur mesure', 'Confection dans notre atelier d’Abidjan.', 'M3 17 17 3l4 4L7 21H3zM13 7l2 2M10 10l2 2M7 13l2 2'],
        ['Livraison à Abidjan', 'Date fixée avec vous, partout à Abidjan.', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M5 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0M15 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0'],
    ];
    $boutiques = [
        ['k' => 'stores', 'name' => 'Stores & Rideaux', 'img' => asset('site/images/realisations/rideaux-occultants.webp'), 'line' => 'Le métier d’origine de la maison : confection d’atelier et pose par nos équipes.', 'cats' => ['Stores intérieurs', 'Stores extérieurs', 'Rideaux', 'Voilages'], 'ctas' => [['Rideaux & voilages', $rideaux], ['Stores', $stores]], 'soon' => false],
        ['k' => 'electro', 'name' => 'Électroménager', 'img' => asset('site/images/soon/electro.jpg'), 'line' => 'Téléviseurs, climatiseurs, réfrigérateurs, cuisine et lavage, livrés et installés.', 'cats' => ['Téléviseurs', 'Climatisation', 'Froid', 'Cuisine', 'Lavage'], 'ctas' => [], 'soon' => true],
        ['k' => 'meubles', 'name' => 'Meubles', 'img' => asset('site/images/soon/meubles.jpg'), 'line' => 'Deux entrées : l’aménagement de vos bureaux et le mobilier de la maison.', 'cats' => ['Salon', 'Salle à manger', 'Chambre', 'Bureau'], 'ctas' => [], 'soon' => true],
    ];
    $pillars = [
        ['01', 'Confection', 'Coupe, ourlets et finitions réalisés dans notre atelier, à partir de vos mesures exactes.', ['Rideaux occultants, à œillets, Wave ou à plis', 'Voilages légers et doublures thermiques', 'Stores enrouleurs, vénitiens, bateau et extérieurs']],
        ['02', 'Livraison', 'Vos stores et rideaux arrivent prêts à poser, à la date convenue, partout à Abidjan.', ['Date fixée avec vous', 'Emballage soigné, aucun pli', 'Suivi par votre conseiller']],
        ['03', 'Pose', 'Nos poseurs installent tringles, rails et mécanismes, règlent et vous montrent l’entretien.', ['Pose incluse à Abidjan', 'Motorisation et télécommandes', 'Réglages et SAV par la même équipe']],
    ];
    $steps = [
        ['1', 'Conseil', 'Au showroom, chez vous ou par téléphone, nous cernons votre besoin.'],
        ['2', 'Prise de mesures', 'Un conseiller relève les cotes exactes et vous présente tissus et toiles.'],
        ['3', 'Fabrication', 'Confection de vos stores et rideaux dans notre atelier d’Abidjan.'],
        ['4', 'Pose', 'Installation et réglages par nos équipes, partout à Abidjan.'],
    ];
    $others = [
        ['electro', 'Électroménager', 'Le même soin pour vos appareils : livraison à l’étage, installation, raccordement et mise en service par nos techniciens.', ['Livraison', 'Installation', 'Mise en service']],
        ['meubles', 'Meubles', 'Bureaux, salles de réunion ou maison : nous livrerons, monterons et installerons votre mobilier, pièce par pièce.', ['Livraison', 'Montage', 'Aménagement de bureaux']],
    ];
    $services = [
        ['Conseil', 'Au showroom, chez vous ou à distance, pour choisir juste.', 'M4 5h16v11H8l-4 4z', null],
        ['Livraison', 'Partout à Abidjan, à la date qui vous convient.', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M5 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0M15 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0', null],
        ['Installation et pose', 'Pose et réglages par nos propres équipes.', 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z', null],
        ['Motorisation', 'Stores et rideaux pilotés à distance.', 'M13 2 4 14h7l-1 8 9-12h-7z', 'Stores'],
        ['Garantie et SAV', 'Un seul interlocuteur après l’achat.', 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z', null],
    ];
    $projects = ['Stores intérieurs', 'Stores extérieurs', 'Rideaux', 'Voilages', 'Motorisation'];
    $places = ['showroom' => 'Au showroom', 'domicile' => 'Chez moi', 'telephone' => 'Par téléphone'];
    $contact = config('site.contact');
@endphp
@section('content')
<main id="contenu">
    <section aria-label="Maison du Store, trois boutiques" class="lp-hero" data-hero>
        <div class="wrap lp-hero-in">
            <div class="lp-intro">
                <h1 class="lp-h1">Habiller, équiper et meubler votre intérieur</h1>
                <div class="lp-intro-r">
                    <p>Stores et rideaux sur mesure aujourd’hui, électroménager et mobilier très bientôt. Un seul interlocuteur, du conseil jusqu’à la pose.</p>
                    <div class="row"><a href="{{ $shopAll }}" class="btn btn-gold">{{ $catalogue ? 'Entrer dans MDS-Shop' : 'Demander un devis' }}</a><a href="#rendez-vous" class="btn btn-line">Prendre rendez-vous</a></div>
                    <span class="lp-note"><x-icon d="M3 7h11v9H3zM14 10h4l3 3v3h-7M5 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0M15 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0" size="18" stroke="1.5" class="gold-t" />Confection, livraison et pose à Abidjan</span>
                </div>
            </div>
            <div class="lp-tabs-row">
                <div role="tablist" aria-label="Choisir une boutique" class="lp-tabs">
                    @foreach($heroTabs as $i => $u)
                        <button type="button" role="tab" id="hero-tab-{{ $i }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="hero-panel-{{ $i }}" tabindex="{{ $i === 0 ? 0 : -1 }}" class="lp-tab" data-hero-tab="{{ $i }}">
                            <span aria-hidden="true" class="lp-rail"><span class="lp-fill"></span><span class="lp-ring"></span></span>
                            <span class="lp-tab-n"><span class="lg-only">{{ $u['name'] }}</span><span class="sm-only">{{ $u['mob'] }}</span></span>
                            <span class="lp-tab-s">{{ $u['short'] }}</span>
                        </button>
                    @endforeach
                </div>
                <button type="button" class="ibtn ibtn-line ibtn-46" aria-label="Mettre en pause la rotation" data-hero-play><x-icon d="M8 5v14M16 5v14" size="16" stroke="1.8" data-hero-play-i /></button>
            </div>
            @foreach($heroTabs as $i => $u)
                <div role="tabpanel" id="hero-panel-{{ $i }}" aria-labelledby="hero-tab-{{ $i }}" class="lp-panel" data-shop="{{ $u['k'] }}" data-hero-panel="{{ $i }}" @if($i > 0) hidden @endif>
                    <div class="stack-22 minw0">
                        @if($u['title'])<h2 class="serif-34">{{ $u['title'] }}</h2>@endif
                        @if($u['soon'])<span class="soon soon-13">Bientôt disponible</span>@endif
                        <p class="lp-line">{{ $u['line'] }}</p>
                        <ul class="tags">@foreach($u['cats'] as $c)<li>{{ $c }}</li>@endforeach</ul>
                        <div class="row">
                            @forelse($u['ctas'] as [$l, $h])<a href="{{ $h }}" class="btn btn-dark">{{ $l }}</a>@empty<a href="#rendez-vous" class="btn btn-line">Nous parler de votre projet</a>@endforelse
                        </div>
                    </div>
                    <figure class="lp-fig">
                        <span aria-hidden="true" class="lp-rod"></span>
                        <img src="{{ $u['img'] }}" alt="" @if($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                        <figcaption>{{ $u['caption'] }}</figcaption>
                    </figure>
                </div>
            @endforeach
        </div>
    </section>

    <section aria-label="Nos engagements" class="lp-reassure">
        <ul class="wrap lp-reassure-g">
            @foreach($reassure as [$t, $s, $d])
                <li><x-icon :d="$d" size="24" stroke="1.4" class="gold-t" /><span class="stack-2"><strong>{{ $t }}</strong><span class="small">{{ $s }}</span></span></li>
            @endforeach
        </ul>
    </section>

    @if($catalogue)
    <section id="catalogue" aria-labelledby="bulles-t" class="sec-cream clip">
        <div class="wrap sec">
            <div class="sec-head-r">
                <div class="stack-12"><h2 id="bulles-t" class="h2">En ce moment dans MDS-Shop</h2><p class="intro">Stores, rideaux et voilages : une sélection qui change à chaque visite. Touchez une bulle pour voir le modèle.</p></div>
                <div class="row-8">
                    @if($bubblePool)<button type="button" class="ibtn ibtn-line ibtn-46" aria-label="Mélanger la sélection" title="Mélanger" data-bubbles-shuffle><x-icon d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5" size="18" /></button>@endif
                    <a href="{{ $shopAll }}" class="ibtn ibtn-dark ibtn-46" aria-label="Voir tout MDS-Shop" title="Tout MDS-Shop"><x-icon d="M5 12h14M13 6l6 6-6 6" size="18" stroke="1.8" /></a>
                </div>
            </div>
            @if($bubblePool)
                <div class="bubbles" data-bubbles data-pool='@json($bubblePool)'></div>
            @else
                <div class="row"><a href="{{ $rideaux }}" class="btn btn-dark">Rideaux & voilages</a><a href="{{ $stores }}" class="btn btn-dark">Stores</a></div>
            @endif
        </div>
    </section>
    @endif

    <section id="boutiques" aria-labelledby="boutiques-t" class="sec-white">
        <div class="wrap sec">
            <div class="sec-head"><h2 id="boutiques-t" class="h2">Trois boutiques, une même exigence</h2><p class="intro">Stores & Rideaux est ouverte. Électroménager et Meubles arrivent bientôt dans MDS-Shop, avec la même pose, la même livraison et le même service après-vente.</p></div>
            <div class="lp-shops">
                @foreach($boutiques as $b)
                    <article @class(['lp-shop', 'is-soon' => $b['soon']]) data-shop="{{ $b['k'] }}">
                        <img src="{{ $b['img'] }}" alt="" loading="lazy">
                        <div class="lp-shop-b">
                            <h3 class="serif-34">{{ $b['name'] }}</h3>
                            @if($b['soon'])<span class="soon soon-13">Bientôt disponible</span>@endif
                            <p class="muted-16">{{ $b['line'] }}</p>
                            <ul class="tags tags-s">@foreach($b['cats'] as $c)<li>{{ $c }}</li>@endforeach</ul>
                            @if($b['ctas'])<div class="lp-shop-c">@foreach($b['ctas'] as [$l, $h])<a href="{{ $h }}" class="btn btn-gold">{{ $l }}</a>@endforeach</div>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="savoir-faire" aria-labelledby="sf-t" class="sec-pine">
        <div class="wrap sec">
            <div class="sf-head">
                <div class="stack-14"><span class="mono-12 gold-l">Notre métier d’origine</span><h2 id="sf-t" class="h2-light">Le sur-mesure, de l’atelier à votre fenêtre</h2></div>
                <p>Rideaux, voilages et stores sont mesurés chez vous, confectionnés dans notre atelier d’Abidjan, puis livrés et posés par nos propres équipes. Vous gardez un seul interlocuteur du premier rendez-vous à la dernière vis.</p>
            </div>
            <ol class="sf-pillars">
                @foreach($pillars as [$n, $t, $s, $pts])
                    <li>
                        <span aria-hidden="true" class="sf-rod"><span></span></span>
                        <span class="mono-13 gold-l">{{ $n }}</span>
                        <h3 class="serif-32 white">{{ $t }}</h3>
                        <p>{{ $s }}</p>
                        <ul>@foreach($pts as $pt)<li><span aria-hidden="true" class="gold-l">—</span>{{ $pt }}</li>@endforeach</ul>
                    </li>
                @endforeach
            </ol>
            <div class="sf-steps-w">
                <h3 class="serif-30 white">Votre projet, en quatre étapes</h3>
                <ol class="sf-steps">
                    @foreach($steps as [$n, $t, $s])
                        <li><span aria-hidden="true" class="sf-n">{{ $n }}</span><span class="serif-24 white">{{ $t }}</span><span class="sf-s">{{ $s }}</span></li>
                    @endforeach
                </ol>
            </div>
            <div class="sf-others">
                @foreach($others as [$k, $t, $s, $tags])
                    <div class="sf-other" data-shop="{{ $k }}">
                        <span class="row-c-10"><span aria-hidden="true" class="dot dot-10"></span><span class="serif-26 white">{{ $t }}</span><span class="soon">Bientôt</span></span>
                        <p>{{ $s }}</p>
                        <ul class="sf-tags">@foreach($tags as $tg)<li>{{ $tg }}</li>@endforeach</ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section aria-labelledby="services-t" class="sec-white">
        <div class="wrap sec">
            <div class="sec-head"><h2 id="services-t" class="h2">Ce que la maison fait pour vous</h2></div>
            <ul class="services">
                @foreach($services as [$t, $s, $d, $tag])
                    <li><x-icon :d="$d" size="28" stroke="1.4" class="gold-t" /><div class="row-c-8"><h3 class="serif-24">{{ $t }}</h3>@if($tag)<span class="soon soon-13">{{ $tag }}</span>@endif</div><p class="small">{{ $s }}</p></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section id="realisations" aria-labelledby="real-t" class="sec-cream">
        <div class="wrap sec">
            <div class="sec-head-r">
                <div class="stack-12"><h2 id="real-t" class="h2">Réalisations</h2><p class="intro">Stores, rideaux et voilages posés par nos équipes à Abidjan.</p></div>
            </div>
            <div class="gallery">
                @foreach($portfolio as $g)
                    <figure>
                        @if($g['img'])
                            <img src="{{ $g['img'] }}" alt="{{ $g['alt'] }}" loading="lazy">
                        @else
                            <div class="ph"><x-icon d="M3 5h18v14H3zM7 10a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0M21 16l-5-5-8 8" size="30" stroke="1.2" /><span class="mono-12">Photo à venir</span></div>
                        @endif
                        <figcaption><span class="serif-24">{{ $g['t'] }}</span>@if($g['s'])<span class="muted-14">{{ $g['s'] }}</span>@endif</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    <section id="rendez-vous" aria-labelledby="rdv-t" class="sec-white" data-rdv-section>
        <div class="wrap sec">
            <div class="sec-head"><h2 id="rdv-t" class="h2">Prendre rendez-vous</h2><p class="intro">Au showroom, chez vous ou par téléphone. Un conseiller confirme votre rendez-vous sous 24 h ouvrées.</p></div>
            <div class="rdv-grid">
                <form method="post" action="{{ url('/api/appointments') }}" id="rdv-form" class="form-card" data-rdv data-availability="{{ url('/api/appointments/availability') }}" novalidate>
                    <fieldset class="fs">
                        <legend class="legend"><span class="legend-n">1</span>Vos coordonnées</legend>
                        <div class="grid-200">
                            <label class="lbl">Nom<input id="rdv-nom" name="lastName" type="text" autocomplete="family-name" placeholder="Kouassi" class="inp" required></label>
                            <label class="lbl">Prénom<input id="rdv-prenom" name="firstName" type="text" autocomplete="given-name" placeholder="Aya" class="inp" required></label>
                            <label class="lbl">Téléphone<input id="rdv-tel" name="phone" type="tel" autocomplete="tel" placeholder="07 00 00 00 00" class="inp" required></label>
                            <label class="lbl">E-mail<input id="rdv-mail" name="email" type="email" autocomplete="email" placeholder="nom@exemple.ci" class="inp" required></label>
                        </div>
                    </fieldset>
                    <div class="hr"></div>
                    <fieldset class="fs fs-20" data-shop="stores">
                        <legend class="legend"><span class="legend-n">2</span>Votre projet et le lieu</legend>
                        <div role="group" aria-label="Votre projet" class="stack-10">
                            <span class="lbl-t">Votre projet Stores & Rideaux <span class="light">(plusieurs choix possibles)</span></span>
                            <div class="row-8">@foreach($projects as $x)<label class="pick pick-tint"><input type="checkbox" name="projects[]" value="{{ $x }}"><span>{{ $x }}</span></label>@endforeach</div>
                        </div>
                        <div role="radiogroup" aria-label="Lieu du rendez-vous" class="stack-10">
                            <span class="lbl-t">Où souhaitez-vous nous rencontrer ?</span>
                            <div class="row-8">@foreach($places as $k => $l)<label class="pick"><input type="radio" name="appointmentType" value="{{ $k }}" data-label="{{ $l }}" @checked($k === 'showroom')><span>{{ $l }}</span></label>@endforeach</div>
                        </div>
                        <label class="lbl">Message <span class="light">(facultatif)</span><textarea rows="2" name="message" class="inp inp-ta" maxlength="3000" placeholder="Nombre de fenêtres, pièce, quartier…"></textarea></label>
                    </fieldset>
                    <div class="hr"></div>
                    <fieldset class="fs">
                        <legend class="legend"><span class="legend-n">3</span>Date et créneau</legend>
                        <div role="radiogroup" aria-label="Date" class="rdv-dates">
                            @foreach($dates as $i => $d)
                                <label class="rdv-date"><input type="radio" name="date" value="{{ $d['value'] }}" data-label="{{ $d['long'] }}" @checked($i === 0)><span><span class="small-13">{{ $d['d'] }}</span><span class="serif-26">{{ $d['n'] }}</span></span></label>
                            @endforeach
                        </div>
                        <div role="radiogroup" aria-label="Créneau" class="row-8" data-rdv-slots>
                            @foreach($slots as $i => $s)<label class="pick"><input type="radio" name="time" value="{{ $s }}" @checked($i === 1)><span>{{ $s }}</span></label>@endforeach
                        </div>
                    </fieldset>
                </form>
                <aside aria-label="Récapitulatif" class="rdv-aside">
                    <h3 class="serif-26">Votre rendez-vous</h3>
                    <dl class="dl" data-rdv-recap></dl>
                    <p class="small-13">Un conseiller vous appelle pour confirmer sous 24 h ouvrées.</p>
                </aside>
            </div>
        </div>
    </section>

    <section id="contact" aria-labelledby="contact-t" class="sec-cream">
        <div class="wrap sec contact-grid">
            <div class="stack-20">
                <h2 id="contact-t" class="h2">Le showroom de Cocody</h2>
                <p class="muted-16">Venez toucher les tissus et voir nos stores en situation. Vous pouvez aussi nous appeler ou nous écrire.</p>
                <dl class="contact-dl">
                    <div><dt>Adresse</dt><dd>{{ $contact['address'][0] }}<br>{{ $contact['address'][1] }}</dd></div>
                    <div><dt>Téléphone</dt><dd class="stack-0">@foreach($contact['phones'] as $ph)<a href="tel:{{ $ph['tel'] }}">{{ $ph['label'] }}</a>@endforeach</dd></div>
                    <div><dt>E-mail</dt><dd><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></dd></div>
                </dl>
                <div class="row"><a href="{{ $contact['whatsapp'] }}" rel="noopener" class="btn btn-dark">Écrire sur WhatsApp</a><a href="{{ route('quote') }}" class="btn btn-line">Demander un devis</a></div>
            </div>
            <figure class="map-fig">
                <img src="{{ asset('site/images/realisations/showroom.webp') }}" alt="Façade du showroom Maison du Store AM à Cocody" loading="lazy">
                <a href="{{ $contact['maps'] }}" rel="noopener" class="btn btn-dark">Ouvrir dans Google Maps</a>
            </figure>
        </div>
    </section>
</main>

<div role="region" aria-label="État de votre demande de rendez-vous" class="float-bar float-rdv" data-rdv-bar hidden>
    <span aria-hidden="true" class="bar-rail"><span data-bar-1></span><span class="on"></span><span class="on"></span></span>
    <span class="bar-t"><strong data-rdv-title></strong><span class="bar-s" data-rdv-sub></span></span>
    <button type="submit" form="rdv-form" class="btn btn-gold btn-46" data-rdv-cta>Confirmer</button>
</div>
@endsection
