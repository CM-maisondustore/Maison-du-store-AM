<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommercialMessage extends Model
{
    protected $guarded = ['id'];
    protected $attributes = ['channel'=>'email'];
    protected $casts = ['sent_at' => 'datetime', 'received_at' => 'datetime', 'read_at' => 'datetime', 'document_snapshot' => 'array', 'template_snapshot'=>'array','auto_send'=>'boolean'];
    public function archive() { return $this->hasOne(DocumentArchive::class); }
    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function contact() { return $this->belongsTo(Contact::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function quote() { return $this->belongsTo(Quote::class); }
}
