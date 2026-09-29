<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

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
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) return $query;
        return $query->where(fn (Builder $q) => $q->where('agent_id',$user->id)->orWhereExists(fn ($jobs) => $jobs
            ->selectRaw('1')->from('interventions')->whereColumn('interventions.quote_id','quotes.id')
            ->whereColumn('interventions.contact_id','quotes.contact_id')->where('interventions.agent_id',$user->id)
            ->where('interventions.status','!=','cancelled')));
    }
    public function canBeViewedBy(User $user): bool
    {
        return static::visibleTo($user)->whereKey($this->id)->exists();
    }
}
