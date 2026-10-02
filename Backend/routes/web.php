<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\AppointmentController;

require __DIR__.'/staff.php';

/* Pages publiques servies par cette application Laravel. */
Route::get('/', App\Http\Controllers\HomeController::class)->name('home');
Route::get('/media/{media}/{hash}', [App\Http\Controllers\MediaController::class, 'show'])->whereNumber('media')->where('hash', '[a-f0-9]{64}')->name('media.show');
Route::view('/devis', 'quote')->name('quote');
Route::get('/devis/produits/{product}/image',[App\Http\Controllers\MediaController::class,'quoteProduct'])->name('quote.product-image');
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
