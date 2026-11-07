<?php
use Illuminate\Database\Migrations\Migration;

/** Publie la configuration Maison du Store AM des modèles PDF et des messages. Les versions PDF précédentes restent restaurables. */
return new class extends Migration {
    public function up(): void
    {
        // Base neuve (modèles pas encore créés par le seeder) : lancer « php artisan mds:templates » après le seed.
        if (\App\Models\CommunicationTemplate::where('channel','pdf')->where('key','quote')->exists()) \App\Services\MdsTemplates::apply();
    }
    public function down(): void
    {
        // Conservé : restaurer une version précédente depuis Mise en page.
    }
};
