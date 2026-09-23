<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['reference', 'details'];
    protected $casts = ['details' => 'array'];
}
