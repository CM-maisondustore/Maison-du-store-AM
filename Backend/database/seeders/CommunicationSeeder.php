<?php
namespace Database\Seeders;
use App\Models\{CommunicationTemplate,CommunicationRule};
use App\Services\CommunicationTemplates;
use Illuminate\Database\Seeder;
class CommunicationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['email','whatsapp'] as $channel) foreach (CommunicationTemplates::LABELS as $key=>$label) {
            [$subject,$body]=CommunicationTemplates::defaults($key,$channel);
            CommunicationTemplate::firstOrCreate(['channel'=>$channel,'key'=>$key],['name'=>$label,'subject'=>$subject,'body'=>$body,'options'=>[]]);
        }
        foreach (['quote'=>'Devis PDF','invoice'=>'Facture PDF'] as $key=>$label) CommunicationTemplate::firstOrCreate(['channel'=>'pdf','key'=>$key],['name'=>$label,'body'=>'','options'=>['accent'=>'#243831','footer'=>'','signature'=>'']]);
        foreach (['quote_received','quote','invoice','appointment','reminder'] as $key) CommunicationRule::firstOrCreate(['key'=>$key],['mode'=>'off','delay_hours'=>24]);
    }
}
