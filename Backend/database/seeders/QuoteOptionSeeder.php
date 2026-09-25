<?php
namespace Database\Seeders;
use App\Models\{Product,QuoteOption};
use Illuminate\Database\Seeder;
class QuoteOptionSeeder extends Seeder
{
    public function run(): void
    {
        $venetian=Product::where('reference','ST-003')->pluck('id')->map(fn($id)=>(int)$id)->all();
        $cloth=Product::where('details->type','store')->where('reference','!=','ST-003')->pluck('id')->map(fn($id)=>(int)$id)->all();
        foreach ([
            ['lin','material','rideau','Lin','any',null], ['coton','material','rideau','Coton','any',null], ['polyester','material','rideau','Polyester','any',null], ['velours','material','rideau','Velours','any',null], ['voilage','material','rideau','Voilage','any',null],
            ['wave','finish','rideau','Wave','any',null], ['oeillets','finish','rideau','Œillets','no',null], ['plis','finish','rideau','Plis flamands','any',null], ['fronceur','finish','rideau','Ruban fronceur','any',null],
            ['toile','material','store','Toile textile','any',$cloth], ['screen','material','store','Toile screen','any',$cloth], ['aluminium','material','store','Aluminium','any',$venetian], ['bois','material','store','Bois','any',$venetian],
            ['standard','finish','store','Standard','any',null], ['coffre','finish','store','Coffre','any',Product::where('reference','ST-001')->pluck('id')->map(fn($id)=>(int)$id)->all()],
        ] as [$code,$kind,$category,$label,$motorization,$ids]) QuoteOption::firstOrCreate(['code'=>$code],['kind'=>$kind,'category'=>$category,'label'=>$label,'motorization'=>$motorization,'product_ids'=>$ids,'active'=>true]);
    }
}
