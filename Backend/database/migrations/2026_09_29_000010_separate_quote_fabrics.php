<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('products',fn (Blueprint $t)=>$t->softDeletes());
        Schema::table('quote_options',fn (Blueprint $t)=>$t->string('fabric_group',20)->nullable());
        $this->retireProducts();
        DB::table('quote_options')->where('kind','material')->where('category','store')->update(['fabric_group'=>'store']);
        DB::table('quote_options')->where('kind','material')->where('category','rideau')->update(['fabric_group'=>'rideau']);
        DB::table('quote_options')->whereIn('code',['voilage-transparent','voilage-luxe'])->update(['fabric_group'=>'voilage']);
    }
    public function retireProducts(): void {
        $references=array_column(json_decode(file_get_contents(database_path('data/products.json')),true,512,JSON_THROW_ON_ERROR),'reference');
        DB::table('products')->whereIn('reference',$references)->whereNull('deleted_at')->update(['deleted_at'=>now()]);
    }
    public function down(): void {
        Schema::table('products',fn (Blueprint $t)=>$t->dropSoftDeletes());
        Schema::table('quote_options',fn (Blueprint $t)=>$t->dropColumn('fabric_group'));
    }
};
