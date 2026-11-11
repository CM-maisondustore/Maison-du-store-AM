@extends('site.layout', ['active' => 'shop', 'shop' => $type === 'rideau' ? 'rideaux' : 'stores'])
@php
    $steps = ['Votre projet', 'Vos choix', 'Vos fenêtres', 'Coordonnées'];
    $communes = config('site.communes');
@endphp
@section('title', 'Demander un devis — Stores & Rideaux | Maison du Store AM')
@section('description', 'Devis gratuit pour vos stores, rideaux et voilages sur mesure à Abidjan. Un conseiller vous rappelle et vient prendre les mesures.')
@section('content')
<main id="contenu" data-shop="{{ $type === 'rideau' ? 'rideaux' : 'stores' }}" data-dv-root>
    <section class="shop-hero tinted">
        <div class="wrap dv-head">
            <div class="row-c">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="ibtn ibtn-line" aria-label="Retour" title="Retour"><x-icon d="M15 6l-6 6 6 6" size="18" stroke="1.8" /></a>
                <span class="kicker">Stores & Rideaux</span>
            </div>
            <h1 id="demande-t" class="h1-60">Demander un devis</h1>
            <p class="lead lead-38">Choisissez votre modèle, vos tissus et vos dimensions en quelques minutes. Un conseiller vous rappelle et vient prendre les mesures exactes avant la fabrication.</p>
            <ol aria-label="Étapes" class="dv-steps" data-dv-steps>
                @foreach($steps as $i => $label)
                    <li @class(['cur' => $i === 0])><span aria-hidden="true" class="st-rail"><span></span></span><span class="st-l">{{ $label }}</span></li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="wrap dv-body">
        <div role="status" class="sent-box grow" data-dv-sent hidden>
            <span class="serif-36">Demande de devis envoyée</span>
            <p data-dv-sent-text></p>
            <div class="row"><a href="{{ route('home') }}" class="btn btn-dark">Retour à l’accueil</a><a href="{{ route('quote') }}" class="btn btn-line">Nouvelle demande</a></div>
        </div>

        <form class="dv-form" id="dv-form" data-devis2 data-steps='@json($steps)' data-endpoints='@json($endpoints)' novalidate>
            <fieldset class="dv-step" data-step="1">
                <legend class="serif-30">Votre projet</legend>
                <div role="radiogroup" aria-label="Produit" class="dv-types">
                    @foreach([['store', 'Stores', 'Intérieurs ou extérieurs, pour régler la lumière et la chaleur.'], ['rideau', 'Rideaux et voilages', 'Confection d’atelier, du voilage léger au rideau occultant.']] as [$k, $l, $s])
                        @php($card = $cards->get('quote-'.$k))
                        <label class="dv-type"><input type="radio" name="type" value="{{ $k }}" @checked($type === $k) data-dv-type><span class="dv-type-b">@if($card?->media)<img src="{{ $card->media->url() }}" alt="{{ $card->image_alt ?: $card->title }}" loading="lazy" class="dv-type-img">@endif<span class="serif-24">{{ $card?->title ?: $l }}</span><span class="small">{{ $card?->description ?: $s }}</span></span></label>
                    @endforeach
                </div>
                <div class="stack-10">
                    <span class="lbl-t">Modèle</span>
                    <div class="dv-cards" role="radiogroup" aria-label="Modèle" data-dv-models></div>
                    <p class="small" data-dv-models-empty hidden>Aucun modèle n’est encore proposé en ligne pour ce produit. Appelez-nous au {{ config('site.contact.phones')[0]['label'] }} ou prenez rendez-vous : un conseiller vous présente la collection.</p>
                </div>
            </fieldset>

            <fieldset class="dv-step" data-step="2">
                <legend class="serif-30">Vos choix</legend>
                <div class="stack-10" data-for="store">
                    <span class="lbl-t">Type de projet</span>
                    <div class="row-8">@foreach(['installation' => 'Nouvelle installation', 'remplacement' => 'Remplacement', 'renovation' => 'Rénovation'] as $k => $l)<label class="pick"><input type="radio" name="orderType" value="{{ $k }}" @checked($k === 'installation')><span>{{ $l }}</span></label>@endforeach</div>
                </div>
                <div class="stack-10">
                    <span class="lbl-t">Manœuvre</span>
                    <div class="row-8">
                        <label class="pick"><input type="radio" name="control" value="manuelle" checked><span>Manuelle</span></label>
                        <label class="pick"><input type="radio" name="control" value="motorisee"><span>Motorisée</span></label>
                        <label class="pick" data-for="store"><input type="radio" name="control" value="inconnu"><span>Je ne sais pas encore</span></label>
                    </div>
                </div>
                <div class="stack-10" data-dv-group="fabric">
                    <span class="lbl-t" data-dv-fabric-label>Tissu</span>
                    <div class="dv-cards" role="radiogroup" aria-label="Tissu" data-dv-cards="fabric"></div>
                </div>
                <div class="stack-10" data-dv-group="sheer" data-for="rideau">
                    <span class="lbl-t">Voilage <span class="light">(facultatif si un tissu est choisi)</span></span>
                    <div class="dv-cards" role="radiogroup" aria-label="Voilage" data-dv-cards="sheer"></div>
                </div>
                <div class="stack-10" data-dv-group="finish">
                    <span class="lbl-t">Finition</span>
                    <div class="dv-cards" role="radiogroup" aria-label="Finition" data-dv-cards="finish"></div>
                </div>
                <div class="stack-10">
                    <span class="lbl-t">Coloris souhaité</span>
                    <div class="row-8" role="radiogroup" aria-label="Coloris" data-dv-colors></div>
                </div>
            </fieldset>

            <fieldset class="dv-step" data-step="3">
                <legend class="serif-30">Vos fenêtres</legend>
                <p class="small">Dimensions approximatives en centimètres (entre 20 et 400). Le conseiller vérifie les cotes avant la fabrication.</p>
                <ol class="dv-lines" data-dv-lines>
                    <li class="dv-line" data-dv-line>
                        <div class="dv-line-h"><span class="mono-13" data-dv-line-n>Fenêtre 1</span><button type="button" class="ibtn ibtn-del ibtn-40" aria-label="Retirer cette fenêtre" title="Retirer" data-dv-remove><x-icon d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" size="18" /></button></div>
                        <div class="dv-fields">
                            <label class="lbl">Pièce<input type="text" placeholder="Salon" data-k="label" maxlength="120" class="inp"></label>
                            <label class="lbl">Largeur (cm)<input type="text" inputmode="decimal" placeholder="180" data-k="width" class="inp"></label>
                            <label class="lbl">Hauteur (cm)<input type="text" inputmode="decimal" placeholder="220" data-k="height" class="inp"></label>
                            <label class="lbl">Quantité<input type="text" inputmode="numeric" value="1" data-k="quantity" class="inp"></label>
                        </div>
                    </li>
                </ol>
                <button type="button" class="dash-add" data-dv-add>+ Ajouter une fenêtre</button>
            </fieldset>

            <fieldset class="dv-step" data-step="4">
                <legend class="serif-30">Coordonnées</legend>
                <div role="radiogroup" aria-label="Civilité" class="row-8">
                    <label class="pick"><input type="radio" name="civility" value="mme"><span>Madame</span></label>
                    <label class="pick"><input type="radio" name="civility" value="m"><span>Monsieur</span></label>
                </div>
                <div class="dv-contact">
                    <label class="lbl">Nom<input name="lastName" type="text" autocomplete="family-name" maxlength="60" class="inp"></label>
                    <label class="lbl">Prénom<input name="firstName" type="text" autocomplete="given-name" maxlength="60" class="inp"></label>
                    <label class="lbl">Téléphone<span class="row-8"><select name="phoneCountryCode" class="inp" style="max-width:110px" aria-label="Indicatif">@foreach($choices['prefixes'] as $code => $country)<option value="{{ $code }}" @selected($code === '+225')>{{ $code }}</option>@endforeach</select><input name="phone" type="tel" autocomplete="tel-national" placeholder="07 00 00 00 00" class="inp grow"></span></label>
                    <label class="lbl"><span>E-mail <span class="light">(facultatif)</span></span><input name="email" type="email" autocomplete="email" maxlength="254" class="inp"></label>
                    <label class="lbl">Adresse ou quartier<input name="address" type="text" autocomplete="street-address" placeholder="Riviera 3, rue…" maxlength="120" class="inp"></label>
                    <label class="lbl">Commune<select name="city" class="inp">@foreach($communes as $c)<option>{{ $c }}</option>@endforeach</select></label>
                </div>
                <label class="lbl">Précisions <span class="light">(facultatif)</span><textarea name="notes" rows="3" maxlength="3000" class="inp inp-ta" placeholder="Accès, horaires de passage, contraintes particulières…"></textarea></label>
                <label class="dv-terms"><input type="checkbox" name="acceptTerms" value="1"><span>J’accepte que Maison du Store AM utilise ces informations pour établir mon devis et me recontacter (<a href="{{ route('site.legal', 'confidentialite') }}" class="alink">confidentialité</a>, <a href="{{ route('site.legal', 'conditions-de-vente') }}" class="alink">conditions de vente</a>).</span></label>
                <p class="dv-err" role="alert" data-dv-error hidden></p>
            </fieldset>
        </form>
        <aside aria-label="Récapitulatif" class="dv-aside" data-dv-aside>
            <h2 class="serif-26">Votre demande</h2>
            <dl class="dl" data-dv-recap></dl>
            <p class="small">Aucun engagement : le devis est établi après la prise de mesures.</p>
        </aside>
    </section>
</main>
<div role="region" aria-label="Progression de la demande" class="float-bar float-dv" data-dv-bar>
    <button type="button" class="ibtn ibtn-line" aria-label="Étape précédente" title="Précédent" data-dv-prev disabled><x-icon d="M15 6l-6 6 6 6" size="18" stroke="1.8" /></button>
    <span aria-hidden="true" class="bar-rail" data-dv-rail>@foreach($steps as $s)<span></span>@endforeach</span>
    <span class="bar-t"><strong data-dv-bar-title>Étape 1 sur {{ count($steps) }} : {{ mb_strtolower($steps[0]) }}</strong><span class="bar-s" data-dv-bar-sub>Rien n’est envoyé avant la dernière étape</span></span>
    <button type="button" class="btn btn-gold btn-round" data-dv-next><span data-dv-next-label>Continuer</span><x-icon d="M5 12h14M13 6l6 6-6 6" size="18" stroke="1.8" data-dv-next-icon /></button>
</div>
<script type="application/json" id="dv-data">{!! json_encode(['preset' => $preset] + $choices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection
