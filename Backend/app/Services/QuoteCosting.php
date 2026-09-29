<?php
namespace App\Services;
use App\Models\QuoteOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class QuoteCosting
{
    public const PARTS = ['secondary_material','support','tape','hooks','sewing','motor','installation'];

    public static function input(Request $r): array
    {
        QuoteSelections::prepare($r);
        $windows=$r->input('windows',[]);
        if (is_array($windows)) foreach ($windows as &$w) {
            if (!is_array($w)) continue;
            foreach (['width','height'] as $key) if (isset($w[$key]) && is_string($w[$key])) $w[$key]=str_replace(',','.',$w[$key]);
            if (isset($w['costing']) && is_array($w['costing'])) {
                if (is_string($w['costing']['material_quantity'] ?? null)) $w['costing']['material_quantity']=str_replace(',','.',$w['costing']['material_quantity']);
                foreach (self::PARTS as $kind) if (is_string($w['costing'][$kind]['quantity'] ?? null)) $w['costing'][$kind]['quantity']=str_replace(',','.',$w['costing'][$kind]['quantity']);
            }
        }
        unset($w); $r->merge(['windows'=>$windows]);
        $rules=['defaults'=>'required|array','windows'=>'required|array|min:1|max:30','windows.*.label'=>'required|string|max:100',
            'windows.*.width'=>'required|numeric|min:1|max:2000','windows.*.height'=>'required|numeric|min:1|max:2000',
            'windows.*.quantity'=>'required|integer|min:1|max:20','windows.*.inherit_defaults'=>'sometimes|boolean',
            'windows.*.costing'=>'required|array','windows.*.costing.material_quantity'=>'nullable|numeric|min:0.001|max:1000',
            'windows.*.costing.zone'=>'required|in:abidjan,other'];
        foreach (['defaults','windows.*'] as $prefix) $rules += [$prefix.'.category'=>'required|in:rideau,store',$prefix.'.product_id'=>'required|integer',$prefix.'.material_id'=>'required|integer',$prefix.'.finish_id'=>'required|integer',$prefix.'.motorized'=>'required|boolean'];
        foreach (['defaults','windows.*'] as $prefix) foreach (['curtain_id','sheer_id','store_id'] as $key) $rules[$prefix.'.'.$key]='nullable|integer';
        foreach (self::PARTS as $kind) {
            $rules['windows.*.costing.'.$kind.'.option_id']='nullable|integer';
            $rules['windows.*.costing.'.$kind.'.quantity']='nullable|numeric|min:0.001|max:1000';
        }
        $rules['windows.*.costing.installation.unit_price']='nullable|integer|min:0|max:100000000';
        $data=$r->validate($rules);
        $defaults=self::choice($data['defaults']);
        $result=[];
        foreach ($data['windows'] as $w) {
            $cost=['material_quantity'=>isset($w['costing']['material_quantity']) ? (float)$w['costing']['material_quantity'] : null,'zone'=>$w['costing']['zone']];
            foreach (self::PARTS as $kind) $cost[$kind]=['option_id'=>!empty($w['costing'][$kind]['option_id']) ? (int)$w['costing'][$kind]['option_id'] : null,
                'quantity'=>isset($w['costing'][$kind]['quantity']) ? (float)$w['costing'][$kind]['quantity'] : null];
            $cost['installation']['unit_price']=isset($w['costing']['installation']['unit_price']) ? (int)$w['costing']['installation']['unit_price'] : null;
            $result[]=[...(!empty($w['inherit_defaults']) ? $defaults : self::choice($w)), 'label'=>$w['label'],'width'=>(float)$w['width'],'height'=>(float)$w['height'],'quantity'=>(int)$w['quantity'],'costing'=>$cost];
        }
        return ['defaults'=>$defaults,'windows'=>$result];
    }
    private static function choice(array $v): array
    {
        $choice=['category'=>$v['category'],'product_id'=>0,'material_id'=>(int)$v['material_id'],'finish_id'=>(int)$v['finish_id'],'motorized'=>(bool)$v['motorized']];
        foreach (['curtain_id','sheer_id','store_id'] as $key) if (array_key_exists($key,$v)) $choice[$key]=!empty($v[$key]) ? (int)$v[$key] : null;
        return $choice;
    }
    public static function technical(Request $r): array {
        $data=self::input($r);
        foreach ($data['windows'] as $i=>&$w) {
            StaffQuoteData::selection($w,'windows.'.$i);
            $finish=QuoteOption::findOrFail($w['finish_id']); $w['components']=[];
            foreach (self::PARTS as $kind) {
                $part=$w['costing'][$kind]; if (!$part['option_id']) continue;
                $o=QuoteOption::find($part['option_id']);
                if (!$o || $o->kind!==($kind==='secondary_material' ? 'material' : $kind) || !$o->compatible($w['category'],0,$w['motorized']) || ($o->finish_codes && !in_array($finish->code,$o->finish_codes,true))) self::invalid($i,$kind.'.option_id','Fourniture incompatible avec cette sélection.');
                if (in_array($o->unit,['piece','lot100','opening','flat']) && $part['quantity']!==null && floor($part['quantity'])!=$part['quantity']) self::invalid($i,$kind.'.quantity','Renseignez une quantité entière.');
                $w['components'][]=['label'=>$o->label,'quantity'=>$part['quantity'],'unit'=>$o->unit];
            }
            unset($w['costing']['installation']['unit_price']);
        }
        unset($w); return $data;
    }
    public static function calculate(Request $r): array
    {
        $input=self::input($r); $lines=[]; $pending=[]; $items=[];
        StaffQuoteData::selection($input['defaults'],'defaults');
        foreach ($input['windows'] as $i=>$w) {
            $selection=StaffQuoteData::selection($w,'windows.'.$i);
            $finish=QuoteOption::findOrFail($w['finish_id']);
            $material=QuoteOption::findOrFail($w['material_id']);
            $qty=$material->unit==='m2' ? $w['width']*$w['height']/10000 : $w['costing']['material_quantity'];
            if (!$qty) self::invalid($i,'material_quantity','Renseignez la quantité de tissu / matériau pour une ouverture.');
            self::add($material,$qty,$material->unit_price,$w,$lines,$pending,$items,$i);
            // A zero-price finish describes a style; its hardware is charged separately.
            if ($finish->unit_price!==0) self::add($finish,$finish->unit==='m' ? ($w['costing']['material_quantity'] ?? $w['width']/100) : 1,$finish->unit_price,$w,$lines,$pending,$items,$i);
            foreach (self::PARTS as $kind) {
                $part=$w['costing'][$kind];
                if (!$part['option_id']) {
                    if ($kind==='installation') $pending[]=$w['label'].' : installation à définir';
                    if ($kind==='motor' && $w['motorized'] && $w['category']==='store') self::invalid($i,$kind.'.option_id','Sélectionnez le kit moteur pour ce store motorisé.');
                    continue;
                }
                $o=QuoteOption::find($part['option_id']);
                if (!$o || $o->kind!==($kind==='secondary_material' ? 'material' : $kind) || ($kind==='secondary_material' && $w['category']!=='rideau') || !$o->compatible($w['category'],$w['product_id'],$w['motorized']) || ($o->finish_codes && !in_array($finish->code,$o->finish_codes,true))) self::invalid($i,$kind.'.option_id','Ce composant est incompatible avec les choix de cette fenêtre.');
                if (!$part['quantity']) self::invalid($i,$kind.'.quantity','Renseignez la quantité pour une ouverture.');
                $price=$o->unit_price;
                if ($kind==='installation') {
                    if ($part['unit_price']!==null) $price=$part['unit_price'];
                    elseif ($w['category']==='rideau' || $w['costing']['zone']==='other') $price=null;
                }
                self::add($o,$part['quantity'],$price,$w,$lines,$pending,$items,$i);
            }
        }
        return [...$input,'lines'=>$lines,'pending'=>$pending,'items'=>$items,'calculated_at'=>now()->toIso8601String()];
    }
    private static function add(QuoteOption $o,float $qty,?int $price,array $w,array &$lines,array &$pending,array &$items,int $index): void
    {
        if (in_array($o->unit,['piece','lot100','opening','flat']) && floor($qty)!=$qty) self::invalid($index,$o->kind.'.quantity','Cette unité nécessite une quantité entière (lots complets pour les accroches).');
        $amount=$price===null ? null : (int)round($qty*$price);
        if ($amount!==null && $amount>100000000) self::invalid($index,$o->kind.'.quantity','Montant trop élevé pour une ligne.');
        $items[]=['window'=>$w['label'],'option_id'=>$o->id,'label'=>$o->label,'kind'=>$o->kind,'unit'=>$o->unit,'quantity'=>$qty,'unit_price'=>$price,'amount'=>$amount,'openings'=>$w['quantity']];
        if ($amount===null) { $pending[]=$w['label'].' : '.$o->label.' — prix à définir'; return; }
        $unit=['m'=>'m','m2'=>'m²','piece'=>'pièce(s)','lot100'=>'lot(s) de 100','opening'=>'ouverture(s)','flat'=>'forfait(s)'][$o->unit];
        $detail=rtrim(rtrim(number_format($qty,4,',',''),'0'),',').' '.$unit.' × '.$price.' FCFA';
        $lines[]=['description'=>mb_substr($w['label'].' — '.$o->label.' ('.$detail.' / ouverture)',0,250),'quantity'=>$w['quantity'],'unit_price'=>$amount];
    }
    private static function invalid(int $i,string $key,string $message): never { throw ValidationException::withMessages(['windows.'.$i.'.costing.'.$key=>$message]); }
    public static function token(array $calculation): string { return Crypt::encryptString(json_encode($calculation,JSON_THROW_ON_ERROR)); }
    public static function saved(Request $r): ?array
    {
        if (!$r->boolean('use_catalogue')) return null;
        $r->validate(['calculation_token'=>'required|string|max:250000']);
        try { $data=json_decode(Crypt::decryptString($r->input('calculation_token')),true,512,JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { throw ValidationException::withMessages(['calculation_token'=>'Recalculez le devis depuis le catalogue.']); }
        $input=self::input($r);
        if ($input['windows']!=$data['windows'] || $input['defaults']!=$data['defaults']) throw ValidationException::withMessages(['calculation_token'=>'Les choix, mesures ou quantités ont changé. Recalculez le chiffrage avant enregistrement.']);
        return $data;
    }
}
