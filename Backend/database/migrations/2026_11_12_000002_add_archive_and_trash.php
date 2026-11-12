<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Archives et corbeille de l'espace pro (devis, catalogue, clients, rendez-vous, messagerie, factures).
 * archived_at : retiré des listes courantes, consultable dans « Archivés ».
 * deleted_at + deleted_by : mis à la corbeille depuis l'espace pro, supprimé définitivement après 90 jours.
 * deleted_by distingue ces éléments des produits déjà retirés par une ancienne migration (jamais purgés).
 */
return new class extends Migration
{
    private const TABLES = ['quotes', 'products', 'contacts', 'appointments', 'commercial_messages', 'invoices'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'archived_at')) $t->timestamp('archived_at')->nullable()->index();
                if (!Schema::hasColumn($table, 'deleted_at')) $t->softDeletes()->index();
                $t->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropConstrainedForeignId('deleted_by');
                if (!in_array($table, ['contacts', 'products'], true)) $t->dropColumn(['archived_at', 'deleted_at']);
                elseif ($table === 'products') $t->dropColumn('archived_at');
                else $t->dropColumn('deleted_at');
            });
        }
    }
};
