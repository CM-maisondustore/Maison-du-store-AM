<?php
namespace App\Services\Quotes;

final class OpeningCalculator
{
    public function calculate(array $input): array
    {
        $width = QuoteValidator::decimal($input['width'], 'largeur', true);
        $height = QuoteValidator::decimal($input['height'], 'hauteur', true);
        $count = QuoteValidator::count($input['quantity'] ?? 1, 'nombre d’ouvertures');
        $area = $width->multipliedBy($height);
        $lines = []; $subtotal = 0;
        if (empty($input['lines']) || count($input['lines']) > 100) QuoteValidator::fail('composants');
        foreach ($input['lines'] as $line) {
            $unit = $line['unit'] ?? 'piece';
            if (!in_array($unit, ['m', 'm2', 'piece', 'flat', 'opening', 'lot100'], true)) QuoteValidator::fail('unité');
            $manual = isset($line['quantity']) && $line['quantity'] !== '';
            $mode = $line['quantityMode'] ?? 'manual';
            if (!in_array($mode,['manual','width','area','coefficient','per_meter'],true)) QuoteValidator::fail('mode de quantité');
            $quantity = $manual ? QuoteValidator::decimal($line['quantity'], 'quantité', true) : match ($mode) {
                'width' => $width,
                'area' => $area,
                'coefficient' => $width->multipliedBy(QuoteValidator::decimal($line['coefficient'] ?? '2.5', 'coefficient', true)),
                default => QuoteValidator::decimal($line['defaultQuantity'] ?? 1, 'quantité', true),
            };
            if (in_array($unit, ['piece','lot100','opening','flat'], true)) QuoteValidator::count(QuoteValidator::text($quantity), 'quantité entière');
            $price = QuoteValidator::money($line['unitPrice'], 'prix unitaire');
            $amount = QuoteValidator::rounded($quantity->multipliedBy($price));
            $total = QuoteValidator::money($amount * $count, 'montant des exemplaires');
            $lines[] = [...$line, 'unit'=>$unit, 'quantity'=>QuoteValidator::text($quantity), 'unitPrice'=>$price,
                'quantitySource'=>$line['quantitySource'] ?? ($manual ? 'manual' : 'calculated'), 'priceSource'=>$line['priceSource'] ?? 'default',
                'amount'=>$amount, 'totalQuantity'=>QuoteValidator::text($quantity->multipliedBy($count)), 'total'=>$total];
            $subtotal += $amount;
        }
        return ['label'=>$input['label'] ?? 'Ouverture', 'width'=>QuoteValidator::text($width), 'height'=>QuoteValidator::text($height),
            'quantity'=>$count, 'unitArea'=>QuoteValidator::text($area), 'totalArea'=>QuoteValidator::text($area->multipliedBy($count)),
            'lines'=>$lines, 'unitSubtotal'=>$subtotal, 'subtotal'=>QuoteValidator::money($subtotal*$count, 'sous-total')];
    }
}
