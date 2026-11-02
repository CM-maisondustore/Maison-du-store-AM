@extends('staff.layout')
@php
 $admin=auth()->user()->isAdmin();
 $type=$quote->type;
 $label=\App\Services\QuoteArticles::LABELS[$type];
 $variantLabel=$type==='meubles' ? 'Finition / dimensions' : 'Modèle / couleur';
 $config=$quote->configuration ?? [];
 $pending=$config['calculation']['pending'] ?? [];
 // Reconstruit les prix enregistrés ligne par ligne ; une ligne en attente reste « à définir ».
 $priceOf=function(string $description) use($quote,$pending){ if(in_array($description,$pending,true)) return null; foreach($quote->lines ?? [] as $line) if($line['description']===$description) return $line['unit_price']; return null; };
 $articles=old('articles',collect($config['articles'] ?? [])->map(function($a) use($priceOf,$admin){ $d=$a['name'].(!empty($a['variant']) ? ', '.$a['variant'] : ''); return $admin ? [...$a,'unit_price'=>$priceOf($d)] : $a; })->all() ?: [['quantity'=>1]]);
 $floor=old('floor',$config['floor'] ?? 'rdc');
 $access=$floor==='rdc' ? '' : ' ('.mb_strtolower(\App\Services\QuoteArticles::FLOORS[$floor]).(($config['has_lift'] ?? false) ? ', ascenseur' : ' sans ascenseur').')';
 $draftKey='article-quote-'.($quote->exists ? $quote->id : $type);
@endphp
@section('title',($quote->exists ? 'Modifier le devis' : 'Nouveau devis').' — '.$label)
@section('back',$quote->exists ? route('staff.quotes.show',$quote) : route('staff.quotes.index'))
@section('crumbs')<a href="{{ route('staff.quotes.index') }}">Devis</a>@endsection
@section('subtitle',$admin ? 'Un prix vide signifie « à définir », jamais gratuit : le devis ne peut pas être validé tant qu’il en reste un.' : 'Le chiffrage est fait au bureau. Renseignez les articles et les informations de livraison.')
@section('body-class','has-action-bar')
@section('content')
<div class="form-narrow">
@unless($quote->exists)@include('staff.partials.boutique-picker',['active'=>$type])@endunless
</div>
<form id="article-quote-form" method="post" @unless($admin) data-local-draft="{{ $draftKey }}" @endunless action="{{ $quote->exists ? route('staff.quotes.form.update',$quote) : route('staff.quotes.store') }}">
@csrf @if($quote->exists) @method('PUT') @else <input type="hidden" name="universe" value="{{ $type }}"> @endif
<input type="hidden" name="submission_token" value="{{ old('submission_token',$quote->submission_token ?: (string)\Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="edit_version" value="{{ old('edit_version',$quote->edit_version ?? 0) }}">
<div class="form-narrow">
<section class="panel"><h2 class="section-title"><span class="step" aria-hidden="true">1</span>Client</h2><div class="grid client-grid">
@foreach(['firstName'=>'Prénom','lastName'=>'Nom / raison sociale','phone'=>'Téléphone','email'=>'E-mail (facultatif)','address'=>'Adresse de livraison','city'=>'Ville'] as $key=>$fieldLabel)
@if($key==='phone')
@include('staff.quote-phone')
@else
<label>{{ $fieldLabel }}<input name="contact[{{ $key }}]" type="{{ $key==='email' ? 'email' : 'text' }}" @required(in_array($key,['lastName','address'])) maxlength="{{ $key==='address' ? 1000 : ($key==='email' ? 255 : 100) }}" value="{{ old('contact.'.$key,$quote->contact[$key] ?? '') }}"></label>
@endif
@endforeach
</div></section>
<section class="panel" aria-labelledby="s-art"><h2 id="s-art" class="section-title"><span class="step" aria-hidden="true">2</span>Articles</h2>
<label>Ajouter depuis le catalogue<input type="search" id="article-search" list="article-catalogue" placeholder="Nom ou référence d’un produit"></label>
<datalist id="article-catalogue">@foreach($catalogue as $product)<option value="{{ $product['name'] }}">{{ $product['reference'] }}</option>@endforeach</datalist>
<div id="articles">@foreach($articles as $index=>$article)@include('staff.quote-article-row')@endforeach</div>
<template id="articles-template">@include('staff.quote-article-row',['index'=>'__INDEX__','article'=>['quantity'=>1]])</template>
<div class="actions"><button type="button" class="secondary" id="add-article">+ Article hors catalogue</button></div>
</section>
<section class="panel" aria-labelledby="s-svc"><h2 id="s-svc" class="section-title"><span class="step" aria-hidden="true">3</span>Livraison et services</h2>
@foreach(\App\Services\QuoteArticles::SERVICES[$type] as $key=>$serviceLabel)
@php($on=old('services.'.$key.'.enabled',!empty($config['services'][$key])))
<div class="service-row @unless($admin) service-only @endunless">
<input type="hidden" name="services[{{ $key }}][enabled]" value="0"><label class="toggle-row"><input class="switch" type="checkbox" name="services[{{ $key }}][enabled]" value="1" @checked($on)><span>{{ $serviceLabel }}</span></label>
@if($admin)<label>Prix<span class="field-unit is-price"><input type="number" min="0" max="100000000" step="1" inputmode="numeric" name="services[{{ $key }}][unit_price]" value="{{ old('services.'.$key.'.unit_price',$on ? $priceOf($serviceLabel.(in_array($key,['delivery','assembly','installation'],true) ? $access : '')) : '') }}" placeholder="À définir"><span class="unit">FCFA</span></span></label>@endif
</div>
@endforeach
<div class="grid mt-12">
<label class="mt-0">Étage<select name="floor">@foreach(\App\Services\QuoteArticles::FLOORS as $value=>$floorLabel)<option value="{{ $value }}" @selected($floor==$value)>{{ $floorLabel }}</option>@endforeach</select></label>
<div><input type="hidden" name="has_lift" value="0"><label class="toggle-row"><span>Ascenseur disponible</span><input class="switch" type="checkbox" name="has_lift" value="1" @checked(old('has_lift',$config['has_lift'] ?? false))></label></div>
</div>
</section>
@if($admin)
<section class="panel" aria-labelledby="s-tax"><h2 id="s-tax" class="section-title"><span class="step" aria-hidden="true">4</span>Dates et chiffrage</h2>
@if(!empty($config['pricing_review_required']))<p class="notice">Les choix de l’agent sont à vérifier avant validation du chiffrage.</p>@endif
<div class="grid">
@foreach(['quote_date'=>'Date du devis','valid_until'=>'Valable jusqu’au','delivery_date'=>'Livraison prévue','installation_date'=>'Pose ou montage prévu'] as $key=>$fieldLabel)
<label>{{ $fieldLabel }}<input name="{{ $key }}" type="date" @required(in_array($key,['quote_date','valid_until'])) value="{{ old($key,$quote->$key ?: ($key==='quote_date' ? now()->format('Y-m-d') : ($key==='valid_until' ? now()->addDays(30)->format('Y-m-d') : ''))) }}"></label>
@endforeach
<label>Agent<select name="agent_id"><option value="">Non affecté</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('agent_id',$quote->agent_id)==$agent->id)>{{ $agent->name }}</option>@endforeach</select></label>
<label>Taux de taxe<span class="field-unit"><input type="number" name="tax_rate" min="0" max="100" step="0.01" required value="{{ old('tax_rate',($quote->tax_basis_points ?? 0)/100) }}"><span class="unit">%</span></span></label>
<label>Mode de paiement<select name="payment_mode"><option value="advance" @selected(old('payment_mode',$quote->payment_mode ?? 'advance')==='advance')>Avance puis solde</option><option value="full" @selected(old('payment_mode',$quote->payment_mode)==='full')>Paiement intégral</option></select></label>
<label>Avance prévue<span class="field-unit"><input type="number" name="deposit_amount" min="0" step="1" required value="{{ old('deposit_amount',$quote->deposit_amount ?? 0) }}"><span class="unit">FCFA</span></span></label>
</div>
<details class="fold" @if(old('receipt_amount') || $errors->hasAny(['receipt_amount','receipt_date','receipt_method','receipt_reference','receipt_confirmed'])) open @endif><summary>Nouveau paiement déjà reçu<small>Seulement si un versement vient d’être encaissé</small></summary>@include('staff.quote-receipt-fields')</details>
</section>
<section class="panel" aria-label="Notes"><div class="internal-block mt-0"><p class="internal-title">@include('staff.partials.icon',['n'=>'lock','s'=>16])Réservé à l’équipe</p><label for="article-notes">Notes internes</label><textarea id="article-notes" name="internal_notes" maxlength="10000">{{ old('internal_notes',$quote->internal_notes) }}</textarea></div>
<label>Notes de terrain<textarea name="field_notes" maxlength="10000">{{ old('field_notes',$config['field_notes'] ?? '') }}</textarea></label></section>
@else
<section class="panel"><label class="mt-0">Notes pour le bureau<textarea name="field_notes" maxlength="10000" placeholder="Accès, contraintes, souhaits du client…">{{ old('field_notes',$config['field_notes'] ?? '') }}</textarea></label></section>
@endif
</div>
<div class="action-bar"><span class="status" role="status" data-draft-status="{{ $draftKey }}">Enregistré en brouillon, jamais envoyé sans action de votre part.</span><button>Enregistrer le devis</button></div>
</form>
@endsection
