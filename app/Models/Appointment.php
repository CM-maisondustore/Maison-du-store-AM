<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['details' => 'array'];
    protected static function booted(): void
    {
        static::saving(function (Appointment $appointment) {
            if ($appointment->status==='confirmed' && $appointment->isDirty(['status','date','time'])) $appointment->confirmed_at=now();
            $appointment->reserved_slot = $appointment->status === 'cancelled' ? null : $appointment->date.' '.$appointment->time;
        });
    }
    public function customer() { return $this->belongsTo(Contact::class, 'contact_id'); }
    public function agent() { return $this->belongsTo(User::class, 'agent_id'); }
}
