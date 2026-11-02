<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'enabled'];
    protected $casts = ['enabled' => 'boolean'];

    public static function catalogueVisible(): bool
    {
        return static::where('key', 'catalogue_visible')->value('enabled') ?? true;
    }
}
