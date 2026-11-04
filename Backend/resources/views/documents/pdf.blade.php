@if(!empty($style['blocks']['items']))@include('documents.blocks')@else<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>{{ $document_title }} {{ $number }}</title>
@include('documents.partials.styles')
</head><body>
@include('documents.partials.header')
@include('documents.partials.customer')
@if($draft)<p class="draft">APERÇU / BROUILLON — non émis</p>@endif
@if(!empty($presentation['introduction']))<p class="prose">{{ $presentation['introduction'] }}</p>@endif
@if($kind==='receipt')
    @include('documents.receipts.show')
@else
    @if(!empty($object))<h2>OBJET {{ $kind==='quote'?'DU DEVIS':'DE LA FACTURE' }}</h2><p class="prose">{{ $object }}</p>@endif
    @include('documents.partials.summary-cards')
    @if($style['show_cover'])<div class="page-break"></div><h1 class="detail-title">{{ $kind==='quote'?'DEVIS DÉTAILLÉ':'DÉTAIL DE LA FACTURE' }}</h1><p class="muted">{{ $number }} · {{ $customer['firstName'] ?? '' }} {{ $customer['lastName'] ?? '' }}</p>@endif
    @if(!empty($pending_costs))<p class="draft">Chiffrage provisoire — postes à définir : {{ implode(' ; ',$pending_costs) }}</p>@endif
    @forelse($opening_groups as $room=>$openings)
        @if($room)<h2 class="room">{{ $room }}</h2>@endif
        @foreach($openings as $opening) @include('documents.quotes.opening') @endforeach
    @empty
        @include('documents.partials.legacy-lines')
    @endforelse
    @include('documents.partials.financial-summary')
    @if(!empty($schedule['delivery']) || !empty($schedule['installation']))<p>Livraison prévue : {{ $schedule['delivery'] ?: 'À préciser' }} · Pose prévue : {{ $schedule['installation'] ?: 'À préciser' }}</p>@endif
    @if($style['show_conditions'] && $conditions)<section class="conditions"><h2>CONDITIONS COMMERCIALES</h2><p class="prose">{{ $conditions }}</p></section>@endif
@if($kind==='quote' && !empty($totals['schedule']) && trim($style['commitment_text'] ?? '')!=='')<section class="conditions"><p class="prose"><strong>Notre engagement.</strong> {{ $style['commitment_text'] }}</p></section>@endif
@endif
<section class="closing">
@include('documents.partials.signatures')
@include('documents.partials.footer')
</section>
</body></html>
@endif