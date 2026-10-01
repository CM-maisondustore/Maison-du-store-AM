<details class="panel"><summary>Présentation du document et éditions archivées</summary>
@if($quote->status!=='accepted' && !\App\Models\Invoice::where('quote_id',$quote->id)->exists())
<form method="post" action="{{ route('staff.layouts.metadata',$quote) }}">@csrf<input type="hidden" name="edit_version" value="{{ $quote->edit_version }}">
<label>Objet du devis<textarea name="object" maxlength="1000">{{ old('object',$quote->document_metadata['object'] ?? '') }}</textarea></label>
@foreach($quote->configuration['calculation']['financial']['openings'] ?? [] as $index=>$opening)<label>Pièce — {{ $opening['label'] }}<input name="rooms[{{ $index }}]" maxlength="100" value="{{ old('rooms.'.$index,$quote->document_metadata['rooms'][$index] ?? '') }}" placeholder="Ex. Salon principal"></label>@endforeach
<button>Enregistrer la présentation</button></form>@endif
<h3>Éditions conservées</h3>
@forelse(\App\Models\DocumentIssue::where('quote_id',$quote->id)->with('payment')->latest('id')->get(['id','quote_payment_id','created_at']) as $edition)<p><a href="{{ route('staff.layouts.archive',$edition) }}">{{ $edition->quote_payment_id?'Reçu':'Devis' }} · {{ $edition->created_at->format('d/m/Y H:i') }} · édition {{ $edition->id }}</a>@if($edition->payment?->voided_at) — paiement annulé, original conservé@endif</p>@empty<p>Aucune édition archivée depuis la refonte. Les anciennes pièces jointes restent accessibles dans la messagerie.</p>@endforelse
</details>
