<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommercialMessage extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['sent_at' => 'datetime', 'received_at' => 'datetime', 'read_at' => 'datetime', 'document_snapshot' => 'array'];
    public function contact() { return $this->belongsTo(Contact::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function quote() { return $this->belongsTo(Quote::class); }
}
