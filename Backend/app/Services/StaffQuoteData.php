<?php
namespace App\Services;
use App\Models\{Product,QuoteOption};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class StaffQuoteData
{
    public static function catalogue(): array
    {
        return ['products'=>Product::select('id','reference','details','published')->get()->map(fn($p)=>['id'=>$p->id,'name'=>$p->details['name'],'category'=>$p->details['type'],'reference'=>$p->reference,'published'=>$p->published])->all(),
            'options'=>QuoteOption::orderByRaw('CASE WHEN unit_price IS NULL THEN 1 ELSE 0 END')->orderBy('label')->get(['id','code','kind','category','label','product_ids','motorization','active','unit_price','unit','finish_codes'])->toArray()];
    }
    public static function validate(Request $r): array
    {
        $windows=$r->input('windows',[]);
        if (is_array($windows)) foreach ($windows as &$window) if (is_array($window)) foreach (['width','height'] as $key) if (isset($window[$key]) && is_string($window[$key])) $window[$key]=str_replace(',','.',$window[$key]);
        unset($window); $r->merge(['windows'=>$windows]);
        $rules=['submission_token'=>'required|uuid','edit_version'=>'required|integer|min:0',
            'contact'=>'required|array','contact.firstName'=>'nullable|string|max:100','contact.lastName'=>'required|string|max:100','contact.email'=>'nullable|email|max:255','contact.phone'=>'required|string|max:50','contact.address'=>'required|string|max:1000','contact.city'=>'nullable|string|max:100',
            'quote_date'=>'required|date_format:Y-m-d','valid_until'=>'required|date_format:Y-m-d|after_or_equal:quote_date','delivery_date'=>'nullable|date_format:Y-m-d|after_or_equal:quote_date',
            'installation_date'=>'nullable|date_format:Y-m-d|after_or_equal:quote_date',
            'payment_mode'=>'required|in:advance,full','deposit_amount'=>'required|integer|min:0|max:1000000000000','internal_notes'=>'nullable|string|max:10000',
            'defaults'=>'required|array','windows'=>'required|array|min:1|max:30','windows.*.inherit_defaults'=>'sometimes|boolean','windows.*.label'=>'required|string|max:100','windows.*.width'=>'required|numeric|min:1|max:2000','windows.*.height'=>'required|numeric|min:1|max:2000','windows.*.quantity'=>'required|integer|min:1|max:20','windows.*.notes'=>'nullable|string|max:1000'];
        foreach (['defaults','windows.*'] as $prefix) $rules += [$prefix.'.category'=>'required|in:store,rideau',$prefix.'.product_id'=>'required|integer',$prefix.'.material_id'=>'required|integer',$prefix.'.finish_id'=>'required|integer',$prefix.'.motorized'=>'required|boolean'];
        if ($r->user()->isAdmin()) $rules['agent_id']=['nullable',Rule::exists('users','id')->where('role','agent')->where('active',true)];
        $data=$r->validate($rules); $pricing=Pricing::validate($r);
        if (!empty($data['delivery_date']) && !empty($data['installation_date']) && $data['installation_date']<$data['delivery_date']) throw ValidationException::withMessages(['installation_date'=>'La pose doit être prévue à partir de la livraison.']);
        $defaults=self::selection($data['defaults'],'defaults'); $dimensions=[];
        foreach ($data['windows'] as $i=>$window) $dimensions[]=[...self::selection(!empty($window['inherit_defaults']) ? $data['defaults'] : $window,'windows.'.$i),'inherit_defaults'=>(bool)($window['inherit_defaults'] ?? false),'label'=>$window['label'],'width'=>(float)$window['width'],'height'=>(float)$window['height'],'quantity'=>(int)$window['quantity'],'notes'=>$window['notes'] ?? ''];
        $calculation=QuoteCosting::saved($r);
        if ($calculation) {
            foreach ($dimensions as $i=>&$dimension) $dimension['costing']=$calculation['windows'][$i]['costing'];
            unset($dimension);
        }
        $total=Pricing::totals($pricing['lines'],$pricing['tax_basis_points'])['total'];
        if ($data['deposit_amount']>$total) throw ValidationException::withMessages(['deposit_amount'=>'L’avance prévue ne peut pas dépasser le total du devis.']);
        return [...collect($data)->only(['quote_date','valid_until','delivery_date','installation_date','internal_notes','payment_mode','agent_id'])->all(),...$pricing,
            'deposit_amount'=>$data['payment_mode']==='full' ? 0 : (int)$data['deposit_amount'],
            'contact'=>collect($data['contact'])->only(['firstName','lastName','email','phone','address','city'])->all(),
            'type'=>$defaults['category'],'configuration'=>['defaults'=>$defaults,'calculation'=>$calculation], 'product'=>['model'=>$defaults['product_name'],'fabric'=>$defaults['material_name'],'finish'=>$defaults['finish_name'],'control'=>$defaults['motorized'] ? 'Motorisée' : 'Manuelle'], 'dimensions'=>$dimensions];
    }
    public static function selection(array $value,string $prefix): array
    {
        $p=Product::find($value['product_id']);
        if (!$p || ($p->details['type'] ?? '')!==$value['category']) throw ValidationException::withMessages([$prefix.'.product_id'=>'Sélectionnez un type de la catégorie choisie.']);
        $snapshot=['category'=>$value['category'],'product_id'=>$p->id,'product_name'=>$p->details['name'],'reference'=>$p->reference,'motorized'=>(bool)$value['motorized']];
        foreach (['material','finish'] as $kind) {
            $option=QuoteOption::find($value[$kind.'_id']);
            if (!$option || $option->kind!==$kind || !$option->compatible($value['category'],$p->id,(bool)$value['motorized'])) throw ValidationException::withMessages([$prefix.'.'.$kind.'_id'=>'Ce choix ne correspond plus à ce type et à cette motorisation. Sélectionnez une option compatible.']);
            $snapshot[$kind.'_id']=$option->id; $snapshot[$kind.'_name']=$option->label;
        }
        return $snapshot;
    }
}
