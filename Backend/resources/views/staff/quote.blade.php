
@extends('staff.layout')
@section('title','Devis '.substr($quote->reference,0,8))
@section('content')<div class="actions">
@include('staff.status',['status'=>$quote->status])<a class="button secondary" href="{{ route('staff.quotes.pdf',$quote) }}">Télécharger le PDF</a>
@if(auth()->user()->isAdmin())<a class="button" href="{{ route('staff.messages.create',['quote_id'=>$quote->id]) }}">Préparer l’envoi du devis</a>
@endif</div>
<div class="grid">
<section class="panel">
<h2>Client</h2>
<dl class="detail-list">
@foreach($quote->contact as $key=>$value)<dt>{{ config('workspace.labels.'.$key, ucfirst($key)) }}</dt>
<dd>{{ $value }}</dd>
@endforeach</dl>
@if(auth()->user()->isAdmin() && $quote->contact_id)<p>
<a href="{{ route('staff.contacts.show',$quote->contact_id) }}">Ouvrir le dossier client</a>
</p>
@endif</section>
<section class="panel">
<h2>Projet {{ $quote->type }}</h2>
<dl class="detail-list">
@foreach($quote->product as $key=>$value)<dt>{{ config('workspace.labels.'.$key, ucfirst($key)) }}</dt>
<dd>{{ $value }}</dd>
@endforeach</dl>
<p class="prose">{{ $quote->notes }}</p>
</section>
</div>
<form class="panel" method="post" action="{{ route('staff.quotes.update',$quote) }}">
@csrf
@method('PUT')<h2>Mesures</h2>
<div id="dimensions">
@foreach(old('dimensions',$quote->dimensions ?? []) as $index=>$line)
@include('staff.dimension-line')
@endforeach</div>
<template id="dimensions-template">
@include('staff.dimension-line',['index'=>'__INDEX__','line'=>[]])</template>
<button type="button" class="secondary" data-add="dimensions">Ajouter une mesure</button>
<h2>Chiffrage</h2>
<div id="lines">
@foreach(old('lines',$quote->lines ?: [['description'=>'','quantity'=>1,'unit_price'=>0]]) as $index=>$line)
@include('staff.price-line')
@endforeach</div>
<template id="lines-template">
@include('staff.price-line',['index'=>'__INDEX__','line'=>[]])</template>
<button type="button" class="secondary" data-add="lines">Ajouter une ligne</button>
<div class="grid">
<label>Taux de taxe (%)<input type="number" name="tax_rate" min="0" max="100" step="0.01" required value="{{ old('tax_rate',$quote->tax_basis_points / 100) }}">
</label>
<label>Valable jusqu’au<input type="date" name="valid_until" required value="{{ old('valid_until',$quote->valid_until ?? now()->addDays(30)->format('Y-m-d')) }}">
</label>
@if(auth()->user()->isAdmin())<label>Agent<select name="agent_id">
<option value="">Non affecté</option>
@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('agent_id',$quote->agent_id)==$agent->id)>{{ $agent->name }}</option>
@endforeach</select>
</label>
<label>État<select name="status">
@foreach(['pending'=>'À traiter','draft'=>'Brouillon','approved'=>'Validé pour envoi','accepted'=>'Accepté par le client','rejected'=>'Refusé','cancelled'=>'Annulé'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$quote->status)===$value)>{{ $label }}</option>
@endforeach</select>
</label>
@endif</div>
<label>Notes internes / preuve d’accord du client<textarea name="internal_notes">{{ old('internal_notes',$quote->internal_notes) }}</textarea>
</label>
<p class="muted">L’acceptation est enregistrée par Admin-CM après accord du client. Elle verrouille le devis.</p>
<div class="actions">
<button @disabled($quote->status==='accepted' || (!auth()->user()->isAdmin() && !in_array($quote->status,['pending','draft'])))>Enregistrer le devis</button>
</div>
</form>
@if(auth()->user()->isAdmin() && $quote->status==='accepted')<form class="panel" method="post" action="{{ route('staff.invoices.create',$quote) }}">
@csrf<h2>Facturation</h2>
<label>Date d’échéance<input type="date" name="due_date" required value="{{ now()->addDays(30)->format('Y-m-d') }}">
</label>
<div class="actions">
<button>Préparer la facture</button>
</div>
</form>
@endif
@endsection
