<?php
namespace App\Services\Quotes;

final class QuoteTotals
{
    /** Au-delà de ce total (F CFA), l'échéancier automatique passe de 2 à 3 échéances. */
    public const SCHEDULE_THRESHOLD = 500000;
    public const SCHEDULE_MODES = ['auto','two','three'];

    public static function calculate(int $supplies, int $fees, array $options): array
    {
        $beforeTax = QuoteValidator::money($supplies+$fees, 'sous-total');
        $basisPoints = QuoteValidator::money($options['taxBasisPoints'] ?? 0, 'taxe');
        if ($basisPoints > 10000) QuoteValidator::fail('taxe');
        $tax = QuoteValidator::rounded(QuoteValidator::decimal($beforeTax, 'sous-total')->multipliedBy($basisPoints)->dividedByExact(10000));
        $gross = QuoteValidator::money($beforeTax+$tax, 'total brut');
        $discount = $options['discount'] ?? ['type'=>'none','value'=>0];
        $value = QuoteValidator::decimal($discount['value'] ?? 0, 'remise');
        if (($discount['type'] ?? 'none') === 'percentage' && $value->isGreaterThan(100)) QuoteValidator::fail('remise');
        $amount = match ($discount['type'] ?? 'none') {
            'none' => 0,
            'percentage' => QuoteValidator::rounded(QuoteValidator::decimal($gross, 'total brut')->multipliedBy($value)->dividedByExact(100)),
            'fixed' => QuoteValidator::money($discount['value'], 'remise'),
            'target_total' => $gross-QuoteValidator::money($discount['value'], 'total négocié'),
            default => QuoteValidator::fail('type de remise'),
        };
        if ($amount < 0 || $amount > $gross) QuoteValidator::fail('remise supérieure au total');
        $final = $gross-$amount;
        $rate = QuoteValidator::decimal($options['depositPercent'] ?? 70, 'pourcentage d’acompte');
        if ($rate->isGreaterThan(100)) QuoteValidator::fail('acompte');
        // Échéancier : absent pour les devis chiffrés avant son arrivée, qui gardent leur acompte unique.
        $schedule = self::schedule($final, $rate, $options['schedule'] ?? null);
        if ($schedule) $rate = QuoteValidator::decimal($schedule['installments'][0]['percent'], 'pourcentage d’acompte');
        $deposit = $schedule ? $schedule['installments'][0]['amount'] : QuoteValidator::rounded(QuoteValidator::decimal($final, 'total définitif')->multipliedBy($rate)->dividedByExact(100));
        $received = QuoteValidator::money($options['receivedAmount'] ?? 0, 'paiements reçus');
        if ($received > $final) QuoteValidator::fail('paiements supérieurs au total');
        return ['suppliesAndConfectionTotal'=>$supplies, 'feesTotal'=>$fees, 'subtotal'=>$beforeTax, 'tax'=>$tax, 'taxBasisPoints'=>$basisPoints,
            'grossTotal'=>$gross, 'discount'=>$discount, 'discountAmount'=>$amount, 'finalTotal'=>$final, 'total'=>$final,
            'depositPercent'=>QuoteValidator::text($rate), 'balancePercent'=>QuoteValidator::text(QuoteValidator::decimal(100,'pourcentage')->minus($rate)), 'deposit'=>$deposit, 'balance'=>$final-$deposit,
            'receivedAmount'=>$received, 'outstanding'=>$final-$received,
            'paymentStatus'=>$received >= $final ? 'fully_paid' : ($received >= $deposit ? 'deposit_paid' : 'pending_deposit'),
            'productionAuthorized'=>$received >= $deposit,
            ...($schedule ? ['scheduleChoice'=>$schedule['choice'], 'scheduleMode'=>$schedule['mode'], 'schedule'=>$schedule['installments']] : [])];
    }

    /**
     * 2 échéances (acompte réglable, solde) jusqu'à 500 000 F, 3 échéances 50 / 30 / 20 au-delà.
     * Chaque échéance est arrondie au franc ; la dernière reprend le reste, pour que la somme fasse le total exact.
     */
    private static function schedule(int $final, \Brick\Math\BigDecimal $rate, ?string $choice): ?array
    {
        if ($choice === null) return null;
        if (!in_array($choice, self::SCHEDULE_MODES, true)) QuoteValidator::fail('échéancier');
        $mode = $choice === 'auto' ? ($final <= self::SCHEDULE_THRESHOLD ? 'two' : 'three') : $choice;
        $steps = $mode === 'three'
            ? [['key'=>'validation','label'=>'Acompte à la validation du chantier','note'=>'Lance la confection','percent'=>'50'],
               ['key'=>'rails','label'=>'Après installation des rails','note'=>'','percent'=>'30'],
               ['key'=>'pose','label'=>'Après livraison et pose','note'=>'','percent'=>'20']]
            : [['key'=>'validation','label'=>'Acompte à la validation du chantier','note'=>'Lance la confection','percent'=>QuoteValidator::text($rate)],
               ['key'=>'pose','label'=>'Solde après livraison et pose','note'=>'','percent'=>QuoteValidator::text(QuoteValidator::decimal(100,'pourcentage')->minus($rate))]];
        $left = $final; $last = count($steps)-1;
        foreach ($steps as $i=>&$step) {
            $step['amount'] = $i === $last ? $left : QuoteValidator::rounded(QuoteValidator::decimal($final, 'total définitif')->multipliedBy($step['percent'])->dividedByExact(100));
            $left -= $step['amount'];
        }
        unset($step);
        return ['choice'=>$choice, 'mode'=>$mode, 'installments'=>$steps];
    }
}
