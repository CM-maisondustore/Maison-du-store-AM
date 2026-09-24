<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['contact' => 'array', 'product' => 'array', 'dimensions' => 'array', 'lines' => 'array', 'terms_accepted_at' => 'datetime'];
    public function customer() { return $this->belongsTo(Contact::class, 'contact_id'); }
    public function agent() { return $this->belongsTo(User::class, 'agent_id'); }
}
