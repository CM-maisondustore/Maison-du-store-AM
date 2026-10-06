<?php
namespace App\Services;
use App\Models\QuoteOption;
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule,ValidationException};
class AgentQuoteData
{
    public const FINISHES=['wave-manuel'=>'Wave manuel','wave-electric'=>"Wave motoris\u{00e9}",'ruflette-wave'=>'Ruflette Wave','reflete-manuel'=>'Ruflette','barre-fer'=>'Barre de fer'];
    public const TYPES=['rideau'=>'Rideaux','voilage'=>'Voilages','rideau_voilage'=>'Rideaux et voilages','store'=>'Stores'];
    public static function catalogue(): array {
        return ['options'=>QuoteOption::where('active',true)->where('kind','finish')->whereIn('category',['store','rideau'])->orderBy('label')->get(['id','code','label','category','motorization'])->map(fn($o)=>['id'=>$o->id,'code'=>$o->code,'label'=>self::FINISHES[$o->code] ?? $o->label,'category'=>$o->category,'motorization'=>$o->motorization])->all()];
    }
    public static function validate(Request $r): array {
        QuotePhone::prepare($r);
        $rules=['submission_token'=>'required|uuid','edit_version'=>'required|integer|min:0','contact'=>'required|array:firstName,lastName,email,phone,address,city',
            'contact.firstName'=>'nullable|string|max:100','contact.lastName'=>'required|string|max:100','contact.email'=>'nullable|email|max:255','contact.phone'=>['required','string','max:30',new \App\Rules\Phone],'contact.address'=>'required|string|max:1000','contact.city'=>'nullable|string|max:100',
            'defaults'=>'required|array:project_type,motorized,finish_id,zone','windows'=>'required|array|min:1|max:30',
            'windows.*'=>'required|array:project_type,motorized,finish_id,zone,inherit_defaults,label,width,height,notes',
            'windows.*.label'=>'required|string|max:100','windows.*.width'=>'required|numeric|min:1|max:2000','windows.*.height'=>'required|numeric|min:1|max:2000','windows.*.notes'=>'nullable|string|max:1000','windows.*.inherit_defaults'=>'sometimes|boolean','field_notes'=>'nullable|string|max:10000'];
        foreach(['lines','pricing','tax_rate','tax_basis_points','payment_mode','deposit_amount','internal_notes','calculation_token','receipt_amount','agent_id','quote_date','valid_until','delivery_date','installation_date'] as $key)$rules[$key]='prohibited';
        foreach(['material_id','curtain_id','sheer_id','store_id','quantity','costing','components'] as $key){$rules['windows.*.'.$key]='prohibited';$rules['defaults.'.$key]='prohibited';}
        foreach(['defaults','windows.*'] as $prefix)foreach(['project_type'=>['required',Rule::in(array_keys(self::TYPES))],'motorized'=>'required|boolean','finish_id'=>'required|integer','zone'=>'required|in:abidjan,other'] as $key=>$rule)$rules[$prefix.'.'.$key]=$rule;
        $windows=$r->input('windows',[]);$defaults=$r->input('defaults',[]);
        if(is_array($windows)){
            if($windows && !collect($windows)->contains(fn($w)=>is_array($w)&&!empty($w['inherit_defaults'])) && is_array(reset($windows)))$defaults=collect(reset($windows))->only(['project_type','motorized','finish_id','zone'])->all();
            foreach($windows as &$w)if(is_array($w)){
                if(!empty($w['inherit_defaults'])&&is_array($defaults))$w=[...$w,...collect($defaults)->only(['project_type','motorized','finish_id','zone'])->all()];
                foreach(['width','height'] as $k)if(isset($w[$k])&&is_string($w[$k]))$w[$k]=str_replace(',','.',$w[$k]);
            }unset($w);
        }
        $r->merge(['defaults'=>$defaults,'windows'=>$windows]);$data=$r->validate($rules);
        $base=self::selection($data['defaults'],'defaults');$dimensions=[];
        foreach($data['windows'] as $i=>$w)$dimensions[$i]=[...self::selection($w,'windows.'.$i),'inherit_defaults'=>(bool)($w['inherit_defaults'] ?? false),'label'=>$w['label'],'width'=>(float)$w['width'],'height'=>(float)$w['height'],'quantity'=>1,'notes'=>$w['notes'] ?? ''];
        return ['contact'=>$data['contact'],'type'=>$base['category'],'configuration'=>['defaults'=>$base,'field_notes'=>$data['field_notes'] ?? '','pricing_review_required'=>true,'field_only'=>true],
            'product'=>['model'=>$base['product_name'],'finish'=>$base['finish_name'],'control'=>$base['motorized']?'Motorisée':'Manuelle'], 'dimensions'=>$dimensions];
    }
    private static function selection(array $v,string $prefix): array {
        $category=$v['project_type']==='store'?'store':'rideau';$finish=QuoteOption::find($v['finish_id']);
        if(!$finish || $finish->kind!=='finish' || !$finish->compatible($category,0,(bool)$v['motorized']))throw ValidationException::withMessages([$prefix.'.finish_id'=>'Choisissez une finition disponible pour ce type de projet.']);
        return ['project_type'=>$v['project_type'],'category'=>$category,'product_name'=>self::TYPES[$v['project_type']],'motorized'=>(bool)$v['motorized'],'finish_id'=>$finish->id,'finish_name'=>self::FINISHES[$finish->code] ?? $finish->label,'zone'=>$v['zone']];
    }
    public static function preserveAdminData(array $data,\App\Models\Quote $quote): array {
        $previous=$quote->configuration ?? [];
        $data['configuration']=[...$previous,...$data['configuration'],'calculation'=>$previous['calculation'] ?? null];
        $data['configuration']['defaults']=[...($previous['defaults'] ?? []),...$data['configuration']['defaults']];
        foreach($data['dimensions'] as $index=>&$w){
            $old=$quote->dimensions[$index] ?? [];
            $w=[...$old,...$w,'quantity'=>$old['quantity'] ?? 1,'costing'=>[...($old['costing'] ?? []),'zone'=>$w['zone']]];
        }unset($w);
        $data['dimensions']=array_values($data['dimensions']);
        return $data;
    }
}
