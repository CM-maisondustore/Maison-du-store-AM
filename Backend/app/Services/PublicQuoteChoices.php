<?php
namespace App\Services;
use App\Models\{QuoteOption,Product};
class PublicQuoteChoices
{
    public static function options(): \Illuminate\Database\Eloquent\Collection
    {
        return QuoteOption::where('active',true)->where('public_choice',true)->whereIn('kind',['material','finish'])->orderBy('id')->get(['id','code','label','kind','category','motorization','finish_codes','product_ids','fabric_group','media_asset_id','image_alt']);
    }
    public static function data(): array
    {
        // Never expose catalogue costs on the public site.
        $products=self::products();
        return self::options()->map(function ($o) use ($products) {
            $image=$products->first(fn($p)=>$p->media_asset_id && ($p->details['type'] ?? '')===$o->category && (!$o->product_ids || in_array($p->id,$o->product_ids,true)));
            return ['id'=>$o->code,'label'=>$o->label,'name'=>$o->label,'text'=>'Sur mesure, à confirmer avec votre conseiller.','icon'=>'i-curtain','kind'=>$o->kind,'category'=>$o->category,'motorization'=>$o->motorization,'finish_codes'=>$o->finish_codes,'product_ids'=>$o->product_ids ?? [],'fabric_group'=>$o->fabric_group ?: $o->category,'imageAlt'=>$o->image_alt,'imageUrl'=>$o->media_asset_id ? route('quote.material-image',$o) : ($image ? route('quote.product-image',$image) : null)];
        })->all();
    }
    public static function finishes(): array
    {
        return \App\Models\SiteContent::with('media')->whereIn('slot',['finish-wave','finish-ruflette','finish-barre-fer'])->where('published',true)->orderBy('position')->orderBy('id')->get()->map(fn($c)=>['id'=>substr($c->slot,7),'label'=>$c->title,'text'=>$c->description,'imageUrl'=>$c->media?->url(),'imageAlt'=>$c->image_alt])->all();
    }
    public static function messages(): array
    {
        return \App\Models\SiteContent::whereIn('slot',['quote-message-store','quote-message-rideau'])->where('published',true)->get()->mapWithKeys(fn($c)=>[substr($c->slot,14)=>['title'=>$c->title,'text'=>$c->description]])->all();
    }
    public static function products(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::with('media')->unarchived()->where('published',true)->where('details->available',true)->orderBy('id')->get();
    }
    public static function models(): array
    {
        return self::products()->map(fn($p)=>['id'=>'product-'.$p->id,'productId'=>$p->id,'category'=>$p->details['type'] ?? '',
            'name'=>$p->details['name'] ?? $p->reference,'text'=>$p->details['description'] ?? '',
            'imageUrl'=>$p->media ? route('quote.product-image',$p) : null,'imageAlt'=>$p->image_alt ?: ($p->details['name'] ?? '')])->all();
    }
}
