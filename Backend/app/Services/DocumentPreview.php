<?php
namespace App\Services;
use App\Services\Quotes\QuoteCalculator;

/** Synthetic fixtures only; never reads customer records. */
final class DocumentPreview
{
    public static function data(string $kind,string $scenario,array $presentation): array
    {
        $count=match($scenario) {'eight'=>8,'twenty'=>20,'thirty'=>30,'four'=>4,default=>1};
        $opening=['label'=>'Baie vitrée','width'=>'4','height'=>'2','quantity'=>1,'lines'=>[
            ['label'=>'Double rail','unit'=>'m','quantity'=>'4','unitPrice'=>10000],
            ['label'=>'Tissu occultant 80 %','unit'=>'m','quantity'=>'8','unitPrice'=>9000],
            ['label'=>'Voilage blanc','unit'=>'m','quantity'=>'8','unitPrice'=>4000],
            ['label'=>'Confection','unit'=>'m','quantity'=>'16','unitPrice'=>1000]]];
        if ($scenario==='store') $opening=['label'=>'Store cuisine','width'=>'2.45','height'=>'1.80','quantity'=>1,'lines'=>[['label'=>'Store lourd','unit'=>'m2','quantityMode'=>'area','unitPrice'=>25000]]];
        $openings=[]; $rooms=[];
        for ($i=0;$i<$count;$i++) { $openings[]=[...$opening,'label'=>$count===1?$opening['label']:'Fenêtre '.($i+1)]; $rooms[]=$i<2?'Salon principal':'Chambre '.(int)ceil(($i-1)/2); }
        if ($scenario==='four') {
            // The saved reference totals must be displayed verbatim by the renderer.
            $openings=[];
            foreach ([60000,60000,60000,61875] as $i=>$amount) $openings[]=[...$opening,'label'=>'Fenêtre '.($i+1),'lines'=>[['label'=>'Fourniture et confection','unit'=>'flat','quantity'=>1,'unitPrice'=>$amount]]];
        }
        if ($scenario==='individual') $openings[0]['quantity']=4;
        $financial=(new QuoteCalculator)->calculate(['openings'=>$openings,'fees'=>[['label'=>'Pose et livraison','scope'=>'global','unitPrice'=>30000]],
            'displayMode'=>$scenario==='individual'?'individual':'grouped','discount'=>match($scenario){'discount'=>['type'=>'percentage','value'=>10],'negotiated'=>['type'=>'target_total','value'=>150000],default=>['type'=>'none','value'=>0]},'depositPercent'=>70]);
        return ['kind'=>$kind,'title'=>$kind==='invoice'?'Facture':($kind==='receipt'?'Reçu':'Devis'),'number'=>'DEMO-2026-001','draft'=>true,
            'date'=>'30/09/2026','due'=>$kind==='receipt'?'':'30/10/2026','issuer'=>CommunicationTemplates::issuer($presentation),'presentation'=>$presentation,
            'customer'=>['firstName'=>'Client','lastName'=>'Démonstration','address'=>'Abidjan','phone'=>'07 00 00 00 00'],
            'object'=>'Fourniture, confection et installation sur mesure','rooms'=>$rooms,'quote_reference'=>$kind==='quote'?null:'DEV-DEMO-001','lines'=>[],'tax_basis_points'=>0,'financial'=>$financial,
            'payment_plan'=>['mode'=>'advance','deposit'=>$financial['deposit'],'received'=>$kind==='quote'?0:50000],
            'receipt'=>['amount'=>50000,'method'=>'Mobile Money','reference'=>'DEMO-VERSEMENT']];
    }
}
