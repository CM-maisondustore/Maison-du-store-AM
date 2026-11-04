<div class="quote-pricing-summary">
@foreach(\App\Services\Quotes\QuoteFinancials::display($financial) as $opening)
<section class="quote-opening">
<h3>{{ $opening['label'] }}</h3>
@if($opening['hasArea'])<p>Surface : {{ \App\Services\Quotes\QuoteFinancials::quantity($opening['unitArea']) }} m² par ouverture · {{ \App\Services\Quotes\QuoteFinancials::quantity($opening['totalArea']) }} m² au total.</p>@endif
<div class="table-wrap"><table><thead><tr><th>Désignation</th><th>Quantité</th><th>PU (F CFA)</th><th>Montant (F CFA)</th></tr></thead><tbody>
@foreach($opening['lines'] as $line)<tr><td>{{ $line['description'] }}</td><td>{{ $line['displayQuantity'] }} {{ ['m'=>'m','m2'=>'m²','piece'=>'pce','flat'=>'forfait','lot100'=>'lot de 100','opening'=>'ouv.'][$line['unit']] }}</td><td>{{ !empty($line['pending']) ? 'À définir' : number_format($line['unitPrice'],0,',',' ') }}</td><td>{{ !empty($line['pending']) ? 'À définir' : number_format($line['displayAmount'],0,',',' ') }}</td></tr>@endforeach
</tbody></table></div><p>@if($opening['quantity']>1)Sous-total unitaire : {{ number_format($opening['unitSubtotal'],0,',',' ') }} F CFA · @endif Sous-total : <strong>{{ number_format($opening['subtotal'],0,',',' ') }} F CFA</strong></p>
</section>
@endforeach
@if(!empty($financial['fees']))<h3>Frais</h3>@foreach($financial['fees'] as $fee)<p>{{ $fee['description'] }} · {{ $fee['quantity'] }} × {{ number_format($fee['unitPrice'],0,',',' ') }} = {{ number_format($fee['amount'],0,',',' ') }} F CFA</p>@endforeach @endif
<dl class="detail-list">
@foreach(['suppliesAndConfectionTotal'=>'Fournitures et confection','feesTotal'=>'Frais','tax'=>'Taxe','grossTotal'=>'Total avant remise','discountAmount'=>'Remise','finalTotal'=>'Total définitif','deposit'=>'Acompte','balance'=>'Solde après acompte'] as $key=>$label)<dt>{{ $label }}</dt><dd>{{ number_format($financial[$key],0,',',' ') }} F CFA</dd>@endforeach
</dl>
@if($scheduleText=\App\Services\Quotes\QuoteFinancials::scheduleText($financial))<p>Échéancier : {{ $scheduleText }}</p>@else<p>Un acompte de {{ str_replace('.',',',$financial['depositPercent']) }} % est obligatoire avant le démarrage des travaux.<br>Le solde de {{ str_replace('.',',',$financial['balancePercent']) }} % est payable à la fin de la prestation.</p>@endif

</div>
