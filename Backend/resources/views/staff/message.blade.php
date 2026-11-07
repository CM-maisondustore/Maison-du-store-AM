@extends('staff.layout')
@section('title',$message->subject ?: '(sans objet)')
@section('back',route('staff.messages.index'))
@section('crumbs')<a href="{{ route('staff.messages.index') }}">Messagerie</a>@endsection
@section('page-actions')@include('staff.status',['status'=>$message->status])@endsection
@section('content')
@php
 $blocked=null;
 if($message->direction==='outgoing' && $message->status==='draft') {
  try { app(\App\Services\CommunicationAutomation::class)->validateMessage($message); }
  catch(\Illuminate\Validation\ValidationException $e) { $blocked=collect($e->errors())->flatten()->first(); }
 }
@endphp
<div class="mail">
@include('staff.partials.message-list',['threads'=>$threads,'current'=>$message->id,'secondary'=>true])
<section class="panel" aria-label="Lecture du message">
@if($blocked)<div class="notice errors" role="alert"><strong>Envoi bloqué.</strong> {{ $blocked }} Préparez un nouveau brouillon avec les données actuelles.</div>@endif
<dl class="detail-list">
<dt>Canal</dt><dd>{{ $message->channel==='whatsapp' ? 'WhatsApp manuel' : 'E-mail' }}</dd>
<dt>De</dt>
<dd>{{ $message->sender }}</dd>
<dt>À</dt>
<dd>{{ $message->recipient }}</dd>
<dt>Objet</dt>
<dd>{{ $message->subject }}</dd>
</dl>
@if($message->contact_id)<p><a class="link" href="{{ route('staff.contacts.show',$message->contact_id) }}">Fiche client</a>@if($message->quote_id) · <a class="link" href="{{ route('staff.quotes.show',$message->quote_id) }}">Devis lié</a>@endif</p>@endif
<div class="divider"></div>
<div class="prose">{{ $message->body }}</div>
@if($message->automation_event)<p class="muted">Préparé par une règle : {{ \App\Services\CommunicationTemplates::LABELS[$message->automation_event] ?? $message->automation_event }}. {{ $message->auto_send && $message->status==='draft' ? 'En attente d’envoi automatique si la règle et la connexion restent actives.' : 'Relecture et envoi manuels.' }}</p>@endif
@if($message->status==='shared')<p>Partage déclaré manuellement ; réception non vérifiée par WhatsApp.</p>@endif
@if($message->direction==='outgoing' && $message->status==='draft')
<details class="mt-12"><summary class="link">Modifier le brouillon</summary>
<form method="post" action="{{ route('staff.messages.update',$message) }}">
@csrf @method('PUT')
<label>Objet<input name="subject" required maxlength="200" value="{{ old('subject',$message->subject) }}"></label>
<label>Message<textarea name="body" required rows="12">{{ old('body',$message->body) }}</textarea></label>
<div class="actions"><button class="secondary">Enregistrer les modifications</button></div>
</form></details>
@endif
@if($message->document_snapshot)<p><a href="{{ route('staff.messages.attachment',$message) }}">{{ $message->archive ? 'Télécharger le PDF exact archivé' : 'Télécharger le PDF reconstitué depuis les données historiques' }}</a></p>@endif
@if(($message->invoice_id || $message->quote_id) && $message->automation_event!=='quote_received')<p class="muted">Document : {{ $message->invoice_id ? 'facture.pdf' : 'devis.pdf' }}</p>
@if($message->channel==='whatsapp')<p><a href="{{ $message->invoice_id ? route('staff.invoices.pdf',$message->invoice_id) : route('staff.quotes.pdf',$message->quote_id) }}">Télécharger le PDF</a> (pour le joindre manuellement si besoin)</p><p class="muted">Un lien sécurisé vers ce PDF, valable {{ \App\Http\Controllers\Staff\MessageController::SHARED_LINK_DAYS }} jours, est ajouté automatiquement à la fin du message WhatsApp. Le PDF est figé à l’ouverture de WhatsApp.</p>@endif
@endif<div class="actions">
@if($message->direction==='incoming')<a class="button" href="{{ route('staff.messages.create',['reply'=>$message->id]) }}">Répondre</a>
@elseif($message->status==='draft' && $message->channel==='whatsapp')
<a class="button" href="{{ route('staff.messages.whatsapp',$message) }}" target="_blank" rel="noopener">Ouvrir le message dans WhatsApp</a>
<form method="post" action="{{ route('staff.messages.shared',$message) }}" data-confirm="Confirmez-vous avoir partagé ce message manuellement ?">@csrf<button class="secondary">Marquer comme partagé</button></form>
@elseif($message->status==='draft')<form method="post" action="{{ route('staff.messages.send',$message) }}" data-confirm="Envoyer ce message à {{ $message->recipient }} ?">
@csrf<button @disabled($blocked)>Envoyer depuis contact@maisondustoream.com</button>
</form>
@endif</div>
@if($message->direction==='outgoing' && $message->status==='draft')<div class="actions">
<a href="{{ route('staff.messages.create',array_filter(['channel'=>$message->channel,'contact_id'=>$message->contact_id,'quote_id'=>$message->quote_id,'invoice_id'=>$message->invoice_id,'appointment_id'=>$message->appointment_id,'template_key'=>$message->template_snapshot['key'] ?? null])) }}">Préparer un nouveau brouillon avec les données actuelles</a>
<form method="post" action="{{ route('staff.messages.cancel',$message) }}">@csrf<button class="quiet">Annuler ce brouillon</button></form></div>@endif
@if(in_array($message->status,['uncertain','sending']))<div class="notice">L’état d’envoi doit être vérifié dans Hostinger avant tout nouvel envoi. Aucun renvoi automatique n’est effectué.</div>
@endif
</section>
</div>
@endsection
