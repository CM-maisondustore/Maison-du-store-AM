<?php
namespace App\Services\Quotes;

use App\Models\QuoteOption;
use App\Services\{QuoteCosting,StaffQuoteData};
use Illuminate\Validation\ValidationException;

final class CatalogueCalculator
{
    public function calculate(array $input, array $pricing): array
    {
        $pending=[]; $openings=[]; $items=[];
        $globalPose=$pricing['fees']['installation']['enabled'] || $pricing['fees']['installation_delivery']['enabled'];
        StaffQuoteData::selection($input['defaults'],'defaults');
        foreach ($input['windows'] as $i=>$w) {
            StaffQuoteData::selection($w,'windows.'.$i);
            $finish=QuoteOption::findOrFail($w['finish_id']); $material=QuoteOption::findOrFail($w['material_id']);
            $cost=$w['costing']; $width=QuoteValidator::decimal($w['width'],'largeur')->dividedByExact(100);
            $height=QuoteValidator::decimal($w['height'],'hauteur')->dividedByExact(100); $lines=[];
            $hasCurtain=!empty($w['curtain_id']) || ($w['category']==='rideau' && $material->fabric_group==='rideau');
            $hasSheer=!empty($w['sheer_id']) || ($w['category']==='rideau' && $material->fabric_group==='voilage');
            $fabricCount=max(1,(int)$hasCurtain+(int)$hasSheer);
            $manualHeight=$w['category']==='rideau' && $height->isGreaterThan('2.8');
            $add=function(QuoteOption $option,array $part,string $key,string $mode='manual',mixed $default=null) use (&$lines,&$pending,&$items,$w,$i) {
                $price=$part['unit_price'] ?? $option->unit_price;
                if ($key==='installation' && !isset($part['unit_price']) && ($w['category']==='rideau' || $w['costing']['zone']==='other')) $price=null;
                if ($price===null) $pending[]=$w['label'].' : '.$option->label.' — prix à définir';
                $qty=$part['quantity'] ?? null;
                if ($qty===null && $mode==='manual' && $default===null) $this->invalid($i,$key.'.quantity','Renseignez la quantité manuelle ou choisissez le calcul par coefficient.');
                if (in_array($option->unit,['piece','lot100','opening','flat']) && $qty!==null && (string)(int)$qty!==(string)$qty) {
                    try { QuoteValidator::count($qty,'quantité entière'); } catch (\InvalidArgumentException) { $this->invalid($i,$key.'.quantity','Cette unité nécessite une quantité entière.'); }
                }
                $label=self::clientLabel($option->label);
                $lines[]=['key'=>$key,'optionId'=>$option->id,'description'=>$label,'unit'=>$part['unit'] ?? $option->unit,
                    'quantity'=>$qty,'quantityMode'=>$mode,'defaultQuantity'=>$default ?? 1,'coefficient'=>$part['coefficient'] ?? '2.5',
                    'unitPrice'=>$price ?? 0,'priceSource'=>isset($part['unit_price']) ? 'manual' : 'default','pending'=>$price===null];
                $items[]=['window'=>$w['label'],'option_id'=>$option->id,'label'=>$label,'kind'=>$key,'unit'=>$option->unit,'quantity'=>$qty,'unit_price'=>$price,'openings'=>$w['quantity']];
            };
            $materialPart=['quantity'=>$cost['material_quantity'],'unit_price'=>$cost['material_unit_price'],'coefficient'=>$cost['material_coefficient']];
            $materialMode=$material->unit==='m2' ? 'area' : $cost['material_mode'];
            if ($material->unit==='m2' && $cost['material_mode']!=='manual_override') $materialPart['quantity']=null;
            if ($materialMode==='manual_override') $materialMode='manual';
            if ($manualHeight && $material->unit!=='m2' && $materialPart['quantity']===null) $this->invalid($i,'material_quantity','Hauteur supérieure à 2,80 m : saisissez manuellement le métrage du tissu ou du voilage.');
            if ($materialPart['quantity']===null && $materialMode==='manual') $this->invalid($i,'material_quantity','Renseignez le métrage du tissu.');
            $add($material,$materialPart,'material',$materialMode);
            if ($finish->unit_price!==0 || isset($cost['finish_unit_price'])) $add($finish,['quantity'=>$cost['finish_quantity'],'unit_price'=>$cost['finish_unit_price']],'finish',$finish->unit==='m'?'width':'manual',1);
            $primaryQuantity=$materialPart['quantity'] ?? ($materialMode==='area' ? (string)$width->multipliedBy($height) : (string)$width->multipliedBy($cost['material_coefficient']));
            foreach (QuoteCosting::PARTS as $kind) {
                $part=$cost[$kind];
                if (!$part['option_id']) {
                    if ($kind==='installation' && !$globalPose) $pending[]=$w['label'].' : installation à définir';
                    if ($kind==='motor' && $w['motorized']) $this->invalid($i,$kind.'.option_id','Sélectionnez le kit moteur.');
                    continue;
                }
                if ($kind==='installation' && $globalPose) $this->invalid($i,'installation.option_id','Décochez la pose par ouverture : elle est déjà prévue dans les frais du devis.');
                $o=QuoteOption::find($part['option_id']);
                $expected=match($kind){'secondary_material'=>'material','extra_support'=>'support','extra_tape'=>'tape',default=>$kind};
                $compatible=$o && ($o->compatible($w['category'],0,$w['motorized']) || ($kind==='extra_support' && $o->compatible($w['category'],0,false)));
                if (!$o || $o->kind!==$expected || !$compatible || ($o->finish_codes && !in_array($finish->code,$o->finish_codes,true) && $kind!=='extra_support')) $this->invalid($i,$kind.'.option_id','Fourniture incompatible avec cette ouverture.');
                if ($manualHeight && $o->unit==='m' && $part['quantity']===null) $this->invalid($i,$kind.'.quantity','Hauteur supérieure à 2,80 m : saisissez manuellement cette quantité.');
                $mode=$part['mode']; $default=null;
                if ($kind==='sewing' && in_array($mode,['fixed','manual'],true)) {
                    $part['quantity']=1; $part['unit']='flat';
                    if ($part['unit_price']===null) $this->invalid($i,'sewing.unit_price','Renseignez le forfait de confection.');
                    $mode='manual';
                } elseif ($part['quantity']===null) {
                    if ($mode==='coefficient') {
                        if (in_array($kind,['support','extra_support','tape','extra_tape'],true) && $part['basis']==='all_fabrics') {
                            $part['coefficient']=(string)QuoteValidator::decimal($part['coefficient'],'coefficient')->multipliedBy($fabricCount);
                        }
                    }
                    elseif (in_array($kind,['support','extra_support'])) $mode='width';
                    elseif (in_array($kind,['tape','extra_tape','sewing'])) {
                        $default=$primaryQuantity;
                        if ($part['basis']==='all_fabrics') {
                            $secondary=$cost['secondary_material'];
                            $secondaryQuantity=$secondary['option_id'] ? ($secondary['quantity'] ?? ($secondary['mode']==='coefficient' ? (string)$width->multipliedBy($secondary['coefficient']) : 0)) : 0;
                            $default=(string)QuoteValidator::decimal($primaryQuantity,'métrage')->plus($secondaryQuantity);
                        }
                        $mode='manual';
                    } elseif (in_array($o->unit,['piece','opening','flat','lot100'])) $default=1;
                }
                $add($o,$part,$kind,$mode,$default);
            }
            $openings[]=['label'=>$w['label'],'width'=>(string)$width,'height'=>(string)$height,'quantity'=>$w['quantity'],'lines'=>$lines];
        }
        $fees=[];
        foreach ($pricing['fees'] as $key=>$fee) if ($fee['enabled']) $fees[]=['key'=>$key,'description'=>PricingConfig::FEES[$key], 'unitPrice'=>$fee['unit_price'],'priceSource'=>$fee['price_override']!==null?'manual':'default','scope'=>$fee['scope'],'quantity'=>$fee['quantity']];
        try {
            $financial=(new QuoteCalculator)->calculate(['openings'=>$openings,'fees'=>$fees,'displayMode'=>$pricing['display_mode'],
                'taxBasisPoints'=>$pricing['tax_basis_points'],'discount'=>['type'=>$pricing['discount_type'],'value'=>$pricing['discount_value']],'depositPercent'=>$pricing['deposit_percent']]);
        } catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['pricing'=>$e->getMessage()]); }
        $lines=[];
        foreach ($financial['openings'] as $opening) foreach ($opening['lines'] as $line) if (!$line['pending']) $lines[]=['description'=>mb_substr($opening['label'].' — '.$line['description'].' ('.$line['quantity'].' '.$line['unit'].' / ouverture)',0,250),'quantity'=>$opening['quantity'],'unit_price'=>$line['amount']];
        foreach ($financial['fees'] as $fee) $lines[]=['description'=>$fee['description'],'quantity'=>$fee['quantity'],'unit_price'=>$fee['unitPrice']];
        return [...$input,'pricing'=>$pricing,'financial'=>$financial,'lines'=>$lines,'items'=>$items,'pending'=>$pending,'calculated_at'=>now()->toIso8601String()];
    }

    public static function clientLabel(string $label): string { return trim(preg_replace('/\s*\d+(?:[.,]\d+)?\s*%/u','',$label)); }
    private function invalid(int $index,string $key,string $message): never { throw ValidationException::withMessages(['windows.'.$index.'.costing.'.$key=>$message]); }
}
