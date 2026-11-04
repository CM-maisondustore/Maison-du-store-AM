<?php
namespace App\Services\Quotes;

use Illuminate\Http\Request;

/** Quote overrides only: catalogue rates are read when calculating, never written. */
final class PricingConfig
{
    public const FEES = ['installation'=>'Pose','delivery'=>'Livraison','transport'=>'Transport','travel'=>'Déplacement','installation_delivery'=>'Pose et livraison'];

    public static function input(Request $request): array
    {
        $raw = $request->input('pricing', []);
        if (!is_array($raw)) throw \Illuminate\Validation\ValidationException::withMessages(['pricing'=>'Paramètres de chiffrage invalides.']);
        foreach (['discount_value','deposit_percent'] as $key) if (is_string($raw[$key] ?? null)) $raw[$key]=str_replace(',','.',$raw[$key]);
        $request->merge(['pricing'=>$raw]);
        $rules = ['pricing'=>'required|array','pricing.engine'=>'required|in:2', 'pricing.display_mode'=>'required|in:grouped,individual',
            'pricing.discount_type'=>'required|in:none,percentage,fixed,target_total', 'pricing.discount_value'=>'nullable|numeric|min:0|max:1000000000000',
            'pricing.deposit_percent'=>'required|numeric|min:0|max:100', 'pricing.schedule'=>'sometimes|nullable|in:auto,two,three', 'tax_rate'=>'nullable|numeric|min:0|max:100|decimal:0,2'];
        foreach (self::FEES as $key=>$label) {
            $rules['pricing.fees.'.$key.'.enabled']='sometimes|boolean';
            $rules['pricing.fees.'.$key.'.option_id']='nullable|integer';
            $rules['pricing.fees.'.$key.'.unit_price']='nullable|integer|min:0|max:100000000';
            $rules['pricing.fees.'.$key.'.scope']='required|in:global,per_opening,per_trip';
            $rules['pricing.fees.'.$key.'.quantity']='nullable|integer|min:1|max:1000';
        }
        $request->validate($rules);
        $result = ['engine'=>2,'display_mode'=>$raw['display_mode'], 'discount_type'=>$raw['discount_type'],
            'discount_value'=>(string)($raw['discount_value'] ?? 0), 'deposit_percent'=>(string)$raw['deposit_percent'], 'schedule'=>$raw['schedule'] ?? null,
            'tax_basis_points'=>QuoteValidator::money(QuoteValidator::decimal($request->input('tax_rate',0),'taxe')->multipliedBy(100)->__toString(),'taxe'), 'fees'=>[]];
        foreach (self::FEES as $key=>$label) {
            $fee=$raw['fees'][$key];
            $option=!empty($fee['option_id']) ? \App\Models\QuoteOption::find($fee['option_id']) : null;
            if (!empty($fee['option_id']) && (!$option || !$option->active || $option->unit!=='flat' || $option->kind!==($key==='installation_delivery'?'combined':$key))) throw \Illuminate\Validation\ValidationException::withMessages(['pricing.fees.'.$key.'.option_id'=>'Choisissez un tarif actif au forfait pour ce poste.']);
            $override=isset($fee['unit_price']) ? (int)$fee['unit_price'] : null;
            $price=$override ?? $option?->unit_price;
            if (!empty($fee['enabled']) && $price===null) throw \Illuminate\Validation\ValidationException::withMessages(['pricing.fees.'.$key.'.unit_price'=>'Renseignez le prix de ce poste, ou zéro s’il est offert.']);
            $result['fees'][$key]=['enabled'=>!empty($fee['enabled']), 'option_id'=>$option?->id, 'price_override'=>$override,'unit_price'=>$price ?? 0,
                'scope'=>$fee['scope'], 'quantity'=>(int)($fee['quantity'] ?? 1)];
        }
        if ($result['fees']['installation_delivery']['enabled'] && ($result['fees']['installation']['enabled'] || $result['fees']['delivery']['enabled']))
            throw \Illuminate\Validation\ValidationException::withMessages(['pricing.fees'=>'Choisissez la pose/livraison combinée OU les deux postes séparés pour éviter un doublon.']);
        return $result;
    }
}
