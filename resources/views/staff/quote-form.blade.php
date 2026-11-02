@extends('staff.layout')
@section('title',$quote->exists ? 'Préparer le devis' : 'Nouveau devis')
@section('back',$quote->exists ? route('staff.quotes.show',$quote) : route('staff.quotes.index'))
@section('crumbs')<a href="{{ route('staff.quotes.index') }}">Devis</a>@if($quote->exists) / <a class="mono" href="{{ route('staff.quotes.show',$quote) }}">{{ \App\Services\StaffInterface::ref($quote->reference) }}</a>@endif @endsection
@section('subtitle','Modifiez librement les choix et les mesures, puis enregistrez. Le devis restera en brouillon pour vérification.')
@section('body-class','has-action-bar')
@section('content')
@php
 $base=$quote->configuration['defaults'] ?? ['category'=>$quote->type ?? 'store','motorized'=>in_array($quote->product['control'] ?? '',['motorisee','Motorisée'],true)];
 $defaults=old('defaults',$base);
 $windows=old('windows',$quote->dimensions ?: [['label'=>'Ouverture 1','quantity'=>1,'inherit_defaults'=>true]]);
 $financial=$quote->configuration['calculation']['financial'] ?? null;
 $liveTotal=$financial && old('calculation_token',true);
 $pendingLines=$quote->configuration['calculation']['pending'] ?? [];
@endphp
<noscript><p class="notice">Activez JavaScript pour afficher les choix adaptés et ajouter des ouvertures.</p></noscript>
@unless($quote->exists)@include('staff.partials.boutique-picker',['active'=>''])@endunless
<form id="staff-quote-form" method="post" action="{{ $quote->exists ? route('staff.quotes.form.update',$quote) : route('staff.quotes.store') }}">
@csrf @if($quote->exists) @method('PUT') @endif
<input type="hidden" name="submission_token" value="{{ old('submission_token',$quote->submission_token ?: (string)\Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="edit_version" value="{{ old('edit_version',$quote->edit_version ?? 0) }}">
<input type="hidden" name="calculation_token" value="{{ old('calculation_token',!empty($quote->configuration['calculation']['financial']) ? \App\Services\QuoteCosting::token($quote->configuration['calculation']) : '') }}">
<div class="form-toc-layout">
<nav class="form-toc" aria-label="Sommaire du devis" data-step-nav="staff-quote-form"><ol>
@foreach(['s-client'=>'Client','quote-defaults'=>'Choix communs','s-fen'=>'Mesures','s-dates'=>'Dates et affectation','s-prix'=>'Chiffrage','s-pay'=>'Paiements','s-notes'=>'Notes'] as $anchor=>$label)<li><a href="#{{ $anchor }}"><strong>{{ $label }}</strong><small></small></a></li>@endforeach
</ol></nav>
<div class="form-steps">
<section class="panel" id="s-client" aria-labelledby="h-client"><h2 id="h-client" class="section-title"><span class="step" aria-hidden="true">1</span>Client</h2><div class="grid client-grid">
@foreach(['firstName'=>'Prénom','lastName'=>'Nom / raison sociale','phone'=>'Téléphone','email'=>'E-mail (facultatif)','address'=>'Adresse de livraison et de pose','city'=>'Ville'] as $key=>$label)
@if($key==='phone')
@include('staff.quote-phone')
@else
<label>{{ $label }}<input name="contact[{{ $key }}]" type="{{ $key==='email' ? 'email' : 'text' }}" @required(in_array($key,['lastName','phone','address'])) maxlength="{{ $key==='address' ? 1000 : ($key==='email' ? 255 : ($key==='phone' ? 50 : 100)) }}" value="{{ old('contact.'.$key,$quote->contact[$key] ?? '') }}"></label>
@endif
@endforeach
</div></section>
<section class="panel" id="quote-defaults" aria-labelledby="h-choix"><h2 id="h-choix" class="section-title"><span class="step" aria-hidden="true">2</span>Choix communs</h2>
<p class="muted">Sélectionnez séparément les tissus rideaux et les voilages, ou choisissez un store. Ces choix sont facultatifs si vous personnalisez chaque ouverture ; ils restent nécessaires pour les ouvertures utilisant les choix communs.</p>
@include('staff.quote-selection',['prefix'=>'defaults','selection'=>$defaults])
</section>
<section id="s-fen" aria-labelledby="h-fen" class="mb-20">
<h2 id="h-fen" class="section-title mb-8"><span class="step" aria-hidden="true">3</span>Mesures ouverture par ouverture</h2><p class="muted">Mesures en centimètres, de 1 à 2000 cm. Ajoutez une ouverture pour chaque configuration différente.</p>
<div id="quote-windows">@foreach($windows as $index=>$window) @include('staff.quote-window',['openCard'=>$loop->first || count($windows)<=3 || $errors->any()]) @endforeach</div>
<template id="quote-window-template">@include('staff.quote-window',['index'=>'__INDEX__','window'=>['inherit_defaults'=>true],'openCard'=>true])</template>
<div class="actions"><button type="button" class="secondary" id="add-quote-window">Ajouter une ouverture</button><span class="muted">30 ouvertures maximum</span></div>
</section>
<section class="panel" id="s-dates" aria-labelledby="h-dates"><h2 id="h-dates" class="section-title"><span class="step" aria-hidden="true">4</span>Dates et affectation</h2><div class="grid">
@foreach(['quote_date'=>'Date du devis','valid_until'=>'Valable jusqu’au','delivery_date'=>'Livraison prévue','installation_date'=>'Pose prévue'] as $key=>$label)
<label>{{ $label }}<input name="{{ $key }}" type="date" @required(in_array($key,['quote_date','valid_until'])) value="{{ old($key,$quote->$key ?: ($key==='quote_date' ? now()->format('Y-m-d') : ($key==='valid_until' ? now()->addDays(30)->format('Y-m-d') : ''))) }}"></label>
@endforeach
<label>Agent<select name="agent_id"><option value="">Non affecté</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('agent_id',$quote->agent_id)==$agent->id)>{{ $agent->name }}</option>@endforeach</select></label>
</div><p class="muted">Ces dates sont prévisionnelles. La planification d’une intervention se fait dans Prestations.</p></section>
<section class="panel" id="s-prix" aria-labelledby="h-prix" data-step-needs="calculation_token" data-step-todo="À calculer" data-step-done="Calculé"><h2 id="h-prix" class="section-title"><span class="step" aria-hidden="true">5</span>Chiffrage du devis</h2><p class="muted">Ajustez les quantités et les prix dans chaque ouverture. Un champ prix vide reprend le catalogue ; vos prix saisis sont conservés lors du recalcul. Les remises s’appliquent au total sans modifier les fournitures.</p>
@if(!empty($quote->configuration['pricing_review_required']))<p class="notice">Les choix de l’agent sont à vérifier avant validation du chiffrage.</p>@endif
@include('staff.quote-pricing-settings')
<label>Taux de taxe<span class="field-unit"><input type="number" name="tax_rate" min="0" max="100" step="0.01" required value="{{ old('tax_rate',($quote->tax_basis_points ?? 0)/100) }}"><span class="unit">%</span></span></label>
<div class="calc-bar"><button type="button" id="calculate-quote" data-url="{{ route('staff.quotes.calculate') }}">Calculer depuis le catalogue</button><p role="status" id="calculation-status">@if(!empty($quote->configuration['calculation']['pending']))Prix à définir : {{ implode(' ; ',$quote->configuration['calculation']['pending']) }}@endif</p></div>
<div id="lines" hidden>@foreach(old('lines',$quote->lines ?: [['description'=>'Fourniture, confection et installation','quantity'=>1,'unit_price'=>0]]) as $index=>$line) @include('staff.quote-calculated-line') @endforeach</div>
<template id="lines-template">@include('staff.quote-calculated-line',['index'=>'__INDEX__','line'=>[]])</template>
<div id="quote-financial-result" aria-live="polite">@if(!empty($quote->configuration['calculation']['financial'])) @include('staff.quote-financial-summary',['financial'=>$quote->configuration['calculation']['financial']]) @elseif($quote->exists)<p class="notice">Le chiffrage historique est conservé jusqu’à l’enregistrement. Vérifiez chaque poste avant de le remplacer par un nouveau calcul.</p>@endif</div>
</section>
<section class="panel" id="s-pay" aria-labelledby="h-pay"><h2 id="h-pay" class="section-title"><span class="step" aria-hidden="true">6</span>Conditions de paiement prévues</h2>
<input type="hidden" name="payment_mode" value="advance"><input type="hidden" name="deposit_amount" value="{{ old('deposit_amount',$quote->deposit_amount ?? 0) }}">
<p>L’acompte et le solde sont calculés sur le montant définitif après remise. Le pourcentage d’acompte se règle dans le chiffrage ci-dessus.</p>
<p class="muted">Déjà reçu : {{ number_format($quote->exists ? $quote->receivedAmount() : 0,0,',',' ') }} FCFA</p>
<details class="fold" @if(old('receipt_amount') || $errors->hasAny(['receipt_amount','receipt_date','receipt_method','receipt_reference','receipt_confirmed'])) open @endif><summary>Nouveau paiement déjà reçu<small>Seulement si un versement vient d’être encaissé</small></summary>@include('staff.quote-receipt-fields')</details>
</section>
<section class="panel" id="s-notes" aria-labelledby="h-notes"><h2 id="h-notes" class="section-title"><span class="step" aria-hidden="true">7</span>Notes</h2>
<div class="internal-block"><p class="internal-title">@include('staff.partials.icon',['n'=>'lock','s'=>16])Réservé à l’équipe</p>
<label for="quote-internal-notes">Notes internes</label><textarea id="quote-internal-notes" name="internal_notes" maxlength="10000">{{ old('internal_notes',$quote->internal_notes) }}</textarea></div>
<label>Notes de terrain<textarea name="field_notes" maxlength="10000">{{ old('field_notes',$quote->configuration['field_notes'] ?? '') }}</textarea></label></section>
</div>
</div>
<div class="action-bar"><div class="live-total" id="quote-live-total" data-state="{{ $pendingLines ? 'pending' : 'ok' }}" role="status" @unless($liveTotal) hidden @endunless><small data-live-label>{{ $pendingLines ? 'Total provisoire' : 'Total définitif' }}</small><strong data-live-amount>{{ $financial ? number_format($financial['finalTotal'],0,',',' ').' FCFA' : '' }}</strong></div><span class="status">Enregistré en brouillon, jamais envoyé sans action de votre part.</span><a class="button quiet" href="{{ $quote->exists ? route('staff.quotes.show',$quote) : route('staff.quotes.index') }}">Retour aux devis</a><button>Enregistrer le devis</button></div>
</form>
<script type="application/json" id="quote-catalogue-data">@json($catalogue)</script>
<script src="{{ asset('quote-editor.js') }}?v={{ filemtime(public_path('quote-editor.js')) }}" defer></script>
@endsection
