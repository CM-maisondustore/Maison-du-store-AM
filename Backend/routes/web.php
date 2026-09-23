<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\AppointmentController;

/* Routes de présentation : à placer dans routes/web.php d'une installation Laravel. */
Route::view('/', 'home')->name('home');
Route::view('/devis', 'quote')->name('quote');

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
