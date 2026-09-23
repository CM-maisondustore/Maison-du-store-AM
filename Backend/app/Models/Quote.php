<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $fillable = ['reference', 'type', 'contact', 'product', 'dimensions', 'notes', 'terms_accepted_at'];
    protected $casts = ['contact' => 'array', 'product' => 'array', 'dimensions' => 'array', 'terms_accepted_at' => 'datetime'];
}
