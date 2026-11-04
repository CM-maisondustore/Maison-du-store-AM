{{-- Étape 4 : échéancier du devis. Les montants viennent du dernier calcul (QuoteTotals) ; changer de choix relance le calcul. --}}
@php
 $pricing=old('pricing',$quote->configuration['calculation']['pricing'] ?? []);
 // Un devis chiffré avant l'échéancier garde son acompte unique (2 échéances au même pourcentage) ; un nouveau devis suit le total.
 $choice=$pricing['schedule'] ?? ($quote->exists && isset($pricing['deposit_percent']) ? 'two' : 'auto');
 $deposit=$pricing['deposit_percent'] ?? 70;
 $steps=$liveTotal ? ($financial['schedule'] ?? null) : null;
 $commitment=\App\Services\DocumentLayout::presentation('quote')['commitment_text'] ?? \App\Services\DocumentLayout::COMMITMENT;
 $threshold=number_format(\App\Services\Quotes\QuoteTotals::SCHEDULE_THRESHOLD,0,',',' ');
@endphp
<fieldset class="bare schedule-choice">
<legend>Échéancier</legend>
<div class="tile-group schedule-tiles">
@foreach(['auto'=>['Selon le total','2 échéances jusqu’à '.$threshold.' F, 3 au-delà'],'two'=>['2 échéances','Acompte puis solde'],'three'=>['3 échéances','50 / 30 / 20']] as $value=>[$title,$note])
<label class="tile"><input type="radio" name="pricing[schedule]" value="{{ $value }}" @checked($choice===$value)><span><strong>{{ $title }}</strong><small>{{ $note }}</small></span></label>
@endforeach
</div>
</fieldset>
<label class="schedule-deposit">Acompte des 2 échéances<span class="field-unit"><input name="pricing[deposit_percent]" type="number" min="0" max="100" step="0.01" required value="{{ $deposit }}"><span class="unit">%</span></span><span class="hint">Le solde reprend le reste. Avec 3 échéances, la répartition est fixe : 50 % à la validation du chantier, 30 % après installation des rails, 20 % après livraison et pose.</span></label>
<div class="schedule" data-schedule aria-live="polite">
<table class="schedule-table" data-schedule-table @unless($steps) hidden @endunless><caption class="sr-only">Échéances prévues</caption><tbody data-schedule-rows>
@foreach($steps ?? [] as $step)<tr><td class="mono">{{ \App\Services\Quotes\QuoteFinancials::quantity($step['percent']) }} %</td><td><strong>{{ $step['label'] }}</strong>@if($step['note'])<small>{{ $step['note'] }}</small>@endif</td><td class="mono">{{ number_format($step['amount'],0,',',' ') }} FCFA</td></tr>@endforeach
</tbody></table>
<p class="muted" data-schedule-empty @if($steps) hidden @endif>{{ $liveTotal ? 'Ce devis a été chiffré avec un acompte unique. Recalculez-le pour afficher son échéancier.' : 'Les montants s’affichent après le calcul de l’étape 3.' }}</p>
</div>
<div class="commitment"><p class="commitment-title">Notre engagement</p><p class="mb-0">{{ $commitment }}</p></div>
<p class="hint">Cette mention s’imprime sous les conditions de paiement du devis. Elle se modifie dans Mise en page, rubrique Entreprise.</p>
