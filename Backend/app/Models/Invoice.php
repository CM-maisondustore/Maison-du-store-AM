<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Invoice extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'issued_at' => 'datetime', 'paid_at' => 'datetime', 'due_date' => 'date'];
    public function contact() { return $this->belongsTo(Contact::class); }
    public function quote() { return $this->belongsTo(Quote::class); }
}
