@php($margins=\App\Services\PdfLetterhead::margins($style))
<style>
@page { margin:{{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm; }
body { margin:0; font-family:"{{ $style['font_family'] }}",sans-serif; font-size:{{ $style['font_size'] }}pt; color:{{ $style['text_color'] }}; line-height:1.35; }
h1 { font-size:23pt; margin:12pt 0; } h2 { font-size:12pt; margin:14pt 0 7pt; page-break-after:avoid; } h3 { font-size:11pt; margin:8pt 0 3pt; page-break-after:avoid; }
p { margin:6pt 0; overflow-wrap:break-word; } .muted {color:{{ $style['muted_color'] }};font-size:9pt}.prose {white-space:pre-wrap;overflow-wrap:break-word}
.brand {font-size:17pt;font-weight:bold}.logo {max-width:150pt;max-height:60pt}.eyebrow {color:{{ $style['muted_color'] }};font-size:8pt;letter-spacing:1pt}
table {width:100%;border-collapse:collapse;table-layout:fixed;margin:6pt 0} td,th {padding:4pt;vertical-align:top;overflow-wrap:break-word} th {text-align:left;font-weight:bold}
thead {display:table-header-group} tr {page-break-inside:avoid} .number {text-align:right}.item {width:43%}.quantity {width:15%}.price,.amount {width:21%}
.lines th {background:{{ $style['table_header']==='gold'?$style['accent']:($style['table_header']==='light'?$style['secondary_color']:'#FFFFFF') }}}
.lines td {border-bottom:0.5pt solid {{ $style['stripe_color'] }}}
@if($style['table_borders']) .lines td,.lines th {border:0.5pt solid {{ $style['muted_color'] }}} @endif
@if($style['striped_rows']) .lines tr.alternate td {background:{{ $style['stripe_color'] }}} @endif
.client td {border-bottom:0.5pt solid {{ $style['stripe_color'] }}} .client th {width:23%;color:{{ $style['muted_color'] }}}
.cards {margin:20pt 0}.cards td {width:33.33%;background:{{ $style['secondary_color'] }};border:4pt solid white}.cards strong {display:block;font-size:16pt;margin-top:6pt}.cards .grand {background:{{ $style['accent'] }};color:#111111}
.room {background:{{ $style['text_color'] }};color:white;padding:7pt}.opening-title {page-break-inside:avoid;page-break-after:avoid}.opening-subtotal {text-align:right;font-weight:bold;padding:7pt;margin-bottom:14pt;background:{{ $style['subtotal_style']==='gold'?$style['accent']:($style['subtotal_style']==='light'?$style['secondary_color']:'#FFFFFF') }}}
.totals {margin-left:25%;width:75%;page-break-inside:avoid}.totals th {width:58%}.totals .total {font-size:13pt;font-weight:bold;background:{{ $style['total_style']==='gold'?$style['accent']:($style['total_style']==='light'?$style['secondary_color']:'#FFFFFF') }}}
@foreach(['discount','deposit','balance'] as $block) .totals .{{ $block }} {background:{{ $style[$block.'_style']==='gold'?$style['accent']:($style[$block.'_style']==='light'?$style['secondary_color']:'#FFFFFF') }}} @endforeach
.draft {background:{{ $style['secondary_color'] }};padding:8pt}.page-break {page-break-before:always}.conditions {font-size:9pt}.closing {page-break-inside:avoid}.signatures {page-break-inside:avoid;margin-top:14pt;font-size:9pt}.signatures td {width:50%;border-top:1pt solid {{ $style['accent'] }};height:68pt}.signature-image {max-width:100pt;max-height:48pt}
.footer {margin-top:10pt;border-top:1pt solid {{ $style['accent'] }};padding-top:5pt;font-size:7.5pt;color:{{ $style['muted_color'] }};page-break-inside:avoid}.footer p {margin:3pt 0}
.detail-title {font-size:19pt;margin:0 0 6pt}.lines td,.lines th,.totals td,.totals th {padding:3pt 4pt;line-height:1.1}.room {margin:10pt 0 5pt;padding:5pt}.opening-title p {margin:3pt 0}.opening-subtotal {margin-bottom:9pt;padding:5pt}.conditions h2 {margin:8pt 0 5pt}
</style>
