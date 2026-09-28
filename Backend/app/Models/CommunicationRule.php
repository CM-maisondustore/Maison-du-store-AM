<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommunicationRule extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['activated_at'=>'datetime', 'delay_hours'=>'integer'];
}
