<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->json('details');
            $table->timestamps();
        });
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('contact');
            $table->json('product');
            $table->json('dimensions');
            $table->text('notes')->nullable();
            $table->timestamp('terms_accepted_at');
            $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('status')->default('pending');
            $table->date('date');
            $table->string('time', 5);
            $table->json('details');
            $table->timestamps();
            $table->unique(['date', 'time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('products');
    }
};
