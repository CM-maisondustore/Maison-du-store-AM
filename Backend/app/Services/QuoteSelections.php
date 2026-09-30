<?php
namespace App\Services;
use Illuminate\Http\Request;
class QuoteSelections
{
    private const CHOICE_KEYS=['category','product_id','material_id','finish_id','motorized','curtain_id','sheer_id','store_id'];
    public static function normalize(array $v): array {
        $v['product_id']=0;
        if (array_key_exists('curtain_id',$v) || array_key_exists('sheer_id',$v) || array_key_exists('store_id',$v)) {
            $v['material_id']=($v['category'] ?? '')==='store' ? ($v['store_id'] ?? null) : (($v['curtain_id'] ?? null) ?: ($v['sheer_id'] ?? null));
        }
        return $v;
    }
    public static function prepare(Request $r): void {
        $defaults=$r->input('defaults'); $windows=$r->input('windows');
        if (!is_array($windows)) return;
        // Unused common choices must not block openings configured individually.
        $inherits=collect($windows)->contains(fn($w)=>is_array($w) && !empty($w['inherit_defaults']));
        if ($windows && !$inherits && is_array(reset($windows))) $defaults=array_intersect_key(reset($windows),array_flip(self::CHOICE_KEYS));
        if (is_array($defaults)) { $defaults=self::normalize($defaults); $r->merge(['defaults'=>$defaults]); }
        foreach ($windows as &$w) {
            if (!is_array($w)) continue;
            if (!empty($w['inherit_defaults']) && is_array($defaults)) $w=[...array_diff_key($w,array_flip(self::CHOICE_KEYS)),...array_intersect_key($defaults,array_flip(self::CHOICE_KEYS))];
            $w=self::normalize($w);
            $selected=!empty($w['inherit_defaults']) && is_array($defaults) ? $defaults : $w;
            if (array_key_exists('curtain_id',$selected) || array_key_exists('sheer_id',$selected)) {
                $w['costing']['secondary_material']['option_id']=($selected['category'] ?? '')==='rideau' && !empty($selected['curtain_id']) ? ($selected['sheer_id'] ?? null) : null;
            }
        }
        unset($w); $r->merge(['windows'=>$windows]);
    }
}
