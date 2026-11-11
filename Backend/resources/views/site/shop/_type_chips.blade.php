@if($shop)
    @php($typeOn = $filter->active['Type'] ?? [])
    <button type="button" aria-pressed="{{ $typeOn ? 'false' : 'true' }}" @class(['tchip', 'on' => ! $typeOn]) data-type-chip="">Tout<span>{{ $baseCount }}</span></button>
    @foreach($typeChips as $t)
        <button type="button" aria-pressed="{{ in_array($t['label'], $typeOn, true) ? 'true' : 'false' }}" @class(['tchip', 'on' => in_array($t['label'], $typeOn, true)]) data-type-chip="{{ $t['label'] }}">{{ $t['label'] }}<span>{{ $t['count'] }}</span></button>
    @endforeach
@endif
