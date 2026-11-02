<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuotePayment extends Model
{
    public const METHODS=['cash'=>'Espèces','bank'=>'Virement','mobile'=>'Mobile Money','card'=>'Carte'];
    protected $guarded=['id'];
    protected $casts=['amount'=>'integer','received_on'=>'date','voided_at'=>'datetime'];
    public function recorder() { return $this->belongsTo(User::class,'recorded_by'); }
}
