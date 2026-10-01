<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('communication_templates', function (Blueprint $t) { $t->json('layout_draft')->nullable(); $t->unsignedInteger('published_version')->nullable(); });
        Schema::table('quotes', function (Blueprint $t) { $t->json('document_metadata')->nullable(); });
        Schema::create('document_template_revisions', function (Blueprint $t) {
            $t->id(); $t->foreignId('communication_template_id')->constrained(); $t->unsignedInteger('version');
            $t->json('snapshot'); $t->timestamp('created_at'); $t->unique(['communication_template_id','version']);
        });
        foreach (DB::table('communication_templates')->where('channel','pdf')->get() as $template) {
            DB::table('communication_templates')->where('id',$template->id)->update(['published_version'=>$template->version]);
            DB::table('document_template_revisions')->insert(['communication_template_id'=>$template->id,'version'=>$template->version,
                'snapshot'=>json_encode(['name'=>$template->name,'body'=>$template->body,'options'=>json_decode($template->options ?? '{}',true)],JSON_THROW_ON_ERROR),'created_at'=>now()]);
        }
        $legacy=DB::table('communication_templates')->where('channel','pdf')->where('key','quote')->first();
        $inherited=json_decode($legacy->options ?? '{}',true); unset($inherited['accent']);
        foreach (['global'=>'Style commun des documents','receipt'=>'Reçu de paiement PDF'] as $key=>$name) {
            DB::table('communication_templates')->insertOrIgnore(['channel'=>'pdf','key'=>$key,'name'=>$name,'subject'=>'','body'=>'','options'=>$key==='global'?json_encode($inherited,JSON_THROW_ON_ERROR):'{}','version'=>1,'published_version'=>1,'created_at'=>now(),'updated_at'=>now()]);
        }
        Schema::create('document_issues', function (Blueprint $t) {
            $t->id(); $t->foreignId('quote_id')->nullable()->constrained(); $t->foreignId('quote_payment_id')->nullable()->unique()->constrained();
            $t->string('fingerprint',64); $t->json('snapshot'); $t->foreignId('document_archive_id')->nullable()->constrained();
            $t->timestamps(); $t->unique(['quote_id','fingerprint']);
        });
    }
    public function down(): void
    {
        // Deliberately retained: published versions and issued documents must survive rollback.
    }
};
