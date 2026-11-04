<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>.quote-opening{page-break-inside:avoid}.quote-opening h3{page-break-after:avoid}.quote-pricing-summary table{margin-top:8px}.quote-pricing-summary .detail-list dt{margin-top:8px}.quote-pricing-summary .detail-list dd{margin-left:0;font-weight:bold}</style>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#243831}h1{font-size:26px}h2{font-size:15px}.muted{color:#667368}table{width:100%;border-collapse:collapse;margin-top:25px}th,td{padding:10px 7px;border-bottom:1px solid #ddd;text-align:left}.total{text-align:right;font-size:17px;margin-top:20px}.draft{padding:12px;background:#fff0dc;font-size:18px}.footer{margin-top:35px;font-size:10px;white-space:pre-wrap}.address{white-space:pre-wrap}</style>
</head>
<body>
@php($presentation = $presentation ?? [])
@if(!empty($presentation['letterhead_enabled']))
@php($margins=\App\Services\PdfLetterhead::margins($presentation))
<style>@page{margin:{{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm}body{margin:0}td,p,.address,.document-text{overflow-wrap:break-word}</style>
@endif
<style>h1,h2,th{color:{{ preg_match('/^#[0-9a-fA-F]{6}$/',$presentation['accent'] ?? '') ? $presentation['accent'] : '#243831' }}}.document-logo{max-width:180px;max-height:90px}.document-text{white-space:pre-wrap}</style>
@if(!empty($presentation['logo']))<img class="document-logo" src="{{ $presentation['logo'] }}" alt="Logo">@endif
<h2>{{ $issuer['name'] }}</h2>
<p class="address">{{ $issuer['address'] }}<br>{{ $issuer['registration'] }}<br>{{ $issuer['email'] }}</p>
<h1>{{ $title }} {{ $number }}</h1>
@if(!empty($presentation['introduction']))<p class="document-text">{{ $presentation['introduction'] }}</p>@endif
@if($draft)<p class="draft">BROUILLON — non émis</p>
@endif<p>Établi le {{ $date }} · {{ $title==='Devis' ? 'Validité' : 'Échéance' }} : {{ $due }}</p>
<h2>{{ $customer['firstName'] ?? '' }} {{ $customer['lastName'] ?? '' }}</h2>
<p class="address">{{ $customer['address'] ?? '' }} {{ $customer['city'] ?? '' }}<br>{{ $customer['email'] ?? '' }}<br>{{ $customer['phone'] ?? '' }}</p>
@if(!empty($windows))<h2>Fenêtres et configurations</h2>
@foreach($windows as $window)<p><strong>{{ $window['label'] }}</strong> · {{ $window['width'] }} × {{ $window['height'] }} cm · Qté {{ $window['quantity'] }}<br>{{ $window['product_name'] ?? '' }} · {{ \App\Services\Quotes\CatalogueCalculator::clientLabel($window['material_name'] ?? '') }} · {{ $window['finish_name'] ?? '' }} · Motorisé : {{ !empty($window['motorized']) ? 'Oui' : 'Non' }}<br>{{ $window['notes'] ?? '' }}</p>@endforeach
@endif
@if(!empty($schedule['delivery']) || !empty($schedule['installation']))<p>Livraison prévue : {{ $schedule['delivery'] ?: 'À préciser' }} · Pose prévue : {{ $schedule['installation'] ?: 'À préciser' }}</p>@endif
@if(!empty($opening_rows))
@if(!empty($pending_costs))<p>Chiffrage provisoire — postes à définir : {{ implode(' ; ',$pending_costs) }}</p>@endif
@include('staff.quote-financial-summary',['financial'=>$totals])
@else
<table>
@if(!empty($pending_costs))<caption>Chiffrage provisoire — postes non inclus, à définir : {{ implode(' ; ',$pending_costs) }}</caption>@endif
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
<td>{{ \App\Services\Quotes\CatalogueCalculator::clientLabel($line['description']) }}</td>
<td>{{ $line['quantity'] }}</td>
<td>{{ number_format($line['unit_price'],0,',',' ') }}</td>
<td>{{ number_format($line['amount'],0,',',' ') }}</td>
</tr>
@endforeach</tbody>
</table>
<p class="total">Sous-total : {{ number_format($totals['subtotal'],0,',',' ') }} FCFA<br>Taxe ({{ $tax_basis_points / 100 }} %) : {{ number_format($totals['tax'],0,',',' ') }} FCFA<br>
<strong>Total : {{ number_format($totals['total'],0,',',' ') }} FCFA</strong>
</p>
@endif
@if(!empty($payment_plan['mode']))<p>Conditions : {{ $payment_plan['mode']==='full' ? 'Paiement intégral' : 'Avance de '.number_format($payment_plan['deposit'],0,',',' ').' F CFA puis solde' }}.<br>Versements reçus enregistrés à l’édition : {{ number_format($payment_plan['received'],0,',',' ') }} F CFA · Reste à recevoir : {{ number_format($remaining_amount,0,',',' ') }} F CFA.</p>@endif
@if($title==='Devis' && !empty($totals['schedule']))<p><strong>Notre engagement.</strong> {{ ($presentation['commitment_text'] ?? '') ?: \App\Services\DocumentLayout::COMMITMENT }}</p>@endif
<div class="footer">{{ $issuer['tax_note'] }}<br>{{ $issuer['payment_terms'] }}<br>Pour toute question : {{ $issuer['email'] }}</div>
@if(!empty($presentation['footer']))<p class="footer">{{ $presentation['footer'] }}</p>@endif
@if(!empty($presentation['signature']))<p class="footer">{{ $presentation['signature'] }}</p>@endif
</body>
</html>
