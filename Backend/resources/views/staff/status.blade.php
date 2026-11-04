@php($badge=\App\Services\StaffInterface::status($status))
<span class="badge st-{{ $badge['tone'] }}"><span aria-hidden="true">{{ $badge['glyph'] }}</span>{{ $badge['label'] }}</span>
