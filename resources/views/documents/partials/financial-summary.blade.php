@if(!empty($totals['fees']))<h2>FRAIS ET PRESTATIONS COMPLÉMENTAIRES</h2>
@include('documents.partials.table',['documentLines'=>$fee_rows])@endif
<table class="totals"><tr><th>Sous-total</th><td class="number">{{ \App\Services\DocumentViewModel::money($totals['subtotal']) }}</td></tr>
@if($totals['tax'])<tr><th>Taxes</th><td class="number">{{ \App\Services\DocumentViewModel::money($totals['tax']) }}</td></tr>@endif
@if(!empty($totals['discountAmount']))<tr><th>TOTAL AVANT REMISE</th><td class="number">{{ \App\Services\DocumentViewModel::money($totals['grossTotal']) }}</td></tr><tr class="discount"><th>REMISE COMMERCIALE</th><td class="number">−{{ \App\Services\DocumentViewModel::money($totals['discountAmount']) }}</td></tr>@endif
<tr class="total"><th>TOTAL GÉNÉRAL</th><td class="number">{{ \App\Services\DocumentViewModel::money($totals['total']) }}</td></tr>
@if(isset($totals['deposit']))<tr class="deposit"><th>ACOMPTE @if(isset($totals['depositPercent']))({{ \App\Services\Quotes\QuoteFinancials::quantity($totals['depositPercent']) }} %)@endif</th><td class="number">{{ \App\Services\DocumentViewModel::money($totals['deposit']) }}</td></tr><tr class="balance"><th>SOLDE @if(isset($totals['balancePercent']))({{ \App\Services\Quotes\QuoteFinancials::quantity($totals['balancePercent']) }} %)@endif</th><td class="number">{{ \App\Services\DocumentViewModel::money($totals['balance']) }}</td></tr>@endif
@if($kind==='invoice' || !empty($payment_plan['received']))<tr><th>Versements reçus à l’édition</th><td class="number">{{ \App\Services\DocumentViewModel::money($payment_plan['received'] ?? 0) }}</td></tr><tr><th>RESTE À PAYER</th><td class="number">{{ \App\Services\DocumentViewModel::money($remaining_amount) }}</td></tr>@endif
</table>
