@php($quoteTotals=\App\Services\Quotes\QuoteFinancials::forQuote($quote))
<section class="panel" id="paiements" aria-labelledby="h-pay"><h2 id="h-pay">Paiements</h2>
<p class="muted">Devis : {{ \App\Services\StaffInterface::date($quote->quote_date ?? $quote->created_at->format('Y-m-d')) }} · Livraison prévue : {{ \App\Services\StaffInterface::date($quote->delivery_date) }} · Pose prévue : {{ \App\Services\StaffInterface::date($quote->installation_date) }}</p>
<p>Conditions : {{ $quote->payment_mode==='full' ? 'Paiement intégral' : ($quote->payment_mode==='advance' ? 'Avance de '.number_format($quote->deposit_amount,0,',',' ').' FCFA puis solde' : 'À préciser') }}</p>
<p><strong>Reçu : {{ number_format($quote->receivedAmount(),0,',',' ') }} FCFA · Reste à recevoir : {{ number_format($quoteTotals['total']-$quote->receivedAmount(),0,',',' ') }} FCFA</strong></p>
<ul class="rows">
@forelse($quote->payments()->with('recorder')->orderBy('received_on')->get() as $payment)
<li class="py-8">
<div @if($payment->voided_at) class="strike" @endif><span class="mono">{{ $payment->received_on->format('d/m/Y') }}</span> · <span class="amount">{{ number_format($payment->amount,0,',',' ') }} FCFA</span> · {{ \App\Models\QuotePayment::METHODS[$payment->method] }} · {{ $payment->reference }}</div>
<small class="muted">Saisi par {{ $payment->recorder?->name }}</small>
@if(!$payment->voided_at)<div><a href="{{ route('staff.layouts.receipt',[$quote,$payment]) }}">Télécharger le reçu PDF</a></div>@endif
@if($payment->voided_at)<p class="muted">Saisie annulée : {{ $payment->void_reason }}</p>
@else<details><summary>Corriger une erreur de saisie</summary><form method="post" action="{{ route('staff.quotes.receipt.void',[$quote,$payment]) }}">@csrf<label>Motif d’annulation de cette saisie<input name="void_reason" required minlength="5" maxlength="1000"></label><button class="secondary mt-8">Annuler cette saisie de paiement</button></form></details>@endif
</li>
@empty<li class="muted py-8">Aucun paiement reçu enregistré.</li>@endforelse
</ul>
@if(!in_array($quote->status,['cancelled','rejected']))
<details><summary class="link">Enregistrer un nouveau paiement reçu</summary><form method="post" action="{{ route('staff.quotes.receipt',$quote) }}">@csrf @include('staff.quote-receipt-fields')<button class="mt-8">Enregistrer le paiement reçu</button></form></details>
@endif
<p class="muted">Le suivi des versements du devis est distinct du statut de règlement de la facture.</p>
</section>
