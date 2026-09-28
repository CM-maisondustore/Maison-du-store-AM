<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotes',fn(Blueprint $t)=>$t->timestamp('approved_at')->nullable());
        Schema::table('appointments',fn(Blueprint $t)=>$t->timestamp('confirmed_at')->nullable());
        Schema::create('communication_templates', function (Blueprint $t) {
            $t->id(); $t->string('channel', 20); $t->string('key', 60); $t->string('name');
            $t->string('subject')->default(''); $t->text('body'); $t->json('options')->nullable();
            $t->unsignedInteger('version')->default(1); $t->timestamps(); $t->unique(['channel', 'key']);
        });
        Schema::create('communication_rules', function (Blueprint $t) {
            $t->id(); $t->string('key', 60)->unique(); $t->string('mode', 20)->default('off');
            $t->unsignedInteger('delay_hours')->default(24); $t->timestamp('activated_at')->nullable(); $t->timestamps();
        });
        Schema::create('communication_checks', function (Blueprint $t) {
            $t->id(); $t->string('key')->unique(); $t->boolean('successful')->default(false);
            $t->string('fingerprint', 64)->nullable(); $t->timestamp('checked_at')->nullable(); $t->timestamps();
        });
        Schema::create('document_archives', function (Blueprint $t) {
            $t->id(); $t->foreignId('commercial_message_id')->nullable()->unique()->constrained('commercial_messages');
            $t->foreignId('invoice_id')->nullable()->unique()->constrained('invoices');
            $t->string('sha256', 64); $t->unsignedInteger('bytes'); $t->longText('payload'); $t->timestamps();
        });
        Schema::table('commercial_messages', function (Blueprint $t) {
            $t->string('channel',20)->default('email');
            $t->string('automation_key', 64)->nullable()->unique(); $t->string('automation_event', 60)->nullable();
            $t->string('source_fingerprint', 64)->nullable(); $t->boolean('auto_send')->default(false);
            $t->json('template_snapshot')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('quotes',fn(Blueprint $t)=>$t->dropColumn('approved_at'));
        Schema::table('appointments',fn(Blueprint $t)=>$t->dropColumn('confirmed_at'));
        Schema::table('commercial_messages', function (Blueprint $t) {
            $t->dropUnique(['automation_key']); $t->dropColumn(['channel','automation_key','automation_event','source_fingerprint','auto_send','template_snapshot']);
        });
        Schema::dropIfExists('document_archives'); Schema::dropIfExists('communication_checks');
        Schema::dropIfExists('communication_rules'); Schema::dropIfExists('communication_templates');
    }
};
