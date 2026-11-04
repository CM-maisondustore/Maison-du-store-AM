<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    // user_id vient toujours du compte connecté (PushController), jamais de la requête.
    protected $fillable = ['user_id', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'label', 'last_used_at'];
    protected $hidden = ['endpoint', 'public_key', 'auth_token'];
    protected $casts = [
        'endpoint' => 'encrypted',
        'public_key' => 'encrypted',
        'auth_token' => 'encrypted',
        'last_used_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
