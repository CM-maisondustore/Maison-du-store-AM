<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['reference', 'details', 'published'];
    protected $casts = ['details' => 'array', 'published' => 'boolean'];
}
