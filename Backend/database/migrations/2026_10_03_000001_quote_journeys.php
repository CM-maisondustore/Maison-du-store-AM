<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        Schema::table('quote_options',function(Blueprint $t){$t->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();$t->string('image_alt')->nullable();});
        $cards=[
            'finish-wave'=>['Wave','Un tombé régulier en vagues. La motorisation et les accessoires seront précisés avec votre conseiller.'],
            'finish-ruflette'=>['Ruflette','Une finition froncée pour ajuster le volume et le tombé de vos rideaux.'],
            'finish-barre-fer'=>['Barre de fer','Une suspension sur barre pour un rendu classique. Le modèle de barre sera défini sur place.'],
            'quote-message-store'=>['Votre projet de store','Un conseiller vous contactera pour confirmer les mesures, les coloris et les détails techniques, puis préparer votre devis personnalisé.'],
            'quote-message-rideau'=>['Votre projet de rideaux et voilages','Un conseiller vous contactera pour confirmer les tissus, les mesures et la finition sur place, puis préparer votre devis personnalisé.'],
        ];
        foreach($cards as $slot=>[$title,$description]) DB::table('site_contents')->insertOrIgnore(['slot'=>$slot,'kind'=>'quote','title'=>$title,'description'=>$description,'published'=>true,'position'=>0,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {}
};
