<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;

class CatalogueController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => Product::with('media')->where('published', true)->orderBy('id')->get()->map(fn ($product) =>
            array_merge($product->details, ['id' => $product->id, 'reference' => $product->reference, 'imageUrl' => $product->media?->url(), 'imageAlt' => $product->image_alt ?: ($product->details['name'] ?? '')]))]);
    }
}
