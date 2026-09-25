<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SiteContent extends Model
{
    protected $fillable = ['slot', 'kind', 'title', 'description', 'image_alt', 'media_asset_id', 'published', 'position'];
    protected $casts = ['published' => 'boolean', 'position' => 'integer'];
    public function media() { return $this->belongsTo(MediaAsset::class, 'media_asset_id')->select(['id', 'sha256', 'width', 'height']); }
}
