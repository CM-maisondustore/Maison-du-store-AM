
@extends('staff.layout')
@section('title','Prestations des agents')
@section('content')<div class="panel">
@include('staff.jobs-table')
@include('staff.pagination',['paginator'=>$jobs])</div>
@if(auth()->user()->isAdmin())<section class="panel">
<h2>Planifier une prestation</h2>
<form method="post" action="{{ route('staff.interventions.store') }}">
@csrf<div class="grid">
<label>Client<select name="contact_id" required>
<option value="">Choisir le dossier</option>
@foreach($contacts as $contact)<option value="{{ $contact->id }}" @selected(old('contact_id',request('contact_id'))==$contact->id)>{{ $contact->name }}</option>
@endforeach</select>
</label>
<label>Prestation<select name="kind">
@foreach(['devis_sur_place'=>'Devis sur place','installation'=>'Installation','sav'=>'Service après-vente'] as $value=>$label)<option value="{{ $value }}" @selected(old('kind')===$value)>{{ $label }}</option>
@endforeach</select>
</label>
</div>
@include('staff.planning-fields',['job'=>new \App\Models\Intervention()])<div class="grid">
<label>Devis lié (optionnel)<select name="quote_id"><option value="">Aucun devis lié</option>
@foreach($quotes as $quote)<option value="{{ $quote->id }}" @selected(old('quote_id',request('quote_id'))==$quote->id)>{{ $quote->customer?->name }} · {{ substr($quote->reference,0,8) }}</option>
@endforeach</select>
</label>
<label>Rendez-vous lié (optionnel)<select name="appointment_id"><option value="">Aucun rendez-vous lié</option>
@foreach($appointments as $appointment)<option value="{{ $appointment->id }}" @selected(old('appointment_id',request('appointment_id'))==$appointment->id)>{{ $appointment->customer?->name }} · {{ $appointment->date }} {{ $appointment->time }}</option>
@endforeach</select>
</label>
</div>
<div class="actions">
<button>Planifier</button>
<a href="{{ route('staff.contacts.index') }}">Créer un client</a>
</div>
</form>
</section>
@endif
@endsection
