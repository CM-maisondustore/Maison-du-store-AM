<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable
{
    use Notifiable;
    protected $attributes = ['active' => true, 'must_change_password' => true, 'role' => 'agent'];
    protected $fillable = ['username', 'name', 'email', 'phone', 'profile_photo', 'profile_photo_mime', 'theme', 'password', 'role', 'active', 'must_change_password'];
    protected $hidden = ['password', 'remember_token', 'profile_photo'];
    protected $casts = ['password' => 'hashed', 'active' => 'boolean', 'must_change_password' => 'boolean'];
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function pushSubscriptions() { return $this->hasMany(PushSubscription::class); }
}
