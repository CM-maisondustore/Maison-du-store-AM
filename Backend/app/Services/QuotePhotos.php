<?php
namespace App\Services;

use App\Models\{MediaAsset, Quote};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Photo facultative de chaque ouverture (étape 1) : une image par ouverture, rangée dans dimensions[*].photo_media_id. */
final class QuotePhotos
{
    public static function validate(Request $r): void
    {
        Validator::make($r->all(),['windows.*.photo' => MediaStorage::rules()['image'], 'windows.*.photo_media_id' => 'nullable|integer', 'windows.*.photo_remove' => 'nullable|boolean'],
            ['windows.*.photo.*' => 'Photo d’ouverture : choisissez une image JPG, PNG ou WebP de 2 Mo maximum.'])->validate();
    }

    /** Ids des photos d'un devis enregistré. */
    public static function ids(?Quote $quote): array
    {
        return collect($quote?->dimensions ?? [])->pluck('photo_media_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Ajoute à chaque ouverture sa photo : nouvelle image envoyée, photo déjà enregistrée sur ce devis (jamais celle d'un autre), ou aucune.
     * À appeler dans la transaction d'enregistrement. Renvoie les ouvertures et les ids devenus inutiles, à supprimer ensuite.
     * Les ouvertures suivent l'ordre des champs windows[...] du formulaire, comme à la validation.
     */
    public static function apply(Request $r, array $dimensions, ?Quote $locked): array
    {
        $kept = self::ids($locked);
        $dimensions = array_values($dimensions);
        $keys = array_keys((array) $r->input('windows', []));
        foreach ($dimensions as $i => $dimension) {
            $key = $keys[$i] ?? $i;
            unset($dimensions[$i]['photo_media_id']);
            if ($r->hasFile('windows.'.$key.'.photo')) $dimensions[$i]['photo_media_id'] = MediaStorage::fromBytes($r->file('windows.'.$key.'.photo')->get())->id;
            elseif (!$r->boolean('windows.'.$key.'.photo_remove') && in_array((int) $r->input('windows.'.$key.'.photo_media_id'), $kept, true)) $dimensions[$i]['photo_media_id'] = (int) $r->input('windows.'.$key.'.photo_media_id');
        }
        $used = collect($dimensions)->pluck('photo_media_id')->filter()->map(fn ($id) => (int) $id)->all();
        return [$dimensions, array_values(array_diff($kept, $used))];
    }

    public static function forget(array $ids): void
    {
        if ($ids) MediaAsset::whereKey($ids)->delete();
    }
}
