<?php
namespace Database\Seeders;
use App\Models\{Product,QuoteOption};
use Illuminate\Database\Seeder;
class QuoteOptionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['wave','finish','rideau','Wave','any',null], ['oeillets','finish','rideau','Œillets','no',null], ['plis','finish','rideau','Plis flamands','any',null], ['fronceur','finish','rideau','Ruban fronceur','any',null],
            ['standard','finish','store','Standard','any',null], ['coffre','finish','store','Coffre','any',Product::where('reference','ST-001')->pluck('id')->map(fn($id)=>(int)$id)->all()],
        ] as [$code,$kind,$category,$label,$motorization,$ids]) QuoteOption::firstOrCreate(['code'=>$code],['kind'=>$kind,'category'=>$category,'label'=>$label,'motorization'=>$motorization,'product_ids'=>$ids,'active'=>true]);
    }
}
