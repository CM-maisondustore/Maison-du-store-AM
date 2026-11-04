@extends('staff.layout')
@section('title',$invoice->number ?? 'Facture en préparation')
@section('back',route('staff.invoices.index'))
@section('crumbs')<a href="{{ route('staff.invoices.index') }}">Factures</a>@endsection
@section('page-actions')
@include('staff.status',['status'=>$invoice->status])
<button type="button" class="quiet" onclick="window.print()">Imprimer</button>
<a class="button quiet" href="{{ route('staff.invoices.pdf',$invoice) }}">@include('staff.partials.icon',['n'=>'download','s'=>18])Télécharger le PDF</a>
@if($invoice->status==='draft')
<button type="button" data-open-dialog="issue-dialog">Émettre la facture</button>
@else
<a class="button quiet" href="{{ route('staff.messages.create',['invoice_id'=>$invoice->id]) }}">Préparer l’envoi</a>
@if($invoice->status==='issued')<form method="post" action="{{ route('staff.invoices.paid',$invoice) }}" data-confirm="Confirmer la réception du paiement intégral ?">
@csrf<button>Marquer comme payée</button>
</form>
@endif
@endif
@endsection
@section('content')
@php($customer=$invoice->snapshot['customer'] ?? [])
<div class="notice {{ $invoice->status==='draft' ? '' : 'info' }}" role="status">
@if($invoice->status==='draft')Brouillon : vérifiez les coordonnées et les montants avant d’émettre.
@elseif($invoice->status==='issued')Facture émise et verrouillée. Elle ne peut plus être modifiée.
@else Paiement enregistré le {{ $invoice->paid_at?->format('d/m/Y H:i') }}.@endif
</div>
<article class="panel" aria-label="Aperçu de la facture">
<div class="title-row between items-start">
<div><h2>{{ $customer['firstName'] ?? '' }} {{ $customer['lastName'] ?? '' }}</h2>
<p class="muted">{{ $customer['address'] ?? '' }} {{ $customer['city'] ?? '' }}<br>{{ $customer['phone'] ?? '' }}<br>{{ $customer['email'] ?? '' }}</p></div>
<div class="tr"><p class="muted m-0">Échéance <span class="mono">{{ $invoice->due_date->format('d/m/Y') }}</span></p>
@if($invoice->quote_id)<p class="muted mt-4 m-x0">Devis <a class="mono" href="{{ route('staff.quotes.show',$invoice->quote_id) }}">{{ \App\Services\StaffInterface::ref($invoice->snapshot['quote_reference'] ?? '') }}</a></p>@endif</div>
</div>
<div class="divider"></div>
<div class="table-wrap">
@if(!empty($totals['openings']))
@include('staff.quote-financial-summary',['financial'=>$totals])
@else
<table>
<thead><tr><th scope="col">Désignation</th><th scope="col" class="num">Quantité</th><th scope="col" class="num">Prix unitaire</th><th scope="col" class="num">Total FCFA</th></tr></thead>
<tbody>
@foreach($invoice->snapshot['lines'] as $line)<tr>
<td>{{ $line['description'] }}</td>
<td class="num mono">{{ $line['quantity'] }}</td>
<td class="num amount">{{ number_format($line['unit_price'],0,',',' ') }}</td>
<td class="num amount">{{ number_format($line['unit_price']*$line['quantity'],0,',',' ') }}</td>
</tr>
@endforeach</tbody>
</table>
@endif
</div>
<p class="total">{{ number_format($totals['total'],0,',',' ') }} FCFA</p>
<p class="muted tr">Dont taxe : {{ number_format($totals['tax'],0,',',' ') }} FCFA</p>
</article>
@if($invoice->status==='draft')
<dialog class="modal" id="issue-dialog" aria-labelledby="em-t">
<form class="modal-body" method="post" action="{{ route('staff.invoices.issue',$invoice) }}">
@csrf
<h2 id="em-t">Émettre cette facture ?</h2>
<p>Après émission, la facture ne peut plus être modifiée : le numéro, les coordonnées du client et les montants sont figés.</p>
<dl class="detail-list">
<dt>Client</dt><dd>{{ $customer['firstName'] ?? '' }} {{ $customer['lastName'] ?? '' }}</dd>
<dt>Total</dt><dd class="amount">{{ number_format($totals['total'],0,',',' ') }} FCFA</dd>
<dt>Dont taxe</dt><dd class="amount">{{ number_format($totals['tax'],0,',',' ') }} FCFA</dd>
</dl>
<label class="check"><input type="checkbox" required>J’ai vérifié les coordonnées du client et les montants.</label>
<div class="modal-actions"><button type="button" class="quiet" data-close-dialog>Revenir au brouillon</button><button>Émettre la facture</button></div>
</form>
</dialog>
@endif
@endsection
