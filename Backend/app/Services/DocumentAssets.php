<?php
namespace App\Services;
use Illuminate\Support\Facades\Storage;

/** Immutable, private, content-addressed assets; old database data URIs remain readable. */
final class DocumentAssets
{
    public const KEYS=['logo','letterhead_standard','letterhead_continuation','signature_image','stamp_image'];
    private static function disk() { return Storage::build(['driver'=>'local','root'=>storage_path('app/private/document-assets'),'throw'=>true]); }
    public static function store(array $options): array
    {
        foreach (self::KEYS as $key) {
            $value=$options[$key] ?? null;
            if (!$value || str_starts_with($value,'asset:')) continue;
            $image=PdfLetterhead::decode($value);
            if (!$image) throw new \RuntimeException('Image documentaire invalide.');
            $name=hash('sha256',$image['bytes']).'.'.(str_starts_with($value,'data:image/png;') ? 'png' : 'jpg');
            if (!self::disk()->exists($name)) self::disk()->put($name,$image['bytes']);
            $options[$key]='asset:'.$name;
        }
        return $options;
    }
    public static function resolve(array $options): array
    {
        foreach (self::KEYS as $key) {
            $value=$options[$key] ?? null;
            if (!$value || !str_starts_with($value,'asset:')) continue;
            if (!preg_match('/^asset:([a-f0-9]{64})\.(png|jpg)$/D',$value,$m)) throw new \RuntimeException('Référence documentaire invalide.');
            $bytes=self::disk()->get($m[1].'.'.$m[2]);
            if (!hash_equals($m[1],hash('sha256',$bytes))) throw new \RuntimeException('Image documentaire corrompue.');
            $options[$key]='data:image/'.($m[2]==='png'?'png':'jpeg').';base64,'.base64_encode($bytes);
        }
        return $options;
    }
}
