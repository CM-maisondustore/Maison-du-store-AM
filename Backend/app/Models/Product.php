<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public function media() { return $this->belongsTo(MediaAsset::class, 'media_asset_id')->select(['id', 'sha256', 'width', 'height']); }
    public const COSTS = ['material' => 'Matériel', 'making' => 'Confection', 'delivery' => 'Livraison', 'installation' => 'Pose'];
    public const COST_UNITS = ['item' => 'Par article', 'sqm' => 'Par m²', 'metre' => 'Par mètre linéaire', 'flat' => 'Au forfait'];
    protected $attributes = [
        'material_cost_unit' => 'item', 'making_cost_unit' => 'item',
        'delivery_cost_unit' => 'flat', 'installation_cost_unit' => 'flat',
    ];
    protected $fillable = [
        'reference', 'details', 'published',
        'material_cost', 'making_cost', 'delivery_cost', 'installation_cost',
        'material_cost_unit', 'making_cost_unit', 'delivery_cost_unit', 'installation_cost_unit',
    ];
    protected $hidden = [
        'material_cost', 'making_cost', 'delivery_cost', 'installation_cost',
        'material_cost_unit', 'making_cost_unit', 'delivery_cost_unit', 'installation_cost_unit',
    ];
    protected $casts = [
        'details' => 'array', 'published' => 'boolean',
        'material_cost' => 'integer', 'making_cost' => 'integer',
        'delivery_cost' => 'integer', 'installation_cost' => 'integer',
    ];
}
