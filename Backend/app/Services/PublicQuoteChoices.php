<?php
namespace App\Services;
use App\Models\QuoteOption;
class PublicQuoteChoices
{
    public static function options(): \Illuminate\Database\Eloquent\Collection
    {
        return QuoteOption::where('active',true)->where('public_choice',true)->whereIn('kind',['material','finish','support'])->orderBy('id')->get(['code','label','kind','category','motorization','finish_codes','product_ids']);
    }
    public static function data(): array
    {
        // Never expose catalogue costs on the public site.
        return self::options()->map(fn ($o)=>['id'=>$o->code,'label'=>$o->label,'name'=>$o->label,'text'=>'Sur mesure, à confirmer avec votre conseiller.','icon'=>'i-curtain','kind'=>$o->kind,'category'=>$o->category,'motorization'=>$o->motorization,'finish_codes'=>$o->finish_codes])->all();
    }
}
