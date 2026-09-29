<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void
    {
        Schema::table('quote_options', fn (Blueprint $t) => $t->softDeletes());
        $this->retireLegacyMaterials();
    }
    public function retireLegacyMaterials(): void
    {
        // Keep old identifiers and labels for history; exclude only the nine
        // material entries from the catalogue preceding the new tariff grid.
        DB::table('quote_options')->where('kind','material')->whereNull('deleted_at')
            ->whereIn('code',['lin','coton','polyester','velours','voilage','toile','screen','aluminium','bois'])
            ->update(['deleted_at'=>now()]);
    }
    public function down(): void
    {
        Schema::table('quote_options', fn (Blueprint $t) => $t->dropSoftDeletes());
    }
};
