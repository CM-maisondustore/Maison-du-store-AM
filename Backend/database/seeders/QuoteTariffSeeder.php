<?php
namespace Database\Seeders;
use App\Models\QuoteOption;
use Illuminate\Database\Seeder;
class QuoteTariffSeeder extends Seeder
{
    public function run(): void
    {
        // Additive and idempotent: never replace an administrator's prices or choices.
        foreach ([
            ['occultant-100','material','rideau','Tissu occultant 100 %',12000,'m'],
            ['occultant-80','material','rideau','Tissu occultant 80 %',9000,'m'],
            ['occultant-60','material','rideau','Tissu occultant 60 %',7000,'m'],
            ['voilage-transparent','material','rideau','Voilage transparent',4000,'m'],
            ['voilage-luxe','material','rideau','Voilage luxe',14000,'m'],
            ['store-lourd','material','store','Store lourd',25000,'m2'],
            ['store-leger','material','store','Store léger',18000,'m2'],
            ['store-standard','finish','store','Finition standard',0,'flat'],
            ['wave-manuel','finish','rideau','Wave manuel',0,'flat','no'],
            ['wave-electric','finish','rideau','Wave électrique',0,'flat','yes'],
            ['barre-fer','finish','rideau','Barre de fer',0,'flat','no'],
            ['reflete-manuel','finish','rideau','Reflète manuelle',0,'flat','no'],
            ['rail-double','support','rideau','Rail double',10000,'m','no',['reflete-manuel']],
            ['rail-simple','support','rideau','Rail simple',5000,'m','no',['reflete-manuel']],
            ['rail-wave-electric','support','rideau','Rail Wave électrique',22000,'m','yes',['wave-electric']],
            ['rail-wave-manuel','support','rideau','Rail Wave manuel',7000,'m','no',['wave-manuel']],
            ['barre-double','support','rideau','Barre de fer double',7000,'m','no',['barre-fer']],
            ['barre-simple','support','rideau','Barre de fer simple',4000,'m','no',['barre-fer']],
            ['reflete-4','tape','rideau','Reflète 4 files',900,'m','no',['reflete-manuel','barre-fer']],
            ['reflete-3','tape','rideau','Reflète 3 files',500,'m','no',['reflete-manuel','barre-fer']],
            ['reflete-wave','tape','rideau','Reflète Wave',1500,'m','any',['wave-manuel','wave-electric']],
            ['accroches-100','hooks','rideau','Accroches — lot de 100',2000,'lot100'],
            ['confection','sewing','rideau','Confection',1000,'m'],
            ['pose-rideaux','installation','rideau','Pose des rideaux — à définir',null,'opening'],
            ['kit-moteur','motor','store','Kit moteur',100000,'piece','yes'],
            ['pose-store-abidjan','installation','store','Installation de store à Abidjan',5000,'opening'],
        ] as $row) {
            [$code,$kind,$category,$label,$price,$unit]=$row;
            QuoteOption::firstOrCreate(['code'=>$code],['kind'=>$kind,'category'=>$category,'label'=>$label,'unit_price'=>$price,'unit'=>$unit,'motorization'=>$row[6] ?? 'any','finish_codes'=>$row[7] ?? [],'active'=>true,'public_choice'=>in_array($kind,['material','finish','support'])]);
        }
    }
}
