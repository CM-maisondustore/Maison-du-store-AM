<?php
namespace App\Services;
use App\Models\QuoteOption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class StaffQuoteData
{
    public static function catalogue(bool $prices=true): array
    {
        return ['options'=>QuoteOption::orderBy('label')->get(['id','code','kind','category','label','motorization','active','unit','finish_codes','fabric_group','product_ids','calc_mode','calc_coefficient','calc_basis',...($prices ? ['unit_price'] : [])])->toArray()];
    }
    public static function validate(Request $r): array
    {
        QuotePhone::prepare($r);
        QuoteSelections::prepare($r);
        $admin=$r->user()->isAdmin();
        $windows=$r->input('windows',[]);
        if (is_array($windows)) foreach ($windows as &$window) if (is_array($window)) foreach (['width','height'] as $key) if (isset($window[$key]) && is_string($window[$key])) $window[$key]=str_replace(',','.',$window[$key]);
        unset($window); $r->merge(['windows'=>$windows]);
        $rules=['submission_token'=>'required|uuid','edit_version'=>'required|integer|min:0',
            'contact'=>'required|array','contact.firstName'=>'nullable|string|max:100','contact.lastName'=>'required|string|max:100','contact.email'=>'nullable|email|max:255','contact.phone'=>['required','string','max:30',new \App\Rules\Phone],'contact.address'=>'required|string|max:1000','contact.city'=>'nullable|string|max:100',
            'quote_date'=>'required|date_format:Y-m-d','valid_until'=>'required|date_format:Y-m-d|after_or_equal:quote_date','delivery_date'=>'nullable|date_format:Y-m-d|after_or_equal:quote_date',
            'installation_date'=>'nullable|date_format:Y-m-d|after_or_equal:quote_date',
            'field_notes'=>'nullable|string|max:10000',
            'defaults'=>'required|array','windows'=>'required|array|min:1|max:30','windows.*.inherit_defaults'=>'sometimes|boolean','windows.*.label'=>'required|string|max:100','windows.*.width'=>'required|numeric|min:1|max:2000','windows.*.height'=>'required|numeric|min:1|max:2000','windows.*.quantity'=>'required|integer|min:1|max:20','windows.*.notes'=>'nullable|string|max:1000'];
        foreach (['defaults','windows.*'] as $prefix) $rules += [$prefix.'.category'=>'required|in:store,rideau',$prefix.'.material_id'=>'required|integer',$prefix.'.finish_id'=>'required|integer',$prefix.'.motorized'=>'required|boolean', $prefix.'.curtain_id'=>'nullable|integer',$prefix.'.sheer_id'=>'nullable|integer',$prefix.'.store_id'=>'nullable|integer'];
        if ($admin) $rules += ['payment_mode'=>'required|in:advance,full','deposit_amount'=>'required|integer|min:0|max:1000000000000','internal_notes'=>'nullable|string|max:10000'];
        else foreach (['lines','tax_rate','tax_basis_points','payment_mode','deposit_amount','internal_notes','calculation_token','receipt_amount','windows.*.costing.installation.unit_price'] as $field) $rules[$field]='prohibited';
        if (!$admin) {
            $rules['pricing']='prohibited';
            foreach (QuoteCosting::PARTS as $kind) $rules['windows.*.costing.'.$kind.'.unit_price']='prohibited';
            foreach (['material_unit_price','finish_unit_price'] as $key) $rules['windows.*.costing.'.$key]='prohibited';
        }
        if ($r->user()->isAdmin()) $rules['agent_id']=['nullable',Rule::exists('users','id')->where('role','agent')->where('active',true)];
        $data=$r->validate($rules); $pricing=$admin ? Pricing::validate($r) : [];
        if (!empty($data['delivery_date']) && !empty($data['installation_date']) && $data['installation_date']<$data['delivery_date']) throw ValidationException::withMessages(['installation_date'=>'La pose doit être prévue à partir de la livraison.']);
        $defaults=self::selection($data['defaults'],'defaults'); $dimensions=[];
        foreach ($data['windows'] as $i=>$window) $dimensions[]=[...self::selection(!empty($window['inherit_defaults']) ? $data['defaults'] : $window,'windows.'.$i),'inherit_defaults'=>(bool)($window['inherit_defaults'] ?? false),'label'=>$window['label'],'width'=>(float)$window['width'],'height'=>(float)$window['height'],'quantity'=>(int)$window['quantity'],'notes'=>$window['notes'] ?? ''];
        $calculation=$admin ? QuoteCosting::saved($r) : null;
        if (!$admin || collect($r->input('windows',[]))->every(fn($w)=>is_array($w['costing'] ?? null))) {
            $technical=QuoteCosting::technical($r);
            foreach ($dimensions as $i=>&$dimension) { $dimension['costing']=$technical['windows'][$i]['costing']; $dimension['components']=$technical['windows'][$i]['components']; }
            unset($dimension);
        }
        if ($calculation) {
            foreach ($dimensions as $i=>&$dimension) $dimension['costing']=$calculation['windows'][$i]['costing'];
            unset($dimension);
        }
        if (!empty($calculation['financial'])) {
            $pricing=['lines'=>$calculation['lines'],'tax_basis_points'=>$calculation['financial']['taxBasisPoints']];
            $data['deposit_amount']=$calculation['financial']['deposit'];
            $data['payment_mode']=$calculation['financial']['depositPercent']==='100' ? 'full' : 'advance';
        }
        $total=$admin ? ($calculation['financial']['total'] ?? Pricing::totals($pricing['lines'],$pricing['tax_basis_points'])['total']) : 0;
        if ($admin && $data['deposit_amount']>$total) throw ValidationException::withMessages(['deposit_amount'=>'L’avance prévue ne peut pas dépasser le total du devis.']);
        return [...collect($data)->only(['quote_date','valid_until','delivery_date','installation_date','internal_notes','payment_mode','agent_id'])->all(),...$pricing,
            ...($admin ? ['deposit_amount'=>!empty($calculation['financial']) ? $calculation['financial']['deposit'] : ($data['payment_mode']==='full' ? 0 : (int)$data['deposit_amount'])] : []),
            'contact'=>collect($data['contact'])->only(['firstName','lastName','email','phone','address','city'])->all(),
            'type'=>$defaults['category'],'configuration'=>['defaults'=>$defaults,'calculation'=>$calculation,'field_notes'=>$data['field_notes'] ?? '', 'pricing_review_required'=>!$admin], 'product'=>['model'=>$defaults['product_name'],'fabric'=>$defaults['material_name'],'finish'=>$defaults['finish_name'],'control'=>$defaults['motorized'] ? 'Motorisée' : 'Manuelle'], 'dimensions'=>$dimensions];
    }
    public static function selection(array $value,string $prefix): array
    {
        $snapshot=['category'=>$value['category'],'product_id'=>0,'product_name'=>$value['category']==='store' ? 'Store' : 'Rideaux / voilages','reference'=>'','motorized'=>(bool)$value['motorized']];
        foreach (['material','finish'] as $kind) {
            $option=QuoteOption::find($value[$kind.'_id']);
            $compatible=$option && $option->compatible($value['category'],0,(bool)$value['motorized']);
            if (!$option || $option->kind!==$kind || !$compatible) throw ValidationException::withMessages([$prefix.'.'.$kind.'_id'=>'Choisissez une option disponible pour ce type de projet.']);
            $snapshot[$kind.'_id']=$option->id; $snapshot[$kind.'_name']=$option->label;
        }
        foreach (['curtain_id'=>'rideau','sheer_id'=>'voilage','store_id'=>'store'] as $key=>$group) {
            $id=$value[$key] ?? null;
            if ($id) {
                $o=QuoteOption::find($id);
                if (!$o || $o->kind!=='material' || ($o->fabric_group ?: $o->category)!==$group || !$o->compatible($value['category'],0,(bool)$value['motorized'])) throw ValidationException::withMessages([$prefix.'.'.$key=>'Sélectionnez un tissu dans le groupe correspondant.']);
            }
            $snapshot[$key]=$id ? (int)$id : null;
        }
        $material=QuoteOption::find($value['material_id']);
        if (!array_key_exists('curtain_id',$value) && !array_key_exists('sheer_id',$value) && !array_key_exists('store_id',$value)) $snapshot[match($material->fabric_group ?: $material->category){'voilage'=>'sheer_id','store'=>'store_id',default=>'curtain_id'}]=$material->id;
        $snapshot['product_name']=$value['category']==='store' ? 'Store' : ($snapshot['curtain_id'] && $snapshot['sheer_id'] ? 'Rideaux et voilages' : ($snapshot['sheer_id'] ? 'Voilages' : 'Rideaux'));
        if ($snapshot['curtain_id'] && $snapshot['sheer_id']) $snapshot['material_name']=QuoteOption::find($snapshot['curtain_id'])->label.' + '.QuoteOption::find($snapshot['sheer_id'])->label;
        return $snapshot;
    }
}
