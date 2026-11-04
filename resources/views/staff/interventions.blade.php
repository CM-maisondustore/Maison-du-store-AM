@extends('staff.layout')
@php($admin=auth()->user()->isAdmin())
@section('title', $admin ? 'Prestations des agents' : 'Mes prestations')
@section('subtitle','Installations, services après-vente et devis sur place confiés aux agents.')
@if($admin)
@section('page-actions')<a class="button" href="#planifier">@include('staff.partials.icon',['n'=>'plus','s'=>18])Nouvelle prestation</a>@endsection
@endif
@section('content')
@php($keep=request()->except(['status','page']))
<nav class="chips" aria-label="Filtrer par statut">
<a class="chip" href="{{ route('staff.interventions.index',$keep) }}" @if(!request('status')) aria-current="true" @endif>Toutes<b>{{ $counts->sum() }}</b></a>
@foreach(['planned'=>'Planifié','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'] as $value=>$label)
<a class="chip" href="{{ route('staff.interventions.index',[...$keep,'status'=>$value]) }}" @if(request('status')===$value) aria-current="true" @endif>{{ $label }}<b>{{ $counts[$value] ?? 0 }}</b></a>
@endforeach
</nav>
<section class="panel flush" aria-label="Liste des prestations">
<form class="filters" method="get" action="{{ route('staff.interventions.index') }}">
@if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
<label class="grow">Rechercher une prestation<input type="search" name="q" maxlength="150" value="{{ request('q') }}" placeholder="Client ou lieu"></label>
@if($admin)<label>Agent<select name="agent"><option value="">Tous</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(request('agent')==$agent->id)>{{ $agent->name }}</option>@endforeach</select></label>@endif
<button class="secondary">Filtrer</button>
</form>
@include('staff.jobs-table')
@include('staff.pagination',['paginator'=>$jobs])
</section>
@if($admin)
@php($linked=old('quote_id',request('quote_id')) || old('appointment_id',request('appointment_id')))
<section class="float-panel" id="planifier" @if(request('contact_id') || request('quote_id') || request('appointment_id') || $errors->any()) data-open @endif>
<form id="planifier-form" class="float-form wide" method="post" action="{{ route('staff.interventions.store') }}" role="dialog" aria-modal="true" aria-labelledby="h-planifier">
@csrf
<div class="float-head"><div><h2 id="h-planifier">Nouvelle prestation</h2><p class="muted">L’agent choisi est prévenu sur son compte. Le client ne reçoit aucun message.</p></div><a class="button quiet icon-btn" href="{{ route('staff.interventions.index') }}" data-close-panel aria-label="Fermer sans planifier">@include('staff.partials.icon',['n'=>'close','s'=>18])</a></div>
<div class="float-body"><div class="float-cols">
<div class="fields-col">
<section class="fgroup">
<h3 class="section-title"><span class="step" aria-hidden="true">1</span>Client</h3>
<label>Client<select name="contact_id" required>
<option value="">Choisir le dossier</option>
@foreach($contacts as $contact)<option value="{{ $contact->id }}" data-address="{{ $contact->address }}" @selected(old('contact_id',request('contact_id'))==$contact->id)>{{ $contact->name }}</option>
@endforeach</select>
</label>
<p class="hint"><a class="link" href="{{ route('staff.contacts.index') }}#nouveau-contact" target="_blank" rel="noopener" data-new-client>Créer un client</a> : s’ouvre dans un nouvel onglet, votre saisie reste ici.</p>
</section>
<section class="fgroup">
<fieldset class="bare">
<legend class="section-title"><span class="step" aria-hidden="true">2</span>Type de prestation</legend>
<div class="tile-group kind-tiles">
@foreach(\App\Services\StaffInterface::JOB_KINDS as $value=>$label)<label class="tile"><input type="radio" name="kind" value="{{ $value }}" @checked(old('kind',array_key_first(\App\Services\StaffInterface::JOB_KINDS))===$value)><span><strong>{{ $label }}</strong><small>{{ ['devis_sur_place'=>'Relevé et mesures','installation'=>'Pose chez le client','sav'=>'Réglage ou réparation'][$value] ?? '' }}</small></span></label>
@endforeach</div>
</fieldset>
</section>
</div>
<div class="fields-col">
@include('staff.planning-fields',['job'=>new \App\Models\Intervention(),'stepped'=>true])
<details class="fold" @if($linked || $errors->hasAny(['quote_id','appointment_id'])) open @endif>
<summary>Liens optionnels<small>Devis et rendez-vous du client choisi</small></summary>
<label class="mt-0">Devis lié (optionnel)<select name="quote_id"><option value="">Aucun devis lié</option>
@foreach($quotes as $quote)<option value="{{ $quote->id }}" data-contact="{{ $quote->contact_id }}" @selected(old('quote_id',request('quote_id'))==$quote->id)>{{ $quote->customer?->name }} · {{ \App\Services\StaffInterface::ref($quote->reference) }}</option>
@endforeach</select>
</label>
<label>Rendez-vous lié (optionnel)<select name="appointment_id"><option value="">Aucun rendez-vous lié</option>
@foreach($appointments as $appointment)<option value="{{ $appointment->id }}" data-contact="{{ $appointment->contact_id }}" @selected(old('appointment_id',request('appointment_id'))==$appointment->id)>{{ $appointment->customer?->name }} · {{ $appointment->date }} {{ $appointment->time }}</option>
@endforeach</select>
</label>
</details>
</div>
</div></div>
<div class="float-foot"><span class="status">La fiche de la prestation s’ouvre après l’enregistrement.</span><a class="button quiet" href="{{ route('staff.interventions.index') }}" data-close-panel>Annuler</a><button>Planifier la prestation</button></div>
</form>
</section>
<script type="application/json" id="agent-planning">@json($planning)</script>
@endif
@endsection
