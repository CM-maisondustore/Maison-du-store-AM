<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        foreach (['store'=>'Devis Store','rideau'=>'Devis Rideaux et voilages'] as $type=>$title) {
            DB::table('site_contents')->insertOrIgnore(['slot'=>'quote-'.$type,'kind'=>'quote','title'=>$title,
                'description'=>'Choisissez les produits, matériaux et finitions de notre catalogue pour votre projet sur mesure.',
                'published'=>true,'position'=>0,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
    public function down(): void {}
};
