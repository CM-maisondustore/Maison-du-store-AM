<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;

class QuoteController
{
    public function __invoke(QuoteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $model=collect(\App\Services\PublicQuoteChoices::models())->firstWhere('id',$data['product']['model']);
        $data['product']['model_id']=$model['productId'];
        $data['product']['model']=$model['name'];
        $publicOptions=\App\Services\PublicQuoteChoices::options()->keyBy('code');
        // Preserve the labels the client selected, even after a catalogue rename.
        foreach (['fabric','sheer',...($request->routeIs('quotes.stores') ? ['finish'] : [])] as $field) if (isset($data['product'][$field]) && isset($publicOptions[$data['product'][$field]])) $data['product'][$field]=$publicOptions[$data['product'][$field]]->label;
        if (!$request->routeIs('quotes.stores')) {
            $finish=collect(\App\Services\PublicQuoteChoices::finishes())->firstWhere('id',$data['product']['finish']);
            $data['product']['finish_code']=$finish['id'];$data['product']['finish']=$finish['label'];
            $data['product']['fabric']=implode(' + ',array_filter([$data['product']['fabric'] ?? null,$data['product']['sheer'] ?? null]));
        }
        $quote = \Illuminate\Support\Facades\DB::transaction(fn () => Quote::create([
            'contact_id' => app(\App\Services\ContactArchive::class)->capture($data['contact'])->id,
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
