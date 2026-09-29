@extends('staff.layout')
@section('title',$quote->exists ? 'Préparer le devis' : 'Nouveau devis')
@section('content')
@php
 $base=$quote->configuration['defaults'] ?? ['category'=>$quote->type ?? 'store','motorized'=>false];
 $defaults=old('defaults',$base);
 $windows=old('windows',$quote->dimensions ?: [['label'=>'Fenêtre 1','quantity'=>1,'inherit_defaults'=>true]]);
@endphp
<div class="actions"><a href="{{ $quote->exists ? route('staff.quotes.show',$quote) : route('staff.quotes.index') }}">← Retour aux devis</a></div>
<p>Modifiez librement les choix et les mesures, puis cliquez sur <strong>Enregistrer le devis</strong>. Le devis restera en brouillon pour vérification.</p>
<noscript><p class="notice">Activez JavaScript pour afficher les choix adaptés et ajouter des fenêtres.</p></noscript>
<form id="staff-quote-form" method="post" action="{{ $quote->exists ? route('staff.quotes.form.update',$quote) : route('staff.quotes.store') }}">
@csrf @if($quote->exists) @method('PUT') @endif
<input type="hidden" name="submission_token" value="{{ old('submission_token',$quote->submission_token ?: (string)\Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="edit_version" value="{{ old('edit_version',$quote->edit_version ?? 0) }}">
@if(auth()->user()->isAdmin())<input type="hidden" name="calculation_token" value="{{ old('calculation_token',!empty($quote->configuration['calculation']) ? \App\Services\QuoteCosting::token($quote->configuration['calculation']) : '') }}">@endif
<section class="panel"><h2>Client</h2><div class="grid">
@foreach(['firstName'=>'Prénom','lastName'=>'Nom / raison sociale','phone'=>'Téléphone','email'=>'E-mail (facultatif)','address'=>'Adresse de livraison et de pose','city'=>'Ville'] as $key=>$label)
<label>{{ $label }}<input name="contact[{{ $key }}]" type="{{ $key==='email' ? 'email' : 'text' }}" @required(in_array($key,['lastName','phone','address'])) maxlength="{{ $key==='address' ? 1000 : ($key==='email' ? 255 : ($key==='phone' ? 50 : 100)) }}" value="{{ old('contact.'.$key,$quote->contact[$key] ?? '') }}"></label>
@endforeach
</div></section>
<section class="panel" id="quote-defaults"><h2>Choix communs</h2>
<p class="muted">Sélectionnez séparément les tissus rideaux et les voilages, ou choisissez un store. Vous pourrez personnaliser chaque fenêtre.</p>
@include('staff.quote-selection',['prefix'=>'defaults','selection'=>$defaults])
</section>
<h2>Mesures fenêtre par fenêtre</h2><p class="muted">Mesures en centimètres, de 1 à 2000 cm. Ajoutez une fenêtre pour chaque configuration différente.</p>
<div id="quote-windows">@foreach($windows as $index=>$window) @include('staff.quote-window') @endforeach</div>
<template id="quote-window-template">@include('staff.quote-window',['index'=>'__INDEX__','window'=>['inherit_defaults'=>true]])</template>
<div class="actions"><button type="button" class="secondary" id="add-quote-window">Ajouter une fenêtre</button><span class="muted">30 fenêtres maximum</span></div>
@if(auth()->user()->isAdmin())
<section class="panel"><h2>Chiffrage du devis</h2><p class="muted">Calculez les lignes depuis le catalogue, puis ajustez librement les prix et descriptions avant d’enregistrer. Un nouveau calcul remplace les lignes de chiffrage actuelles.</p>
@if(!empty($quote->configuration['pricing_review_required']))<p class="notice">Les choix de l’agent sont à vérifier avant validation du chiffrage.</p>@endif
<label>Mode de chiffrage<select name="use_catalogue"><option value="1" @selected(old('use_catalogue',!$quote->exists || !empty($quote->configuration['calculation']) ? '1' : '0')==='1')>À partir du catalogue</option><option value="0" @selected((string)old('use_catalogue',$quote->exists && empty($quote->configuration['calculation']) ? '0' : '1')==='0')>Saisie entièrement manuelle des lignes</option></select></label>
<button type="button" id="calculate-quote" data-url="{{ route('staff.quotes.calculate') }}">Calculer depuis le catalogue</button>
<p role="status" id="calculation-status">@if(!empty($quote->configuration['calculation']['pending']))Prix à définir : {{ implode(' ; ',$quote->configuration['calculation']['pending']) }}@endif</p>
<div id="lines">@foreach(old('lines',$quote->lines ?: [['description'=>'Fourniture, confection et installation','quantity'=>1,'unit_price'=>0]]) as $index=>$line) @include('staff.price-line') @endforeach</div>
<template id="lines-template">@include('staff.price-line',['index'=>'__INDEX__','line'=>[]])</template>
<button type="button" class="secondary" data-add="lines">Ajouter une ligne</button>
<label>Taux de taxe (%)<input type="number" name="tax_rate" min="0" max="100" step="0.01" required value="{{ old('tax_rate',($quote->tax_basis_points ?? 0)/100) }}"></label>
<p class="total" aria-live="polite">Total : <strong data-quote-total>0</strong> FCFA</p>
</section>
@endif
<section class="panel"><h2>Dates et affectation</h2><div class="grid">
@foreach(['quote_date'=>'Date du devis','valid_until'=>'Valable jusqu’au','delivery_date'=>'Livraison prévue','installation_date'=>'Pose prévue'] as $key=>$label)
<label>{{ $label }}<input name="{{ $key }}" type="date" @required(in_array($key,['quote_date','valid_until'])) value="{{ old($key,$quote->$key ?: ($key==='quote_date' ? now()->format('Y-m-d') : ($key==='valid_until' ? now()->addDays(30)->format('Y-m-d') : ''))) }}"></label>
@endforeach
@if(auth()->user()->isAdmin())<label>Agent<select name="agent_id"><option value="">Non affecté</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('agent_id',$quote->agent_id)==$agent->id)>{{ $agent->name }}</option>@endforeach</select></label>@endif
</div><p class="muted">Ces dates sont prévisionnelles. La planification d’une intervention se fait dans Prestations.</p></section>
@if(auth()->user()->isAdmin())<section class="panel"><h2>Conditions de paiement prévues</h2><div class="grid">
<label>Modalité<select name="payment_mode"><option value="advance" @selected(old('payment_mode',$quote->payment_mode)==='advance')>Avance puis solde</option><option value="full" @selected(old('payment_mode',$quote->payment_mode)==='full')>Paiement intégral</option></select></label>
<label>Avance prévue (FCFA)<input required name="deposit_amount" type="number" min="0" max="1000000000000" step="1" value="{{ old('deposit_amount',$quote->deposit_amount ?? 0) }}"></label>
</div><p class="muted">Déjà reçu : {{ number_format($quote->exists ? $quote->receivedAmount() : 0,0,',',' ') }} FCFA</p>
<h3>Nouveau paiement déjà reçu</h3>@include('staff.quote-receipt-fields')
</section>
<section class="panel"><label for="quote-internal-notes">Notes internes</label><textarea id="quote-internal-notes" name="internal_notes" maxlength="10000">{{ old('internal_notes',$quote->internal_notes) }}</textarea></section>
@endif
<section class="panel"><label>Notes de terrain<textarea name="field_notes" maxlength="10000">{{ old('field_notes',$quote->configuration['field_notes'] ?? '') }}</textarea></label></section>
<div class="actions"><button>Enregistrer le devis</button></div>
</form>
<script type="application/json" id="quote-catalogue-data">@json($catalogue)</script>
<script src="{{ asset('quote-editor.js') }}?v={{ filemtime(public_path('quote-editor.js')) }}" defer></script>
@endsection
