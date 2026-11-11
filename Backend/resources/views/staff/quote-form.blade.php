@extends('staff.layout')
@section('title',$quote->exists ? 'Préparer le devis' : 'Nouveau devis')
@section('back',$quote->exists ? route('staff.quotes.show',$quote) : route('staff.quotes.index'))
@section('crumbs')<a href="{{ route('staff.quotes.index') }}">Devis</a>@if($quote->exists) / <a class="mono" href="{{ route('staff.quotes.show',$quote) }}">{{ \App\Services\StaffInterface::ref($quote->reference) }}</a>@endif @endsection
@section('subtitle','Quatre étapes. Le devis reste en brouillon : rien n’est envoyé au client sans action de votre part.')
@section('body-class','has-action-bar')
@section('content')
@php
 // Demande du site : type, manœuvre, tissus et finition choisis par le client pré-remplissent les choix communs.
 $base=$quote->configuration['defaults'] ?? \App\Services\PublicQuoteRequest::defaults($quote);
 $defaults=old('defaults',$base);
 $windows=old('windows',$quote->dimensions ?: [['label'=>'Ouverture 1','quantity'=>1,'inherit_defaults'=>true]]);
 $financial=$quote->configuration['calculation']['financial'] ?? null;
 $liveTotal=$financial && old('calculation_token',true);
 $pendingLines=$quote->configuration['calculation']['pending'] ?? [];
 $steps=[1=>'Client et ouvertures',2=>'Matériaux et finitions',3=>'Affectation et chiffrage',4=>'Paiement'];
@endphp
<noscript><p class="notice">Activez JavaScript pour afficher les choix adaptés et ajouter des ouvertures.</p></noscript>
@unless($quote->exists)@include('staff.partials.boutique-picker',['active'=>''])@endunless
<nav class="wizard" data-wizard-for="staff-quote-form" aria-label="Étapes du devis"><ol>
@foreach($steps as $n=>$label)<li><button type="button" data-wizard-go="{{ $n }}"><span class="wizard-ring" aria-hidden="true"></span><small>Étape {{ $n }}</small><strong>{{ $label }}</strong><small data-wizard-state></small></button></li>@endforeach
</ol></nav>
<form id="staff-quote-form" data-wizard method="post" enctype="multipart/form-data" action="{{ $quote->exists ? route('staff.quotes.form.update',$quote) : route('staff.quotes.store') }}">
@csrf @if($quote->exists) @method('PUT') @endif
<input type="hidden" name="submission_token" value="{{ old('submission_token',$quote->submission_token ?: (string)\Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="edit_version" value="{{ old('edit_version',$quote->edit_version ?? 0) }}">
<input type="hidden" name="calculation_token" value="{{ old('calculation_token',!empty($quote->configuration['calculation']['financial']) ? \App\Services\QuoteCosting::token($quote->configuration['calculation']) : '') }}">

<section class="panel" id="quote-defaults" data-step-section="2" aria-labelledby="h-choix"><h2 id="h-choix">Choix communs</h2>
<p class="muted">Appliqués à toutes les ouvertures, sauf celles que vous personnalisez ci-dessous. Facultatifs si vous personnalisez chaque ouverture.</p>
{{-- Pastilles remplies par quote-editor.js à partir des matériaux en base ; les menus restent les champs enregistrés. --}}
<div class="choice-board" data-choice-board></div>
@include('staff.quote-selection',['prefix'=>'defaults','selection'=>$defaults])
<div class="supply-board" data-supply-board hidden><h3 class="choice-title">Fournitures communes</h3><p class="hint">Oui ou non pour chaque fourniture des ouvertures qui suivent les choix communs. Quantités et prix : sous chaque ouverture.</p><div data-supply-lines></div></div>
</section>

<div class="wizard-row">
<section class="panel wizard-side" id="s-client" data-step-section="1" aria-labelledby="h-client"><h2 id="h-client">Client</h2><div class="grid">
@foreach(['lastName'=>'Nom / raison sociale','firstName'=>'Prénom','phone'=>'Téléphone','email'=>'E-mail (facultatif)'] as $key=>$label)
@if($key==='phone')
@include('staff.quote-phone')
@else
<label>{{ $label }}<input name="contact[{{ $key }}]" type="{{ $key==='email' ? 'email' : 'text' }}" @required(in_array($key,['lastName','phone'])) maxlength="{{ $key==='email' ? 255 : 100 }}" value="{{ old('contact.'.$key,$quote->contact[$key] ?? '') }}"></label>
@endif
@endforeach
@include('staff.quote-address',['addressLabel'=>'Complément d’adresse de livraison et de pose'])
</div><p class="hint">Le dossier client est créé, ou retrouvé par son e-mail, à l’enregistrement.</p></section>

<section class="wizard-main" id="s-fen" data-step-section="1 2" aria-labelledby="h-fen">
<div class="wizard-head" data-step-part="1"><h2 id="h-fen">Ouvertures</h2><p class="muted">Désignation, mesures en centimètres (de 1 à 2000 cm) et notes de chaque ouverture.</p></div>
<div class="wizard-head" data-step-part="2"><h2>Par ouverture</h2><p class="muted">Changez un menu pour personnaliser une seule ouverture ; recochez « Choix communs » pour revenir aux choix communs. Quantités, fournitures et prix : sous chaque ouverture. Confection et pose : étape 3.</p></div>
<div class="openings">
<div class="openings-head" data-step-part="2" aria-hidden="true"><span>Ouverture</span><span>Projet</span><span>Motorisé</span><span>Tissu ou matériau</span><span>Voilage</span><span>Finition</span><span>Rail / support</span><span>Coefficient</span></div>
<div id="quote-windows">@foreach($windows as $index=>$window) @include('staff.quote-window',['openCard'=>true]) @endforeach</div>
</div>
<template id="quote-window-template">@include('staff.quote-window',['index'=>'__INDEX__','window'=>['inherit_defaults'=>true],'openCard'=>true])</template>
<div class="actions" data-step-part="1"><button type="button" class="secondary" id="add-quote-window">Ajouter une ouverture</button><span class="muted">30 ouvertures maximum</span></div>
<section class="panel" data-step-part="1"><label class="mt-0">Notes de terrain<textarea name="field_notes" rows="2" maxlength="10000" placeholder="Accès, étage, contraintes de pose, souhaits du client…">{{ old('field_notes',$quote->configuration['field_notes'] ?? '') }}</textarea></label></section>
</section>
</div>

<div class="wizard-row">
<section class="panel wizard-side" id="s-dates" data-step-section="3" aria-labelledby="h-dates"><h2 id="h-dates">Affectation</h2>
<label>Agent<select name="agent_id"><option value="">Non affecté</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('agent_id',$quote->agent_id)==$agent->id)>{{ $agent->name }}</option>@endforeach</select></label>
<div class="grid dates-grid">
@foreach(['quote_date'=>'Date du devis','valid_until'=>'Valable jusqu’au','delivery_date'=>'Livraison prévue','installation_date'=>'Pose prévue'] as $key=>$label)
<label>{{ $label }}<input name="{{ $key }}" type="date" @required(in_array($key,['quote_date','valid_until'])) value="{{ old($key,$quote->$key ?: ($key==='quote_date' ? now()->format('Y-m-d') : ($key==='valid_until' ? now()->addDays(30)->format('Y-m-d') : ''))) }}"></label>
@endforeach
</div>
<p class="hint">Dates prévisionnelles. La prestation se planifie ensuite dans Prestations.</p>
<div class="internal-block"><p class="internal-title">@include('staff.partials.icon',['n'=>'lock','s'=>16])Réservé à l’équipe</p>
<label for="quote-internal-notes">Notes internes</label><textarea id="quote-internal-notes" name="internal_notes" rows="3" maxlength="10000">{{ old('internal_notes',$quote->internal_notes) }}</textarea></div>
</section>

<section class="panel wizard-main" id="s-prix" data-step-section="3" aria-labelledby="h-prix" data-step-needs="calculation_token"><h2 id="h-prix">Chiffrage</h2><p class="muted">Un champ prix vide reprend le catalogue ; vos prix saisis sont conservés lors du recalcul. Les remises s’appliquent au total sans modifier les fournitures. Quantités et fournitures ouverture par ouverture : étape 2 ; confection et pose : ci-dessous.</p>
@if(!empty($quote->configuration['pricing_review_required']))<p class="notice">Les choix de l’agent sont à vérifier avant validation du chiffrage.</p>@endif
@include('staff.quote-coefficients')
@include('staff.quote-pricing-settings')
<label>Taux de taxe<span class="field-unit"><input type="number" name="tax_rate" min="0" max="100" step="0.01" required value="{{ old('tax_rate',($quote->tax_basis_points ?? 0)/100) }}"><span class="unit">%</span></span></label>
<div class="calc-bar"><button type="button" id="calculate-quote" data-url="{{ route('staff.quotes.calculate') }}">Calculer depuis le catalogue</button><p role="status" id="calculation-status">@if(!empty($quote->configuration['calculation']['pending']))Prix à définir : {{ implode(' ; ',$quote->configuration['calculation']['pending']) }}@endif</p></div>
<div id="lines" hidden>@foreach(old('lines',$quote->lines ?: [['description'=>'Fourniture, confection et installation','quantity'=>1,'unit_price'=>0]]) as $index=>$line) @include('staff.quote-calculated-line') @endforeach</div>
<template id="lines-template">@include('staff.quote-calculated-line',['index'=>'__INDEX__','line'=>[]])</template>
<div id="quote-financial-result" aria-live="polite">@if(!empty($quote->configuration['calculation']['financial'])) @include('staff.quote-financial-summary',['financial'=>$quote->configuration['calculation']['financial']]) @elseif($quote->exists)<p class="notice">Le chiffrage historique est conservé jusqu’à l’enregistrement. Vérifiez chaque poste avant de le remplacer par un nouveau calcul.</p>@endif</div>
</section>
</div>

<div class="wizard-row" data-step-section="4">
<div class="wizard-main">
<section class="panel" id="s-pay" aria-labelledby="h-pay"><h2 id="h-pay">Conditions de paiement</h2>
<input type="hidden" name="payment_mode" value="advance"><input type="hidden" name="deposit_amount" value="{{ old('deposit_amount',$quote->deposit_amount ?? 0) }}">
@include('staff.quote-schedule')
</section>
<section class="panel" id="s-received" aria-labelledby="h-received"><h2 id="h-received">Paiement déjà reçu</h2>
<p class="muted">Déjà reçu : {{ number_format($quote->exists ? $quote->receivedAmount() : 0,0,',',' ') }} FCFA</p>
<details class="fold" @if(old('receipt_amount') || $errors->hasAny(['receipt_amount','receipt_date','receipt_method','receipt_reference','receipt_confirmed'])) open @endif><summary>Nouveau paiement déjà reçu<small>Seulement si un versement vient d’être encaissé</small></summary>@include('staff.quote-receipt-fields')</details>
</section>
</div>
@include('staff.quote-recap')
</div>

<div class="action-bar"><div class="live-total" id="quote-live-total" data-state="{{ $pendingLines ? 'pending' : 'ok' }}" role="status" @unless($liveTotal) hidden @endunless><small data-live-label>{{ $pendingLines ? 'Total provisoire' : 'Total définitif' }}</small><strong data-live-amount>{{ $financial ? number_format($financial['finalTotal'],0,',',' ').' FCFA' : '' }}</strong></div><span class="status">Enregistré en brouillon, jamais envoyé sans action de votre part.</span><button type="button" class="quiet" data-wizard-prev hidden>Précédent</button><button type="button" data-wizard-next hidden>Suivant</button><button data-wizard-save>Enregistrer le devis</button></div>
</form>
<script type="application/json" id="quote-catalogue-data">@json($catalogue)</script>
<script src="{{ asset('quote-editor.js') }}?v={{ filemtime(public_path('quote-editor.js')) }}" defer></script>
@endsection
