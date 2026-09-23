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
            foreach (['products', 'quotes', 'appointments'] as $table) {
                DB::table($table)->select('id')->limit(1)->get();
            }
            return response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store');
        } catch (Throwable $exception) {
            return response()->json(['status' => 'unavailable'], 503)->header('Cache-Control', 'no-store');
        }
    }
}
