
@extends('staff.layout')
@section('title','Messagerie commerciale')
@section('content')<p>contact@maisondustoream.com</p>
@unless($configured)<div class="notice">Connexion Hostinger Email à activer. Vous pouvez déjà préparer des brouillons et consulter l’historique enregistré.</div>
@endunless<div class="actions">
<a class="button" href="{{ route('staff.messages.create') }}">Nouveau message</a>
<a href="{{ route('staff.messages.index',['direction'=>'incoming']) }}">Reçus</a>
<a href="{{ route('staff.messages.index',['direction'=>'outgoing']) }}">Brouillons & envois</a>
<a href="https://mail.hostinger.com" target="_blank" rel="noopener">Ouvrir le webmail</a>
</div>
<form class="panel" method="post" action="{{ route('staff.messages.sync') }}">
@csrf<div class="actions">
<label>Page à importer<input name="page" type="number" min="1" max="1000" value="1" style="width:100px">
</label>
<button @disabled(!$configured)>Relever les e-mails</button>
</div>
<p class="muted">20 messages par page, du plus récent au plus ancien. Lecture en texte seul, sans chargement d’images externes. Les messages de plus de 1 Mo et les pièces jointes se consultent dans le webmail. Les messages restent sur Hostinger.</p>
</form>
<div class="panel table-wrap">
<table>
<thead>
<tr>
<th>Objet</th>
<th>Correspondant</th>
<th>Date</th>
<th>État</th>
</tr>
</thead>
<tbody>
@forelse($messages as $message)<tr>
<td>
<a href="{{ route('staff.messages.show',$message) }}">{{ $message->subject ?: '(sans objet)' }}</a>
@if($message->direction==='incoming' && !$message->read_at)<small>Non lu</small>
@endif</td>
<td>{{ $message->direction==='incoming' ? $message->sender : $message->recipient }}<small>{{ $message->contact?->name }}</small>
</td>
<td>{{ $message->created_at->format('d/m/Y H:i') }}</td>
<td>
@include('staff.status',['status'=>$message->status])</td>
</tr>
@empty<tr>
<td colspan="4" class="empty">Aucun message enregistré.</td>
</tr>
@endforelse</tbody>
</table>
@include('staff.pagination',['paginator'=>$messages])</div>
@endsection
