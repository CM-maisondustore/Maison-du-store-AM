<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Suivi des liens de documents transmis (devis, factures, reçus) : ouverture de la page, PDF ouvert ou téléchargé,
 * lien expiré. Journal d'accès au document, sans adresse IP ; seul le type d'appareil est noté.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_link_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('commercial_message_id')->constrained('commercial_messages')->cascadeOnDelete();
            $t->string('event', 20);
            $t->string('device', 20)->nullable();
            $t->timestamp('created_at')->index();
            $t->index(['commercial_message_id', 'event']);
        });
    }

    public function down(): void { Schema::dropIfExists('shared_link_events'); }
};
