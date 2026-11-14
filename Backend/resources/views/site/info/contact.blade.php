@php($c = config('site.contact'))
<p>Venez toucher les tissus et voir nos stores en situation au showroom de Cocody, ou contactez-nous par téléphone, WhatsApp ou e-mail. Un conseiller vous répond sous 24 h ouvrées.</p>

<h2>Showroom</h2>
<p>{{ $c['address'][0] }}, {{ $c['address'][1] }}<br><a href="{{ $c['maps'] }}" rel="noopener">Ouvrir dans Google Maps</a></p>

<h2>Téléphone et WhatsApp</h2>
<ul>
    @foreach($c['phones'] as $ph)<li><a href="tel:{{ $ph['tel'] }}">+225 {{ $ph['label'] }}</a></li>@endforeach
    <li>WhatsApp : <a href="{{ $c['whatsapp'] }}" rel="noopener">+225 {{ $c['phones'][0]['label'] }}</a></li>
</ul>

<h2>E-mail</h2>
<p><a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></p>

<h2>Devis et rendez-vous</h2>
<p>Pour un projet de stores ou de rideaux, <a href="{{ route('quote') }}">demandez un devis en ligne</a> ou <a href="{{ route('home') }}#rendez-vous">prenez rendez-vous</a> : au showroom, chez vous ou par téléphone.</p>
