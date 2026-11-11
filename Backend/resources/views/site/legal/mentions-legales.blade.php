@php($c = config('site.legal'))
<h2>Éditeur du site</h2>
<p>Le site maisondustoream.com est édité par <strong>{{ $c['company'] }}</strong>, société à responsabilité limitée au capital de {{ $c['capital'] }}.</p>
<ul>
    <li>Siège social et showroom : {{ $c['address'] }}</li>
    <li>RCCM : {{ $c['rccm'] }}</li>
    <li>Numéro de compte contribuable (NCC) : {{ $c['ncc'] }}</li>
    <li>Téléphone : @foreach(config('site.contact.phones') as $i => $ph){{ $i ? ' / ' : '' }}<a href="tel:{{ $ph['tel'] }}">+225 {{ $ph['label'] }}</a>@endforeach</li>
    <li>WhatsApp : <a href="{{ config('site.contact.whatsapp') }}" rel="noopener">+225 {{ config('site.contact.phones')[0]['label'] }}</a></li>
    <li>E-mail : <a href="mailto:{{ config('site.contact.email') }}">{{ config('site.contact.email') }}</a></li>
    <li>Directeur de la publication : {{ $c['director'] }}</li>
</ul>

<h2>Hébergement</h2>
<p>Le site est hébergé par Hostinger International Ltd., 61 Lordou Vironos Street, 6023 Larnaca, Chypre (<a href="https://www.hostinger.com" rel="noopener">hostinger.com</a>).</p>

<h2>Propriété intellectuelle</h2>
<p>Les textes, photographies de réalisations, logos, vidéos et la mise en page du site sont la propriété de Maison du Store ou utilisés avec l’accord de leurs auteurs. Toute reproduction, même partielle, sans autorisation écrite est interdite. Les marques des fabricants restent la propriété de leurs titulaires.</p>

<h2>Photographies de chantiers</h2>
<p>Les photos de réalisations sont publiées avec l’accord des clients concernés, sans information permettant de les identifier. Un client peut demander le retrait d’une photo de son intérieur à l’adresse <a href="mailto:{{ config('site.contact.email') }}">{{ config('site.contact.email') }}</a>.</p>

<h2>Données personnelles</h2>
<p>Le traitement des données collectées sur le site est décrit dans la <a href="{{ route('site.legal', 'confidentialite') }}">politique de confidentialité</a>. @if($c['artci'])Il a fait l’objet d’une déclaration auprès de l’ARTCI sous le numéro {{ $c['artci'] }}.@else La déclaration de ce traitement auprès de l’ARTCI est en cours.@endif</p>
