<?php
namespace App\Services;
use Illuminate\Http\Request;
class QuotePhone
{
    public const PREFIXES=['+225'=>'Côte d’Ivoire','+33'=>'France','+221'=>'Sénégal','+223'=>'Mali','+226'=>'Burkina Faso','+229'=>'Bénin','+228'=>'Togo','+233'=>'Ghana','+237'=>'Cameroun','+243'=>'RD Congo','+241'=>'Gabon','+212'=>'Maroc','+44'=>'Royaume-Uni','+1'=>'États-Unis / Canada'];
    private const TRUNK_ZERO=['+33','+44','+233','+212'];
    public static function normalize(string $phone,string $prefix=''): string {
        $phone=preg_replace('/[\s.\-()]/u','',trim($phone));
        if(str_starts_with($phone,'00'))$phone='+'.substr($phone,2);
        if($phone==='' || str_starts_with($phone,'+') || $prefix==='')return $phone;
        if(in_array($prefix,self::TRUNK_ZERO,true)&&str_starts_with($phone,'0'))$phone=substr($phone,1);
        return $prefix.$phone;
    }
    public static function split(?string $phone): array {
        $phone=self::normalize($phone ?? '');$prefixes=array_keys(self::PREFIXES);usort($prefixes,fn($a,$b)=>strlen($b)<=>strlen($a));
        foreach($prefixes as $prefix)if(str_starts_with($phone,$prefix))return ['prefix'=>$prefix,'number'=>substr($phone,strlen($prefix))];
        return ['prefix'=>str_starts_with($phone,'+')?'':'+225','number'=>$phone];
    }
    public static function prepare(Request $r): void {
        if(!$r->exists('contact.phoneCountryCode'))return; // Preserve compatibility with saved/older submissions.
        $r->validate(['contact.phoneCountryCode'=>['nullable','string','regex:/^\+[1-9][0-9]{0,2}$/D']],[ 'contact.phoneCountryCode.regex'=>'Indiquez un indicatif valide, par exemple +225.']);
        $contact=$r->input('contact');if(!is_array($contact))return;
        if(is_string($contact['phone'] ?? null))$contact['phone']=self::normalize($contact['phone'],$contact['phoneCountryCode'] ?? '');
        unset($contact['phoneCountryCode']);$r->merge(['contact'=>$contact]);
    }
}
