<?php
namespace App\Services;
use App\Models\{MediaAsset, Product, SiteContent};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
class MediaStorage
{
    public static function rules(): array
    {
        return ['image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'image_alt' => 'sometimes|nullable|string|max:255', 'remove_image' => 'sometimes|boolean'];
    }
    public static function fromBytes(string $bytes): MediaAsset
    {
        $size = getimagesizefromstring($bytes);
        if (!$size || !in_array($size['mime'], ['image/jpeg','image/png','image/webp'], true) || strlen($bytes) > 2097152 || $size[0] > 6000 || $size[1] > 6000) {
            throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'Choisissez une image JPG, PNG ou WebP de 2 Mo maximum (6000 pixels par côté).']);
        }
        return MediaAsset::create(['mime' => $size['mime'], 'width' => $size[0], 'height' => $size[1], 'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'payload' => base64_encode($bytes)]);
    }
    // Caller owns the transaction: metadata and image are committed together.
    public static function apply(Model $owner, Request $request): void
    {
        $old = $owner->media_asset_id;
        if ($request->hasFile('image')) $owner->media_asset_id = self::fromBytes($request->file('image')->get())->id;
        elseif ($request->boolean('remove_image')) $owner->media_asset_id = null;
        if ($request->exists('image_alt')) $owner->image_alt = $request->input('image_alt') ?? '';
        $owner->save();
        if ($old && $old !== $owner->media_asset_id && !Product::where('media_asset_id', $old)->exists() && !SiteContent::where('media_asset_id', $old)->exists()) MediaAsset::whereKey($old)->delete();
    }
}
