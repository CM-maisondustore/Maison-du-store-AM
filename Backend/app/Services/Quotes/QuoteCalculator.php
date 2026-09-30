<?php
namespace App\Services\Quotes;

/** Pure calculator. No database, HTTP, UI or PDF dependency. Amounts are XOF integers. */
final class QuoteCalculator
{
    public function calculate(array $input): array
    {
        if (empty($input['openings']) || count($input['openings']) > 100) QuoteValidator::fail('ouvertures');
        $openings = []; $supplies = 0; $count = 0;
        foreach ($input['openings'] as $opening) {
            $calculated = (new OpeningCalculator)->calculate($opening);
            $openings[] = $calculated; $supplies += $calculated['subtotal']; $count += $calculated['quantity'];
        }
        $fees = []; $feesTotal = 0;
        foreach ($input['fees'] ?? [] as $fee) {
            $scope = $fee['scope'] ?? 'global';
            if (!in_array($scope, ['global','per_opening','per_trip'], true)) QuoteValidator::fail('portée des frais');
            $quantity = $scope === 'per_opening' ? $count : ($scope === 'per_trip' ? QuoteValidator::count($fee['quantity'] ?? 1,'trajets') : 1);
            $price = QuoteValidator::money($fee['unitPrice'], 'frais');
            $amount = QuoteValidator::money($quantity*$price, 'montant des frais');
            $fees[] = [...$fee, 'scope'=>$scope, 'quantity'=>$quantity, 'unitPrice'=>$price, 'amount'=>$amount]; $feesTotal += $amount;
        }
        $mode = $input['displayMode'] ?? 'grouped';
        if (!in_array($mode, ['grouped','individual'], true)) QuoteValidator::fail('affichage des ouvertures');
        return ['version'=>2, 'displayMode'=>$mode, 'openings'=>$openings, 'fees'=>$fees,
            ...QuoteTotals::calculate($supplies, $feesTotal, $input)];
    }
}
