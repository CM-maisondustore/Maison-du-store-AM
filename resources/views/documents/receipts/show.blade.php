<table class="totals"><tr class="total"><th>MONTANT REÇU</th><td class="number">{{ \App\Services\DocumentViewModel::money($receipt['amount']) }}</td></tr>
<tr><th>Moyen de paiement</th><td>{{ $receipt['method'] }}</td></tr><tr><th>Référence du paiement</th><td>{{ $receipt['reference'] ?: '—' }}</td></tr>
<tr><th>Total du devis</th><td class="number">{{ \App\Services\DocumentViewModel::money($totals['total']) }}</td></tr>
<tr><th>Cumul reçu à l’édition</th><td class="number">{{ \App\Services\DocumentViewModel::money($payment_plan['received']) }}</td></tr>
<tr class="balance"><th>RESTE À PAYER À L’ÉDITION</th><td class="number">{{ \App\Services\DocumentViewModel::money($remaining_amount) }}</td></tr></table>
<p>Nous attestons avoir reçu le versement indiqué ci-dessus.</p>
