<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/*
 * MariaDB (Hostinger) : une colonne TIMESTAMP NOT NULL sans valeur par défaut reçoit « ON UPDATE CURRENT_TIMESTAMP »,
 * si bien que l'heure d'arrivée d'une visite était remplacée à chaque page vue. Colonnes rendues explicites.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) return;
        Schema::table('site_visits', function (Blueprint $t) {
            $t->timestamp('arrived_at')->nullable()->change();
            $t->timestamp('last_activity_at')->nullable()->change();
        });
    }

    public function down(): void {}
};
