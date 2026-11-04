{{-- Rendu par blocs (options.blocks) : positions absolues en millimètres, une page A4 par élément .bp. Montants lus dans le document, jamais recalculés. --}}
@php
 $pages=\App\Services\DocumentBlocks::pages(get_defined_vars());
 $fields=\App\Services\DocumentBlocks::fields(get_defined_vars());
 $payment=\App\Services\DocumentBlocks::payment(get_defined_vars());
 $shortTitle=match($kind){'invoice'=>'FACTURE','receipt'=>'REÇU',default=>'DEVIS'};
 $clip=fn(string $text,float $mm,int $size)=>mb_strimwidth($text,0,max(4,(int)floor($mm/($size*0.3528*0.56))),'…');
 $box=function(array $b){
   $css='left:'.$b['x'].'mm;top:'.$b['y'].'mm;width:'.$b['w'].'mm;height:'.$b['h'].'mm;font-family:"'.$b['font'].'";font-size:'.$b['size'].'pt;color:'.$b['color'].';text-align:'.$b['align'].';';
   if ($b['background']) $css.='background:'.$b['background'].';';
   if ($b['border_width']>0) $css.='border:'.$b['border_width'].'mm solid '.$b['border_color'].';';
   return $css;
 };
 $money=fn($value)=>\App\Services\DocumentViewModel::money($value);
@endphp
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>{{ $document_title }} {{ $number }}</title>
<style>
@page { margin:0; }
body { margin:0; font-family:"{{ $style['font_family'] }}",sans-serif; color:{{ $style['text_color'] }}; line-height:1.25; }
.bp { position:relative; width:210mm; height:296.5mm; overflow:hidden; page-break-after:always; }
.bp.last { page-break-after:auto; }
.b { position:absolute; overflow:hidden; }
.in { padding:1.5mm 2mm; }
p { margin:0 0 1mm; }
.strong { font-weight:bold; }
.eyebrow { font-size:6.5pt; letter-spacing:0.6pt; text-transform:uppercase; color:{{ $style['muted_color'] }}; margin-bottom:1mm; }
.muted { color:{{ $style['muted_color'] }}; }
.prose { white-space:pre-wrap; }
.doc-name { font-weight:bold; letter-spacing:1pt; line-height:1.05; margin-bottom:1.5mm; }
.lines { width:100%; border-collapse:collapse; table-layout:fixed; }
.lines td, .lines th { padding:0 1.2mm; white-space:nowrap; overflow:hidden; vertical-align:middle; line-height:1.1; }
.lines th { font-weight:bold; text-align:left; background:{{ $style['table_header']==='gold' ? $style['accent'] : ($style['table_header']==='light' ? $style['secondary_color'] : '#FFFFFF') }}; }
.lines td { border-bottom:0.2mm solid {{ $style['stripe_color'] }}; }
.lines .n { text-align:right; }
.lines tr.opening td { font-weight:bold; background:{{ $style['stripe_color'] }}; }
.lines tr.room td { font-weight:bold; color:#FFFFFF; background:{{ $style['text_color'] }}; }
.lines tr.subtotal td, .lines tr.sum td { font-weight:bold; }
.total-row { width:100%; border-collapse:collapse; height:100%; }
.total-row td { padding:0 3mm; vertical-align:middle; font-weight:bold; }
.total-label { font-size:0.75em; letter-spacing:0.8pt; text-align:left; white-space:nowrap; }
.total-amount { text-align:right; white-space:nowrap; }
.draft-band { position:absolute; left:0; top:0; width:210mm; padding:1mm 0; text-align:center; font-size:7pt; background:{{ $style['secondary_color'] }}; }
img.fit { max-width:100%; max-height:100%; }
</style>
</head><body>
@foreach($pages as $page)
<div class="bp {{ $loop->last ? 'last' : '' }}">
@if($draft)<div class="draft-band">APERÇU / BROUILLON — non émis</div>@endif
@foreach($page['items'] as $b)
@php $type=$b['type']; @endphp
@if($type==='shape')
<div class="b" style="left:{{ $b['x'] }}mm;top:{{ $b['y'] }}mm;width:{{ $b['w'] }}mm;height:{{ $b['shape']==='line' ? 0 : $b['h'] }}mm;{{ $b['shape']==='line' ? 'border-top:'.max(0.2,$b['border_width']).'mm solid '.$b['border_color'] : 'border:'.max(0.2,$b['border_width']).'mm solid '.$b['border_color'] }};{{ $b['background'] && $b['shape']==='frame' ? 'background:'.$b['background'] : '' }}"></div>
@elseif($type==='logo')
@if(!empty($style[$b['source']]))<div class="b" style="{{ $box($b) }}"><img class="fit" src="{{ $style[$b['source']] }}" alt="{{ \App\Services\DocumentBlocks::SOURCES[$b['source']] }}" style="max-width:{{ $b['w'] }}mm;max-height:{{ $b['h'] }}mm"></div>@endif
@elseif($type==='lines')
@php
 $rh=round(\App\Services\DocumentBlocks::rowHeight($b)-0.3,2);
 $cols=array_values(array_intersect(['quantity','unit_price','amount'],$b['columns']));
 $widths=['quantity'=>0.14,'unit_price'=>0.17,'amount'=>0.19];
 $designation=1-array_sum(array_map(fn($c)=>$widths[$c],$cols));
 $span=count($cols)+1;
@endphp
<div class="b" style="{{ $box($b) }}"><table class="lines"><thead><tr>@if($kind==='receipt')<th style="height:{{ $rh }}mm" colspan="{{ max(1,$span-1) }}">Détail</th>@if($span>1)<th class="n" style="height:{{ $rh }}mm">Valeur</th>@endif @else<th style="height:{{ $rh }}mm;width:{{ round($designation*100,2) }}%">Désignation</th>
@foreach($cols as $c)<th class="n" style="height:{{ $rh }}mm;width:{{ $widths[$c]*100 }}%">{{ ['quantity'=>'Qté','unit_price'=>'PU (F)','amount'=>'Montant (F)'][$c] }}</th>@endforeach @endif</tr></thead><tbody>
@foreach($b['rows'] as $row)
<tr class="{{ $row['kind'] }}">
@if(in_array($row['kind'],['opening','room'],true))<td style="height:{{ $rh }}mm" colspan="{{ $span }}">{{ $clip($row['label'],$b['w']-3,$b['size']) }}</td>
@elseif(in_array($row['kind'],['subtotal','sum'],true))<td style="height:{{ $rh }}mm" colspan="{{ max(1,$span-1) }}">{{ $clip($row['label'],$b['w']*0.6,$b['size']) }}</td>@if($span>1)<td class="n" style="height:{{ $rh }}mm">{{ $row['amount'] }}</td>@endif
@else<td style="height:{{ $rh }}mm">{{ $clip($row['label'],$b['w']*$designation-2.4,$b['size']) }}</td>@foreach($cols as $c)<td class="n" style="height:{{ $rh }}mm">{{ $row[$c] }}</td>@endforeach
@endif
</tr>
@endforeach
</tbody></table></div>
@else
<div class="b" style="{{ $box($b) }}"><div class="in">
@switch($type)
@case('text')<p class="prose">{{ strtr($b['text'],$fields) }}</p>@break
@case('commitment')@php $commitment=trim($b['text'])!=='' ? $b['text'] : ($style['commitment_text'] ?? ''); @endphp @if(trim($commitment)!=='')<p class="prose"><span class="strong">Notre engagement.</span> {{ strtr($commitment,$fields) }}</p>@endif @break
@case('company')<p class="strong" style="font-size:1.6em">{{ $issuer['name'] }}</p>@if($style['show_activities'] && $style['activities'])<p class="muted">{{ $style['activities'] }}</p>@endif<p class="muted">{{ implode(' · ',array_filter([$issuer['address'] ?? '',$style['issuer_phone'] ?? '',$issuer['email'] ?? ''])) }}</p>@break
@case('title')<p class="doc-name" style="font-size:2.6em">{{ $shortTitle }}</p><p>N° {{ $number }} · {{ $date }}@if($due) · {{ $kind==='quote' ? 'valable jusqu’au' : 'échéance' }} {{ $due }}@endif</p>@if(!empty($quote_reference))<p class="muted">Devis {{ $quote_reference }}</p>@endif @break
@case('client')<p class="eyebrow">Client</p><p class="strong">{{ trim(($customer['firstName'] ?? '').' '.($customer['lastName'] ?? '')) }}</p><p>{{ implode(' · ',array_filter([$customer['phone'] ?? '',trim(($customer['address'] ?? '').' '.($customer['city'] ?? ''))])) }}</p>@if(!empty($customer['email']))<p>{{ $customer['email'] }}</p>@endif @break
@case('object')@php $objectText=$kind==='receipt' ? 'Versement reçu pour le devis '.($quote_reference ?? $number) : trim($object ?? ''); @endphp @if($objectText!=='')<p class="eyebrow">Objet</p><p class="prose">{{ $objectText }}</p>@endif @break
@case('total')<table class="total-row"><tr><td class="total-label">{{ $kind==='receipt' ? 'MONTANT REÇU' : 'TOTAL GÉNÉRAL' }}</td><td class="total-amount">{{ $money($kind==='receipt' ? ($receipt['amount'] ?? 0) : ($totals['total'] ?? 0)) }}</td></tr></table>@break
@case('payment')<p class="strong">Conditions de paiement</p>@foreach($payment['lines'] as $line)<p>{{ $line }}</p>@endforeach @if($payment['extra'])<p class="prose muted">{{ $payment['extra'] }}</p>@endif @break
@case('signature')<p class="muted">{{ $kind==='receipt' ? 'Signature du client' : 'Bon pour accord, signature du client' }}</p>@break
@case('stamp')<p class="muted">Cachet et signature de l’entreprise</p>@if(!empty($style['signature']))<p>{{ $style['signature'] }}</p>@endif @foreach(['stamp_image','signature_image'] as $asset)@if(!empty($style[$asset]))<img src="{{ $style[$asset] }}" alt="" style="max-width:{{ $b['w']/2-3 }}mm;max-height:{{ $b['h']-9 }}mm">@endif @endforeach @break
@case('footer')<p>{{ implode(' · ',array_filter([$issuer['name'] ?? '',$issuer['address'] ?? '',$style['issuer_rccm'] ? 'RCCM '.$style['issuer_rccm'] : '',$style['issuer_ncc'] ? 'NCC '.$style['issuer_ncc'] : '',$issuer['registration'] ?? ''])) }}</p>@if(!empty($issuer['tax_note']))<p class="prose">{{ $issuer['tax_note'] }}</p>@endif @if(!empty($style['footer']))<p class="prose">{{ $style['footer'] }}</p>@endif @break
@endswitch
</div></div>
@endif
@endforeach
</div>
@endforeach
</body></html>
