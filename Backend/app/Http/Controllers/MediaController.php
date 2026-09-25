<?php
namespace App\Http\Controllers;
use App\Models\{MediaAsset, Product, SiteContent};
use Illuminate\Http\Request;
class MediaController
{
    public function show(Request $request, int $media, string $hash)
    {
        abort_unless(Product::where('media_asset_id', $media)->where('published', true)->exists() || SiteContent::where('media_asset_id', $media)->where('published', true)->exists(), 404);
        $asset = MediaAsset::whereKey($media)->where('sha256', $hash)->firstOrFail();
        $response = response(base64_decode($asset->payload), 200, ['Content-Type' => $asset->mime, 'Cache-Control' => 'public, max-age=0, must-revalidate', 'Content-Security-Policy' => "default-src 'none'"])->setEtag($asset->sha256);
        $response->isNotModified($request);
        return $response;
    }
    public function preview(int $media)
    {
        $asset = MediaAsset::findOrFail($media);
        return response(base64_decode($asset->payload), 200, ['Content-Type' => $asset->mime, 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'"]);
    }
}
