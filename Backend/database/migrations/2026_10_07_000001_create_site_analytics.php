<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_key', 64)->index();
            $table->string('session_key', 64)->unique();
            $table->string('current_path', 500)->nullable();
            $table->string('referrer_host', 190)->nullable();
            $table->string('origin', 40)->default('direct')->index();
            $table->string('campaign', 190)->nullable();
            $table->string('device', 20)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('operating_system', 60)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedSmallInteger('page_views')->default(0);
            $table->timestamp('arrived_at')->index();
            $table->timestamp('last_activity_at')->index();
            $table->timestamps();
        });
        Schema::create('site_visit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_visit_id')->constrained('site_visits')->cascadeOnDelete();
            $table->string('name', 40)->index();
            $table->string('path', 500)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visit_events');
        Schema::dropIfExists('site_visits');
    }
};
