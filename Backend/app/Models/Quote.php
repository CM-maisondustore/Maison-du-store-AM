<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Quote $quote) {
            if (in_array($quote->status,['approved','accepted']) && !in_array($quote->getOriginal('status'),['approved','accepted'])) $quote->approved_at=now();
        });
    }
    protected $guarded = ['id'];
    protected $casts = ['contact' => 'array', 'product' => 'array', 'dimensions' => 'array', 'lines' => 'array', 'configuration'=>'array', 'deposit_amount'=>'integer', 'edit_version'=>'integer', 'terms_accepted_at' => 'datetime'];
    public function payments() { return $this->hasMany(QuotePayment::class); }
    public function receivedAmount(): int { return (int)$this->payments()->whereNull('voided_at')->sum('amount'); }
    public function customer() { return $this->belongsTo(Contact::class, 'contact_id'); }
    public function agent() { return $this->belongsTo(User::class, 'agent_id'); }
}
