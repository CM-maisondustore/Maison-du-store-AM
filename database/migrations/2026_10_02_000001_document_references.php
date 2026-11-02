<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotes', fn (Blueprint $t) => $t->string('reference',80)->change());
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->string('name')->primary();
            $t->unsignedBigInteger('next_value');
        });
        DB::table('document_sequences')->insert(['name'=>'quotes','next_value'=>112]);
    }
    // Never reset an issued document sequence during an application rollback.
    public function down(): void {}
};
