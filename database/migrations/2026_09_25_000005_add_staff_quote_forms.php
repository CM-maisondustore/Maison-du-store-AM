<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('quote_options', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('kind',20); $t->string('category',20); $t->string('label');
            $t->json('product_ids')->nullable(); $t->string('motorization',10)->default('any'); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::table('quotes', function (Blueprint $t) {
            $t->json('configuration')->nullable(); $t->date('quote_date')->nullable(); $t->date('delivery_date')->nullable(); $t->date('installation_date')->nullable();
            $t->string('payment_mode',20)->nullable(); $t->unsignedBigInteger('deposit_amount')->default(0);
            $t->uuid('submission_token')->nullable()->unique(); $t->foreignId('created_by')->nullable()->constrained('users'); $t->unsignedInteger('edit_version')->default(0);
        });
        Schema::create('quote_payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('quote_id')->constrained('quotes'); $t->foreignId('recorded_by')->constrained('users');
            $t->uuid('token')->unique(); $t->unsignedBigInteger('amount'); $t->date('received_on'); $t->string('method',20); $t->string('reference')->nullable();
            $t->timestamp('voided_at')->nullable(); $t->text('void_reason')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('quote_payments'); Schema::dropIfExists('quote_options');
        Schema::table('quotes', function (Blueprint $t) { $t->dropConstrainedForeignId('created_by'); $t->dropColumn(['configuration','quote_date','delivery_date','installation_date','payment_mode','deposit_amount','submission_token','edit_version']); });
    }
};
