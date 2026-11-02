@extends('staff.layout')
@section('title','Messagerie commerciale')
@section('subtitle','contact@maisondustoream.com')
@section('page-actions')
<a class="button" href="{{ route('staff.messages.create') }}">Nouveau message</a>
<a class="button secondary" href="{{ route('staff.messages.create',['channel'=>'whatsapp']) }}">Nouveau WhatsApp</a>
<a class="button quiet" href="{{ route('staff.communications.index') }}">Modèles &amp; automatisations</a>
<a class="button quiet" href="https://mail.hostinger.com" target="_blank" rel="noopener">Ouvrir le webmail</a>
@endsection
@section('content')
@unless($configured)<div class="notice">Connexion Hostinger Email à activer. Vous pouvez déjà préparer des brouillons et consulter l’historique enregistré.</div>
@endunless
<div class="mail">
<div>
@include('staff.partials.message-list',['threads'=>$messages])
@include('staff.pagination',['paginator'=>$messages])
</div>
<form class="panel" method="post" action="{{ route('staff.messages.sync') }}">
@csrf<h2>Synchroniser</h2>
<div class="grid">
<label>Page à importer<input name="page" type="number" min="1" max="1000" value="1"></label>
<div class="self-end"><button class="secondary" @disabled(!$configured)>Relever les e-mails</button></div>
</div>
<p class="muted">20 messages par page, du plus récent au plus ancien. Lecture en texte seul, sans chargement d’images externes. Les messages de plus de 1 Mo et les pièces jointes se consultent dans le webmail. Les messages restent sur Hostinger.</p>
<p class="muted">Sélectionnez un message dans la liste pour le lire.</p>
</form>
</div>
@endsection
