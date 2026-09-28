<?php
namespace App\Services;
use Illuminate\Http\Request;
class Pricing
{
    public static function validate(Request $r): array
    {
        if ($r->has('tax_rate')) {
            $r->validate(['tax_rate' => 'required|numeric|min:0|max:100|decimal:0,2']);
            $r->merge(['tax_basis_points' => (int) round((float) $r->input('tax_rate') * 100)]);
        }
        $data = $r->validate([
            'lines' => 'required|array|min:1|max:300', 'lines.*.description' => 'required|string|max:250',
            'lines.*.quantity' => 'required|integer|min:1|max:1000', 'lines.*.unit_price' => 'required|integer|min:0|max:100000000',
            'tax_basis_points' => 'required|integer|min:0|max:10000',
        ]);
        $data['lines'] = array_map(fn ($line) => ['description' => $line['description'], 'quantity' => (int) $line['quantity'], 'unit_price' => (int) $line['unit_price']], $data['lines']);
        return $data;
    }
    public static function totals(array $lines, int $basisPoints): array
    {
        $subtotal = array_sum(array_map(fn ($line) => $line['quantity'] * $line['unit_price'], $lines));
        $tax = (int) round($subtotal * $basisPoints / 10000);
        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax];
    }
}
