<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // A missing cost differs from a service explicitly priced at zero.
            foreach (['material', 'making', 'delivery', 'installation'] as $cost) {
                $table->unsignedBigInteger($cost.'_cost')->nullable();
                $table->string($cost.'_cost_unit', 20)->default(
                    in_array($cost, ['delivery', 'installation'], true) ? 'flat' : 'item'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['material', 'making', 'delivery', 'installation'] as $cost) {
                $table->dropColumn([$cost.'_cost', $cost.'_cost_unit']);
            }
        });
    }
};
