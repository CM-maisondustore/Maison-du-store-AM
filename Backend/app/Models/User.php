<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable
{
    protected $attributes = ['active' => true, 'must_change_password' => true, 'role' => 'agent'];
    protected $fillable = ['username', 'name', 'email', 'password', 'role', 'active', 'must_change_password'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed', 'active' => 'boolean', 'must_change_password' => 'boolean'];
    public function isAdmin(): bool { return $this->role === 'admin'; }
}
