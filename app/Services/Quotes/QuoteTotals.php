<?php
namespace App\Services\Quotes;

final class QuoteTotals
{
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
        $deposit = QuoteValidator::rounded(QuoteValidator::decimal($final, 'total définitif')->multipliedBy($rate)->dividedByExact(100));
        $received = QuoteValidator::money($options['receivedAmount'] ?? 0, 'paiements reçus');
        if ($received > $final) QuoteValidator::fail('paiements supérieurs au total');
        return ['suppliesAndConfectionTotal'=>$supplies, 'feesTotal'=>$fees, 'subtotal'=>$beforeTax, 'tax'=>$tax, 'taxBasisPoints'=>$basisPoints,
            'grossTotal'=>$gross, 'discount'=>$discount, 'discountAmount'=>$amount, 'finalTotal'=>$final, 'total'=>$final,
            'depositPercent'=>QuoteValidator::text($rate), 'balancePercent'=>QuoteValidator::text(QuoteValidator::decimal(100,'pourcentage')->minus($rate)), 'deposit'=>$deposit, 'balance'=>$final-$deposit,
            'receivedAmount'=>$received, 'outstanding'=>$final-$received,
            'paymentStatus'=>$received >= $final ? 'fully_paid' : ($received >= $deposit ? 'deposit_paid' : 'pending_deposit'),
            'productionAuthorized'=>$received >= $deposit];
    }
}
