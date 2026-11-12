<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Intervention extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime', 'duration_minutes' => 'integer'];
    public function contact() { return $this->belongsTo(Contact::class)->withTrashed(); }
    public function agent() { return $this->belongsTo(User::class, 'agent_id'); }
    public function quote() { return $this->belongsTo(Quote::class)->withTrashed(); }
}
