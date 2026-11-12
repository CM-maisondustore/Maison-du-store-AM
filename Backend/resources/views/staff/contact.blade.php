@extends('staff.layout')
@section('title', $contact->name)
@section('back',route('staff.contacts.index'))
@section('crumbs')<a href="{{ route('staff.contacts.index') }}">Clients et contacts</a>@endsection
@section('subtitle',ucfirst($contact->kind).($contact->archived_at ? ' · Archivé' : ''))
@section('page-actions')
<a class="button quiet" href="{{ route('staff.messages.create',['contact_id'=>$contact->id]) }}">Préparer un e-mail</a>
<a class="button quiet" href="{{ route('staff.interventions.index',['contact_id'=>$contact->id]) }}#planifier">Planifier une prestation</a>
<form method="post" action="{{ route('staff.contacts.update',$contact) }}" data-confirm="{{ $contact->archived_at ? 'Réactiver ce dossier ?' : 'Archiver ce client ? Il reste consultable et son historique est conservé.' }}">
@csrf @method('PUT')
@foreach(['name'=>$contact->name,'kind'=>$contact->kind,'email'=>$contact->email,'phone'=>$contact->phone,'address'=>$contact->address,'notes'=>$contact->notes,'consent_source'=>$contact->consent_source] as $field=>$value)<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endforeach
@if($contact->marketing_consent_at)<input type="hidden" name="marketing" value="1">@endif
@unless($contact->archived_at)<input type="hidden" name="archived" value="1">@endunless
<button class="quiet">{{ $contact->archived_at ? 'Réactiver' : 'Archiver' }}</button>
</form>
@endsection
@section('content')
@php
 $relations=['quotes'=>['Devis','staff.quotes.show'],'appointments'=>['Rendez-vous','staff.appointments.show'],'interventions'=>['Prestations','staff.interventions.show'],'invoices'=>['Factures','staff.invoices.show'],'messages'=>['Communications','staff.messages.show']];
 $records=collect($relations)->map(fn($info,$relation)=>$contact->$relation()->latest()->limit(50)->get());
@endphp
<div class="with-aside">
<section class="panel flush" aria-labelledby="h-hist">
<div class="panel-head"><h2 id="h-hist">Historique du client</h2></div>
<div class="panel-body" data-tabs>
<div class="chips" role="tablist" aria-label="Historique">
@foreach($relations as $relation=>$info)<a class="chip" role="tab" href="#tab-{{ $relation }}" aria-controls="tab-{{ $relation }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $info[0] }}<b>{{ $records[$relation]->count() }}</b></a>@endforeach
</div>
@foreach($relations as $relation=>$info)
<div role="tabpanel" id="tab-{{ $relation }}" aria-label="{{ $info[0] }}">
<h3 class="sr-only">{{ $info[0] }}</h3>
<ul class="rows">
@forelse($records[$relation] as $record)
<li><a class="row" class="px-0" href="{{ route($info[1],$record) }}"><span><strong>{{ $record->subject ?? $record->number ?? $record->reference ?? ('Prestation #'.$record->id) }}</strong><small class="mono muted">{{ $record->created_at->format('d/m/Y') }}</small></span>@include('staff.status',['status'=>$record->status])</a></li>
@empty<li class="muted py-8">Aucun enregistrement.</li>
@endforelse
</ul>
</div>
@endforeach
</div>
</section>
<aside>
<section class="panel" aria-labelledby="h-coord">
<h2 id="h-coord">Dossier {{ $contact->kind }}</h2>
<p class="muted">Ce dossier ne donne aucun accès client au site.</p>
<form method="post" action="{{ route('staff.contacts.update',$contact) }}">
@csrf
@method('PUT')
@include('staff.contact-fields')<div class="actions">
<button class="secondary">Enregistrer</button>
</div>
</form>
</section>
</aside>
</div>
@include('staff.partials.record-actions',['type'=>'clients','record'=>$contact])
@endsection
