<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\AppointmentController;

require __DIR__.'/staff.php';

/* Pages publiques servies par cette application Laravel. */
// Site public V2 : boutiques Stores & Rideaux, branchées sur les API de l'espace pro ci-dessous.
Route::get('/', [App\Http\Controllers\Site\SiteController::class, 'home'])->name('home');
Route::get('/shop', [App\Http\Controllers\Site\SiteController::class, 'shop'])->name('site.shop');
Route::get('/shop/{shop}', [App\Http\Controllers\Site\SiteController::class, 'shop'])->whereIn('shop', ['rideaux', 'stores'])->name('site.shop.show');
Route::get('/produit/{reference}', [App\Http\Controllers\Site\SiteController::class, 'product'])->where('reference', '[A-Za-z0-9._-]+')->name('site.product');
Route::get('/{page}', [App\Http\Controllers\Site\SiteController::class, 'legal'])->whereIn('page', array_keys(App\Http\Controllers\Site\SiteController::LEGAL))->name('site.legal');
Route::redirect('/cgv', '/conditions-de-vente', 301);
Route::redirect('/cookies', '/confidentialite#cookies', 301);
Route::get('/media/{media}/{hash}', [App\Http\Controllers\MediaController::class, 'show'])->whereNumber('media')->where('hash', '[a-f0-9]{64}')->name('media.show');
Route::get('/devis', [App\Http\Controllers\Site\SiteController::class, 'devis'])->name('quote');
Route::get('/devis/materiaux/{option}/image', [\App\Http\Controllers\MediaController::class, 'quoteMaterial'])->name('quote.material-image');
Route::get('/devis/produits/{product}/image',[App\Http\Controllers\MediaController::class,'quoteProduct'])->name('quote.product-image');
// Lien de document envoyé sur WhatsApp : page de téléchargement (signature vérifiée par le contrôleur), puis le PDF.
Route::get('/d/{message}', [App\Http\Controllers\SharedDocumentController::class, 'show'])->whereNumber('message')->middleware('throttle:30,1')->name('documents.whatsapp');
Route::get('/d/{message}/pdf', [App\Http\Controllers\SharedDocumentController::class, 'file'])->whereNumber('message')->middleware(['signed:relative','throttle:30,1'])->name('documents.whatsapp.file');
Route::get('/health/ready', App\Http\Controllers\ReadinessController::class)->middleware('throttle:60,1');
Route::post('/api/analytics/collect', [App\Http\Controllers\AnalyticsController::class, 'collect'])->middleware('throttle:120,1');
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
