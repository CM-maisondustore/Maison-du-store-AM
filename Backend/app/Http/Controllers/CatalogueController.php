<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;

class CatalogueController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => Product::orderBy('id')->get()->map(fn ($product) =>
            array_merge($product->details, ['id' => $product->id, 'reference' => $product->reference]))]);
    }
}
