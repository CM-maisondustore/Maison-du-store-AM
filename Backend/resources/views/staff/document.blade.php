<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#243831}h1{font-size:26px}h2{font-size:15px}.muted{color:#667368}table{width:100%;border-collapse:collapse;margin-top:25px}th,td{padding:10px 7px;border-bottom:1px solid #ddd;text-align:left}.total{text-align:right;font-size:17px;margin-top:20px}.draft{padding:12px;background:#fff0dc;font-size:18px}.footer{margin-top:35px;font-size:10px;white-space:pre-wrap}.address{white-space:pre-wrap}</style>
</head>
<body>
<h2>{{ $issuer['name'] }}</h2>
<p class="address">{{ $issuer['address'] }}<br>{{ $issuer['registration'] }}<br>{{ $issuer['email'] }}</p>
<h1>{{ $title }} {{ $number }}</h1>
@if($draft)<p class="draft">BROUILLON — non émis</p>
@endif<p>Établi le {{ $date }} · {{ $title==='Devis' ? 'Validité' : 'Échéance' }} : {{ $due }}</p>
<h2>{{ $customer['firstName'] ?? '' }} {{ $customer['lastName'] ?? '' }}</h2>
<p class="address">{{ $customer['address'] ?? '' }} {{ $customer['city'] ?? '' }}<br>{{ $customer['email'] ?? '' }}<br>{{ $customer['phone'] ?? '' }}</p>
@if(!empty($windows))<h2>Fenêtres et configurations</h2>
@foreach($windows as $window)<p><strong>{{ $window['label'] }}</strong> · {{ $window['width'] }} × {{ $window['height'] }} cm · Qté {{ $window['quantity'] }}<br>{{ $window['product_name'] }} · {{ $window['material_name'] }} · {{ $window['finish_name'] }} · Motorisé : {{ $window['motorized'] ? 'Oui' : 'Non' }}<br>{{ $window['notes'] ?? '' }}</p>@endforeach
@endif
@if(!empty($schedule['delivery']) || !empty($schedule['installation']))<p>Livraison prévue : {{ $schedule['delivery'] ?: 'À préciser' }} · Pose prévue : {{ $schedule['installation'] ?: 'À préciser' }}</p>@endif
<table>
<thead>
<tr>
<th>Désignation</th>
<th>Qté</th>
<th>PU FCFA</th>
<th>Total FCFA</th>
</tr>
</thead>
<tbody>
@foreach($lines as $line)<tr>
<td>{{ $line['description'] }}</td>
<td>{{ $line['quantity'] }}</td>
<td>{{ number_format($line['unit_price'],0,',',' ') }}</td>
<td>{{ number_format($line['quantity']*$line['unit_price'],0,',',' ') }}</td>
</tr>
@endforeach</tbody>
</table>
<p class="total">Sous-total : {{ number_format($totals['subtotal'],0,',',' ') }} FCFA<br>Taxe ({{ $tax_basis_points / 100 }} %) : {{ number_format($totals['tax'],0,',',' ') }} FCFA<br>
<strong>Total : {{ number_format($totals['total'],0,',',' ') }} FCFA</strong>
</p>
@if(!empty($payment_plan['mode']))<p>Conditions : {{ $payment_plan['mode']==='full' ? 'Paiement intégral' : 'Avance de '.number_format($payment_plan['deposit'],0,',',' ').' FCFA puis solde' }}.<br>Versements reçus enregistrés à l’édition : {{ number_format($payment_plan['received'],0,',',' ') }} FCFA · Reste à recevoir : {{ number_format($totals['total']-$payment_plan['received'],0,',',' ') }} FCFA.</p>@endif
<div class="footer">{{ $issuer['tax_note'] }}<br>{{ $issuer['payment_terms'] }}<br>Pour toute question : {{ $issuer['email'] }}</div>
</body>
</html>
