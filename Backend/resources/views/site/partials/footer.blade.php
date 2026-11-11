@php
    $c = config('site.contact');
    $shopLinks = $catalogue
        ? [['Rideaux & voilages', route('site.shop.show', 'rideaux')], ['Stores', route('site.shop.show', 'stores')]]
        : [['Rideaux & voilages', route('quote', ['type' => 'rideau'])], ['Stores', route('quote', ['type' => 'store'])]];
    $cols = [
        ['MDS-Shop', [...$shopLinks, ['Électroménager (bientôt)', null], ['Meubles (bientôt)', null]]],
        ['La maison', [['Notre savoir-faire', route('home').'#savoir-faire'], ['Réalisations', route('home').'#realisations'], ['MDS-Services (bientôt)', null], ['Nos boutiques', route('home').'#boutiques']]],
        ['Demande rapide', [['Devis store', route('quote', ['type' => 'store'])], ['Devis rideau', route('quote', ['type' => 'rideau'])], ['Prendre rendez-vous', route('home').'#rendez-vous'], ['WhatsApp', $c['whatsapp']]]],
    ];
@endphp
<footer class="ft">
    <div class="wrap ft-grid">
        <div class="ft-brand">
            <span class="brand"><span aria-hidden="true" class="mark mark-ft">AM</span><span class="ft-n">Maison du Store</span></span>
            <span class="ft-tag">Habiller, équiper, meubler votre intérieur</span>
            <p>Stores et rideaux sur mesure, confectionnés à Abidjan. Conseil, livraison et pose par nos équipes. Électroménager et meubles bientôt disponibles.</p>
            <span aria-hidden="true" class="ft-rod"></span>
        </div>
        @foreach($cols as [$t, $links])
            <nav aria-label="{{ $t }}" class="ft-col">
                <span class="ft-t">{{ $t }}</span>
                @foreach($links as [$label, $href])
                    @if($href)<a href="{{ $href }}">{{ $label }}</a>@else<span aria-disabled="true">{{ $label }}</span>@endif
                @endforeach
            </nav>
        @endforeach
        <nav aria-label="Showroom" class="ft-col">
            <span class="ft-t">Showroom</span>
            <a href="{{ $c['maps'] }}" rel="noopener">{{ $c['address'][0] }}</a>
            @foreach($c['phones'] as $ph)<a href="tel:{{ $ph['tel'] }}">{{ $ph['label'] }}</a>@endforeach
            <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a>
        </nav>
    </div>
    <div class="wrap ft-legal">
        <span>© {{ date('Y') }} Maison du Store AM. Tous droits réservés.</span>
        <span class="ft-legal-l"><a href="{{ route('site.legal', 'mentions-legales') }}">Mentions légales</a><a href="{{ route('site.legal', 'conditions-utilisation') }}">CGU</a><a href="{{ route('site.legal', 'conditions-de-vente') }}">CGV</a><a href="{{ route('site.legal', 'confidentialite') }}">Confidentialité</a><button type="button" class="ft-cookie" data-cookie-open>Gérer les cookies</button></span>
    </div>
</footer>
