<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class QuoteController
{
    public function __invoke(QuoteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $quote = Quote::create([
            'reference' => (string) Str::uuid(),
            'type' => $request->routeIs('quotes.stores') ? 'store' : 'rideau',
            'contact' => $data['contact'],
            'product' => $data['product'],
            'dimensions' => $data['dimensions'],
            'notes' => $data['notes'] ?? null,
            'terms_accepted_at' => now(),
        ]);
        return response()->json(['data' => ['reference' => $quote->reference, 'status' => 'pending']], 201);
    }
}
