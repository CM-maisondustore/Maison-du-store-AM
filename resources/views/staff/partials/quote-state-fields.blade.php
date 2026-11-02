{{-- Reprend à l'identique le contrat de PUT devis/{quote}, pour changer d'état sans toucher aux montants. --}}
@csrf
@method('PUT')
<input type="hidden" name="edit_version" value="{{ $quote->edit_version }}">
<input type="hidden" name="tax_rate" value="{{ $quote->tax_basis_points / 100 }}">
<input type="hidden" name="valid_until" value="{{ $quote->valid_until ?? now()->addDays(30)->format('Y-m-d') }}">
<input type="hidden" name="agent_id" value="{{ $quote->agent_id }}">
@foreach($quote->lines ?? [] as $index=>$line)@include('staff.quote-calculated-line')@endforeach
@foreach($quote->configuration ? [] : ($quote->dimensions ?? []) as $index=>$line)
<input type="hidden" name="dimensions[{{ $index }}][label]" value="{{ $line['label'] ?? '' }}"><input type="hidden" name="dimensions[{{ $index }}][width]" value="{{ $line['width'] ?? '' }}"><input type="hidden" name="dimensions[{{ $index }}][height]" value="{{ $line['height'] ?? '' }}"><input type="hidden" name="dimensions[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? 1 }}">
@endforeach
