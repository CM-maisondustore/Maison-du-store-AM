<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Throwable;

class ReadinessController
{
    public function __invoke(): JsonResponse
    {
        try {
            foreach (['products', 'quotes', 'appointments', 'users', 'contacts', 'interventions', 'invoices', 'commercial_messages', 'site_contents', 'media_assets', 'quote_options', 'quote_payments'] as $table) {
                DB::table($table)->select('id')->limit(1)->get();
            }
            return response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store');
        } catch (Throwable $exception) {
            return response()->json(['status' => 'unavailable'], 503)->header('Cache-Control', 'no-store');
        }
    }
}
