
@extends('staff.layout')
@section('title', $contact->name)
@section('content')<div class="panel">
<h2>Dossier {{ $contact->kind }}</h2>
<p class="muted">Ce dossier ne donne aucun accès client au site.</p>
<form method="post" action="{{ route('staff.contacts.update',$contact) }}">
@csrf
@method('PUT')
@include('staff.contact-fields')<div class="actions">
<button>Enregistrer</button>
<a href="{{ route('staff.messages.create',['contact_id'=>$contact->id]) }}">Préparer un e-mail</a>
</div>
</form>
</div>
<div class="grid">
@foreach(['quotes'=>['Devis','staff.quotes.show'],'appointments'=>['Rendez-vous','staff.appointments.show'],'interventions'=>['Prestations','staff.interventions.show'],'invoices'=>['Factures','staff.invoices.show'],'messages'=>['Communications','staff.messages.show']] as $relation=>$info)<section class="panel">
<h2>{{ $info[0] }}</h2>
@forelse($contact->$relation()->latest()->limit(50)->get() as $record)<p>
<a href="{{ route($info[1],$record) }}">{{ $record->subject ?? $record->number ?? $record->reference ?? ('Prestation #'.$record->id) }}</a>
<br>
<small>{{ $record->created_at->format('d/m/Y') }}</small>
@include('staff.status',['status'=>$record->status])</p>
@empty<p class="muted">Aucun enregistrement.</p>
@endforelse</section>
@endforeach</div>
@endsection
