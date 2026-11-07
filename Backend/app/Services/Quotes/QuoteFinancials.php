<?php
namespace App\Services\Quotes;

use App\Models\Quote;
use App\Services\Pricing;

/** Read saved amounts. Legacy documents retain their original calculation. */
final class QuoteFinancials
{
    public static function forQuote(Quote $quote): array
    {
        $totals=$quote->configuration['calculation']['financial'] ?? Pricing::totals($quote->lines ?? [],$quote->tax_basis_points ?? 0);
        $total=$totals['total']; $deposit=$quote->deposit_amount ?? 0; $received=$quote->exists ? $quote->receivedAmount() : 0;
        return [...$totals,'deposit'=>$deposit,'balance'=>$total-$deposit,'receivedAmount'=>$received,'outstanding'=>$total-$received,
            'paymentStatus'=>$received >= $total ? 'fully_paid' : ($received >= $deposit ? 'deposit_paid' : 'pending_deposit'),
            'productionAuthorized'=>$received >= $deposit && empty($quote->configuration['pricing_review_required']) && empty($quote->configuration['calculation']['pending'])];
    }
    /** Échéancier lisible : « 50 % à la validation du chantier (257 032 F CFA) ; … ». Null pour un devis à acompte unique. */
    public static function scheduleText(array $financial): ?string
    {
        if (empty($financial['schedule'])) return null;
        $when=count($financial['schedule'])===2 ? ['validation'=>'à la commande','pose'=>'à la livraison et pose']
            : ['validation'=>'à la validation du chantier','rails'=>'après installation des rails','pose'=>'après livraison et pose'];
        return implode(' ; ',array_map(fn($step)=>self::quantity($step['percent']).' % '.($when[$step['key']] ?? mb_strtolower($step['label'])).' ('.number_format($step['amount'],0,',',' ').' F CFA)',$financial['schedule'])).'.';
    }
    public static function forSnapshot(array $snapshot): array
    {
        return $snapshot['financial'] ?? Pricing::totals($snapshot['lines'] ?? [],$snapshot['tax_basis_points'] ?? 0);
    }
    public static function display(array $financial): array
    {
        $rows=[];
        foreach ($financial['openings'] ?? [] as $opening) {
            $individual=($financial['displayMode'] ?? 'grouped')==='individual';
            for ($index=1; $index<=($individual ? $opening['quantity'] : 1); $index++) {
                $rows[]=['label'=>$opening['label'].($individual ? ' — exemplaire '.$index.'/'.$opening['quantity'] : ' × '.$opening['quantity']),
                    'lines'=>array_map(fn($line)=>[...$line,'displayQuantity'=>self::quantity($individual ? $line['quantity'] : $line['totalQuantity']), 'displayAmount'=>$individual ? $line['amount'] : $line['total']],$opening['lines']),
                    'quantity'=>$individual ? 1 : $opening['quantity'], 'hasArea'=>in_array('m2',array_column($opening['lines'],'unit'),true), 'unitArea'=>$opening['unitArea'],'totalArea'=>$individual ? $opening['unitArea'] : $opening['totalArea'],
                    'unitSubtotal'=>$opening['unitSubtotal'],'subtotal'=>$individual ? $opening['unitSubtotal'] : $opening['subtotal']];
            }
        }
        return $rows;
    }
    public static function quantity(mixed $value): string
    {
        return str_replace('.',',',QuoteValidator::text(\Brick\Math\BigDecimal::of((string)$value)));
    }
    /** Compatibility boundary for old snapshots and previews. Templates only display values. */
    public static function document(array $data): array
    {
        $totals=$data['financial'] ?? Pricing::totals($data['lines'] ?? [],$data['tax_basis_points'] ?? 0);
        $lines=isset($totals['version']) ? $data['lines'] : array_map(fn($line)=>[...$line,'amount'=>QuoteValidator::rounded(QuoteValidator::decimal($line['quantity'],'quantité')->multipliedBy($line['unit_price']))],$data['lines'] ?? []);
        return [...$data,'lines'=>$lines,'totals'=>$totals,'opening_rows'=>self::display($totals),
            'remaining_amount'=>$totals['total']-($data['payment_plan']['received'] ?? 0)];
    }
}
