<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $t) {
            $t->id(); $t->string('mime', 40); $t->unsignedInteger('width'); $t->unsignedInteger('height');
            $t->unsignedInteger('bytes'); $t->string('sha256', 64); $t->longText('payload'); $t->timestamps();
        });
        Schema::create('site_contents', function (Blueprint $t) {
            $t->id(); $t->string('slot')->unique(); $t->string('kind', 20)->index();
            $t->string('title'); $t->text('description')->nullable(); $t->string('image_alt')->default('');
            $t->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('published')->default(false); $t->unsignedInteger('position')->default(0); $t->timestamps();
        });
        Schema::table('products', function (Blueprint $t) {
            $t->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $t->string('image_alt')->default('');
        });
    }
    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) { $t->dropConstrainedForeignId('media_asset_id'); $t->dropColumn('image_alt'); });
        Schema::dropIfExists('site_contents'); Schema::dropIfExists('media_assets');
    }
};
