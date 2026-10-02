<?php
namespace App\Services;
use App\Models\{QuoteOption,Product};
class PublicQuoteChoices
{
    public static function options(): \Illuminate\Database\Eloquent\Collection
    {
        return QuoteOption::where('active',true)->where('public_choice',true)->whereIn('kind',['material','finish','support'])->orderBy('id')->get(['code','label','kind','category','motorization','finish_codes','product_ids']);
    }
    public static function data(): array
    {
        // Never expose catalogue costs on the public site.
        $products=self::products();
        return self::options()->map(function ($o) use ($products) {
            $image=$products->first(fn($p)=>$p->media_asset_id && ($p->details['type'] ?? '')===$o->category && (!$o->product_ids || in_array($p->id,$o->product_ids,true)));
            return ['id'=>$o->code,'label'=>$o->label,'name'=>$o->label,'text'=>'Sur mesure, à confirmer avec votre conseiller.','icon'=>'i-curtain','kind'=>$o->kind,'category'=>$o->category,'motorization'=>$o->motorization,'finish_codes'=>$o->finish_codes,'product_ids'=>$o->product_ids ?? [],'imageUrl'=>$image ? route('quote.product-image',$image) : null];
        })->all();
    }
    public static function products(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::with('media')->where('published',true)->where('details->available',true)->orderBy('id')->get();
    }
    public static function models(): array
    {
        return self::products()->map(fn($p)=>['id'=>'product-'.$p->id,'productId'=>$p->id,'category'=>$p->details['type'] ?? '',
            'name'=>$p->details['name'] ?? $p->reference,'text'=>$p->details['description'] ?? '',
            'imageUrl'=>$p->media ? route('quote.product-image',$p) : null,'imageAlt'=>$p->image_alt ?: ($p->details['name'] ?? '')])->all();
    }
}
