<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;

class CatalogueController
{
    public function __invoke(): JsonResponse
    {
        if (!\App\Models\SiteSetting::catalogueVisible()) {
            return response()->json(['data' => []])->header('Cache-Control', 'no-store');
        }
        return response()->json(['data' => Product::with('media')->unarchived()->where('published', true)->orderBy('id')->get()->map(fn ($product) =>
            array_merge(\Illuminate\Support\Arr::except($product->details, ['reference_price', 'reference_source']), ['id' => $product->id, 'reference' => $product->reference, 'imageUrl' => $product->media?->url(), 'imageAlt' => $product->image_alt ?: ($product->details['name'] ?? '')]))]);
    }
}
