<table class="client"><tr><th>{{ $kind==='receipt'?'REÇU':($kind==='invoice'?'FACTURE':'DEVIS') }} N°</th><td>{{ $number }}</td></tr>
<tr><th>DATE</th><td>{{ $date }}@if($due) · {{ $kind==='quote'?'Validité':'Échéance' }} : {{ $due }}@endif</td></tr>
<tr><th>CLIENT</th><td>{{ $customer['firstName'] ?? '' }} {{ $customer['lastName'] ?? '' }}</td></tr>
<tr><th>TÉLÉPHONE</th><td>{{ $customer['phone'] ?? '' }}</td></tr>
<tr><th>ADRESSE</th><td>{{ $customer['address'] ?? '' }} {{ $customer['city'] ?? '' }}</td></tr>
@if(!empty($customer['email']))<tr><th>E-MAIL</th><td>{{ $customer['email'] }}</td></tr>@endif
@if(!empty($quote_reference))<tr><th>RÉFÉRENCE DEVIS</th><td>{{ $quote_reference }}</td></tr>@endif
@if(!empty($invoice_reference))<tr><th>RÉFÉRENCE FACTURE</th><td>{{ $invoice_reference }}</td></tr>@endif
</table>
