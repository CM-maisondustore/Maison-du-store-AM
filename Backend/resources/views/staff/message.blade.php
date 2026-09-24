
@extends('staff.layout')
@section('title',$message->subject ?: '(sans objet)')
@section('content')<section class="panel">
@include('staff.status',['status'=>$message->status])<dl class="detail-list">
<dt>De</dt>
<dd>{{ $message->sender }}</dd>
<dt>À</dt>
<dd>{{ $message->recipient }}</dd>
<dt>Objet</dt>
<dd>{{ $message->subject }}</dd>
</dl>
<div class="divider">
</div>
<div class="prose">{{ $message->body }}</div>
@if($message->direction==='outgoing' && $message->status==='draft')
<details><summary>Modifier le brouillon</summary>
<form method="post" action="{{ route('staff.messages.update',$message) }}">
@csrf @method('PUT')
<label>Objet<input name="subject" required maxlength="200" value="{{ old('subject',$message->subject) }}"></label>
<label>Message<textarea name="body" required rows="12">{{ old('body',$message->body) }}</textarea></label>
<div class="actions"><button class="secondary">Enregistrer les modifications</button></div>
</form></details>
@endif
@if($message->document_snapshot)<p><a href="{{ route('staff.messages.attachment',$message) }}">Télécharger le document archivé lors de l’envoi</a></p>@endif
@if($message->invoice_id || $message->quote_id)<p>Pièce jointe prévue : {{ $message->invoice_id ? 'facture.pdf' : 'devis.pdf' }}</p>
@endif<div class="actions">
@if($message->direction==='incoming')<a class="button" href="{{ route('staff.messages.create',['reply'=>$message->id]) }}">Répondre</a>
@elseif($message->status==='draft')<form method="post" action="{{ route('staff.messages.send',$message) }}" data-confirm="Envoyer ce message à {{ $message->recipient }} ?">
@csrf<button>Envoyer depuis contact@maisondustoream.com</button>
</form>
@endif</div>
@if(in_array($message->status,['uncertain','sending']))<div class="notice">L’état d’envoi doit être vérifié dans Hostinger avant tout nouvel envoi. Aucun renvoi automatique n’est effectué.</div>
@endif</section>
@endsection
