<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('quote_options', function (Blueprint $t) {
            $t->unsignedInteger('unit_price')->nullable();
            $t->string('unit',20)->default('m');
            $t->json('finish_codes')->nullable();
            $t->boolean('public_choice')->default(false);
        });
    }
    public function down(): void { Schema::table('quote_options', fn (Blueprint $t) => $t->dropColumn(['unit_price','unit','finish_codes','public_choice'])); }
};
