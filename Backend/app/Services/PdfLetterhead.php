<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PdfLetterhead
{
    public const MARGINS = ['top'=>40,'bottom'=>30,'left'=>15,'right'=>15];
    public const IMAGES = ['letterhead_standard','letterhead_continuation'];

    public static function options(Request $request, array $saved): array
    {
        $rules=['letterhead_enabled'=>'sometimes|boolean','letterhead_opacity'=>'sometimes|required|integer|min:10|max:100'];
        foreach (self::MARGINS as $side=>$default) $rules['margin_'.$side.'_mm']='sometimes|required|numeric|min:5|max:'.(in_array($side,['top','bottom']) ? 100 : 50);
        foreach (self::IMAGES as $key) {
            $rules[$key]=['nullable','file','image','mimes:png,jpg,jpeg','max:2048','dimensions:max_width=2500,max_height=3600'];
            $rules['remove_'.$key]='sometimes|boolean';
        }
        $values=$request->validate($rules);
        $options=['letterhead_enabled'=>(bool)($saved['letterhead_enabled'] ?? false),'letterhead_opacity'=>(int)($saved['letterhead_opacity'] ?? 100)];
        foreach (self::MARGINS as $side=>$default) $options['margin_'.$side.'_mm']=(float)($values['margin_'.$side.'_mm'] ?? $saved['margin_'.$side.'_mm'] ?? $default);
        if (array_key_exists('letterhead_enabled',$values)) $options['letterhead_enabled']=$request->boolean('letterhead_enabled');
        if (isset($values['letterhead_opacity'])) $options['letterhead_opacity']=(int)$values['letterhead_opacity'];
        foreach (self::IMAGES as $key) {
            $options[$key]=$request->boolean('remove_'.$key) ? null : ($saved[$key] ?? null);
            if ($request->hasFile($key)) {
                $bytes=$request->file($key)->get(); $size=getimagesizefromstring($bytes);
                if (!$size || !in_array($size['mime'],['image/png','image/jpeg'],true)) throw ValidationException::withMessages([$key=>'Utilisez une image PNG ou JPEG.']);
                $options[$key]='data:'.$size['mime'].';base64,'.base64_encode($bytes);
            }
        }
        if ($options['letterhead_enabled'] && (!$options['letterhead_standard'] || !$options['letterhead_continuation'])) throw ValidationException::withMessages(['letterhead_enabled'=>'Ajoutez les deux images avant d’activer les fonds de page, ou désactivez cette option pour les retirer.']);
        return $options;
    }

    public static function margins(array $options): array
    {
        $margins=[];
        foreach (self::MARGINS as $side=>$default) $margins[$side]=max(5,min(in_array($side,['top','bottom']) ? 100 : 50,(float)($options['margin_'.$side.'_mm'] ?? $default)));
        return $margins;
    }

    /** Only locally uploaded PNG/JPEG data can become a PDF background. */
    public static function decode(?string $data): ?array
    {
        if (!$data || strlen($data)>2800000 || !preg_match('~^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$~D',$data,$m)) return null;
        $bytes=base64_decode($m[2],true); $size=$bytes===false ? false : @getimagesizefromstring($bytes);
        if (!$size || !in_array($size['mime'],['image/png','image/jpeg'],true) || $size[0]>2500 || $size[1]>3600) return null;
        return ['bytes'=>$bytes,'width'=>$size[0],'height'=>$size[1]];
    }
}
