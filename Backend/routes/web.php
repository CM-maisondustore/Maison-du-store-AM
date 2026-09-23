<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\AppointmentController;

/* Pages publiques servies par cette application Laravel. */
Route::view('/', 'home')->name('home');
Route::view('/devis', 'quote')->name('quote');
Route::get('/health/ready', App\Http\Controllers\ReadinessController::class)->middleware('throttle:60,1');
Route::get('/robots.txt', function () {
    return response("User-agent: *\n".(config('deployment.indexing') ? "Allow: /\n" : "Disallow: /\n"), 200)
        ->header('Content-Type', 'text/plain');
});

// Même origine et middleware web : sessions et CSRF restent actifs pour les POST.
Route::prefix('api')->group(function () {
    Route::get('/products', CatalogueController::class);
    Route::get('/appointments/availability', [AppointmentController::class, 'availability'])->middleware('throttle:60,1');
    Route::middleware('throttle:submissions')->group(function () {
        Route::post('/quotes/stores', QuoteController::class)->name('quotes.stores');
        Route::post('/quotes/curtains', QuoteController::class)->name('quotes.curtains');
        Route::post('/appointments', [AppointmentController::class, 'store']);
    });
});
