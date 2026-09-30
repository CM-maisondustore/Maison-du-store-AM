@foreach(['description','quantity','unit_price'] as $key)<input type="hidden" name="lines[{{ $index }}][{{ $key }}]" value="{{ $line[$key] ?? '' }}">@endforeach
