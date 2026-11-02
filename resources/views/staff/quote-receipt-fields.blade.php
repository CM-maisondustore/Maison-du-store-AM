<input type="hidden" name="receipt_token" value="{{ old('receipt_token',(string)\Illuminate\Support\Str::uuid()) }}">
<div class="grid">
<label>Nouveau montant reçu (FCFA)<input name="receipt_amount" type="number" min="0" max="1000000000000" step="1" value="{{ old('receipt_amount') }}" placeholder="0"></label>
<label>Date de réception<input type="date" name="receipt_date" max="{{ now()->format('Y-m-d') }}" value="{{ old('receipt_date') }}"></label>
<label>Moyen de paiement<select name="receipt_method"><option value="">À préciser si un montant est reçu</option>@foreach(\App\Models\QuotePayment::METHODS as $key=>$label)<option value="{{ $key }}" @selected(old('receipt_method')===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Référence du paiement / reçu<input name="receipt_reference" maxlength="255" value="{{ old('receipt_reference') }}"></label>
</div>
<label class="check"><input name="receipt_confirmed" type="checkbox" value="1" @checked(old('receipt_confirmed',false))>Je confirme que ce nouveau montant a effectivement été reçu.</label>
<p class="muted">Renseignez uniquement un nouveau versement. Les paiements déjà enregistrés restent dans l’historique. Cette saisie consigne le versement reçu ; elle ne déclenche aucun débit.</p>
