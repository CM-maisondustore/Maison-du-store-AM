@extends('staff.layout')
@php
 $client=\App\Services\StaffInterface::quoteClient($quote);
 $ref=\App\Services\StaffInterface::ref($quote->reference);
 $invoice=\App\Models\Invoice::where('quote_id',$quote->id)->first();
 $locked=$quote->status==='accepted' || $invoice;
 $hasFinancial=!empty($quote->configuration['calculation']['financial']);
 $isArticle=\App\Services\StaffInterface::isArticleQuote($quote);
 $pending=$quote->configuration['calculation']['pending'] ?? [];
 $review=!empty($quote->configuration['pricing_review_required']);
 $canValidate=!$pending && !$review && !empty($quote->lines);
 $sent=\App\Models\CommercialMessage::where('quote_id',$quote->id)->whereIn('status',['sent','shared'])->latest('sent_at')->first();
 $history=\Illuminate\Support\Facades\DB::table('audit_events')->leftJoin('users','users.id','=','audit_events.user_id')->where('subject_type','Quote')->where('subject_id',$quote->id)->orderByDesc('audit_events.id')->limit(12)->get(['audit_events.action','audit_events.created_at','users.name']);
 $steps=[['Reçu',$quote->created_at?->format('d/m/Y'),true],
  ['Brouillon',null,in_array($quote->status,['draft','approved','accepted'])],
  ['Validé',$quote->approved_at ? \Illuminate\Support\Carbon::parse($quote->approved_at)->format('d/m/Y') : null,in_array($quote->status,['approved','accepted'])],
  ['Envoyé',$sent?->sent_at?->format('d/m/Y'),(bool)$sent],
  ['Accepté',null,$quote->status==='accepted'],
  ['Facturé',$invoice?->issued_at?->format('d/m/Y'),(bool)$invoice]];
 $currentStep=collect($steps)->search(fn($s)=>!$s[2]);
 $actionLabels=['devis_interne_cree'=>'Devis créé','formulaire_devis_modifie'=>'Formulaire enregistré','devis_modifie'=>'Devis enregistré','devis_sur_place_cree'=>'Devis sur place créé','avancement_devis_modifie'=>'Avancement modifié','presentation_devis_modifiee'=>'Présentation modifiée'];
@endphp
@section('title','Devis '.$ref)
@section('back',route('staff.quotes.index'))
@section('head')
<div class="page-head keep">
<div>
<nav class="crumbs" aria-label="Fil d’Ariane"><a href="{{ route('staff.quotes.index') }}">Devis</a> / <span class="mono" aria-current="page">{{ $ref }}</span></nav>
<div class="title-row"><h1>{{ $client }}</h1>@include('staff.status',['status'=>$quote->status])</div>
<p class="sub">{{ \App\Services\StaffInterface::QUOTE_TYPES[$quote->type] ?? ucfirst($quote->type) }}, {{ \App\Services\StaffInterface::SOURCES[$quote->source] ?? $quote->source }}, reçu le {{ $quote->created_at->format('d/m/Y') }}.</p>
</div>
<div class="page-actions">
<a class="button quiet" href="{{ route('staff.quotes.pdf',$quote) }}">@include('staff.partials.icon',['n'=>'download','s'=>18])Télécharger le PDF</a>
<a class="button quiet" href="{{ route('staff.messages.create',['quote_id'=>$quote->id]) }}">Préparer l’envoi du devis</a>
@if($quote->status!=='accepted')<a class="button quiet" href="{{ route('staff.quotes.edit',$quote) }}">Ouvrir le formulaire complet</a>@endif
@if(in_array($quote->status,['pending','draft']) && $canValidate)
<form method="post" action="{{ route('staff.quotes.update',$quote) }}" data-confirm="Valider ce devis pour envoi au client ?">@include('staff.partials.quote-state-fields')<input type="hidden" name="internal_notes" value="{{ $quote->internal_notes }}"><button name="status" value="approved">Valider le devis</button></form>
@elseif($quote->status==='approved')
<button type="button" data-open-dialog="accept-dialog">Enregistrer l’acceptation</button>
@endif
</div>
</div>
@endsection
@section('content')
<section class="panel" aria-label="Avancement du devis">
<ol class="rail">
@foreach($steps as $i=>$step)<li class="{{ $step[2] ? 'done' : '' }} {{ $i===$currentStep ? 'current' : '' }}" @if($i===$currentStep) aria-current="step" @endif>{{ $step[0] }}<small>{{ $step[1] ?? ($step[2] ? '✓' : ($i===$currentStep ? 'en attente' : '—')) }}</small></li>@endforeach
</ol>
</section>
@if($locked)<div class="notice info" role="status"><strong>Devis verrouillé.</strong> {{ $invoice ? 'Une facture existe pour ce devis' : 'Le client l’a accepté' }} : les montants et les fenêtres ne peuvent plus changer.</div>@endif
@if(in_array($quote->status,['pending','draft']) && !$canValidate)
<div class="notice" role="status"><strong>Prochaine étape : compléter le chiffrage.</strong> La validation reste bloquée tant que des prix sont à définir ou que les choix de l’agent ne sont pas vérifiés. <a class="link" href="{{ route('staff.quotes.edit',$quote) }}">Ouvrir le formulaire complet</a></div>
@endif
<div class="with-aside">
<div>
@if($quote->configuration && !$isArticle)
<section class="panel" id="fenetres">@include('staff.quote-windows-summary')</section>
@elseif($isArticle)
<section class="panel" id="articles-recap" aria-labelledby="h-art"><h2 id="h-art">Articles</h2>
@if($pending)<p class="notice">Devis incomplet : {{ implode(' ; ',$pending) }}. Ces postes ne sont pas inclus dans le total provisoire. Complétez le formulaire avant validation.</p>@endif
<ul class="rows">@foreach($quote->configuration['articles'] ?? [] as $article)<li class="row py-8"><span>{{ $article['name'] }}@if(!empty($article['variant']))<small class="muted"> · {{ $article['variant'] }}</small>@endif</span><span class="mono">× {{ $article['quantity'] }}</span></li>@endforeach</ul>
<p class="muted">{{ \App\Services\QuoteArticles::FLOORS[$quote->configuration['floor'] ?? 'rdc'] ?? '' }}{{ ($quote->configuration['floor'] ?? 'rdc')!=='rdc' ? (!empty($quote->configuration['has_lift']) ? ', ascenseur' : ', sans ascenseur') : '' }}</p>
@if(!empty($quote->configuration['field_notes']))<p class="prose">{{ $quote->configuration['field_notes'] }}</p>@endif
</section>
@endif
<form class="panel" id="suivi" method="post" action="{{ route('staff.quotes.update',$quote) }}">
@csrf
@method('PUT')
<input type="hidden" name="edit_version" value="{{ $quote->edit_version }}">
@if(!$quote->configuration)
<h2>Mesures</h2>
<div id="dimensions">
@foreach(old('dimensions',$quote->dimensions ?? []) as $index=>$line)
@include('staff.dimension-line')
@endforeach</div>
<template id="dimensions-template">
@include('staff.dimension-line',['index'=>'__INDEX__','line'=>[]])</template>
<button type="button" class="secondary" data-add="dimensions">Ajouter une mesure</button>
<div class="divider"></div>
@endif
<h2>Chiffrage</h2>
@if($hasFinancial)
@include('staff.quote-financial-summary',['financial'=>$quote->configuration['calculation']['financial']])
<p class="muted">Pour modifier les quantités, prix, frais ou remises, ouvrez le formulaire complet.</p>
@endif
@if($isArticle)
@php($articleTotals=\App\Services\Pricing::totals($quote->lines ?? [],$quote->tax_basis_points ?? 0))
<div class="table-wrap"><table><thead><tr><th>Désignation</th><th class="num">Quantité</th><th class="num">Prix unitaire</th><th class="num">Total</th></tr></thead><tbody>
@foreach($quote->lines ?? [] as $line)@php($isPending=in_array($line['description'],$pending,true))<tr><td>{{ $line['description'] }}</td><td class="num mono">{{ $line['quantity'] }}</td><td class="num">@if($isPending)<span class="undefined">À définir</span>@else<span class="amount">{{ number_format($line['unit_price'],0,',',' ') }} FCFA</span>@endif</td><td class="num">@if($isPending)<span class="undefined">À définir</span>@else<span class="amount">{{ number_format($line['unit_price']*$line['quantity'],0,',',' ') }} FCFA</span>@endif</td></tr>@endforeach
</tbody></table></div>
<dl class="totals"><dt>Sous-total HT</dt><dd>{{ number_format($articleTotals['subtotal'],0,',',' ') }} FCFA</dd><dt>TVA {{ ($quote->tax_basis_points ?? 0)/100 }} %</dt><dd>{{ number_format($articleTotals['tax'],0,',',' ') }} FCFA</dd><dt class="grand">{{ $pending ? 'Total TTC provisoire' : 'Total TTC' }}</dt><dd class="grand">{{ number_format($articleTotals['total'],0,',',' ') }} FCFA</dd></dl>
@endif
<div @if($hasFinancial || $isArticle) hidden @endif>
<div id="lines">
@foreach(old('lines',$quote->lines ?: [['description'=>'','quantity'=>1,'unit_price'=>0]]) as $index=>$line)
@include(($hasFinancial || $isArticle) ? 'staff.quote-calculated-line' : 'staff.price-line')
@endforeach</div>
<template id="lines-template">
@include('staff.price-line',['index'=>'__INDEX__','line'=>[]])</template>
<button type="button" class="secondary" data-add="lines">Ajouter une ligne</button>
</div>
<div class="divider"></div>
<h2>Suivi du devis</h2>
<div class="grid">
<label>Taux de taxe (%)<input type="number" name="tax_rate" min="0" max="100" step="0.01" required @readonly($hasFinancial || $isArticle) value="{{ old('tax_rate',$quote->tax_basis_points / 100) }}">
</label>
<label>Valable jusqu’au<input type="date" name="valid_until" required value="{{ old('valid_until',$quote->valid_until ?? now()->addDays(30)->format('Y-m-d')) }}">
</label>
<label>Agent<select name="agent_id">
<option value="">Non affecté</option>
@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('agent_id',$quote->agent_id)==$agent->id)>{{ $agent->name }}</option>
@endforeach</select>
</label>
<label>État<select name="status">
@foreach(['pending'=>'À traiter','draft'=>'Brouillon','approved'=>'Validé pour envoi','accepted'=>'Accepté par le client','rejected'=>'Refusé','cancelled'=>'Annulé'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$quote->status)===$value)>{{ $label }}</option>
@endforeach</select>
</label>
</div>
<label>Notes internes / preuve d’accord du client<textarea name="internal_notes">{{ old('internal_notes',$quote->internal_notes) }}</textarea>
</label>
<p class="muted">L’acceptation est enregistrée par Admin-CM après accord du client. Elle verrouille le devis.</p>
<div class="actions">
<button class="secondary" @disabled($quote->status==='accepted')>Enregistrer le devis</button>
</div>
</form>
@if(!empty($quote->product))
@if(\App\Services\PublicQuoteRequest::isPublic($quote))
<details class="panel" open><summary>Choix du client sur le site</summary>
<dl class="detail-list">
@foreach(\App\Services\PublicQuoteRequest::summary($quote) as [$label,$value])<dt>{{ $label }}</dt><dd>{{ $value }}</dd>
@endforeach</dl>
@if(empty($quote->configuration['defaults']))<p class="hint">Ces choix pré-remplissent « Préparer le devis » : vérifiez-les avec le client lors de la prise de mesures.</p>@endif
@else
<details class="panel"><summary>Demande d’origine</summary>
<dl class="detail-list">
@foreach($quote->product as $key=>$value)<dt>{{ config('workspace.labels.'.$key, ucfirst($key)) }}</dt>
<dd>{{ is_array($value) ? implode(', ',$value) : $value }}</dd>
@endforeach</dl>
@endif
@if($quote->notes)<p class="prose">{{ $quote->notes }}</p>@endif
</details>
@endif
@include('staff.document-history')
</div>
<aside>
<section class="panel" aria-labelledby="h-cli"><h2 id="h-cli">Client</h2>
<dl class="detail-list">
@foreach($quote->contact as $key=>$value)@if($value)<dt>{{ config('workspace.labels.'.$key, ucfirst($key)) }}</dt>
<dd>@if($key==='phone')<a href="tel:{{ preg_replace('/[^0-9+]/','',$value) }}">{{ $value }}</a>@else{{ $value }}@endif</dd>@endif
@endforeach</dl>
@if($quote->contact_id)<p>
<a class="link" href="{{ route('staff.contacts.show',$quote->contact_id) }}">Ouvrir le dossier client</a>
</p>
@endif</section>
<section class="panel" aria-labelledby="h-plan"><h2 id="h-plan">Planification</h2>
<dl class="detail-list">
<dt>Agent</dt><dd>{{ $quote->agent?->name ?? 'Non affecté' }}</dd>
<dt>Valable jusqu’au</dt><dd class="mono">{{ \App\Services\StaffInterface::date($quote->valid_until,'—') }}</dd>
<dt>Livraison</dt><dd class="mono">{{ \App\Services\StaffInterface::date($quote->delivery_date) }}</dd>
<dt>Pose</dt><dd class="mono">{{ \App\Services\StaffInterface::date($quote->installation_date) }}</dd>
</dl>
<p><a class="link" href="{{ route('staff.interventions.index',['contact_id'=>$quote->contact_id,'quote_id'=>$quote->id]) }}#planifier">Planifier une prestation</a></p>
</section>
@include('staff.quote-payments')
@if($hasFinancial)
@php($workflow=\App\Services\Quotes\QuoteWorkflow::state($quote))
<section class="panel" id="fabrication"><h2>Fabrication et suivi</h2><p>État : {{ \App\Services\Quotes\QuoteWorkflow::STAGES[$workflow] ?? ['draft'=>'Brouillon','approved'=>'Validé pour envoi','sent'=>'Validé pour envoi','accepted'=>'Accord client reçu','deposit_pending'=>'Acompte attendu','deposit_paid'=>'Acompte reçu — démarrage autorisé','cancelled'=>'Annulé'][$workflow] ?? $workflow }}</p>
@if($quote->status==='accepted')<form method="post" action="{{ route('staff.quotes.workflow',$quote) }}">@csrf<input type="hidden" name="edit_version" value="{{ $quote->edit_version }}"><label>Avancement<select name="stage">@foreach(\App\Services\Quotes\QuoteWorkflow::STAGES as $key=>$label)<option value="{{ $key }}" @selected($workflow===$key)>{{ $label }}</option>@endforeach</select></label><div class="actions"><button class="secondary">Enregistrer l’avancement</button></div></form>@endif
</section>
@endif
@if($quote->status==='accepted')
@if($invoice)<section class="panel"><h2>Facturation</h2><p><a class="link" href="{{ route('staff.invoices.show',$invoice) }}">Ouvrir la facture {{ $invoice->number }}</a></p></section>
@else<form class="panel" method="post" action="{{ route('staff.invoices.create',$quote) }}">
@csrf<h2>Facturation</h2>
<label>Date d’échéance<input type="date" name="due_date" required value="{{ now()->addDays(30)->format('Y-m-d') }}">
</label>
<div class="actions">
<button>Préparer la facture</button>
</div>
</form>@endif
@endif
<section class="panel" aria-labelledby="h-hist"><h2 id="h-hist">Historique</h2>
<ol class="timeline">
@foreach($history as $event)<li>{{ $actionLabels[$event->action] ?? ucfirst(str_replace('_',' ',$event->action)) }}{{ $event->name ? ' par '.$event->name : '' }}<small>{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('d/m/Y H:i') }}</small></li>@endforeach
<li>Demande enregistrée<small>{{ $quote->created_at->format('d/m/Y H:i') }}</small></li>
</ol>
</section>
</aside>
</div>
@if($quote->status==='approved')
<dialog class="modal" id="accept-dialog" aria-labelledby="acc-title">
<form class="modal-body" method="post" action="{{ route('staff.quotes.update',$quote) }}">
@include('staff.partials.quote-state-fields')
<h2 id="acc-title">Enregistrer l’acceptation</h2>
<p>Une fois acceptés, les montants et les fenêtres du devis sont verrouillés.</p>
<label>Notes internes / preuve d’accord du client<textarea name="internal_notes" required rows="4" placeholder="Date et forme de l’accord : devis papier signé, réponse par e-mail, message WhatsApp…">{{ $quote->internal_notes }}</textarea></label>
<div class="modal-actions"><button type="button" class="quiet" data-close-dialog>Annuler</button><button name="status" value="accepted">Enregistrer l’acceptation</button></div>
</form>
</dialog>
@endif
@endsection
