@php($quoteTotals=\App\Services\Quotes\QuoteFinancials::forQuote($quote))
<section class="panel"><h2>Dates et paiements</h2>
<p>Devis : {{ $quote->quote_date ?? $quote->created_at->format('Y-m-d') }} · Livraison prévue : {{ $quote->delivery_date ?: 'À préciser' }} · Pose prévue : {{ $quote->installation_date ?: 'À préciser' }}</p>
<p>Conditions : {{ $quote->payment_mode==='full' ? 'Paiement intégral' : ($quote->payment_mode==='advance' ? 'Avance de '.number_format($quote->deposit_amount,0,',',' ').' FCFA puis solde' : 'À préciser') }}</p>
<p><strong>Reçu : {{ number_format($quote->receivedAmount(),0,',',' ') }} FCFA · Reste à recevoir : {{ number_format($quoteTotals['total']-$quote->receivedAmount(),0,',',' ') }} FCFA</strong></p>
@forelse($quote->payments()->with('recorder')->orderBy('received_on')->get() as $payment)
<div class="panel"><p>{{ $payment->received_on->format('d/m/Y') }} · {{ number_format($payment->amount,0,',',' ') }} FCFA · {{ \App\Models\QuotePayment::METHODS[$payment->method] }} · {{ $payment->reference }}<br><small>Saisi par {{ $payment->recorder?->name }}</small></p>
@if(auth()->user()->isAdmin() && !$payment->voided_at)<a href="{{ route('staff.layouts.receipt',[$quote,$payment]) }}">Télécharger le reçu PDF</a>@endif
@if($payment->voided_at)<p>Saisie annulée : {{ $payment->void_reason }}</p>
@elseif(auth()->user()->isAdmin())<details><summary>Corriger une erreur de saisie</summary><form method="post" action="{{ route('staff.quotes.receipt.void',[$quote,$payment]) }}">@csrf<label>Motif d’annulation de cette saisie<input name="void_reason" required minlength="5" maxlength="1000"></label><button class="secondary">Annuler cette saisie de paiement</button></form></details>@endif
</div>
@empty<p class="muted">Aucun paiement reçu enregistré.</p>@endforelse
@if(!in_array($quote->status,['cancelled','rejected']))
<details><summary>Enregistrer un nouveau paiement reçu</summary><form method="post" action="{{ route('staff.quotes.receipt',$quote) }}">@csrf @include('staff.quote-receipt-fields')<button>Enregistrer le paiement reçu</button></form></details>
@endif
<p class="muted">Le suivi des versements du devis est distinct du statut de règlement de la facture.</p>
</section>
