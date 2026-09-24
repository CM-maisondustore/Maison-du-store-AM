
@extends('staff.layout')
@section('title','Préparer un message')
@section('content')<form class="panel" method="post" action="{{ route('staff.messages.store') }}">
@csrf<p class="muted">Expéditeur : contact@maisondustoream.com. L’envoi se fait après relecture du brouillon.</p>
@foreach(['quote_id','invoice_id','appointment_id'] as $key)
@if(!empty($draft[$key]))<input type="hidden" name="{{ $key }}" value="{{ $draft[$key] }}">
@endif
@endforeach<div class="grid">
<label>Dossier associé<select name="contact_id">
<option value="">Aucun / association par e-mail</option>
@foreach($contacts as $contact)<option value="{{ $contact->id }}" @selected(old('contact_id',$draft['contact_id'] ?? request('contact_id'))==$contact->id)>{{ $contact->name }} — {{ $contact->email }}</option>
@endforeach</select>
</label>
<label>Destinataire<input name="recipient" type="email" required value="{{ old('recipient',$draft['recipient']) }}">
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
@if(!empty($draft['invoice_id']) || !empty($draft['quote_id']))<p>Le document PDF sera joint lors de l’envoi.</p>
@endif<p class="muted">Pour une promotion, indiquez comment demander l’arrêt des communications. L’envoi exige un consentement enregistré dans le dossier.</p>
<div class="actions">
<button>Enregistrer le brouillon</button>
</div>
</form>
@endsection
