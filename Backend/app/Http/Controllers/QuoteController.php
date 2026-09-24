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
        $quote = \Illuminate\Support\Facades\DB::transaction(fn () => Quote::create([
            'contact_id' => app(\App\Services\ContactArchive::class)->capture($data['contact'])->id,
            'reference' => (string) Str::uuid(),
            'type' => $request->routeIs('quotes.stores') ? 'store' : 'rideau',
            'contact' => $data['contact'],
            'product' => $data['product'],
            'dimensions' => $data['dimensions'],
            'notes' => $data['notes'] ?? null,
            'terms_accepted_at' => now(),
        ]));
        return response()->json(['data' => ['reference' => $quote->reference, 'status' => 'pending']], 201);
    }
}
