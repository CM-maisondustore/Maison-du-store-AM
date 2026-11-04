<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable
{
    use Notifiable;
    protected $attributes = ['active' => true, 'must_change_password' => true, 'role' => 'agent'];
    protected $fillable = ['username', 'name', 'email', 'password', 'role', 'active', 'must_change_password', 'profile', 'preferences'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed', 'active' => 'boolean', 'must_change_password' => 'boolean', 'profile' => 'array', 'preferences' => 'array'];
    /** L'admin principal a tout ; le manager a le bureau sans les réglages du site ; l'agent reste sur le terrain. */
    public const ROLES = ['admin' => 'Admin principal', 'manager' => 'Manager', 'agent' => 'Agent'];
    /** Boutiques qu'un agent peut ouvrir dans « Nouveau devis ». Sans choix enregistré : les trois. */
    public const SHOPS = ['stores' => 'Stores & Rideaux', 'electromenager' => 'Électroménager', 'meubles' => 'Meubles'];
    /** Bureau (admin principal ou manager) : prix, tous les devis, planning. */
    public function isAdmin(): bool { return in_array($this->role, ['admin', 'manager'], true); }
    /** Admin principal seul : équipe, visiteurs, mise en page, documents et automatisations, images et portfolio. */
    public function isOwner(): bool { return $this->role === 'admin'; }
    public function roleLabel(): string { return self::ROLES[$this->role] ?? (string) $this->role; }
    public function shops(): array
    {
        if ($this->isAdmin()) return array_keys(self::SHOPS);
        return array_values(array_intersect(array_keys(self::SHOPS), (array) ($this->profile['shops'] ?? []))) ?: array_keys(self::SHOPS);
    }
    public function canOpenShop(string $shop): bool { return in_array($shop, $this->shops(), true); }
    public function pushSubscriptions() { return $this->hasMany(PushSubscription::class); }
}
