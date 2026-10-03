@extends('staff.layout')
@section('title',$quote->exists ? 'Modifier le relevé' : 'Nouveau devis — relevé sur place')
@section('content')
<p>Renseignez les coordonnées du client, les ouvertures et vos observations. Admin-CM complétera les matériaux, les quantités, le chiffrage et les dates.</p>
<form method="post" id="agent-quote-form" action="{{ $quote->exists ? route('staff.quotes.form.update',$quote) : route('staff.quotes.store') }}">
@csrf @if($quote->exists) @method('PUT') @endif
<input type="hidden" name="submission_token" value="{{ old('submission_token',$quote->submission_token ?: (string)\Illuminate\Support\Str::uuid()) }}"><input type="hidden" name="edit_version" value="{{ old('edit_version',$quote->edit_version ?? 0) }}">
<section class="panel"><h2>Client</h2><div class="grid">
@foreach(['firstName'=>'Prénom','lastName'=>'Nom / raison sociale','phone'=>'Téléphone','email'=>'E-mail (facultatif)','address'=>'Adresse de pose','city'=>'Ville'] as $key=>$label)
@if($key==='phone')
@include('staff.quote-phone')
@else
<label>{{ $label }}<input name="contact[{{ $key }}]" type="{{ $key==='email'?'email':'text' }}" @required(in_array($key,['lastName','phone','address'])) value="{{ old('contact.'.$key,$quote->contact[$key] ?? '') }}"></label>
@endif
@endforeach
</div></section>
<section class="panel" id="agent-defaults"><h2>Choix communs</h2>@include('staff.agent-quote-selection',['prefix'=>'defaults','selection'=>old('defaults',$quote->configuration['defaults'] ?? [])])</section>
<h2>Mesures des ouvertures</h2><div id="agent-openings">@foreach(old('windows',$quote->dimensions ?: [['inherit_defaults'=>true]]) as $index=>$window) @include('staff.agent-quote-window') @endforeach</div>
<template id="agent-opening-template">@include('staff.agent-quote-window',['index'=>'__INDEX__','window'=>['inherit_defaults'=>true]])</template>
<button type="button" class="secondary" id="agent-add-opening">Ajouter une ouverture</button>
<section class="panel"><label>Notes de terrain<textarea name="field_notes" maxlength="10000">{{ old('field_notes',$quote->configuration['field_notes'] ?? '') }}</textarea></label></section>
<button>Enregistrer le devis</button></form>
<script type="application/json" id="agent-catalogue">@json($catalogue)</script>
<script src="{{ asset('agent-quote.js') }}?v={{ filemtime(public_path('agent-quote.js')) }}" defer></script>
@endsection
