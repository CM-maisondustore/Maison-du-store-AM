<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('push:process --limit=100')->everyMinute()->withoutOverlapping();

// Corbeille : suppression définitive après 90 jours (aussi déclenchée une fois par jour depuis l'espace pro).
Illuminate\Support\Facades\Artisan::command('corbeille:purger', function () {
    $this->info(App\Services\Trash::purgeExpired().' élément(s) supprimé(s) définitivement.');
})->purpose('Supprime définitivement les éléments en corbeille depuis plus de 90 jours');
Schedule::command('corbeille:purger')->dailyAt('03:30');
