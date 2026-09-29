
@extends('staff.layout')
@section('title','Préparer un message')
@section('content')
@php
 $draft['channel']=in_array(old('channel'),['email','whatsapp'],true) ? old('channel') : $draft['channel'];
 $draft['recipient']=$draft['recipients'][$draft['channel']];
@endphp
<form class="panel" method="get" action="{{ route('staff.messages.create') }}">
@foreach(['quote_id','invoice_id','appointment_id'] as $key)@if(!empty($draft[$key]))<input type="hidden" name="{{ $key }}" value="{{ $draft[$key] }}">@endif @endforeach
@if(empty($draft['quote_id']) && empty($draft['invoice_id']) && empty($draft['appointment_id']))
<label>Personnaliser pour un contact<select name="contact_id"><option value="">Choisir un contact</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}" @selected(($draft['contact_id'] ?? null)===$contact->id)>{{ $contact->name }}</option>@endforeach</select></label>
@elseif(!empty($draft['contact_id']))<input type="hidden" name="contact_id" value="{{ $draft['contact_id'] }}">@endif
<div class="grid"><label>Canal<select name="channel" id="message-channel"><option value="email" @selected($draft['channel']==='email')>E-mail</option><option value="whatsapp" @selected($draft['channel']==='whatsapp')>WhatsApp manuel</option></select></label>
<label>Modèle<select name="template_key">@foreach(\App\Services\CommunicationTemplates::LABELS as $key=>$label)<option value="{{ $key }}" @selected($draft['template_key']===$key)>{{ $label }}</option>@endforeach</select></label></div>
<button class="secondary">Charger le modèle</button><p class="muted">Charger un modèle remplace le texte non enregistré ci-dessous.</p></form>
<noscript><p class="notice">Après avoir choisi le canal, cliquez sur « Charger le modèle » pour adapter le destinataire.</p></noscript>
<form class="panel" id="message-compose" method="post" action="{{ route('staff.messages.store') }}" data-email="{{ $draft['recipients']['email'] }}" data-whatsapp="{{ $draft['recipients']['whatsapp'] }}" data-sender-email="{{ config('mail.from.address') }}" data-sender-whatsapp="{{ config('workspace.whatsapp_number') }}" data-document="{{ !empty($draft['quote_id']) || !empty($draft['invoice_id']) || !empty($draft['appointment_id']) ? '1' : '0' }}">
@csrf<input type="hidden" name="channel" value="{{ $draft['channel'] }}"><input type="hidden" name="template_key" value="{{ $draft['template_key'] }}">
<p class="muted">Expéditeur : <span data-message-sender>{{ $draft['channel']==='whatsapp' ? config('workspace.whatsapp_number') : config('mail.from.address') }}</span>. L’envoi se fait après relecture du brouillon.</p>
@foreach(['quote_id','invoice_id','appointment_id'] as $key)
@if(!empty($draft[$key]))<input type="hidden" name="{{ $key }}" value="{{ $draft[$key] }}">
@endif
@endforeach<div class="grid">
<label>Dossier associé<select name="contact_id">
<option value="">{{ $draft['channel']==='whatsapp' ? 'Aucun dossier associé' : 'Aucun / association par e-mail' }}</option>
@foreach($contacts as $contact)<option value="{{ $contact->id }}" data-email="{{ $contact->email }}" data-whatsapp="{{ $contact->phone }}" @selected(old('contact_id',$draft['contact_id'] ?? request('contact_id'))==$contact->id)>{{ $contact->name }}</option>
@endforeach</select>
</label>
<label><span data-recipient-label>{{ $draft['channel']==='whatsapp' ? 'Numéro du client avec indicatif pays' : 'Destinataire' }}</span><input name="recipient" type="{{ $draft['channel']==='whatsapp' ? 'tel' : 'email' }}" required value="{{ old('recipient',$draft['recipient']) }}">
</label>
<label>Nature<select name="kind">
@foreach(['commercial'=>'Échange commercial','promotion'=>'Offre / promotion','devis'=>'Devis','rendez_vous'=>'Rendez-vous','facture'=>'Facture'] as $value=>$label)<option value="{{ $value }}" @selected(old('kind',$draft['kind'])===$value)>{{ $label }}</option>
@endforeach</select>
</label>
</div>
<label>Objet<input name="subject" required maxlength="200" value="{{ old('subject',$draft['subject']) }}">
</label>
<label>Message<textarea name="body" required rows="14">{{ old('body',$draft['body']) }}</textarea>
</label>
@if(!empty($draft['invoice_id']) || !empty($draft['quote_id']))<p data-message-attachment>{{ $draft['channel']==='whatsapp' ? 'Le PDF devra être ajouté manuellement dans WhatsApp.' : 'Le document PDF sera joint lors de l’envoi.' }}</p>
@endif<p class="muted">Pour une promotion, indiquez comment demander l’arrêt des communications. L’envoi exige un consentement enregistré dans le dossier.</p>
<div class="actions">
<button>Enregistrer le brouillon</button>
</div>
</form>
<script src="{{ asset('message-compose.js') }}?v={{ filemtime(public_path('message-compose.js')) }}" defer></script>
@endsection
