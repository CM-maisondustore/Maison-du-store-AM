<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentIssue extends Model
{
    protected $guarded=['id'];
    protected $casts=['snapshot'=>'array'];
    public function archive() { return $this->belongsTo(DocumentArchive::class,'document_archive_id'); }
    public function payment() { return $this->belongsTo(QuotePayment::class,'quote_payment_id'); }
}
