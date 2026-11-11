<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Configuration du chiffrage par matériau / fourniture (Matériaux et finitions) :
 * calcul proposé, coefficient et base quand la quantité du devis est vide.
 * Vide = comportement historique ; les devis enregistrés gardent leurs paramètres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_options', function (Blueprint $t) {
            $t->string('calc_mode', 20)->nullable();
            $t->decimal('calc_coefficient', 8, 3)->nullable();
            $t->string('calc_basis', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quote_options', fn (Blueprint $t) => $t->dropColumn(['calc_mode', 'calc_coefficient', 'calc_basis']));
    }
};
