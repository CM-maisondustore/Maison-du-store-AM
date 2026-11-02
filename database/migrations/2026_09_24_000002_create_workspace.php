<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('username')->unique(); $t->string('name');
            $t->string('email')->unique(); $t->string('password');
            $t->string('role')->default('agent'); $t->boolean('active')->default(true);
            $t->boolean('must_change_password')->default(true); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('contacts', function (Blueprint $t) {
            $t->id(); $t->string('kind')->default('client'); $t->string('name');
            $t->string('email')->nullable()->unique(); $t->string('phone')->nullable();
            $t->text('address')->nullable(); $t->text('notes')->nullable();
            $t->timestamp('marketing_consent_at')->nullable(); $t->string('consent_source')->nullable();
            $t->timestamp('archived_at')->nullable(); $t->timestamps();
        });
        Schema::table('products', fn (Blueprint $t) => $t->boolean('published')->default(true));
        Schema::table('quotes', function (Blueprint $t) {
            $t->timestamp('terms_accepted_at')->nullable()->change();
            $t->foreignId('contact_id')->nullable()->constrained('contacts');
            $t->foreignId('agent_id')->nullable()->constrained('users');
            $t->string('source')->default('site'); $t->json('lines')->nullable();
            $t->unsignedInteger('tax_basis_points')->default(0);
            $t->text('internal_notes')->nullable(); $t->date('valid_until')->nullable();
        });
        Schema::table('appointments', function (Blueprint $t) {
            $t->foreignId('contact_id')->nullable()->constrained('contacts');
            $t->foreignId('agent_id')->nullable()->constrained('users');
            $t->text('internal_notes')->nullable(); $t->string('reserved_slot')->nullable()->unique();
        });
        DB::table('appointments')->orderBy('id')->each(function ($row) {
            DB::table('appointments')->where('id', $row->id)->update(['reserved_slot' => $row->date.' '.$row->time]);
        });
        Schema::table('appointments', fn (Blueprint $t) => $t->dropUnique(['date', 'time']));
        Schema::create('interventions', function (Blueprint $t) {
            $t->id(); $t->foreignId('contact_id')->constrained('contacts');
            $t->foreignId('agent_id')->constrained('users');
            $t->foreignId('quote_id')->nullable()->constrained('quotes');
            $t->foreignId('appointment_id')->nullable()->constrained('appointments');
            $t->string('kind'); $t->string('status')->default('planned');
            $t->dateTime('scheduled_at'); $t->unsignedInteger('duration_minutes')->default(60);
            $t->text('address'); $t->text('instructions')->nullable(); $t->text('report')->nullable();
            $t->timestamp('completed_at')->nullable(); $t->timestamps();
            $t->index(['agent_id', 'scheduled_at']);
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->foreignId('quote_id')->unique()->constrained('quotes');
            $t->foreignId('contact_id')->constrained('contacts');
            $t->string('number')->nullable()->unique(); $t->string('status')->default('draft');
            $t->json('snapshot'); $t->date('due_date'); $t->timestamp('issued_at')->nullable();
            $t->timestamp('paid_at')->nullable(); $t->timestamps();
        });
        Schema::create('commercial_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('contact_id')->nullable()->constrained('contacts');
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->foreignId('quote_id')->nullable()->constrained('quotes');
            $t->foreignId('invoice_id')->nullable()->constrained('invoices');
            $t->foreignId('appointment_id')->nullable()->constrained('appointments');
            $t->string('direction')->default('outgoing'); $t->string('kind')->default('commercial');
            $t->string('status')->default('draft'); $t->string('sender'); $t->string('recipient');
            $t->string('subject'); $t->longText('body'); $t->json('document_snapshot')->nullable(); $t->string('remote_key')->nullable()->unique();
            $t->timestamp('sent_at')->nullable(); $t->timestamp('received_at')->nullable();
            $t->timestamp('read_at')->nullable(); $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('action'); $t->string('subject_type'); $t->unsignedBigInteger('subject_id');
            $t->timestamp('created_at')->useCurrent();
        });
        // Link historic requests without altering their original contact snapshots.
        foreach (['quotes' => 'contact', 'appointments' => 'details'] as $table => $column) {
            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column) {
                $data = json_decode($row->$column, true);
                $email = strtolower(trim($data['email'] ?? ''));
                if (!$email) return;
                $id = DB::table('contacts')->where('email', $email)->value('id');
                if (!$id) $id = DB::table('contacts')->insertGetId([
                    'name' => trim(($data['firstName'] ?? '').' '.($data['lastName'] ?? '')) ?: $email,
                    'email' => $email, 'phone' => $data['phone'] ?? null,
                    'address' => trim(($data['address'] ?? '').' '.($data['city'] ?? '')),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table($table)->where('id', $row->id)->update(['contact_id' => $id]);
            });
        }
    }

    public function down(): void
    {
        // Cancellation permits multiple historic entries per slot. Never discard
        // those records automatically just to recreate the original unique index.
        throw new RuntimeException('Workspace rollback requires an explicit database restoration plan.');
    }
};
