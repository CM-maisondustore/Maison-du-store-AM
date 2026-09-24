
@extends('staff.layout')
@section('title',$invoice->number ?? 'Facture en préparation')
@section('content')<section class="panel">
@include('staff.status',['status'=>$invoice->status])<h2>{{ $invoice->snapshot['customer']['firstName'] ?? '' }} {{ $invoice->snapshot['customer']['lastName'] ?? '' }}</h2>
<p>{{ $invoice->snapshot['customer']['email'] ?? '' }} · Échéance {{ $invoice->due_date->format('d/m/Y') }}</p>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Désignation</th>
<th>Quantité</th>
<th>Prix unitaire</th>
<th>Total FCFA</th>
</tr>
</thead>
<tbody>
@foreach($invoice->snapshot['lines'] as $line)<tr>
<td>{{ $line['description'] }}</td>
<td>{{ $line['quantity'] }}</td>
<td>{{ number_format($line['unit_price'],0,',',' ') }}</td>
<td>{{ number_format($line['unit_price']*$line['quantity'],0,',',' ') }}</td>
</tr>
@endforeach</tbody>
</table>
</div>
<p class="total">{{ number_format($totals['total'],0,',',' ') }} FCFA</p>
<p class="muted">Dont taxe : {{ number_format($totals['tax'],0,',',' ') }} FCFA</p>
<div class="actions">
<a class="button secondary" href="{{ route('staff.invoices.pdf',$invoice) }}">Télécharger le PDF</a>
@if($invoice->status==='draft')<form method="post" action="{{ route('staff.invoices.issue',$invoice) }}" data-confirm="Émettre et verrouiller cette facture ? Vérifiez les coordonnées, montants et taxes avant de continuer.">
@csrf<button>Émettre la facture</button>
</form>
@else<a class="button" href="{{ route('staff.messages.create',['invoice_id'=>$invoice->id]) }}">Préparer l’envoi</a>
@if($invoice->status==='issued')<form method="post" action="{{ route('staff.invoices.paid',$invoice) }}" data-confirm="Confirmer la réception du paiement intégral ?">
@csrf<button class="secondary">Marquer comme payée</button>
</form>
@endif
@endif</div>
@if($invoice->paid_at)<p>Paiement enregistré le {{ $invoice->paid_at->format('d/m/Y H:i') }}.</p>
@endif</section>
@endsection
