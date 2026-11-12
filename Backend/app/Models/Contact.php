<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Contact extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes, Concerns\Archivable;
    protected $guarded = ['id'];
    protected $casts = ['marketing_consent_at' => 'datetime', 'archived_at' => 'datetime'];
    protected static function booted(): void
    {
        static::saving(function (Contact $contact) {
            if ($contact->email) $contact->email = mb_strtolower(trim($contact->email));
        });
    }
    public function quotes() { return $this->hasMany(Quote::class); }
    public function appointments() { return $this->hasMany(Appointment::class); }
    public function interventions() { return $this->hasMany(Intervention::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function messages() { return $this->hasMany(CommercialMessage::class); }
}
