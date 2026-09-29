<?php
namespace App\Services;
use Illuminate\Http\Request;
class QuoteSelections
{
    public static function normalize(array $v): array {
        $v['product_id']=0;
        if (array_key_exists('curtain_id',$v) || array_key_exists('sheer_id',$v) || array_key_exists('store_id',$v)) {
            $v['material_id']=($v['category'] ?? '')==='store' ? ($v['store_id'] ?? null) : (($v['curtain_id'] ?? null) ?: ($v['sheer_id'] ?? null));
        }
        return $v;
    }
    public static function prepare(Request $r): void {
        $defaults=$r->input('defaults'); $windows=$r->input('windows');
        if (is_array($defaults)) $r->merge(['defaults'=>self::normalize($defaults)]);
        if (!is_array($windows)) return;
        foreach ($windows as &$w) {
            if (!is_array($w)) continue;
            $w=self::normalize($w);
            $selected=!empty($w['inherit_defaults']) && is_array($defaults) ? $defaults : $w;
            if (array_key_exists('curtain_id',$selected) || array_key_exists('sheer_id',$selected)) {
                $w['costing']['secondary_material']['option_id']=($selected['category'] ?? '')==='rideau' && !empty($selected['curtain_id']) ? ($selected['sheer_id'] ?? null) : null;
            }
        }
        unset($w); $r->merge(['windows'=>$windows]);
    }
}
