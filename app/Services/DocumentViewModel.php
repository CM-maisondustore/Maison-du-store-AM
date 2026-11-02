<?php
namespace App\Services;
use App\Services\Quotes\{QuoteFinancials,CatalogueCalculator};

/** Formats saved amounts only. No catalogue lookup or pricing computation. */
final class DocumentViewModel
{
    public static function money(mixed $value): string { return number_format((int)$value,0,',',' ').' F CFA'; }
    private static function lineLabel(array $line): string
    {
        if (!empty($line['commercialLabel'])) return $line['commercialLabel'];
        return CatalogueCalculator::clientLabel($line['description'] ?? $line['label'] ?? 'Prestation');
    }
    public static function prepare(array $data): array
    {
        $data=QuoteFinancials::document($data);
        $data['style']=[...DocumentLayout::defaults(),...($data['presentation'] ?? [])];
        $data['kind']=$data['kind'] ?? ($data['title']==='Facture' ? 'invoice' : 'quote');
        $rows=[]; $openingCount=0;
        foreach ($data['totals']['openings'] ?? [] as $index=>$opening) {
            $openingCount+=$opening['quantity'];
            $display=QuoteFinancials::display([...$data['totals'],'openings'=>[$opening]]);
            foreach ($display as $row) {
                $row['room']=$data['rooms'][$index] ?? $data['windows'][$index]['room'] ?? '';
                $row['dimensions']='L '.QuoteFinancials::quantity($opening['width']).' m × H '.QuoteFinancials::quantity($opening['height']).' m';
                foreach ($row['lines'] as &$line) $line['label']=self::lineLabel($line);
                unset($line); $rows[]=$row;
            }
        }
        // Stable grouping; unnamed rooms remain in their original relative order.
        $groups=[];
        foreach ($rows as $row) $groups[$row['room']][]=$row;
        $data['opening_groups']=$groups; $data['opening_count']=$openingCount ?: count($data['windows'] ?? []);
        $data['fee_rows']=array_map(fn($fee)=>['label'=>self::lineLabel($fee),'displayQuantity'=>$fee['quantity'],'unit'=>'','unitPrice'=>$fee['unitPrice'],'displayAmount'=>$fee['amount']],$data['totals']['fees'] ?? []);
        $data['document_title']=match($data['kind']) {'invoice'=>'FACTURE','receipt'=>'REÇU DE PAIEMENT',default=>'DEVIS PROFESSIONNEL'};
        $data['conditions']=$data['style']['issuer_payment_terms'] ?? $data['issuer']['payment_terms'] ?? '';
        if (isset($data['totals']['depositPercent'])) {
            $deposit=QuoteFinancials::quantity($data['totals']['depositPercent']); $balance=QuoteFinancials::quantity($data['totals']['balancePercent']);
            $extra=preg_replace('/(acompte\s*(?:de\s*)?)\d+(?:[.,]\d+)?\s*%/iu','${1}'.$deposit.' %',$data['conditions']);
            $extra=preg_replace('/(solde\s*(?:de\s*)?)\d+(?:[.,]\d+)?\s*%/iu','${1}'.$balance.' %',$extra);
            $data['conditions']='Un acompte de '.$deposit.' % est obligatoire avant le démarrage des travaux. Le solde de '.$balance.' % est payable à la fin de la prestation.'.($extra ? "\n".$extra : '');
        } elseif (!empty($data['payment_plan']['mode'])) {
            $data['conditions']=($data['payment_plan']['mode']==='full'?'Paiement intégral.':'Avance de '.self::money($data['payment_plan']['deposit']).' puis solde.').($data['conditions'] ? "\n".$data['conditions'] : '');
        }
        return $data;
    }
}
