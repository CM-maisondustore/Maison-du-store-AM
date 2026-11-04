<?php
namespace App\Http\Controllers\Staff;
use App\Models\{QuoteOption,Product};
use App\Services\{Audit,MediaStorage};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
class QuoteOptionController
{
    public function index() { return view('staff.quote-options',['options'=>QuoteOption::orderBy('category')->orderBy('kind')->orderBy('label')->get(),'products'=>Product::select('id','reference','details')->whereIn('details->type',['store','rideau'])->get()]); }
    private function data(Request $r): array
    {
        $r->validate(MediaStorage::rules());
        $data=$r->validate(['label'=>'required|string|max:150','kind'=>['required',Rule::in(array_keys(QuoteOption::KINDS))],'category'=>'required|in:store,rideau','motorization'=>'required|in:any,yes,no','active'=>'required|boolean','product_ids'=>'nullable|array|max:100','product_ids.*'=>'integer|distinct|exists:products,id',
            'fabric_group'=>'nullable|in:rideau,voilage,store','public_choice'=>'sometimes|boolean','unit_price'=>'sometimes|nullable|integer|min:0|max:100000000','unit'=>['sometimes','required',Rule::in(array_keys(QuoteOption::UNITS))],
            'finish_codes'=>'sometimes|nullable|array|max:100','finish_codes.*'=>['string','distinct',Rule::exists('quote_options','code')->where('kind','finish')->where('category',$r->input('category'))]]);
        if ($r->has('tariff_form')) $data['finish_codes']=$data['finish_codes'] ?? [];
        if ($data['kind']==='material') {
            $data['fabric_group']=$data['fabric_group'] ?? $data['category'];
            if (($data['fabric_group']==='store')!==($data['category']==='store')) throw ValidationException::withMessages(['fabric_group'=>'Le groupe de tissu doit correspondre à la catégorie.']);
        } else $data['fabric_group']=null;
        $data['product_ids']=array_map('intval',$data['product_ids'] ?? []);
        if (Product::whereIn('id',$data['product_ids'])->where('details->type','!=',$data['category'])->exists()) throw ValidationException::withMessages(['product_ids'=>'Tous les types sélectionnés doivent appartenir à la catégorie choisie.']);
        return $data;
    }
    public function store(Request $r) { $data=$this->data($r); DB::transaction(function() use($r,$data){$o=QuoteOption::create([...$data,'code'=>(string)Str::uuid()]);MediaStorage::apply($o,$r);Audit::record('option_devis_creee',$o);}); return back()->with('success','Option enregistrée.'); }
    public function update(Request $r,QuoteOption $option) { $data=$this->data($r);DB::transaction(function() use($r,$option,$data){$locked=QuoteOption::whereKey($option->id)->lockForUpdate()->firstOrFail();$locked->fill($data);MediaStorage::apply($locked,$r);Audit::record('option_devis_modifiee',$locked);}); return back()->with('success','Option enregistrée. Les devis existants conservent leurs descriptions.'); }
}
