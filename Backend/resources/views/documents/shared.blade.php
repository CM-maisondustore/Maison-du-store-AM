@php
    // $doc : données du document, ou null si le lien a expiré / n'est pas valide (rien n'est alors révélé).
    $v = fn (string $f) => asset($f).'?v='.@filemtime(public_path($f));
    $wa = $contact['whatsapp'].'?text='.rawurlencode($doc
        ? 'Bonjour, j’ai une question sur le document '.$doc['number'].'.'
        : 'Bonjour, le lien de mon document a expiré. Pouvez-vous me le renvoyer ?');
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#20332c">
<title>{{ $doc ? $doc['title'].' '.$doc['number'] : 'Lien expiré' }} – Maison du Store AM</title>
<link rel="icon" href="{{ asset('site/favicon.svg') }}" type="image/svg+xml">
<link rel="preload" href="{{ asset('fonts/cormorant-garamond-latin-500-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ $v('site/css/document.css') }}">
</head>
<body>
<main class="dl" data-kind="{{ $doc['kind'] ?? 'expire' }}">
    <header class="dl-brand">
        <img src="{{ asset('site/favicon.svg') }}" alt="" width="36" height="36">
        <span>Maison du Store AM<small>Stores et rideaux sur mesure, Abidjan</small></span>
    </header>

    <section class="dl-note" aria-labelledby="dl-title">
        @if($doc)
            <h1 id="dl-title">{{ $doc['firstName'] ? 'Bonjour '.$doc['firstName'].',' : 'Bonjour,' }}<br>voici {{ ['devis' => 'votre devis', 'facture' => 'votre facture', 'recu' => 'votre reçu'][$doc['kind']] }}.</h1>
            <p>Téléchargez-le pour le garder, ou ouvrez-le dans votre navigateur. Le document est identique à celui préparé par votre conseiller.</p>
            <p class="dl-expiry">Ce lien reste valable jusqu’au <strong>{{ $doc['expires'] }}</strong>. Passé cette date, demandez-nous un nouveau lien.</p>
        @else
            <h1 id="dl-title">Ce lien n’est plus valable.</h1>
            <p>Les liens vers nos devis, factures et reçus sont valables 30 jours. Écrivez-nous sur WhatsApp : votre conseiller vous renvoie le document.</p>
        @endif
        <a class="dl-help" href="{{ $wa }}" rel="noopener">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7a2.8 2.8 0 0 0 1.8-1.3 2.3 2.3 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>
            {{ $doc ? 'Une question sur ce document ? Écrire sur WhatsApp' : 'Demander un nouveau lien sur WhatsApp' }}
        </a>
    </section>

    <div class="dl-stage">
        <div class="dl-sleeve" aria-hidden="true"></div>
        <article class="dl-sheet" @if($doc) aria-labelledby="dl-number" @else aria-label="Document indisponible" @endif>
            @if($doc)
                <div class="dl-head">
                    <p class="dl-type">{{ $doc['title'] }}@if($doc['draft']) <span>provisoire</span>@endif</p>
                    <p class="dl-number" id="dl-number">{{ $doc['number'] }}</p>
                </div>
                <dl class="dl-facts">
                    @if($doc['date'])<div><dt>{{ $doc['kind'] === 'recu' ? 'Paiement du' : 'Établi le' }}</dt><dd>{{ $doc['date'] }}</dd></div>@endif
                    @if($doc['due'])<div><dt>{{ $doc['dueLabel'] }}</dt><dd>{{ $doc['due'] }}</dd></div>@endif
                    @if($doc['method'])<div><dt>Mode de paiement</dt><dd>{{ $doc['method'] }}</dd></div>@endif
                    @if($doc['amount'])<div class="dl-amount"><dt>{{ $doc['amountLabel'] }}</dt><dd>{{ $doc['amount'] }}</dd></div>@endif
                </dl>
                <div class="dl-stub">
                    <a class="dl-download" href="{{ $doc['download'] }}" download="{{ $doc['filename'] }}">
                        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 20h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Télécharger le PDF
                    </a>
                    <a class="dl-open" href="{{ $doc['open'] }}" target="_blank" rel="noopener">Ouvrir dans le navigateur</a>
                    <p class="dl-file">{{ $doc['filename'] }}</p>
                </div>
            @else
                <div class="dl-void">
                    <span class="dl-void-line"></span><span class="dl-void-line"></span><span class="dl-void-line short"></span>
                    <p>Document indisponible</p>
                </div>
            @endif
        </article>
    </div>

    <footer class="dl-foot">
        <p>Showroom : {{ $contact['address'][0] ?? '' }}, {{ $contact['address'][1] ?? '' }}</p>
        <nav aria-label="Liens du site"><a href="{{ url('/') }}">maisondustoream.com</a><a href="{{ url('/confidentialite') }}">Confidentialité</a></nav>
    </footer>
</main>
</body>
</html>
