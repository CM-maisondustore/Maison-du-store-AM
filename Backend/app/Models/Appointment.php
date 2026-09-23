<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = ['reference', 'date', 'time', 'details'];
    protected $casts = ['details' => 'array'];
}
