<?php
namespace App\Services;

use App\Models\{Product,Quote};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Devis par article pour l'électroménager et les meubles.
 * Le calcul des stores et rideaux n'est pas concerné : ces devis utilisent les lignes et les totaux historiques (Pricing).
 * Un prix vide veut dire « à définir », jamais 0 : le poste est listé et la validation reste refusée.
 */
final class QuoteArticles
{
    public const LABELS=['electromenager'=>'Électroménager','meubles'=>'Meubles'];
    public const UNIVERSES=['electromenager'=>['electromenager'],'meubles'=>['meubles_bureau','meubles_domicile']];
    public const SERVICES=[
        'electromenager'=>['delivery'=>'Livraison','installation'=>'Installation par appareil','takeback'=>'Reprise de l’ancien appareil'],
        'meubles'=>['delivery'=>'Livraison','assembly'=>'Montage','takeback'=>'Reprise de l’ancien meuble'],
    ];
    public const FLOORS=['rdc'=>'Rez-de-chaussée','1'=>'1er étage','2'=>'2e étage','3'=>'3e étage ou plus'];

    /** Noms et références seulement : ce catalogue est aussi servi aux agents. */
    public static function catalogue(?string $type): array
    {
        return Product::unarchived()->whereIn('details->universe',self::UNIVERSES[$type] ?? [])->orderBy('id')->get(['id','reference','details'])
            ->map(fn($p)=>['name'=>$p->details['name'] ?? $p->reference,'reference'=>$p->reference])->all();
    }

    public static function validate(Request $r,?string $type=null): array
    {
        QuotePhone::prepare($r);
        $admin=$r->user()->isAdmin();
        $type=$type ?? $r->input('universe');
        $rules=['submission_token'=>'required|uuid','edit_version'=>'required|integer|min:0',
            'contact'=>'required|array','contact.firstName'=>'nullable|string|max:100','contact.lastName'=>'required|string|max:100','contact.email'=>'nullable|email|max:255','contact.phone'=>['required','string','max:30',new \App\Rules\Phone],'contact.address'=>'required|string|max:1000','contact.city'=>'nullable|string|max:100',
            'universe'=>['sometimes',Rule::in(array_keys(self::LABELS))],
            'articles'=>'required|array|min:1|max:100','articles.*.name'=>'required|string|max:200','articles.*.variant'=>'nullable|string|max:150','articles.*.quantity'=>'required|integer|min:1|max:1000',
            'services'=>'nullable|array','services.*.enabled'=>'sometimes|boolean',
            'floor'=>['nullable',Rule::in(array_keys(self::FLOORS))],'has_lift'=>'sometimes|boolean','field_notes'=>'nullable|string|max:10000'];
        if ($admin) $rules+=['articles.*.unit_price'=>'nullable|integer|min:0|max:100000000','services.*.unit_price'=>'nullable|integer|min:0|max:100000000',
            'tax_rate'=>'required|numeric|min:0|max:100|decimal:0,2','payment_mode'=>'required|in:advance,full','deposit_amount'=>'required|integer|min:0|max:1000000000000','internal_notes'=>'nullable|string|max:10000',
            'quote_date'=>'required|date_format:Y-m-d','valid_until'=>'required|date_format:Y-m-d|after_or_equal:quote_date','delivery_date'=>'nullable|date_format:Y-m-d|after_or_equal:quote_date','installation_date'=>'nullable|date_format:Y-m-d|after_or_equal:quote_date',
            'agent_id'=>['nullable',Rule::exists('users','id')->where('role','agent')->where('active',true)]];
        else foreach (['articles.*.unit_price','services.*.unit_price','tax_rate','tax_basis_points','lines','pricing','payment_mode','deposit_amount','internal_notes','calculation_token','receipt_amount','agent_id','quote_date','valid_until','delivery_date','installation_date'] as $field) $rules[$field]='prohibited';
        $v=$r->validate($rules);

        $floor=$v['floor'] ?? 'rdc'; $lift=$r->boolean('has_lift');
        $access=$floor==='rdc' ? '' : ' ('.mb_strtolower(self::FLOORS[$floor]).($lift ? ', ascenseur' : ' sans ascenseur').')';
        $articles=[]; $lines=[]; $pending=[];
        foreach (array_values($v['articles']) as $a) {
            $price=$admin ? ($a['unit_price'] ?? null) : null;
            $description=$a['name'].(!empty($a['variant']) ? ', '.$a['variant'] : '');
            $articles[]=['name'=>$a['name'],'variant'=>$a['variant'] ?? '','quantity'=>(int)$a['quantity']];
            $lines[]=['description'=>$description,'quantity'=>(int)$a['quantity'],'unit_price'=>(int)$price];
            if ($price===null) $pending[]=$description;
        }
        $services=[];
        foreach (self::SERVICES[$type] ?? [] as $key=>$label) {
            $service=$v['services'][$key] ?? [];
            if (empty($service['enabled'])) continue;
            $price=$admin ? ($service['unit_price'] ?? null) : null;
            $description=$label.(in_array($key,['delivery','assembly','installation'],true) ? $access : '');
            $services[$key]=true;
            $lines[]=['description'=>$description,'quantity'=>1,'unit_price'=>(int)$price];
            if ($price===null) $pending[]=$description;
        }
        $configuration=['universe'=>$type,'articles'=>$articles,'services'=>$services,'floor'=>$floor,'has_lift'=>$lift,'field_notes'=>$v['field_notes'] ?? '',
            'calculation'=>['pending'=>$pending],'pricing_review_required'=>!$admin];
        $data=['type'=>$type,'contact'=>collect($v['contact'])->only(['firstName','lastName','email','phone','address','city'])->all(),'product'=>['model'=>self::LABELS[$type] ?? $type],'dimensions'=>[],'configuration'=>$configuration,'lines'=>$lines,'tax_basis_points'=>0];
        if ($admin) {
            $data=[...$data,'tax_basis_points'=>(int)round((float)$v['tax_rate']*100),'payment_mode'=>$v['payment_mode'],'deposit_amount'=>(int)$v['deposit_amount'],'internal_notes'=>$v['internal_notes'] ?? null,
                'quote_date'=>$v['quote_date'],'valid_until'=>$v['valid_until'],'delivery_date'=>$v['delivery_date'] ?? null,'installation_date'=>$v['installation_date'] ?? null,'agent_id'=>$v['agent_id'] ?? null];
        }
        return $data;
    }

    /** Un agent corrige articles et quantités sans jamais voir ni effacer les prix du bureau. */
    public static function preserveAdminData(array $data,Quote $quote): array
    {
        $known=collect($quote->lines ?? [])->keyBy('description');
        $pending=[];
        foreach ($data['lines'] as &$line) {
            $old=$known->get($line['description']);
            $line['unit_price']=(int)($old['unit_price'] ?? 0);
            if (in_array($line['description'],$quote->configuration['calculation']['pending'] ?? [],true) || !$old) $pending[]=$line['description'];
        }
        unset($line);
        $data['configuration']['calculation']['pending']=$pending;
        $data['tax_basis_points']=$quote->tax_basis_points ?? 0;
        return $data;
    }
}
