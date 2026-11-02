<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteVisit extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['arrived_at' => 'datetime', 'last_activity_at' => 'datetime'];
}
